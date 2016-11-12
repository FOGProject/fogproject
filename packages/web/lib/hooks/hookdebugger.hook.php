<?php
/**
 * Prints all the hooks so we can debug
 *
 * PHP version 5
 *
 * @category HookDebugger
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Change host name hook.
 *
 * @category HookDebugger
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HookDebugger extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'HookDebugger';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Displays/Logs Hooks and event data.';
    /**
     * Is the hook active or not.
     *
     * @var bool
     */
    public $active = false;
    /**
     * The log level of the hook.
     *
     * @var int
     */
    public $logLevel = 9;
    /**
     * Log to file?
     *
     * @var bool
     */
    public $logToFile = false;
    /**
     * Log to browser?
     *
     * @var bool
     */
    public $logToBrowser = true;
    /**
     * What to do for running.
     *
     * @param mixed $arguments The arguments to alter.
     *
     * @return void
     */
    public function run($arguments)
    {
        $this->log(
            print_r(
                $arguments,
                1
            ),
            $this->logLevel
        );
    }
}
$HookDebugger = new HookDebugger();
if (!$HookManager->events) {
    $HookManager->getEvents();
}
foreach ($HookManager->events as &$event) {
    $HookManager
        ->register(
            $event,
            array(
                $HookDebugger,
                'run'
            )
        );
    unset($event);
}
