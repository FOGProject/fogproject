<?php
class HookManager extends EventManager {
    public $logLevel = 0;
    public $data;
    public $events;
    public function register($event, $function) {
        try {
            if ($this->isMobile && !$function[0]->mobile) throw new Exception(_('Not registering to mobile page'));
            if (!is_array($function) || count($function) != 2) throw new Exception('Function is invalid');
            if (!method_exists($function[0], $function[1])) throw new Exception('Function does not exist');
            if (!($function[0] instanceof Hook)) throw new Exception('Not a valid hook class');
            $this->log(sprintf('Registering Hook: Event: %s, Function: %s', $event, $function[1]));
            $this->data[$event][] = $function;
            return true;
        }
        catch (Exception $e) {
            $this->log(sprintf('Could not register Hook: Error: %s, Event: %s, Function: %s', $e->getMessage(), $event, $function[1]));
        }
        return false;
    }
    public function getEvents() {
        global $Init;
        $paths = array(BASEPATH.'/management');
        $paths = array_merge((array)$paths,(array)$Init->PagePaths,(array)$Init->FOGPaths);
        foreach($paths AS $i => &$path) {
            if (is_dir($path)) {
                $dir = new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS);
                $Iterator = new RecursiveIteratorIterator($dir);
                $Iterator = new RegexIterator($Iterator,'/^.+\.php$/i',RecursiveRegexIterator::GET_MATCH);
                $regexp = '#processEvent\([\'\"](.*?)[\'\"]#';
                foreach($Iterator AS $i => $file) preg_match_all($regexp,file_get_contents($file[0]),$matches[]);
                unset($file);
                $matches = $this->array_filter_recursive($matches);
                foreach($matches AS $match => &$value) {
                    if ($matches[$match][1]) $matching[] = $matches[$match][1];
                }
                unset($value);
                foreach($matching AS $ind => &$arr) {
                    foreach($arr AS $i => &$val) $this->events[] = $val;
                    unset($val);
                }
                unset($arr);
            }
        }
        unset($path);
        $ServiceCats = $this->getClass(ServiceManager)->getSettingCats();
        foreach($ServiceCats AS $i => &$CAT) {
            $divTab = preg_replace('/[\s+\:\.]/','_',$CAT);
            array_push($this->events,'CLIENT_UPDATE_'.$divTab);
        }
        unset($CAT);
        $PXEs = $this->getClass(PXEMenuOptionsManager)->find();
        foreach($PXEs AS $i => &$Menu) {
            $divTab = preg_replace('/[\s+\:\.]/','_',$Menu->get('name'));
            array_push($this->events,'BOOT_ITEMS_'.$divTab);
        }
        unset($Menu);
        array_push($this->events,'HOST_DEL','HOST_DEL_POST','GROUP_DEL','GROUP_DEL_POST','IMAGE_DEL','IMAGE_DEL_POST','SNAPIN_DEL','SNAPIN_DEL_POST','PRINTER_DEL','PRINTER_DEL_POST','HOST_DEPLOY','GROUP_DEPLOY','HOST_EDIT_TASKS','GROUP_EDIT_TASKS','HOST_EDIT_ADV','GROUP_EDIT_ADV','HOST_EDIT_AD','GROUP_EDIT_AD');
        $this->events = array_unique($this->events);
        $this->events = array_values($this->events);
        asort($this->events);
    }
    public function processEvent($event, $arguments = array()) {
        if ($this->data[$event]) {
            foreach ($this->data[$event] AS $i => &$function) {
                if ($function[0]->active) {
                    $this->log(sprintf('Running Hook: Event: %s, Class: %s', $event, get_class($function[0]), $function[0]));
                    call_user_func($function, array_merge(array('event'=>$event), (array)$arguments));
                } else $this->log(sprintf('Inactive Hook: Event: %s, Class: %s', $event, get_class($function[0]), $function[0]));
            }
        }
    }
    public function load() {
        global $Init;
        foreach($Init->HookPaths AS $i => &$path) {
            if (!file_exists($path)) continue;
            if (preg_match('#plugins#i',$path) && !in_array(basename(substr($path,0,-6)),(array)$_SESSION['PluginsInstalled'])) continue;
            $iterator = $this->getClass('DirectoryIterator',$path);
            foreach ($iterator AS $i => $fileInfo) {
                $className = null;
                if (substr($fileInfo->getFilename(),-9) != '.hook.php') continue;
                $className = substr($fileInfo->getFilename(),0,-9);
                if (!$className || in_array($className,get_declared_classes())) continue;
                if (preg_match('#plugins#i',$fileInfo->getPathname())) {
                    $this->getClass($className);
                    continue;
                }
                if (!($handle = fopen($fileInfo->getPathname(),'rb'))) continue;
                while (($line = fgets($handle,8192)) !== false && !$linefound) {
                    $linefound = preg_match('#(active\s=|active=)#',$line) ? $line : false;
                    if ($linefound) break;
                }
                fclose($handle);
                if (strpos($linefound,'true')) $this->getClass($className);
                unset($handle,$line,$linefound);
            }
            unset($iterator);
            unset($path);
        }
        parent::load();
    }
    public function log($txt, $level = 1) {
        if (!$this->ajax && $this->logLevel >= $level)
            printf('[%s] %s%s', $this->nice_date()->format("d-m-Y H:i:s"), trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt)), "<br />\n");
    }
}
