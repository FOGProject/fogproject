<?php
/**
 * HookManager handles registering and loading
 * events and hooks.
 *
 * PHP version 7.4+
 *
 * @category HookManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
     * The file extension this manager's listeners are declared in.
     *
     * @var string
     */
    protected $fileExtension = '.hook.php';
    /**
     * The directory under BASEPATH those files live in.
     *
     * @var string
     */
    protected $fileDirectory = 'hooks';
    /**
     * Log level if needed.
     *
     * @var int
     */
    public $logLevel = 0;
    /**
     * Data to store and use.
     *
     * @var array
     */
    public $data = [];
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
     * Refuses to notify. A hook is not an event listener.
     *
     * notify() is EventManager's, and it iterates listeners as objects --
     * $element->active, $element->onEvent(). HookManager stores them as
     * [object, method] arrays and Closures, so under PHP 8 the property read
     * on an array is a warning that yields null, every listener is skipped,
     * and the inherited method returned TRUE having invoked nothing.
     *
     * Nothing in core, in packages/service or in fog-plugins calls it, so the
     * only code this reaches is third-party code whose listeners have never
     * fired. Say so rather than fixing it silently: the two are not two spellings
     * of one thing. processEvent() merges an `event` key into the payload and
     * calls a named method that can mutate its arguments through references;
     * notify() passes a copy to a fixed method and discards the result. Quietly
     * making one behave as the other would blur a boundary the rest of this
     * work is sharpening -- see EventManager::register(), which no longer
     * accepts a Hook as an event listener either.
     *
     * @param string $event     the event to notify against
     * @param array  $eventData the data to pass
     *
     * @return bool
     */
    public function notify($event, $eventData = [])
    {
        self::log(
            sprintf(
                '%s: %s. %s',
                _('Cannot notify from the hook manager'),
                is_string($event) ? $event : gettype($event),
                _('Use processEvent instead')
            ),
            $this->logLevel,
            0,
            0,
            $this
        );

        return false;
    }
    /**
     * Refuses a listener this manager cannot dispatch.
     *
     * A hook listener is either [Hook, method] or a Closure. Both have an
     * owner -- the object for the pair, and whatever $this the closure was
     * written inside for the closure -- and the owner is what carries
     * $active, so admitting closures needed no new activation rule. A closure
     * with no bound $this has no owner and always runs: registering it is the
     * opt-in.
     *
     * docs/plugin-development.md has documented the closure form for the
     * three Phase 2 authentication seams since ADR 0014.
     *
     * @param mixed $listener The listener as the caller supplied it.
     *
     * @throws Exception
     *
     * @return void
     */
    protected function acceptListener($listener)
    {
        if ($listener instanceof \Closure) {
            return;
        }
        if (!is_array($listener) || count($listener ?: []) !== 2) {
            throw new \Exception(
                _('Listener must be [class,function] or a closure')
            );
        }
        if (!($listener[0] instanceof Hook)) {
            throw new \Exception(_('Class must extend hook'));
        }
        if (!method_exists($listener[0], $listener[1])) {
            throw new \Exception(
                sprintf(
                    '%s: %s->%s',
                    _('Method does not exist'),
                    // Short name: this is log text, not a class reference.
                    self::shortName($listener[0]),
                    $listener[1]
                )
            );
        }
    }
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
            // Marked known before the save, not after, so a hook fired from
            // inside save() could never recurse into saving the same name
            // again. Matches the dev-branch port (e827fd1bd).
            self::$knownEvents[$event] = true;
            self::getClass('HookEvent')
                ->set('name', $event)
                ->save();
        }
        if (!isset($this->data[$event])) {
            return;
        }
        foreach ((array) $this->data[$event] as $function) {
            // Two listener shapes, one activation rule. A pair's owner is its
            // object; a closure's owner is whatever $this it was written
            // inside, which for a closure declared in a hook constructor is
            // that hook. Either way the owner is what carries $active, so a
            // closure obeys the flag exactly as [$this, 'method'] does. A
            // closure with no bound $this has no owner and always runs.
            if ($function instanceof \Closure) {
                $owner = (new \ReflectionFunction($function))->getClosureThis();
                $callable = $function;
            } else {
                $owner = $function[0];
                if (!method_exists($owner, $function[1])) {
                    continue;
                }
                $callable = [$owner, $function[1]];
            }
            // $active decides, wherever the file lives. This used to reflect
            // on the listener's class, take its filename and force
            // active = true whenever the path contained the substring
            // "plugins" -- so the flag was decorative for every plugin hook
            // and a plugin could not turn one of its own hooks off. See
            // docs/adr/0017.
            if ($owner instanceof Hook && !$owner->active) {
                continue;
            }
            // Kept in a variable rather than inlined into the call: a listener
            // is free to declare its parameter by reference, and only a
            // variable can bind to one.
            $mergedArr = self::fastmerge(
                ['event' => $event],
                $arguments
            );
            $callable($mergedArr);
        }
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\HookManager', 'HookManager');
