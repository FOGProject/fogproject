<?php
/**
 * EventManager handles registering and loading
 * events and hooks.
 *
 * PHP version 7.4+
 *
 * @category EventManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

use FOG\Router\Route;

/**
 * EventManager handles registering and loading
 * events and hooks.
 *
 * @category EventManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class EventManager extends FOGBase
{
    /**
     * The src/ bucket this manager's listeners live in.
     *
     * Core's are src/Events and src/Hooks; a plugin's are
     * <plugin>/src/Events and <plugin>/src/Hooks. One name serves both,
     * which is the point of the layout (ADR 0035).
     *
     * Declared per class rather than decided in load(), which used to run two
     * sequential instanceof checks -- EventManager first, then HookManager.
     * HookManager extends EventManager, so it satisfied both, and reached
     * hooks only because the second assignment overwrote the first. Swapping
     * the blocks made every HookManager load events instead, and nothing
     * would have said so.
     *
     * @var string
     */
    protected $fileBucket = 'Events';
    /**
     * Items log level.
     *
     * @var int
     */
    public $logLevel = 0;
    /**
     * The data to work from.
     *
     * @var array
     */
    public $data = [];
    /**
     * Names already present in notifyEvents, as a lookup set.
     *
     * The same fix HookManager::$knownEvents carries for GH-707, applied to
     * the other half of the subsystem. notify() asked the database whether it
     * had seen the name before on EVERY call -- an exists() SELECT that was
     * 87% of the cost of a notify() nobody was listening to. The table is a
     * discovery aid for the notification plugins' settings pages, so the
     * answer is safe to remember for the life of the process: nothing here
     * removes names, so the only staleness possible is a name another process
     * added after we loaded, which costs at worst one redundant save(), and
     * save() is an upsert.
     *
     * @var array|null
     */
    private static $knownNotifyEvents = null;
    /**
     * The events to work from.
     *
     * @var mixed
     */
    //public $events;
    /**
     * Registers events and listeners within the system.
     *
     * @param string       $event    the event name to register
     * @param array|object $listener the listener to work from
     *
     * @throws Exception
     *
     * @return bool
     */
    public function register($event, $listener)
    {
        try {
            if (!is_string($event)) {
                throw new \Exception(_('Event must be a string'));
            }
            if (!is_array($listener) && !is_object($listener)) {
                throw new \Exception(_('Listener must be an array or an object'));
            }
            $this->acceptListener($listener);
            if (!isset($this->data[$event])) {
                $this->data[$event] = [];
            }
            $this->data[$event][] = $listener;
        } catch (\Exception $e) {
            $string = sprintf(
                '%s: %s: %s, %s: %s, %s: %s',
                _('Could not register'),
                _('Error'),
                $e->getMessage(),
                _('Event'),
                self::_describeEvent($event),
                _('Class'),
                self::_describeListener($listener)
            );
            self::log(
                $string,
                $this->logLevel,
                0,
                0,
                $this
            );
        }
        return $this;
    }
    /**
     * Remembers an event name in notifyEvents, once per process.
     *
     * @param string $event The event name to record.
     *
     * @return void
     */
    private static function _recordEventName($event)
    {
        if (self::$knownNotifyEvents === null) {
            self::$knownNotifyEvents = array_flip(
                Route::getIds('notifyevent', false, 'name')
            );
        }
        if (isset(self::$knownNotifyEvents[$event])) {
            return;
        }
        // Marked known before the save, not after, so an event fired from
        // inside save() could never recurse into saving the same name again.
        // Matches HookManager::processEvent().
        self::$knownNotifyEvents[$event] = true;
        self::getClass('NotifyEvent')
            ->set('name', $event)
            ->save();
    }
    /**
     * Refuses a listener this manager cannot dispatch.
     *
     * The override point that replaced a switch on self::shortName($this),
     * with a case per subclass and a default arm that threw. A parent
     * enumerating its children by name is fragile in exactly the way the
     * comment above that switch recorded: during the namespace migration every
     * register() call landed on the default arm, so no hook and no event
     * registered anywhere, and because the throw is caught and logged the
     * application went on serving pages with every hook silently absent. It
     * also meant any subclass of either manager -- something a plugin is free
     * to write -- registered nothing at all and was told so only in the log.
     *
     * Each manager now answers for its own listener shapes and inherits the
     * one it does not override.
     *
     * @param mixed $listener The listener as the caller supplied it.
     *
     * @throws Exception
     *
     * @return void
     */
    protected function acceptListener($listener)
    {
        // Hooks first, and it has to stay first. Both arms refuse a hook now
        // that Hook no longer extends Event (#1203), so whichever runs first
        // decides the message -- and "a hook is not an event listener" is the
        // one that tells a plugin author what they did. Falling through to
        // "Class must extend event" would send them off to add an `extends`
        // that is exactly the thing #1203 removed.
        //
        // The refusal itself predates the hierarchy change and is not made
        // redundant by it: the two have genuinely different dispatch
        // contracts -- see HookManager::notify() -- and before #1194 a hook
        // registered here would be handed to Event::onEvent(), whose default
        // printed the event name into the response, which on a client
        // protocol endpoint is arbitrary text in front of a ##@GO reply.
        if ($listener instanceof Hook) {
            throw new \Exception(_('A hook is not an event listener'));
        }
        if (!($listener instanceof Event)) {
            throw new \Exception(_('Class must extend event'));
        }
    }
    /**
     * Renders an event name for a log line.
     *
     * The other half of the _describeListener() defect below, missed when
     * that one was fixed. One of the conditions that reaches either catch is
     * "$event is not a string", and %s on an object with no __toString is an
     * \Error, which catch (\Exception) does not catch -- so reporting the
     * bad event name was itself fatal.
     *
     * @param mixed $event The event as the caller supplied it.
     *
     * @return string
     */
    private static function _describeEvent($event)
    {
        return is_string($event) ? $event : gettype($event);
    }
    /**
     * Names a listener for a log line, whatever shape it arrived in.
     *
     * This used to be a bare `$listener[0]`, written inside the very catch
     * that exists to swallow a bad listener. Handing register() an object that
     * is not an array -- a Closure, say -- therefore raised "Cannot use object
     * of type X as array", which is an \Error and not caught by
     * catch (\Exception). Registration runs in a hook constructor during
     * LoadGlobals, so that escaped to the top and the whole application
     * answered 500 with an empty body, on every entry point, until the file
     * was deleted from disk. An error handler must not be able to fail harder
     * than the error it is reporting.
     *
     * @param mixed $listener The listener as the caller supplied it.
     *
     * @return string
     */
    private static function _describeListener($listener)
    {
        if (is_array($listener)) {
            $first = reset($listener);
            // Short name: this is log text, not a class reference.
            return is_object($first) ? self::shortName($first) : gettype($first);
        }
        if (is_object($listener)) {
            // Short name: as above.
            return self::shortName($listener);
        }
        return gettype($listener);
    }
    /**
     * Tells whether anything is registered against an event.
     *
     * Lets a caller skip work it would only be doing to fill an event
     * payload. Route::deletemass() uses it to avoid constructing an object
     * per row for DESTROY_HOST/DESTROY_IMAGE when no hook is listening --
     * on a mass delete that is one full model load per host that nothing
     * would ever read. It deliberately does NOT test the listener's
     * `active` flag; that is processEvent()'s call to make, and answering
     * "could anyone care" is all this is for.
     *
     * @param string $event the event name to test
     *
     * @return bool
     */
    public function hasListeners($event)
    {
        return isset($this->data[$event])
            && count((array) $this->data[$event]) > 0;
    }
    /**
     * Notifies the system of events.
     *
     * @param string $event     the event to notify against
     * @param array  $eventData the data to pass
     *
     * @throws Exception
     *
     * @return bool
     */
    public function notify($event, $eventData = [])
    {
        try {
            if (!is_string($event)) {
                throw new \Exception(_('Event must be a string'));
            }
            if (!is_array($eventData)) {
                throw new \Exception(_('Event Data must be an array'));
            }
            // After the guards, not before them: this used to run first, so a
            // caller who passed something that was not an event name got it
            // written to the database before being told it was invalid.
            self::_recordEventName($event);
            if (!isset($this->data[$event])) {
                // Nobody is listening. That is not an error -- it is the
                // ordinary case for four of the five names core notifies, and
                // for HOST_CHECKIN it is the only case there has ever been --
                // so it returns like processEvent() does instead of throwing
                // into the handler below and logging a line per checkin.
                return false;
            }
            foreach ((array) $this->data[$event] as &$element) {
                if (!$element->active) {
                    continue;
                }
                $element->onEvent($event, $eventData);
                unset($element);
            }
        } catch (\Exception $e) {
            $string = sprintf(
                '%s: %s: %s, %s: %s',
                _('Could not notify'),
                _('Error'),
                $e->getMessage(),
                _('Event'),
                self::_describeEvent($event)
            );
            self::log(
                $string,
                $this->logLevel,
                0,
                0,
                $this
            );

            return false;
        }

        return true;
    }
    /**
     * Whether a hook or event file's class declares itself active.
     *
     * Reads the declared default of $active off the class, which is what the
     * source-text regex this replaced was trying to approximate. A value
     * assigned in a constructor is still not consulted, exactly as before, so
     * no shipped file changes verdict -- all eleven core hooks and events
     * declare `public $active = false;` and none of the 87 bundled plugin
     * files disagrees with the old regex either.
     *
     * Two things do change, both deliberately. Spacing and case no longer
     * decide anything. And a file that declares no $active at all now
     * inherits Event's default of true and runs, where the regex found no
     * literal and skipped it -- which is the point of asking the class: the
     * class genuinely is active.
     *
     * Truthiness, not identity, so this agrees with the check
     * HookManager::processEvent() makes at dispatch. One notion of active.
     *
     * @param string $file Absolute path to the discovered file.
     *
     * @return bool
     */
    private static function _declaresActive($file)
    {
        // class-name consumer: handed to class_exists() and ReflectionClass.
        // classFromDiscoveredFile() derives the FQCN from the path, which is
        // the only name a listener answers to -- a core hook under src/Hooks
        // is FOG\Hooks\<Class> and nothing else.
        $className = self::classFromDiscoveredFile($file);
        if (!class_exists($className)) {
            return false;
        }
        $defaults = (new \ReflectionClass($className))->getDefaultProperties();
        return !empty($defaults['active']);
    }
    /**
     * Loads the events or hooks.
     *
     * @return void
     */
    public function load()
    {
        // Each manager says which bucket it loads; see $fileBucket. Core and
        // plugins are the same shape now -- src/<Bucket>/<Class>.php -- so
        // the two calls differ only in which root they walk (ADR 0035).
        //
        // $normalfiles keeps its meaning: the set that must opt in through
        // $active. A plugin's listeners do not, because installing the plugin
        // IS the opt-in, and pluginitems() has already dropped every plugin
        // that is not installed.
        $normalfiles = self::coreitems($this->fileBucket);
        $pluginfiles = self::pluginitems($this->fileBucket);
        // Non-plugin files opt in through $active. Ask the class, not the
        // file: this used to be a line-by-line regex for the literal text
        // `$active = true;`, which decided whether a hook ran on its
        // whitespace and its case, and could not tell a comment from code.
        // `public $active  = true;` with two spaces was inactive, and so was
        // `TRUE`, while `public $active = false;` with `= true;` written in
        // the comment above it -- the obvious way to document the toggle --
        // was active.
        $startfiles = [];
        foreach ($normalfiles as &$file) {
            if (self::_declaresActive($file)) {
                $startfiles[] = $file;
            }
            unset($file);
        }
        unset($normalfiles);
        $startfiles = self::fastmerge(
            $pluginfiles,
            $startfiles
        );
        unset($pluginfiles);
        self::startClassFromFiles($startfiles);
    }
}
