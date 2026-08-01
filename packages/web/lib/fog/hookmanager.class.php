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
    //public $events = [];
    /**
     * Names already present in hookEvents, as a lookup set.
     *
     * GH-707: processEvent() used to ask the database whether the event name
     * was already registered on EVERY fire. Hooks fire constantly -- even
     * TaskState::getQueuedState() fires one -- so a group tasking of 1000
     * hosts spent over 2000 round trips answering the same handful of
     * questions, and that alone was the bulk of the 78 seconds reported. The
     * table is only a discovery aid for the hook debugger and the API's
     * hookevent list, so the answer is safe to remember for the life of the
     * process. Nothing here removes names, so the only staleness possible is
     * a name another process added after we loaded -- which costs at worst
     * one redundant save(), and save() is an upsert.
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
    public function processEvent($event, $arguments = [])
    {
        //$this->events[] = $event;
        if (self::$knownEvents === null) {
            self::$knownEvents = array_flip(
                Route::getIds('hookevent', false, 'name')
            );
        }
        if (!isset(self::$knownEvents[$event])) {
            self::getClass('HookEvent')
                ->set('name', $event)
                ->save();
            self::$knownEvents[$event] = true;
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
                ['event' => $event],
                $arguments
            );
            $function[0]->{$function[1]}($mergedArr);
            unset($function);
        }
    }
}
