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
    public $data = array();
    /**
     * The events to work from.
     *
     * @var mixed
     */
    public $events;
    /**
     * Names already present in notifyEvents, as a lookup set.
     *
     * notify() used to ask the database whether the event name was already
     * recorded on EVERY call, and notify() is called from the snapin client
     * protocol and from taskqueue, so that was a round trip per snapin per
     * check-in. This is the same mistake HookManager::processEvent() had and
     * the same fix; see HookManager::$knownEvents for why remembering the
     * answer for the life of the process is safe.
     *
     * @var array|null
     */
    private static $knownNotifyEvents = null;
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
                throw new Exception(_('Event must be a string'));
            }
            if (!is_array($listener) && !is_object($listener)) {
                throw new Exception(_('Listener must be an array or an object'));
            }
            switch (get_class($this)) {
                case 'EventManager':
                    if (!($listener instanceof Event)) {
                        throw new Exception(_('Class must extend event'));
                    }
                    if (!isset($this->data[$event])) {
                        $this->data[$event] = array();
                    }
                    array_push($this->data[$event], $listener);
                    break;
                case 'HookManager':
                    if (!is_array($listener) || count($listener) !== 2) {
                        throw new Exception(
                            _('Second parameter must be in array(class,function)')
                        );
                    }
                    if (!($listener[0] instanceof Hook)) {
                        throw new Exception(_('Class must extend hook'));
                    }
                    if (!method_exists($listener[0], $listener[1])) {
                        $msg = sprintf(
                            '%s: %s->%s',
                            _('Method does not exist'),
                            get_class($listener[0]),
                            $listener[1]
                        );
                        throw new Exception($msg);
                    }
                    $this->data[$event][] = $listener;
                    break;
                default:
                    throw new Exception(
                        _('Register must be managed from hooks or events')
                    );
                    break;
            }
        } catch (Exception $e) {
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
     * is not an array -- a closure, say -- therefore raised "Cannot use object
     * of type X as array" from the error handler itself, which is an Error and
     * not caught by catch (Exception). Registration runs in a hook constructor
     * during LoadGlobals, so that escaped to the top and the whole application
     * answered 500 with an empty body, on every entry point, until the file was
     * deleted from disk. An error handler must not be able to fail harder than
     * the error it is reporting.
     *
     * @param mixed $listener The listener as the caller supplied it.
     *
     * @return string
     */
    private static function _describeListener($listener)
    {
        if (is_array($listener)) {
            $first = reset($listener);
            return is_object($first) ? get_class($first) : gettype($first);
        }
        if (is_object($listener)) {
            return get_class($listener);
        }
        return gettype($listener);
    }
    /**
     * Renders an event name for a log line.
     *
     * One of the conditions that reaches the catch below is "$event is not a
     * string", and %s on an object with no __toString is an Error, which
     * catch (Exception) does not catch. Same defect as _describeListener()
     * covers for the listener: an error handler must not be able to fail
     * harder than the error it is reporting.
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
     * Records an event name in notifyEvents if it is not already there.
     *
     * The table is a discovery aid -- it is what the notify event list is
     * built from -- so it is written opportunistically rather than being
     * authoritative. Marked known before the save, not after, so an event
     * fired from inside save() could not recurse into saving the same name.
     *
     * @param string $event the event name to record
     *
     * @return void
     */
    private static function _recordEventName($event)
    {
        if (self::$knownNotifyEvents === null) {
            self::$knownNotifyEvents = array_flip(
                (array) self::getSubObjectIDs(
                    'NotifyEvent',
                    array(),
                    'name'
                )
            );
        }
        if (isset(self::$knownNotifyEvents[$event])) {
            return;
        }
        self::$knownNotifyEvents[$event] = true;
        self::getClass('NotifyEvent')
            ->set('name', $event)
            ->save();
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
    public function notify($event, $eventData = array())
    {
        try {
            if (!is_string($event)) {
                throw new Exception(_('Event must be a string'));
            }
            if (!is_array($eventData)) {
                throw new Exception(_('Event Data must be an array'));
            }
            // Recorded here rather than above the try, which is where it used
            // to sit. Running before the guard meant a caller that passed an
            // array or an object still got that value handed to
            // NotifyEvent::set('name') and saved, so the discovery table
            // collected rows for things that were never event names -- and
            // the guard then rejected the same value one line later.
            self::_recordEventName($event);
            if (!isset($this->data[$event])) {
                throw new Exception(_('Event and data are not set'));
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
        } catch (Exception $e) {
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
                $this,
                0
            );

            return false;
        }

        return true;
    }
    /**
     * Loads the events or hooks.
     *
     * @return void
     */
    public function load()
    {
        // Sets up regex and paths to scan for.
        //
        // HookManager extends EventManager, so a HookManager satisfies
        // `instanceof self` too. This used to be two sequential ifs and the
        // hook branch was reached only because it ran second and overwrote
        // what the event branch had just assigned -- reordering the two
        // blocks silently made every hook load as an event and find nothing.
        // One decision, taken most-specific first, cannot be reordered wrong.
        if ($this instanceof HookManager) {
            $type = 'hook';
        } else {
            $type = 'event';
        }
        $regext = sprintf(
            '#^.+%s%ss%s.*\.%s\.php$#',
            DS,
            $type,
            DS,
            $type
        );
        $dirpath = sprintf(
            '%s%ss%s',
            DS,
            $type,
            DS
        );
        $strlen = -strlen(sprintf('.%s.php', $type));
        // Initiates plugins used in fileitems function
        $plugins = '';
        // Function simply returns the files based on the regex and data passed.
        $fileitems = function ($element) use ($dirpath, &$plugins) {
            preg_match(
                sprintf(
                    "#^($plugins.+%splugins%s)(?=.*$dirpath).*$#",
                    DS,
                    DS
                ),
                $element[0],
                $match
            );

            return isset($match[0]) ? $match[0] : '';
        };
        // Instantiates our items to get all files based on our regext info.
        $RecursiveDirectoryIterator = new RecursiveDirectoryIterator(
            BASEPATH,
            FileSystemIterator::SKIP_DOTS
        );
        $RecursiveIteratorIterator = new RecursiveIteratorIterator(
            $RecursiveDirectoryIterator
        );
        $RegexIterator = new RegexIterator(
            $RecursiveIteratorIterator,
            $regext,
            RegexIterator::GET_MATCH
        );
        // Makes all the returned items into an iterable array
        $files = iterator_to_array($RegexIterator, false);
        unset(
            $RecursiveDirectoryIterator,
            $RecursiveIteratorIterator,
            $RegexIterator
        );
        // First pass we don't care about plugins, only based files
        $plugins = '?!';
        $tFiles = array_map($fileitems, (array) $files);
        $fFiles = array_filter($tFiles);
        $normalfiles = array_values($fFiles);
        unset($tFiles, $fFiles);
        // Second pass we only care about plugins.
        $plugins = '?=';
        $grepString = sprintf(
            '#%s(%s)%s#',
            DS,
            implode(
                '|',
                self::$pluginsinstalled
            ),
            DS
        );
        $tFiles = array_map($fileitems, (array) $files);
        $fFiles = preg_grep($grepString, $tFiles);
        $fFiles = array_filter($fFiles);
        $pluginfiles = array_values($fFiles);
        unset($tFiles, $fFiles, $files);
        // All Data is now set, we have normal and plugin files.
        // startClass simply iterates the passed data and starts the needed
        // hooks or events.
        // Plugins don't need to know if the active flag is set either
        $startClass = function ($element) use ($strlen) {
            $className = str_replace(
                array("\t", "\n", ' '),
                '_',
                substr(
                    basename($element),
                    0,
                    $strlen
                )
            );
            // There used to be a loop building a lookup of every declared
            // class into $exists here, immediately before $exists was
            // overwritten by the class_exists() call below. It ran once per
            // hook or event file and its result was never read.
            $exists = class_exists(
                $className,
                false
            );
            if ($exists) {
                return;
            }
            self::getClass(
                str_replace(
                    array("\t", "\n", ' '),
                    '_',
                    $className
                )
            );
            unset($element);
        };
        // Plugins should be established first so menus and what not are setup.
        array_map(
            $startClass,
            (array) $pluginfiles
        );
        // Cleanup the plugin files
        unset($pluginfiles);
        // This function is a secondary to start class and only used on
        // non plugin files.  We have to find out if the class has the active
        // flag set or not.
        $checkNormalAndStart = function ($element) use ($strlen, $startClass) {
            // If we can't open the file just return
            if (($fh = fopen($element, 'rb')) === false) {
                return;
            }
            // Start processing to find the active variable
            while (feof($fh) === false) {
                // reset loop active flag just in case
                unset($active);
                // get the line
                $line = fgets($fh, 8192);
                if ($line === false) {
                    continue;
                }
                // We get the value and pop the line off and make it set as
                // a part of the code.
                preg_match('#(\$active\s?=\s?true;)#', $line, $linefound);
                if (count($linefound) < 1) {
                    continue;
                }
                // We are set and active start the class and break from the loop.
                $startClass($element);
                break;
            }
            // Close the file.
            fclose($fh);
        };
        // Perform the checks.
        if (count($normalfiles) > 0) {
            array_walk(
                $normalfiles,
                $checkNormalAndStart
            );
        }
    }
}
