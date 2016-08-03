<?php
class Plugin extends FOGController {
    private $strName;
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
    protected $additionalFields = array(
        'description',
    );
    public function getRunInclude($hash) {
        $hash = trim($hash);
        $Plugin = self::getClass('Plugin',0);
        array_map(function(&$P) use (&$Plugin,$hash) {
            $tmphash = trim(md5(trim($P->get('name'))));
            if ($tmphash !== $hash) return;
            $Plugin = $P;
            unset($P);
            $_SESSION['fogactiveplugin'] = $Plugin->get('name');
        },(array)$this->getPlugins());
        return $Plugin->getEntryPoint();
    }
    private function getActivePlugs() {
        $this->blIsActive = (bool)($this->get('state'));
        $this->blIsInstalled = (bool)($this->get('installed'));
    }
    private function getDirs() {
        $dir = trim(self::getSetting('FOG_PLUGINSYS_DIR'));
        if ($dir != '../lib/plugins/') {
            $this->setSetting('FOG_PLUGINSYS_DIR','../lib/plugins/');
            $dir = '../lib/plugins/';
        }
        $patternReplacer = function($element) {
            return preg_replace('#config/plugin\.config\.php$#i','',$element[0]);
        };
        $files = array_map($patternReplacer,(array)iterator_to_array(self::getClass('RegexIterator',self::getClass('RecursiveIteratorIterator',self::getClass('RecursiveDirectoryIterator',$dir,FileSystemIterator::SKIP_DOTS)),'#^.+/config/plugin\.config\.php$#i',RegexIterator::GET_MATCH),false));
        natcasesort($files);
        return (array)array_values(array_unique(array_filter($files)));
    }
    public function getPlugins() {
        return array_map(function(&$file) {
            require sprintf('%s/config/plugin.config.php',rtrim($file,'/'));
            $p = self::getClass('Plugin',@min(self::getSubObjectIDs('Plugin',array('name'=>$fog_plugin['name']))))
                ->set('name',$fog_plugin['name'])
                ->set('description',$fog_plugin['description']);
            $p->strPath = $file;
            $p->strEntryPoint = sprintf('%s%s',$file,$fog_plugin['entrypoint']);
            $p->strIcon = preg_match('#^fa[-]?#',$fog_plugin['menuicon']) ? $fog_plugin['menuicon'] : sprintf('%s%s',$file,$fog_plugin['menuicon']);
            $p->strIconHover = sprintf('%s%s',$file,$fog_plugin['menuicon_hover']);
            unset($file,$fog_plugin);
            return $p;
        },(array)$this->getDirs());
    }
    public function activatePlugin($hash) {
        $hash = trim($hash);
        array_map(function(&$Plugin) use (&$hash) {
            $tmphash = trim(md5(trim($Plugin->get('name'))));
            if ($tmphash !== $hash) return;
            $Plugin
                ->set('state','1')
                ->set('installed','0')
                ->set('name',$Plugin->get('name'))
                ->save();
        },(array)$this->getPlugins());
        return $this;
    }
    public function getManager() {
        if (!class_exists(sprintf('%sManager',$this->get('name')))) return parent::getManager();
        return self::getClass($this->get('name'))->getManager();
    }
    public function getPath() {
        return $this->strPath;
    }
    private function getEntryPoint() {
        return $this->strEntryPoint;
    }
    public function getIcon() {
        return $this->strIcon;
    }
    public function isInstalled() {
        $this->getActivePlugs();
        return (bool)$this->blIsInstalled;
    }
    public function isActive() {
        $this->getActivePlugs();
        return (bool)$this->blIsActive;
    }
    public function getVersion() {
        return $this->strVersion;
    }
}
