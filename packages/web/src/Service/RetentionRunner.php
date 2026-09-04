<?php
/**
 * Ages out the tables that record what happened.
 *
 * PHP version 7.4+
 *
 * @category RetentionRunner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Service;

use FOG\Audit\Retention;

/**
 * Ages out the tables that record what happened.
 *
 * A DAEMON OF ITS OWN BECAUSE THE NAME IS THE FEATURE. This sweep shipped
 * inside FOGPluginRunner: retention is not plugin work, but that was the one
 * non-root periodic daemon FOG had, and standing up a ninth to run one DELETE
 * an hour did not look proportionate. It was the wrong call, and the reason
 * is what an administrator reads rather than what the code costs.
 *
 * "FOGPluginRunner" tells an administrator this daemon is for plugins. A site
 * that installs none has every reason to switch it off -- in the UI through
 * PLUGINRUNNERGLOBALENABLED, or by disabling the unit outright -- and there
 * was nothing anywhere to tell them that doing so also stopped the audit
 * trail, the administrative history, the host login records and the task log
 * from ever being pruned again. Retention already has an off switch, and it
 * is per table: 0 days means keep forever. A SECOND off switch, unrelated,
 * undocumented and named after something else, is a breakage nobody asked
 * for -- the kind that comes back as a bug report against a feature that is
 * working exactly as written.
 *
 * So the cost of a ninth unit is paid, once, and everything below follows
 * from making the daemon say what it does:
 *
 * - RETENTIONRUNNERGLOBALENABLED turns retention off. It is the only switch
 *   that does, apart from the windows themselves, and it is named for it.
 * - The sleep time IS the sweep interval. There is no second schedule held
 *   inside the loop, so `systemctl status` and the log agree with the
 *   setting, and lowering it genuinely makes a catch-up finish sooner.
 * - Every pass writes a line, throttled while nothing changes. A daemon that
 *   says nothing for days is the failure ADR 0010 decision 4 exists to
 *   expose, and it is the failure that matters most here: "is retention
 *   actually running" is precisely the question this whole change exists to
 *   let somebody answer.
 *
 * Non-root, like FOGPluginRunner and unlike the other seven. It needs a
 * database connection and nothing else -- no filesystem, no network, no
 * process control -- so there is no reason for it to be able to do more.
 * That is enforced by User=/Group= in the unit file, which installInitScript()
 * rewrites from the FOGWEBUSER placeholder.
 *
 * The sweep itself, the registry, the batch bound and the audit-before-delete
 * refusal all live in Retention (ADR 0021 Decision 10, ADR 0023). This class
 * is the schedule and the log, and deliberately holds no policy of its own.
 *
 * @category RetentionRunner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RetentionRunner extends FOGService
{
    /**
     * Seconds before an unchanged idle reason is repeated in the log.
     *
     * A server whose windows are all 0, or whose tables are already inside
     * their windows, has nothing to remove on any pass -- which is the normal
     * steady state, not a fault. At the default sleep of an hour that is 24
     * lines a day and no throttle would be needed; the throttle is here
     * because the sleep time is an administrator's to lower, and somebody who
     * drops it to 60 to make a catch-up finish sooner should not thereby
     * rotate the interesting history out from under themselves.
     *
     * The skipped-cycle count goes out on the next line for the reason
     * FOGPluginRunner's does: without it a loop that is turning quietly and a
     * loop that has stopped produce byte-identical logs.
     *
     * @var int
     */
    const IDLE_REPEAT = 900;
    /**
     * Is the service globally enabled.
     *
     * @var int
     */
    private static $_runnerOn = 0;
    /**
     * Where to get the service's sleeptime.
     *
     * @var string
     */
    public static $sleeptime = 'RETENTIONRUNNERSLEEPTIME';
    /**
     * Fallback sleep when the globalSetting above is unset.
     *
     * @var int
     */
    public static $sleepdefault = 3600;
    /**
     * The last idle reason logged, and when.
     *
     * @var string|null
     */
    private $_lastIdle = null;
    /**
     * @var int
     */
    private $_lastIdleAt = 0;
    /**
     * Cycles that went by without a line since the last one was written.
     *
     * @var int
     */
    private $_idleSkipped = 0;
    /**
     * Initializes the RetentionRunner class.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $runnerkeys = [
            'RETENTIONRUNNERDEVICEOUTPUT',
            'RETENTIONRUNNERLOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($runnerkeys);
        // Its own sub-directory of the service log path, for the reason
        // FOGPluginRunner's is: wlog() rotates by rename() and unlink(),
        // which need write on the DIRECTORY rather than the file, and the
        // top level belongs to the seven root daemons. A separate directory
        // from plugins/ even though both writers are the same account --
        // there is no privilege boundary between them to defend, but a
        // retention log filed under plugins/ would reintroduce the exact
        // confusion this daemon exists to remove.
        //
        // The subdirectory is core policy rather than part of the setting, so
        // RETENTIONRUNNERLOGFILENAME stays a plain filename like every other
        // service's. FOGLogPaths::FOG_SUBDIRS carries the same name and is
        // what makes the file reachable from the log viewer.
        $logdir = sprintf(
            '%sretention%s',
            (
                self::$logpath ?
                self::$logpath :
                FOG_LOG_DIR . DS
            ),
            DS
        );
        // Best effort. The installer creates and chowns this; a daemon that
        // silently writes nowhere because a directory is missing is exactly
        // the failure this service is built to make visible, and it cannot
        // create it as root the way the other seven could.
        if (!is_dir($logdir)) {
            @mkdir($logdir, 0755, true);
        }
        static::$log = sprintf(
            '%s%s',
            $logdir,
            (
                $log ?
                $log :
                'fogretentionrunner.log'
            )
        );
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty3'
        );
        // An hour, and it is the sweep interval rather than a poll around a
        // schedule held somewhere else. Nothing about a retention window
        // changes minute to minute, each pass costs a COUNT per configured
        // table, and Retention::MAX_PER_PASS bounds one pass -- so a first
        // sweep on a long-neglected table catches up over hours instead of
        // holding locks for the length of one enormous DELETE. Lowering this
        // raises the catch-up rate proportionally; that is the knob, and it
        // is the only one.
        static::$zzz = (
            $zzz ?
            $zzz :
            3600
        );
    }
    /**
     * Logs a reason the cycle did no work, throttled to one line per
     * IDLE_REPEAT while the reason is unchanged.
     *
     * @param string $reason why nothing happened
     *
     * @return void
     */
    private function _logIdle($reason)
    {
        $now = self::niceDate()->getTimestamp();
        if ($reason === $this->_lastIdle
            && ($now - $this->_lastIdleAt) < self::IDLE_REPEAT
        ) {
            $this->_idleSkipped++;
            return;
        }
        self::outall(
            sprintf(
                ' * %s%s',
                $reason,
                (
                    $this->_idleSkipped > 0 ?
                    sprintf(
                        ' (%s %d %s)',
                        _('unchanged for'),
                        $this->_idleSkipped,
                        _('further cycles')
                    ) :
                    ''
                )
            )
        );
        $this->_lastIdle = $reason;
        $this->_lastIdleAt = $now;
        $this->_idleSkipped = 0;
    }
    /**
     * Service run.
     *
     * @return void
     */
    public function serviceRun()
    {
        // Two try blocks, not one, because the two failures are different
        // kinds of thing. "Disabled" and "not the master node" are the normal
        // state of most servers and belong in the throttle; a sweep that
        // THREW is a fault and must not be throttled away behind an
        // unchanged-reason check.
        try {
            self::$_runnerOn = (int) self::getSetting('RETENTIONGLOBALENABLED');
            if (self::$_runnerOn < 1) {
                throw new \Exception(
                    _('Retention is globally disabled')
                );
            }
            // Every other daemon gates on this, and retention needs it for a
            // sharper reason than most: the sweep DELETEs, so a group whose
            // nodes all ran it would have every node racing to remove the
            // same rows and auditing that it had.
            //
            // Called for the throw, not for the return value --
            // checkIfNodeMaster() either returns a non-empty list or throws
            // " | This is not the master node" itself.
            $this->checkIfNodeMaster();
        } catch (\Exception $e) {
            $this->_logIdle($e->getMessage());
            return;
        }
        try {
            $removed = Retention::sweep();
        } catch (\Throwable $e) {
            // Throwable, not Exception: the registry is extensible by a
            // plugin hook (RETENTION_REGISTRY_DATA), so a bad contribution
            // arrives here as an Error rather than an Exception and would
            // otherwise kill the child -- which the supervisor re-forks
            // straight back into the same failure (#815).
            self::outall(
                sprintf(
                    ' * %s: %s',
                    _('Retention sweep failed'),
                    $e->getMessage()
                )
            );
            return;
        }
        $total = 0;
        foreach ($removed as $table => $count) {
            if (false === $count) {
                // NOT an error, and deliberately not retried harder. A table
                // whose audit row would not store is left ALONE, growing,
                // rather than being shrunk without a record (ADR 0021
                // Decision 10). The next pass tries again.
                self::outall(
                    sprintf(
                        ' * %s: %s',
                        _('Retention refused, audit row would not store'),
                        $table
                    )
                );
                $total++;
                continue;
            }
            self::outall(
                sprintf(
                    ' * %s: %s (%d)',
                    _('Retention removed rows from'),
                    $table,
                    $count
                )
            );
            $total++;
        }
        if ($total > 0) {
            // Reached work, so the next quiet spell is a state change and
            // gets its line immediately rather than waiting out IDLE_REPEAT.
            $this->_lastIdle = null;
            $this->_idleSkipped = 0;
            return;
        }
        // Retention::sweep() writes no audit row for a table with nothing to
        // remove -- an hourly "deleted 0" would bury the passes that did
        // something. So this line is the ONLY evidence the sweep ran at all,
        // which makes it the answer to "is retention working", and that is
        // the question this daemon exists to let somebody answer.
        $this->_logIdle(_('Nothing to age out'));
    }
}
