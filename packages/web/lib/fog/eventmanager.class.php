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
            $runEvent = function($element) use ($event,$eventData){
                if (!$element->active) return;
                $element->onEvent($event,$eventData);
            };
            array_map($runEvent,(array)$this->data[$event]);
        } catch (Exception $e) {
            $this->log(sprintf('Could not register: Error: %s, Event: %s, Class: %s', $e->getMessage(), $event, $class[1]));
            return false;
        }
        return true;
    }
    public function load() {
        if ($this instanceof EventManager) {
            $regext = '#^.+/events/.*\.event\.php$#';
            $dirpath = '/events/';
            $strlen = -strlen('.event.php');
        }
        if ($this instanceof HookManager) {
            $regext = '#^.+/hooks/.*\.hook\.php$#';
            $dirpath = '/hooks/';
            $strlen = -strlen('.hook.php');
        }
        $plugins = '';
        $fileitems = function($element) use ($dirpath,&$plugins) {
            preg_match("#^($plugins.+/plugins/)(?=.*$dirpath).*$#",$element[0],$match);
            return $match[0];
        };
        $files = iterator_to_array(self::getClass('RegexIterator',self::getClass('RecursiveIteratorIterator',self::getClass('RecursiveDirectoryIterator',BASEPATH,FileSystemIterator::SKIP_DOTS)),$regext,RecursiveRegexIterator::GET_MATCH),false);
        $plugins = '?!';
        $normalfiles = array_values(array_filter(array_map($fileitems,(array)$files)));
        $plugins = '?=';
        $pluginfiles = array_values(array_filter(preg_grep(sprintf('#/(%s)/#',implode('|',$_SESSION['PluginsInstalled'])),array_map($fileitems,(array)$files))));
        $startClass = function($element) use ($strlen) {
            self::getClass(substr(basename($element),0,$strlen));
        };
        array_map($startClass,(array)$pluginfiles);
        unset($pluginfiles);
        $checkNormalAndStart = function($element) use ($strlen,$startClass) {
            if (($fh = fopen($element,'rb')) === false) return;
            while (feof($fh) === false) {
                unset($active);
                $line = fgets($fh, 4096);
                if ($line === false) continue;
                preg_match('#(\$active\s?=\s?.*;)#',$line,$linefound);
                eval(array_pop($linefound));
                if (!isset($active) || $active === false) continue;
                $startClass($element);
                break;
            }
            fclose($fh);
        };
        array_map($checkNormalAndStart,(array)$normalfiles);
    }
}
