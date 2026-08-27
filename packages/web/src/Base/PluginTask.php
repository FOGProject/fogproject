<?php
/**
 * Base class for background work a plugin declares.
 *
 * PHP version 7.4+
 *
 * @category PluginTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

use FOG\Service\PluginRunner;

/**
 * Base class for background work a plugin declares.
 *
 * Everything else a plugin can register runs inside a request: a page, a REST
 * resource, a hook on an event core fires. A task is the seam for work with no
 * request behind it -- polling, reconciling, expiring, retrying (ADR 0010).
 *
 * A plugin declares one file per task at `<plugin>/tasks/<name>.task.php`,
 * declaring a class named for the file (the same filename-equals-class-name
 * rule every other FOG source file follows). FOGPluginRunner discovers them
 * and calls run().
 *
 * Three properties of the runner shape what a task may assume:
 *
 * - **It runs as the web user, not root.** Installing a plugin grants exactly
 *   the privilege installing a plugin already granted, and no more. A task
 *   cannot mount, write image trees or touch device nodes.
 * - **Tasks share one process, run one at a time, in no guaranteed order.** A
 *   task that blocks holds up every other plugin's tasks until it returns, so
 *   bound your network and database calls. Nothing else is affected: this
 *   daemon runs plugin tasks and nothing else.
 * - **run() must be idempotent.** $interval is a floor, not a promise. The
 *   runner holds next-run times in memory, so a service restart makes every
 *   task immediately due, and a run that throws is retried on the next cycle.
 *
 * @category PluginTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class PluginTask extends FOGBase
{
    /**
     * Human-readable name, used in the service log.
     *
     * Falls back to the class name when left empty.
     *
     * @var string
     */
    public $name = '';
    /**
     * What this task does. Not currently rendered; kept so the runner's log
     * and a later UI have something to say beyond a class name.
     *
     * @var string
     */
    public $description = '';
    /**
     * Seconds between runs.
     *
     * The runner clamps this to a floor (see PluginRunner::MIN_INTERVAL) so a
     * task declaring 0 cannot spin the daemon, and the runner's own sleep time
     * is the scheduling granularity -- an interval below it is rounded up to
     * it in practice.
     *
     * @var int
     */
    public $interval = 3600;
    /**
     * Set false to ship a task without running it.
     *
     * Deliberately separate from the plugin's own active state: that is the
     * admin's switch, this is the author's.
     *
     * @var bool
     */
    public $active = true;
    /**
     * Does the work.
     *
     * Anything thrown is caught by the runner, logged against the plugin and
     * task, and the cycle continues -- so a task should let a genuine failure
     * propagate rather than swallow it, and the log will name it.
     *
     * @return void
     */
    abstract public function run();
    /**
     * The label used in log lines.
     *
     * @return string
     */
    public function label()
    {
        // Short name: this label is displayed and written to the runner log.
        return $this->name ?: self::shortName($this);
    }
    /**
     * Writes a line to the runner's log, tagged with this task.
     *
     * Exists so that a task's own output lands between the start and finish
     * lines the runner writes around it, in the one file an admin is already
     * tailing. Without it every plugin invents its own destination and the
     * log the runner keeps is only ever half the story.
     *
     * FOGService::$log is a single shared static, set by PluginRunner's
     * constructor, so the static call reaches the running daemon's log rather
     * than needing the task to hold a reference to it.
     *
     * Named logLine() rather than log(): FOGBase::log() is `public static`,
     * and PHP refuses to let a subclass redeclare an inherited static method
     * as an instance one. That is a fatal at class-declaration time, so
     * naming this log() made PluginTask itself unloadable -- and since
     * PluginRunner reaches every task through is_subclass_of($class,
     * 'PluginTask'), it took the runner down with it.
     *
     * @param string $message the line to write
     *
     * @return void
     */
    protected function logLine($message)
    {
        PluginRunner::outall(
            sprintf(
                '   - [%s] %s',
                $this->label(),
                $message
            )
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\PluginTask', 'PluginTask');
