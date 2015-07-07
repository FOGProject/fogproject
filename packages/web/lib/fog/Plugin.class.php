<?php
class Plugin extends FOGController {
    private $strName, $strDesc, $strEntryPoint, $strVersion, $strPath, $strIcon, $strIconHover, $blIsInstalled, $blIsActive;
    // Table
    public $databaseTable = 'plugins';
    // Name -> Database field name
    public $databaseFields = array(
        'id'			=> 'pID',
        'name'			=> 'pName',
        'state'			=> 'pState',
        'installed'		=> 'pInstalled',
        'version'		=> 'pVersion',
        'pAnon1'		=> 'pAnon1',
        'pAnon2'		=> 'pAnon2',
        'pAnon3'		=> 'pAnon3',
        'pAnon4'		=> 'pAnon4',
        'pAnon5'		=> 'pAnon5',
    );
    // Required database fields
    public $databaseFieldsRequired = array(
        'name',
    );
    public function getRunInclude($hash) {
        $Plugins = $this->getPlugins();
        foreach($Plugins AS $i => &$Plugin) {
            if(md5(trim($Plugin->getName())) == trim($hash)) {
                $_SESSION['fogactiveplugin']=serialize($Plugin);
                $entrypoint = $Plugin->getEntryPoint();
                break;
            }
        }
        unset($Plugin);
        return $entrypoint;
    }
    public function getActivePlugs() {
        $Plugin = current($this->getClass('PluginManager')->find(array('name' => $this->getName())));
        $this->blIsActive = ($Plugin && $Plugin->isValid() ? ($Plugin->get('state') == 1 ? 1 : 0) : 0);
        $this->blIsInstalled = ($Plugin && $Plugin->isValid() ? ($Plugin->get('installed') == 1 ? 1 : 0) : 0);
    }
    private function getDirs() {
        $dir = trim($this->FOGCore->getSetting('FOG_PLUGINSYS_DIR'));
        // For now, automatically sets the plugin directory.  Should not be moved though so classes work properly.
        $dir != '../lib/plugins/' ?	$this->FOGCore->setSetting('FOG_PLUGINSYS_DIR','../lib/plugins/') : null;
        $dir = '../lib/plugins/';
        $handle=opendir($dir);
        while(false !== ($file=readdir($handle))) {
            if(file_exists($dir.$file.'/config/plugin.config.php')) $files[] = $dir.$file.'/';
        }
        closedir($handle);
        return $files;
    }
    public function getPlugins() {
        $cfgfile = 'plugin.config.php';
        $Dirs = $this->getDirs();
        foreach($Dirs AS $i => &$file) {
            require(rtrim($file,'/').'/config/'.$cfgfile);
            $p=new Plugin(array('name' => $fog_plugin['name']));
            $p->strPath = $file;
            $p->strName = $fog_plugin['name'];
            $p->strDesc = $fog_plugin['description'];
            $p->strEntryPoint = $file.$fog_plugin['entrypoint'];
            $p->strIcon = $file.$fog_plugin['menuicon'];
            $p->strIconHover = $file.$fog_plugin['menuicon_hover'];
            $arPlugs[] = $p;
        }
        unset($file);
        return $arPlugs;
    }
    public function activatePlugin($plugincode) {
        $Plugins = $this->getPlugins();
        foreach($Plugins AS $i => &$Plugin) {
            if(md5(trim($Plugin->getName())) == trim($plugincode)) {
                $this->set('state',1)
                    ->set('installed',0)
                    ->set('name',$Plugin->getName())
                    ->save();
            }
        }
        unset($Plugin);
        return $this;
    }
    public function getManager() {return $this->getClass(ucfirst($this->get(name)).'Manager');}
        public function getPath() {return $this->strPath;}
        public function getName() {return $this->strName;}
        public function getDesc() {return $this->strDesc;}
        public function getEntryPoint() {return $this->strEntryPoint;}
        public function getIcon() {return $this->strIcon;}
        public function isInstalled() {$this->getActivePlugs();return $this->blIsInstalled;}
        public function isActive() {$this->getActivePlugs();return $this->blIsActive;}
        public function getVersion() {return $this->strVersion;}
}
