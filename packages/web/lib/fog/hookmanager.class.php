<?php
class HookManager extends EventManager {
    public $logLevel = 0;
    public $data;
    public $events;
    public function getEvents() {
        $regexp = '#processEvent\([\'\"](.*?)[\'\"]#';
        global $Init;
        foreach (self::getClass('RegexIterator',self::getClass('RecursiveIteratorIterator',self::getClass('RecursiveDirectoryIterator',BASEPATH,FileSystemIterator::SKIP_DOTS)),'/^.+\.php$/i',RecursiveRegexIterator::GET_MATCH) AS $file) {
            if (($fh = fopen($file[0],'rb')) === false) continue;
            while (feof($fh) === false) {
                if (($line = fgets($fh,4096)) === false) continue;
                preg_match_all($regexp,$line,$match);
                $this->events[] = array_shift($match[1]);
            }
            fclose($fh);
            unset($file);
        }
        foreach(self::getClass('ServiceManager')->getSettingCats() AS &$CAT) {
            $divTab = preg_replace('/[\s+\:\.]/','_',$CAT);
            $this->events[] = sprintf('CLIENT_UPDATE_%s',$divTab);
            unset($CAT);
        }
        foreach(self::getClass('PXEMenuOptionsManager')->find() AS &$Menu) {
            $divTab = preg_replace('/[\s+\:\.]/','_',$Menu->get('name'));
            $this->events[] = sprintf('BOOT_ITEMS_%s',$divTab);
            unset($Menu);
        }
        array_merge($this->events,array('HOST_DEL','HOST_DEL_POST','GROUP_DEL','GROUP_DEL_POST','IMAGE_DEL','IMAGE_DEL_POST','SNAPIN_DEL','SNAPIN_DEL_POST','PRINTER_DEL','PRINTER_DEL_POST','HOST_DEPLOY','GROUP_DEPLOY','HOST_EDIT_TASKS','GROUP_EDIT_TASKS','HOST_EDIT_ADV','GROUP_EDIT_ADV','HOST_EDIT_AD','GROUP_EDIT_AD'));
        natcasesort($this->events);
        $this->events = array_unique(array_filter($this->events));
        $this->events = array_values($this->events);
    }
    public function processEvent($event, $arguments = array()) {
        if (!isset($this->data[$event])) return;
        foreach ($this->data[$event] AS &$function) {
            if (!$function[0]->active) continue;
            call_user_func($function, array_merge(array('event'=>$event), (array)$arguments));
            unset($function);
        }
    }
}
