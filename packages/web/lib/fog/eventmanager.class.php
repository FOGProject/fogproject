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

namespace FOG;

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
     * The file extension this manager's listeners are declared in.
     *
     * Declared per class rather than decided in load(), which used to run two
     * sequential instanceof checks -- EventManager first, then HookManager.
     * HookManager extends EventManager, so it satisfied both, and reached
     * .hook.php only because the second assignment overwrote the first.
     * Swapping the blocks made every HookManager load .event.php instead, and
     * nothing would have said so.
     *
     * @var string
     */
    protected $fileExtension = '.event.php';
    /**
     * The directory under BASEPATH those files live in.
     *
     * @var string
     */
    protected $fileDirectory = 'events';
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
            // Short name: the cases below are bare class names and the
            // default arm throws. A namespaced FQCN would hit that default
            // for every register() call, so no hook and no event would ever
            // register -- and the throw is caught and merely logged.
            switch (self::shortName($this)) {
                case 'EventManager':
                    if (!($listener instanceof Event)) {
                        throw new \Exception(_('Class must extend event'));
                    }
                    if (!isset($this->data[$event])) {
                        $this->data[$event] = [];
                    }
                    array_push($this->data[$event], $listener);
                    break;
                case 'HookManager':
                    // A hook listener is either [Hook, method] or a Closure.
                    // Both have an owner -- the object for the pair, and
                    // whatever $this the closure was written inside for the
                    // closure -- and the owner is what carries $active, so
                    // admitting closures needs no new activation rule. A
                    // closure with no bound $this has no owner and is
                    // therefore always active: its registration is the opt-in.
                    //
                    // docs/plugin-development.md has documented the closure
                    // form for the three Phase 2 authentication seams since
                    // ADR 0014; until now it did not work.
                    if ($listener instanceof \Closure) {
                        $this->data[$event][] = $listener;
                        break;
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
                        $msg = sprintf(
                            '%s: %s->%s',
                            _('Method does not exist'),
                            self::shortName($listener[0]),
                            $listener[1]
                        );
                        throw new \Exception($msg);
                    }
                    $this->data[$event][] = $listener;
                    break;
                default:
                    throw new \Exception(
                        _('Register must be managed from hooks or events')
                    );
            }
        } catch (\Exception $e) {
            $string = sprintf(
                '%s: %s: %s, %s: %s, %s: %s',
                _('Could not register'),
                _('Error'),
                $e->getMessage(),
                _('Event'),
                $event,
                _('Class'),
                self::_describeListener($listener)
            );
            self::log(
                $string,
                $this->logLevel,
                0,
                $this,
                0
            );
        }
        return $this;
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
        $exists = self::getClass('NotifyEventManager')->exists(
            $event,
            '',
            'name'
        );
        if (!$exists) {
            self::getClass('NotifyEvent')
                ->set('name', $event)
                ->save();
        }
        try {
            if (!is_string($event)) {
                throw new \Exception(_('Event must be a string'));
            }
            if (!is_array($eventData)) {
                throw new \Exception(_('Event Data must be an array'));
            }
            if (!isset($this->data[$event])) {
                throw new \Exception(_('Event and data are not set'));
            }
            $runEvent = function ($element) use ($event, $eventData) {
                if (!$element->active) {
                    return;
                }
                $element->onEvent($event, $eventData);
            };
            foreach ((array) $this->data[$event] as &$element) {
                $runEvent($element);
                unset($element);
            }
        } catch (\Exception $e) {
            $string = sprintf(
                '%s: %s: %s, $s: %s',
                _('Could not notify'),
                _('Error'),
                $e->getMessage(),
                _('Event'),
                $event
            );
            self::log(
                $string,
                $this->logLevel,
                0,
                $this,
                0
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
     * @param string $file   Absolute path to the .hook.php/.event.php file.
     * @param int    $strlen Negative length of the extension, as load() has it.
     *
     * @return bool
     */
    private static function _declaresActive($file, $strlen)
    {
        $className = str_replace(
            ["\t","\n",' '],
            '_',
            substr(
                basename($file),
                0,
                $strlen
            )
        );
        // class-name consumer: handed to class_exists() and ReflectionClass,
        // both of which resolve a namespaced name and a global one alike.
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
        // Each manager says what it loads; see $fileExtension.
        $extension = $this->fileExtension;
        $dirpath = $this->fileDirectory;
        $strlen = -strlen($extension);
        list(
            $normalfiles,
            $pluginfiles
        ) = self::fileitems(
            $extension,
            $dirpath,
            true
        );
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
            if (self::_declaresActive($file, $strlen)) {
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
        self::startClassFromFiles($startfiles, $strlen);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\EventManager', 'EventManager');
