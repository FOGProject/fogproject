<?php
class EventManager extends FOGBase {
    public $logLevel = 0;
    public $data = array();
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
    public function notify($event, $eventData=array()) {
        try {
            if  (!is_array($eventData)) throw new Exception('Data is invalid');
            $this->log(sprintf('Notifiying listeners: Event: %s, Data: %d', $event, $eventData));
            if (isset($this->data[$event])) {
                foreach ((array)$this->data[$event] AS $i => &$className) {
                    if ($className->active) $className->onEvent($event, $eventData);
                    unset($className);
                }
            }
        } catch (Exception $e) {
            $this->log(sprintf('Could not register v: Error: %s, Event: %s, Class: %s', $e->getMessage(), $event, $class[1]));
            return false;
        }
        return true;
    }
    public function load() {
        global $Init;
        foreach((array)$Init->HookPaths AS $i => &$path) {
            if (!file_exists($path)) continue;
            if (preg_match('#plugins#i',$path)) {
                $PluginName = basename(substr($path,0,-7));
                if (!in_array($PluginName,(array)$_SESSION['PluginsInstalled'])) continue;
            }
            $iterator = $this->getClass('DirectoryIterator',$path);
            foreach ($iterator AS $i => $fileInfo) {
                $className = null;
                if (substr($fileInfo->getFilename(),-10) != '.event.php') continue;
                $className = substr($fileInfo->getFilename(),0,-10);
                if (!$className || in_array($className,get_declared_classes())) continue;
                if (preg_match('#plugins#i',$fileInfo->getPathname())) {
                    $this->getClass($className);
                    continue;
                }
                if (!($handle = fopen($fileInfo->getPathname(),'rb'))) continue;
                while (($line = fgets($handle,4096)) !== false && !$linefound) $linefound = (strpos($line,'$active') !== false) ? $line : false;
                if (strpos($linefound,'true')) $this->getClass($className);
            }
        }
        unset($path);
    }
    public function log($txt, $level = 1) {
        if (!$this->ajax && $this->logLevel >= $level)
            printf('[%s] %s%s', $this->nice_date()->format("d-m-Y H:i:s"), trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt)), "<br />\n");
    }
}
