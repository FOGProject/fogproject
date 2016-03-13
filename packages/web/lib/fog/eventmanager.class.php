<?php
class EventManager extends FOGBase {
    public $logLevel = 0;
    public $data = array();
    public $events;
    public function register($event, $listener) {
        try {
            if (!is_string($event)) throw new Exception(_('Event must be a string'));
            switch (get_class($this)) {
            case 'EventManager':
                if (!($listener instanceof Event)) throw new Exception(_('Class must extend event'));
                if(!isset($this->data[$event])) $this->data[$event] = array();
                array_push($this->data[$event], $listener);
                break;
            case 'HookManager':
                if ($this->isMobile && !$listener[0]->mobile) throw new Exception(_('Not registering to mobile page'));
                if (!is_array($listener) || count($listener) < 2 || count($listener) > 2) throw new Exception(_('Second parameter must be in the form array(Hook class,Function to run)'));
                if (!($listener[0] instanceof Hook)) throw new Exception(_('Class must extend hook'));
                if (!method_exists($listener[0],$listener[1])) throw new Exception(sprintf('%s: %s->%s',_('Method does not exist'),get_class($listener[0]),$listener[1]));
                $this->data[$event][] = $listener;
                break;
            default:
                throw new Exception(_('Register event is not from EventManager or HookManager'));
                break;
            }
            return true;
        } catch (Exception $e) {
            $this->log($e->getMessage(),$this->logLevel);
        }
        return false;
    }
    public function notify($event, $eventData=array()) {
        try {
            if  (!is_array($eventData)) throw new Exception(_('Data is invalid'));
            if (!isset($this->data[$event])) return;
            foreach ($this->data[$event] AS &$className) {
                if (!$className->active) continue;
                $className->onEvent($event,$eventData);
                unset($className);
            }
        } catch (Exception $e) {
            $this->log(sprintf('Could not register: Error: %s, Event: %s, Class: %s', $e->getMessage(), $event, $class[1]));
            return false;
        }
        return true;
    }
    public function load($paths = 'EventPaths',$dirpath = '/event/',$ext = '.event.php') {
        global $Init;
        foreach($Init->$paths AS &$path) {
            if (!file_exists($path)) continue;
            if (preg_match('#plugins#i',$path) && !in_array(basename(substr($path,0,-strlen($dirpath))),(array)$_SESSION['PluginsInstalled'])) continue;
            foreach ($this->getClass('DirectoryIterator',$path) AS $fileInfo) {
                if ($fileInfo->isDot()) continue;
                if (substr($fileInfo->getFilename(),-strlen($ext)) != $ext) continue;
                $className = substr($fileInfo->getFilename(),0,-strlen($ext));
                if (in_array($className,get_declared_classes())) continue;
                if (preg_match('#plugins#i',$fileInfo->getPathname())) {
                    $this->getClass($className);
                    continue;
                }
                if (($fh = fopen($fileInfo->getPathname(),'rb')) === false) continue;
                while (feof($fh) === false) {
                    unset($active);
                    $line = fgets($fh,4096);
                    if ($line === false) continue;
                    preg_match('#(\$active\s?=\s?.*;)#',$line,$linefound);
                    eval(array_pop($linefound));
                    if (!isset($active) || $active === false) continue;
                    $this->getClass($className);
                    break;
                }
                fclose($fh);
            }
            unset($path);
        }
    }
}
