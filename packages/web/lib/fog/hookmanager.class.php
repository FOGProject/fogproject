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
     * Get events from other items.
     *
     * @return void
     */
    public function getEvents()
    {
        global $Init;
        $eventStart = 'CLIENT_UPDATE';
        /**
         * Lambda to iterate through the lines of a file
         * and return the registry items called.
         *
         * @param string $file the file to read from
         */
        $fileRead = function (&$file) {
            $regexp = '#processEvent\([\'\"](.*?)[\'\"]#';
            if (($fh = fopen($file[0], 'rb')) === false) {
                return;
            }
            while (feof($fh) === false) {
                if (($line = fgets($fh, 4096)) === false) {
                    return;
                }
                preg_match_all($regexp, $line, $match);
                $this->events[] = array_shift($match[1]);
            }
            fclose($fh);
            unset($file);
        };
        /**
         * Lambda to iterate event callers that are dynamic
         * in calling.
         *
         * @param string $item the item to scan and replace
         */
        $specTabs = function (&$item) use (&$eventStart) {
            $divTab = preg_replace(
                '/[\s+\:\.]/',
                '_',
                $item
            );
            $this->events[] = sprintf(
                '%s_%s',
                $eventStart,
                $divTab
            );
            unset($item);
        };
        $regext = '/^.+\.php$/i';
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
        $files = iterator_to_array($RegexIterator);
        unset(
            $RecursiveDirectoryIterator,
            $RecursiveIteratorIterator,
            $RegexIterator
        );
        foreach ($files as &$file) {
            $fileRead($file);
            unset($file);
        }
        $settingCats = self::getClass('ServiceManager')
            ->getSettingCats();
        $pxeNames = self::getSubObjectIDs(
            'PXEMenuOptions',
            '',
            'name'
        );
        $tabsArr = array_merge(
            $settingCats,
            $pxeNames
        );
        foreach ($tabsArr as &$item) {
            $specTabs($item);
            unset($item);
        }
        $eventStart = 'BOOT_ITEMS';
        $additional = array(
            'HOST_DEL',
            'HOST_DEL_POST',
            'GROUP_DEL',
            'GROUP_DEL_POST',
            'IMAGE_DEL',
            'IMAGE_DEL_POST',
            'SNAPIN_DEL',
            'SNAPIN_DEL_POST',
            'PRINTER_DEL',
            'PRINTER_DEL_POST',
            'HOST_DEPLOY',
            'GROUP_DEPLOY',
            'HOST_EDIT_TASKS',
            'GROUP_EDIT_TASKS',
            'HOST_EDIT_ADV',
            'GROUP_EDIT_ADV',
            'HOST_EDIT_AD',
            'GROUP_EDIT_AD',
        );
        array_merge($this->events, $additional);
        natcasesort($this->events);
        $this->events = array_filter($this->events);
        $this->events = array_unique($this->events);
        $this->events = array_values($this->events);
    }
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
            $mergedArr = array_merge(
                array('event' => $event),
                $arguments
            );
            $function[0]->{$function[1]}($mergedArr);
            unset($function);
        }
    }
}
