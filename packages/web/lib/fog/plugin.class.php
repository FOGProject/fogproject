<?php
class Plugin extends FOGController {
    private $strName;
    private $strDesc;
    private $strEntryPoint;
    private $strVersion;
    private $strPath;
    private $strIcon;
    private $strIconHover;
    private $blIsInstalled;
    private $blIsActive;
    protected $databaseTable = 'plugins';
    protected $databaseFields = array(
        'id' => 'pID',
        'name' => 'pName',
        'state' => 'pState',
        'installed' => 'pInstalled',
        'version' => 'pVersion',
        'pAnon1' => 'pAnon1',
        'pAnon2' => 'pAnon2',
        'pAnon3' => 'pAnon3',
        'pAnon4' => 'pAnon4',
        'pAnon5' => 'pAnon5',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    public function getRunInclude($hash) {
        $Plugins = $this->getPlugins();
        foreach($Plugins AS $i => &$Plugin) {
            if(trim(md5(trim($Plugin->getName()))) == trim($hash)) {
                $_SESSION['fogactiveplugin'] = serialize($Plugin);
                return $Plugin->getEntryPoint();
            }
        }
        unset($Plugin);
    }
    public function getActivePlugs() {
        $Plugin = $this->getClass('Plugin',@min($this->getSubObjectIDs('Plugin',array('name'=>$this->getName()),'id')));
        $this->blIsActive = (bool)($Plugin->get('state') == 1);
        $this->blIsInstalled = (bool)($Plugin->get('installed') == 1);
    }
    private function getDirs() {
        $dir = trim($this->getSetting('FOG_PLUGINSYS_DIR'));
        if ($dir != '../lib/plugins/') $this->setSetting('FOG_PLUGINSYS_DIR','../lib/plugins/');
        $dir='../lib/plugins/';
        $handle = opendir($dir);
        while (false !== ($file = readdir($handle))) {
            if(file_exists(sprintf('%s%s/config/plugin.config.php',$dir,$file))) $files[] = sprintf('%s%s/',$dir,$file);
        }
        closedir($handle);
        natcasesort($files);
        $files = array_values((array)$files);
        return $files;
    }
    public function getPlugins() {
        $cfgfile = 'plugin.config.php';
        foreach ((array)$this->getDirs() AS $i => &$file) {
            require(sprintf('%s/config/%s',rtrim($file,'/'),$cfgfile));
            $p = $this->getClass('Plugin',array('name'=>$fog_plugin['name']));
            $p->strPath = $file;
            $p->strName = $fog_plugin['name'];
            $p->strDesc = $fog_plugin['description'];
            $p->strEntryPoint = sprintf('%s%s',$file,$fog_plugin['entrypoint']);
            $p->strIcon = sprintf('%s%s',$file,$fog_plugin['menuicon']);
            $p->strIconHover = sprintf('%s%s',$file,$fog_plugin['menuicon_hover']);
            $arPlugs[] = $p;
            unset($file);
        }
        unset($cfgfile);
        return $arPlugs;
    }
    public function activatePlugin($plugincode) {
        $this->debug = true;
        $Plugins = $this->getPlugins();
        foreach ((array)$this->getPlugins() AS $i => &$Plugin) {
            if (trim(md5(trim($Plugin->getName()))) != trim($plugincode)) continue;
            $Plugin->set('state',1)
                ->set('installed',0)
                ->set('name',$Plugin->getName())
                ->save();
            unset($Plugin);
        }
        return $this;
    }
    public function getManager() {
        if (!class_exists(sprintf('%sManager',ucfirst($this->get('name'))))) return parent::getManager();
        return $this->getClass(sprintf('%sManager',ucfirst($this->get('name'))));
    }
    public function getPath() {
        return $this->strPath;
    }
    public function getName() {
        if (isset($this->strName)) return $this->strName;
        return $this->get('name');
    }
    public function getDesc() {
        return $this->strDesc;
    }
    public function getEntryPoint() {
        return $this->strEntryPoint;
    }
    public function getIcon() {
        return $this->strIcon;
    }
    public function isInstalled() {
        $this->getActivePlugs();
        return $this->blIsInstalled;
    }
    public function isActive() {
        $this->getActivePlugs();
        return $this->blIsActive;
    }
    public function getVersion() {
        return $this->strVersion;
    }
}
