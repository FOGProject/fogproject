<?php
/**
 * EventManager handles registering and loading
 * events and hooks.
 *
 * PHP version 5
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
                        _('Second paramater must be in array(class,function)')
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
                '%s: %s: %s, $s: %s, %s: %s',
                _('Could not register'),
                _('Error'),
                $e->getMessage(),
                _('Event'),
                $event,
                _('Class'),
                $listener[0]
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
                throw new Exception(_('Event must be a string'));
            }
            if (!is_array($eventData)) {
                throw new Exception(_('Event Data must be an array'));
            }
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
     * Loads the events or hooks.
     *
     * @return void
     */
    public function load()
    {
        // Sets up regex and paths to scan for
        if ($this instanceof self) {
            $regext = sprintf(
                '#^.+%sevents%s.*\.event\.php$#',
                DS,
                DS
            );
            ;
            $dirpath = sprintf(
                '%sevents%s',
                DS,
                DS
            );
            $strlen = -strlen('.event.php');
        }
        if ($this instanceof HookManager) {
            $regext = sprintf(
                '#^.+%shooks%s.*\.hook\.php$#',
                DS,
                DS
            );
            $dirpath = sprintf(
                '%shooks%s',
                DS,
                DS
            );
            $strlen = -strlen('.hook.php');
        }
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
        // Makes all the returned items into an iteratable array
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
        $startClass = function (&$element) use ($strlen) {
            $className = str_replace(
                array("\t", "\n", ' '),
                '_',
                substr(
                    basename($element),
                    0,
                    $strlen
                )
            );
            $decClasses = get_declared_classes();
            foreach ((array)$decClasses as $key => &$classExist) {
                $exists[$classExist] = 1;
                unset($classExist);
            }
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
            unset($element, $key);
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
