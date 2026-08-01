<?php
/**
 * HookManager handles registering and loading
 * events and hooks.
 *
 * PHP version 5
 *
 * @category HookManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * HookManager handles registering and loading
 * events and hooks.
 *
 * @category HookManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HookManager extends EventManager
{
    /**
     * Log level if needed.
     *
     * @var int
     */
    public $logLevel = 0;
    /**
     * Data to store and use.
     *
     * @var mixed
     */
    public $data;
    /**
     * Events to work off.
     *
     * @var array
     */
    public $events = array();
    /**
     * Names already present in hookEvents, as a lookup set.
     *
     * processEvent() used to ask the database whether the event name was
     * already registered on EVERY fire. Hooks fire constantly -- even
     * TaskState::getQueuedState() fires one, from inside batch loops -- so
     * this was a round trip per hook, thousands of them on a large group
     * tasking. The table is only a discovery aid for the hook debugger and
     * the API's hookevent list, so the answer is safe to remember for the
     * life of the process. Nothing here removes names, so the only staleness
     * possible is a name another process added after we loaded -- which costs
     * at worst one redundant save(), and save() is an upsert.
     *
     * Ported from working-1.6, where it was found while profiling GH-707.
     *
     * @var array|null
     */
    private static $knownEvents = null;
    /**
     * Processes the system for customizable elements.
     *
     * @param string $event     the event to process
     * @param array  $arguments the arguments to pass
     *
     * @return void
     */
    public function processEvent($event, $arguments = array())
    {
        if (self::$knownEvents === null) {
            self::$knownEvents = array_flip(
                (array) self::getSubObjectIDs(
                    'HookEvent',
                    array(),
                    'name'
                )
            );
        }
        if (!isset(self::$knownEvents[$event])) {
            self::$knownEvents[$event] = true;
            self::getClass('HookEvent')
                ->set('name', $event)
                ->save();
        }
        if (!isset($this->data[$event])) {
            return;
        }
        foreach ((array) $this->data[$event] as &$function) {
            $active = false;
            $className = get_class($function[0]);
            $refClass = new ReflectionClass($className);
            $filename = $refClass->getFileName();
            if (!method_exists($function[0], $function[1])) {
                continue;
            }
            if (stripos($filename, 'plugins') !== false) {
                $function[0]->active = true;
            }
            $active = $function[0]->active;
            if (!$active) {
                continue;
            }
            $mergedArr = self::fastmerge(
                array('event' => $event),
                $arguments
            );
            $function[0]->{$function[1]}($mergedArr);
            unset($function);
        }
    }
}
