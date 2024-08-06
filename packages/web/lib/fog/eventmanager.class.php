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
                        $this->data[$event] = [];
                    }
                    array_push($this->data[$event], $listener);
                    break;
                case 'HookManager':
                    if (!is_array($listener) || count($listener ?: []) !== 2) {
                        throw new Exception(
                            _('Second paramater must be in [class,function]')
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
        if ($this instanceof EventManager) {
            $extension = '.event.php';
            $dirpath = 'events';
        }
        if ($this instanceof HookManager) {
            $extension = '.hook.php';
            $dirpath = 'hooks';
        }
        $strlen = -strlen($extension);
        list(
            $normalfiles,
            $pluginfiles
        ) = self::fileitems(
            $extension,
            $dirpath,
            true
        );
        // Scan non plugin files and see if the active flag is set.
        // If active, start the class, otherwise on to next file.
        $startfiles = [];
        foreach ($normalfiles as &$file) {
            if (false === ($fh = fopen($file, 'rb'))) {
                continue;
            }
            while (feof($fh) === false) {
                unset($active);
                $line = fgets($fh, 4096);
                if (false === $line) {
                    continue;
                }
                preg_match(
                    '#(\$active\s?=\s?true;)#',
                    $line,
                    $linefound
                );
                if (count($linefound ?: []) < 1) {
                    continue;
                }
                $startfiles[] = $file;
                break;
            }
            fclose($fh);
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
