<?php
class HookManager extends EventManager {
    public $logLevel = 0;
    public $data;
    public $events;
    public function getEvents() {
        global $Init;
        $eventStart = 'CLIENT_UPDATE';
        $fileRead = function(&$file) {
            $regexp = '#processEvent\([\'\"](.*?)[\'\"]#';
            if (($fh = fopen($file[0],'rb')) === false) return;
            while (feof($fh) === false) {
                if (($line = fgets($fh,4096)) === false) return;
                preg_match_all($regexp,$line,$match);
                $this->events[] = array_shift($match[1]);
            }
            fclose($fh);
            unset($file);
        };
        $specTabs = function(&$item) use (&$eventStart) {
            $divTab = preg_replace('/[\s+\:\.]/','_',$item);
            $this->events[] = sprintf('%s_%s',$eventStart,$divTab);
            unset($item);
        };
        array_map($fileRead,(array)iterator_to_array(self::getClass('RegexIterator',self::getClass('RecursiveIteratorIterator',self::getClass('RecursiveDirectoryIterator',BASEPATH,FileSystemIterator::SKIP_DOTS)),'/^.+\.php$/i',RegexIterator::GET_MATCH)));
        array_map($specTabs,(array)self::getClass('ServiceManager')->getSettingCats());
        $eventStart = 'BOOT_ITEMS';
        array_map($specTabs,(array)self::getSubObjectIDs('PXEMenuOptions','','name'));
        array_merge($this->events,array('HOST_DEL','HOST_DEL_POST','GROUP_DEL','GROUP_DEL_POST','IMAGE_DEL','IMAGE_DEL_POST','SNAPIN_DEL','SNAPIN_DEL_POST','PRINTER_DEL','PRINTER_DEL_POST','HOST_DEPLOY','GROUP_DEPLOY','HOST_EDIT_TASKS','GROUP_EDIT_TASKS','HOST_EDIT_ADV','GROUP_EDIT_ADV','HOST_EDIT_AD','GROUP_EDIT_AD'));
        natcasesort($this->events);
        $this->events = array_unique(array_filter($this->events));
        $this->events = array_values($this->events);
    }
    public function processEvent($event, $arguments = array()) {
        if (!isset($this->data[$event])) return;
        array_map(function(&$function) use ($event,$arguments) {
            if (stripos(self::getClass('ReflectionClass',get_class($function[0]))->getFileName(),'plugins') === false && !$function[0]->active) return;
            if (!method_exists($function[0],$function[1])) return;
            $function[0]->{$function[1]}(array_merge(array('event'=>$event),(array)$arguments));
            unset($function);
        },(array)$this->data[$event]);
    }
}
