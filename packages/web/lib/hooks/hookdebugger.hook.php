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
     * Initialize our stuff.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        foreach (
            FOGCore::getSubObjectIDs(
                'HookEvent',
                '',
                'name'
            ) as &$event
        ) {
            self::$HookManager->register(
                $event,
                array(
                    $this,
                    'run'
                )
            );
            unset($event);
        }
    }
    /**
     * What to do for running.
     *
     * @param mixed $arguments The arguments to alter.
     *
     * @return void
     */
    public function run($arguments)
    {
        self::log(
            print_r(
                $arguments,
                1
            ),
            $this->logLevel,
            $this->logToFile,
            $this->logToBrowser,
            $this,
            $this->logLevel
        );
    }
}
