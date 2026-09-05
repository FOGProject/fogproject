<?php
/**
 * Runs background work declared by installed, active plugins.
 *
 * PHP version 7.4+
 *
 * @category PluginRunner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Service;

use FOG\Base\PluginTask;
use FOG\Items\Plugin;
use FOG\Router\Route;

/**
 * Runs background work declared by installed, active plugins.
 *
 * One core-owned daemon for every plugin, rather than a unit per plugin
 * (ADR 0010). A plugin never ships a systemd unit: the external plugin root
 * is web-writable whenever uploads are enabled, and a web-writable path
 * feeding a root-executed unit file is the GHSA-2hqx shape exactly.
 *
 * The lifecycle is the plugin's, not systemd's -- a task runs only while its
 * plugin is both active and installed, so deactivating a plugin stops its
 * tasks on the next cycle with no unit to disable and nothing left behind on
 * Forget.
 *
 * @category PluginRunner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PluginRunner extends FOGService
{
    /**
     * Floor for a task's declared interval, in seconds.
     *
     * Third-party code declares $interval, and a task shipping 0 -- by
     * mistake or by a property it forgot to set -- would otherwise run on
     * every pass of the loop. The floor makes the worst case "runs once a
     * minute", not "saturates a core".
     *
     * @var int
     */
    const MIN_INTERVAL = 60;
    /**
     * Seconds before an unchanged idle reason is repeated in the log.
     *
     * The loop wakes every PLUGINRUNNERSLEEPTIME and the normal state of a
     * server with no plugin tasks is to have nothing to do, so logging the
     * reason every pass writes ~1440 identical lines a day and rotates the
     * interesting history out from under itself.
     *
     * Not silenced altogether: a daemon that says nothing for days is the
     * failure ADR 0010 decision 4 exists to expose. A CHANGE of reason is
     * always logged at once.
     *
     * This was an hour, and an hour was too long. A throttled idle line is
     * the ONLY proof this daemon is still turning, so the throttle window is
     * also the window in which "idle" and "wedged" are indistinguishable --
     * a freshly restarted runner looked frozen to its own maintainer for the
     * first hour of its life. Fifteen minutes costs 96 lines a day, which is
     * 7% of the flood that prompted the throttle, and brings the worst-case
     * "is it alive?" answer down from a working morning to a coffee break.
     * The cycle count on each line closes the rest of the gap: it says how
     * many passes happened in the silence, so one line proves the loop
     * turned rather than merely that the process is resident.
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
    public static $sleeptime = 'PLUGINRUNNERSLEEPTIME';
    /**
     * Fallback sleep when the globalSetting above is unset.
     *
     * @var int
     */
    public static $sleepdefault = 60;
    /**
     * When each discovered task is next due, keyed "<plugin>/<task>".
     *
     * Held in memory, not a table (ADR 0010 decision 5). A restart therefore
     * makes every task immediately due, which is why PluginTask requires
     * run() to be idempotent. One fewer table, and the failure mode is a task
     * running early rather than a schedule silently drifting.
     *
     * @var array
     */
    private $_nextRun = [];
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
     * Reported on the next line so a reader can tell a loop that is turning
     * quietly from one that has stopped -- without it, the two produce
     * byte-identical logs.
     *
     * @var int
     */
    private $_idleSkipped = 0;
    /**
     * Initializes the PluginRunner class.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $runnerkeys = [
            'PLUGINRUNNERDEVICEOUTPUT',
            'PLUGINRUNNERLOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($runnerkeys);
        // Its own sub-directory of the service log path, not a file alongside
        // the other seven. wlog() rotates by rename() and unlink(), which need
        // write on the DIRECTORY, not just the file -- so giving this daemon a
        // rotatable log in the shared directory would mean giving the web user
        // (and therefore every plugin) the ability to rename or delete the
        // root daemons' logs. That is the same escalation ADR 0010 avoided by
        // not running as root, arriving through the log path instead.
        //
        // The subdirectory is core policy rather than part of the setting, so
        // PLUGINRUNNERLOGFILENAME stays a plain filename like every other
        // service's.
        $logdir = sprintf(
            '%splugins%s',
            (
                self::$logpath ?
                self::$logpath :
                FOG_LOG_DIR . DS
            ),
            DS
        );
        // Best effort. The installer creates and chowns this, but a daemon
        // that silently writes nowhere because a directory is missing is
        // precisely the failure this service is built to make visible -- and
        // this one cannot create it as root the way the other eight could.
        if (!is_dir($logdir)) {
            @mkdir($logdir, 0755, true);
        }
        static::$log = sprintf(
            '%s%s',
            $logdir,
            (
                $log ?
                $log :
                'fogpluginrunner.log'
            )
        );
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty3'
        );
        // The sleep time is also the scheduling granularity: a task asking for
        // 60 seconds gets 60 seconds only because this defaults to 60. Raise
        // it and every task's effective interval rounds up to it.
        static::$zzz = (
            $zzz ?
            $zzz :
            60
        );
    }
    /**
     * Finds the tasks every active, installed plugin declares.
     *
     * Keyed "<plugin>/<class>" so two plugins may ship a task of the same
     * name without one displacing the other's schedule.
     *
     * @return array of key => PluginTask
     */
    private function _discoverTasks()
    {
        $tasks = [];
        // getList() always sets inputoverride, which is why the explicit
        // argument is gone: without it listem() parses php://input for
        // DataTables paging, and there is no request behind a daemon.
        $plugins = Route::getList(
            'plugin',
            [
                'installed' => 1,
                'state' => 1
            ]
        );
        foreach ($plugins as $plugin) {
            $name = strtolower(trim((string)$plugin->name));
            $location = trim((string)$plugin->location);
            // A row whose code is not on disk. Plugin::isMissing() does not
            // deactivate for this -- the external root can be an unmounted
            // NFS share -- so the row is still active and it falls to each
            // reader to skip it.
            if (Plugin::isMissing($location)) {
                continue;
            }
            $dir = rtrim($location, DS) . DS . 'src' . DS . 'Tasks';
            if (!is_dir($dir)) {
                continue;
            }
            foreach ((array)glob($dir . DS . '*.php') as $file) {
                // Derived from the path, not qualified from a basename. A
                // task is <plugin>/src/Tasks/<Class>.php declaring
                // FOG\Plugins\<Segment>\Tasks\<Class> (ADR 0035), so the
                // FQCN is the path and nothing has to be looked up. The
                // basename on its own is a BARE name and is not a class at
                // all now that core no longer re-exports itself globally --
                // is_subclass_of() would then be false for every task and the
                // runner would silently skip all of them.
                $class = self::classFromDiscoveredFile($file);
                // is_subclass_of() rather than class_exists() alone. Belt and
                // braces since the name became derivable -- a plugin can no
                // longer reach a core class by naming a file after it -- but
                // it is also what keeps a file that is simply not a task from
                // being instantiated and having run() called on it.
                //
                // PluginTask::class, not the string 'PluginTask'. A class
                // name in a string is resolved as written, with no namespace
                // applied and no `use` consulted, so the literal named the
                // GLOBAL \PluginTask -- which existed only while core
                // re-exported itself there (ADR 0013 §2). Without the alias
                // the test is false for every task and the runner silently
                // skips all of them.
                if (!is_subclass_of($class, PluginTask::class)) {
                    self::outall(
                        sprintf(
                            ' * %s: %s/%s',
                            _('Skipping, not a PluginTask'),
                            $name,
                            basename($file)
                        )
                    );
                    continue;
                }
                $task = self::getClass($class);
                if (!$task->active) {
                    continue;
                }
                $tasks[$name . '/' . $class] = $task;
            }
        }
        return $tasks;
    }
    /**
     * Runs one task, logging both ends of it.
     *
     * The finish line is the point of this method. #815, #917, #944 and the
     * 1.5 FileDeleter that sat wedged for ten months all shared one shape:
     * systemd reports the unit active while the daemon does nothing, and the
     * log says nothing at all. A start line with no matching finish line is
     * the signal that was missing every time, and third-party code in the
     * loop makes that shape more likely, not less.
     *
     * @param string     $key  the "<plugin>/<class>" identifier
     * @param PluginTask $task the task to run
     *
     * @return void
     */
    private function _runTask($key, PluginTask $task)
    {
        self::outall(
            sprintf(
                ' * %s: %s (%s)',
                _('Task starting'),
                $key,
                $task->label()
            )
        );
        $started = microtime(true);
        try {
            $task->run();
            self::outall(
                sprintf(
                    ' * %s: %s %s %.2fs',
                    _('Task finished'),
                    $key,
                    _('in'),
                    microtime(true) - $started
                )
            );
        } catch (\Throwable $e) {
            // Throwable, not Exception: an Error walks past a catch on
            // Exception and would kill the child, which the supervisor then
            // re-forks straight back into the same failure (#815). A plugin
            // task is third-party code and a TypeError in it is at least as
            // likely as a thrown Exception.
            self::outall(
                sprintf(
                    ' * %s: %s %s %.2fs -- %s',
                    _('Task failed'),
                    $key,
                    _('after'),
                    microtime(true) - $started,
                    $e->getMessage()
                )
            );
        }
    }
    /**
     * Service run.
     *
     * @return void
     */
    public function serviceRun()
    {
        try {
            self::$_runnerOn = (int) self::getSetting('PLUGINRUNNERGLOBALENABLED');
            if (self::$_runnerOn < 1) {
                throw new \Exception(
                    _('Plugin runner is globally disabled')
                );
            }
            // Every other daemon gates on this, and plugin tasks need it for
            // the same reason: without it each node in a group runs every
            // task, so a task that sends a notification sends one per node.
            //
            // Called for the throw, not for the return value.
            // checkIfNodeMaster() either returns a non-empty list or throws
            // " | This is not the master node" itself, so a count guard here
            // can never fire -- which is why the other seven daemons simply
            // foreach over it. This used to wrap it in a count() test and
            // throw a second message of its own; that message was
            // unreachable, and reading it here suggested the log line came
            // from this class when it always came from the base.
            $this->checkIfNodeMaster();
            // Retention used to be swept here, above the plugin gate but
            // under this daemon's enable flag, on the grounds that this was
            // the only non-root periodic daemon FOG had (ADR 0010) and a
            // ninth unit for one DELETE an hour was not proportionate.
            //
            // It has its own daemon now -- FOGRetentionRunner. The cost was
            // never the point: a daemon named for plugins is one an operator
            // who runs no plugins will switch off, and switching it off also
            // stopped the audit trail, the history, the host login records
            // and the task log being pruned, with nothing anywhere to say
            // so. That is a breakage nobody asked for, and the fix is for
            // the daemon to say what it does rather than for the sweep to
            // hide inside one that does not.
            if (!self::getSetting('FOG_PLUGINSYS_ENABLED')) {
                throw new \Exception(_('The plugin system is disabled'));
            }
            $tasks = $this->_discoverTasks();
            if (!count($tasks)) {
                throw new \Exception(_('No plugin tasks to run'));
            }
            // Reached work, so the next idle spell is a state change and gets
            // logged immediately rather than waiting out IDLE_REPEAT. The
            // skipped count goes with it: it measures a silence, and the run
            // lines below have just ended the one it was counting.
            $this->_lastIdle = null;
            $this->_idleSkipped = 0;
            // Drop schedule entries for tasks that have gone -- a plugin
            // deactivated, upgraded, or Forgotten. Left in place they are a
            // slow leak in a process meant to run for months.
            $this->_nextRun = array_intersect_key(
                $this->_nextRun,
                $tasks
            );
            $now = self::niceDate()->getTimestamp();
            foreach ($tasks as $key => $task) {
                $interval = max(
                    self::MIN_INTERVAL,
                    (int)$task->interval
                );
                if (isset($this->_nextRun[$key])
                    && $now < $this->_nextRun[$key]
                ) {
                    continue;
                }
                $this->_runTask($key, $task);
                // Scheduled after the run, and scheduled whether it succeeded
                // or threw. Setting it only on success would put a task that
                // fails fast into a hot loop, which is how a broken plugin
                // takes the log and the database with it.
                $this->_nextRun[$key] = self::niceDate()->getTimestamp()
                    + $interval;
            }
        } catch (\Exception $e) {
            $this->_logIdle($e->getMessage());
        }
    }
    /**
     * Logs a reason the cycle did no work, throttled to one line per
     * IDLE_REPEAT while the reason is unchanged.
     *
     * The messages these carry are not errors -- "no plugin tasks to run" is
     * the normal state of a stock server -- but they are the only proof the
     * loop is turning, so they are throttled rather than dropped. A change of
     * reason is a state change and always logs at once.
     *
     * @param string $message the reason, without the ' * ' prefix
     *
     * @return void
     */
    private function _logIdle($message)
    {
        $now = self::niceDate()->getTimestamp();
        if ($message === $this->_lastIdle
            && ($now - $this->_lastIdleAt) < self::IDLE_REPEAT
        ) {
            ++$this->_idleSkipped;
            return;
        }
        // Only once there is a silence to account for. The first line after
        // a start has nothing behind it, and "(cycles since last line: 0)"
        // on it would just be noise.
        $skipped = '';
        if ($this->_idleSkipped > 0) {
            $skipped = sprintf(
                ' (%s: %d)',
                _('cycles since last line'),
                $this->_idleSkipped
            );
        }
        $this->_lastIdle = $message;
        $this->_lastIdleAt = $now;
        $this->_idleSkipped = 0;
        self::outall(
            sprintf(
                ' * %s%s',
                $message,
                $skipped
            )
        );
    }
}
