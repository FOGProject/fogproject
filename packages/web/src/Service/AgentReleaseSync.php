<?php
/**
 * Keeps this server's copy of the fog-agent releases current.
 *
 * PHP version 7.4+
 *
 * @category AgentReleaseSync
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Service;

use FOG\Agent\Releases;

/**
 * Keeps this server's copy of the fog-agent releases current.
 *
 * Each pass downloads the signed release manifest, records when this server
 * first saw each version (update rings count their delays from that time),
 * downloads the files enrolled hosts need into /opt/fog/agent/versions, and
 * removes the files nothing keeps. Releases holds all of that; this class is
 * the schedule and the log, like RetentionRunner.
 *
 * Non-root. It needs the database, outbound HTTPS and its own directory, and
 * the web tier serves the files it writes, so it runs as the web user the
 * installer gives that directory to.
 *
 * Idle when nothing asks for a release: Off mode, no host's own desired
 * version, and no minimum version. A server that never turns updates on
 * then never contacts fogproject.org for them.
 *
 * Only the master node syncs. Agents poll the web tier, which reads the
 * master's directory, and a storage node's copy would be one nobody reads.
 *
 * @category AgentReleaseSync
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AgentReleaseSync extends FOGService
{
    /**
     * Seconds before an unchanged idle reason is repeated in the log.
     *
     * @var int
     */
    const IDLE_REPEAT = 900;
    /**
     * Where to get the service's sleeptime.
     *
     * @var string
     */
    public static $sleeptime = 'AGENTRELEASESYNCSLEEPTIME';
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
     * Initializes the AgentReleaseSync class.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting(
            [
                'AGENTRELEASESYNCDEVICEOUTPUT',
                'AGENTRELEASESYNCLOGFILENAME',
                self::$sleeptime
            ]
        );
        // Its own sub-directory of the service log path, as RetentionRunner's
        // is: wlog() rotates by rename(), which needs write on the directory,
        // and the top level belongs to the root daemons. FOGLogPaths carries
        // the same name so the log viewer can reach the file.
        $logdir = sprintf(
            '%sagentreleasesync%s',
            (
                self::$logpath ?
                self::$logpath :
                FOG_LOG_DIR . DS
            ),
            DS
        );
        if (!is_dir($logdir)) {
            @mkdir($logdir, 0755, true);
        }
        static::$log = $logdir . ($log ? $log : 'fogagentreleasesync.log');
        static::$dev = $dev ? $dev : '/dev/tty3';
        // An hour. The sleep is the sync interval, so it is also the longest
        // a new release waits before ring 0 can see it.
        static::$zzz = $zzz ? $zzz : self::$sleepdefault;
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
        try {
            if ((int)self::getSetting('AGENTRELEASESYNCGLOBALENABLED') < 1) {
                throw new \Exception(_('Agent release sync is globally disabled'));
            }
            $this->checkIfNodeMaster();
            if (!Releases::wanted()) {
                throw new \Exception(
                    _('Agent updates are off, and no host or minimum version needs a release')
                );
            }
        } catch (\Exception $e) {
            $this->_logIdle($e->getMessage());
            return;
        }
        try {
            $counts = Releases::sync(
                function ($line) {
                    self::outall(' * ' . $line);
                }
            );
        } catch (\Throwable $e) {
            // A failed sync keeps the files and index it had, so agents keep
            // updating from the last good copy. Not throttled: it is a fault.
            self::outall(
                sprintf(' * %s: %s', _('Agent release sync failed'), $e->getMessage())
            );
            $this->_lastIdle = null;
            return;
        }
        if ($counts['downloaded'] + $counts['failed'] + $counts['pruned'] > 0) {
            $this->_lastIdle = null;
            $this->_idleSkipped = 0;
            return;
        }
        $this->_logIdle(
            sprintf(
                '%s (%d %s)',
                _('Agent releases are current'),
                $counts['files'],
                _('files in the manifest')
            )
        );
    }
}
