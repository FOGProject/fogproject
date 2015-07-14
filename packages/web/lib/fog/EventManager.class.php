<?php
class EventManager extends FOGBase {
    /** @var $loglevel the default loglevel */
    public $logLevel = 0;
    /** @var $data the data as passed */
    public $data = array();
    /** @function register() registers the event with the fog system
     * @param $event the event to register
     * @param $listener the listener of the event
     * @returns boolean of registered status
     */
    public function register($event, $listener) {
        try {
            if (!class_exists($listener, false)) throw new Exception('Listiner is invalid');
            if (!($listener instanceof Event)) throw new Exception('Not a valid event listener');
            $this->log(sprintf('Registering Event Linster: Event: %s, Class: %s', $event, $className));
            if(!isset($this->data[$event])) $this->data[$event] = array();
            array_push($this->data[$event], $listener);
            return true;
        } catch (Exception $e) {
            $this->log(sprintf('Could not register v: Error: %s, Event: %s, Class: %s', $e->getMessage(), $event, $class[1]));
        }
        return false;
    }
    /** @function notify() notifies/sends the event
     * @param $event the event to notify
     * @param $eventData the data of the event to notify
     * @return boolean of notified status
     */
    public function notify($event, $eventData=array()) {
        try {
            if (!is_array($eventData)) throw new Exception('Data is invalid');
            $this->log(sprintf('Notifiying listeners: Event: %s, Data: %d', $event, $eventData));
            if(isset($this->data[$event])) {
                foreach($this->data[$event] AS $i => &$className) {
                    if($className->active) $className->onEvent($event, $eventData);
                }
                unset($classname);
            }
            return true;
        } catch (Exception $e) {
            $this->log(sprintf('Could not register v: Error: %s, Event: %s, Class: %s', $e->getMessage(), $event, $class[1]));
        }
        return false;
    }
    /** @function load() loads the events into the system
     * @return void
     */
    public function load() {
        global $Init;
        foreach($Init->EventPaths AS $i => &$subscriberDirectory) {
            if (file_exists($subscriberDirectory)) {
                $subscriberIterator = new DirectoryIterator($subscriberDirectory);
                foreach ($subscriberIterator AS $fileInfo) {
                    $file = !$fileInfo->isDot() && $fileInfo->isFile() && substr($fileInfo->getFilename(),-10) == '.event.php' ? file($fileInfo->getPathname()) : null;
                    $PluginName = preg_match('#plugins#i',$subscriberDirectory) ? basename(substr($subscriberDirectory,0,-7)) : null;
                    if (in_array($PluginName,(array)$_SESSION['PluginsInstalled'])) $className = (substr($fileInfo->getFilename(),-10) == '.event.php' ? substr($fileInfo->getFilename(),0,-10) : null);
                    else if ($file && !preg_match('#plugins#',$fileInfo->getPathname())) {
                        $key = '$active';
                        foreach($file AS $lineNumber => $line) {
                            if (strpos($line,$key) !== false)
                                break;
                        }
                        if(preg_match('#true#i',$file[$lineNumber])) $className = (substr($fileInfo->getFileName(),-10) == '.event.php' ? substr($fileInfo->getFilename(),0,-10) : null);
                    }
                    if ($className && !in_array($className,get_declared_classes())) $this->getClass($className);
                }
            }
        }
        unset($subscriberDirectory);
    }
    /** @function log() logs the event as it happens
     * @param $txt the string to log
     * @param $level the level of logging
     * @return void
     */
    public function log($txt, $level = 1) {
        if (!$this->isAJAXRequest() && $this->logLevel >= $level)
            printf('[%s] %s%s', $this->nice_date()->format("d-m-Y H:i:s"), trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt)), "<br />\n");
    }
}
