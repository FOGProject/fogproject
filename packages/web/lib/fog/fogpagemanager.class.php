<?php
class FOGPageManager Extends FOGBase {
    private $pageTitle;
    private $nodes = array();
    protected $classValue;
    protected $methodValue;
    private $arguments;
    private $plugin_checked;
    private function replaceVariable(&$value) {
        $value = trim(preg_replace('#[^\w]#','_',urldecode(trim($value))));
        return $value;
    }
    public function __construct() {
        parent::__construct();
        $this->classValue = isset($_REQUEST['node']) && $_REQUEST['node'] ? $this->replaceVariable($_REQUEST['node']) : 'home';
        unset($value);
        $this->methodValue = $this->replaceVariable($_REQUEST['sub']);
        $this->HookManager->processEvent('SEARCH_PAGES',array('searchPages'=>&$this->searchPages));
    }
    public function getFOGPageClass() {
        return $this->nodes[$this->classValue];
    }
    public function getFOGPageName() {
        return $this->getFOGPageClass()->name;
    }
    public function getFOGPageTitle() {
        return $this->getFOGPageClass()->title;
    }
    public function isFOGPageTitleEnabled() {
        return (bool)$this->getFOGPageClass()->titleEnabled == true && !empty($this->FOGPageClass()->title);
    }
    public function getSideMenu() {
        if ($this->FOGUser->isValid()) {
            $class = $this->getFOGPageClass();
            $this->FOGSubMenu = $this->getClass('FOGSubMenu');
            foreach ((array)$class->menu AS $link => &$title) $this->FOGSubMenu->addItems($this->classValue,array((string)$title=>(string)$link));
            unset($title);
            if (is_object($class->obj)) {
                foreach ((array)$class->subMenu AS $link => &$title) $this->FOGSubMenu->addItems($this->classValue,array((string)$title=>(string)$link),$class->id,sprintf($this->foglang['SelMenu'],get_class($class->obj)));
                unset($title);
                foreach((array)$class->notes AS $title => $item) $this->FOGSubMenu->addNotes($this->classValue,array((string)$title => (string)$item),$class->id,sprintf($this->foglang[SelMenu],get_class($class->obj)));
                unset($item);
            }
            return sprintf('<div id="sidebar">%s</div>',$this->FOGSubMenu->get($this->classValue));
        }
    }
    public function render() {
        $toRender = in_array($_REQUEST['node'],array('client','schemaupdater')) || in_array($_REQUEST['sub'],array('configure','authorize','requestClientInfo')) || ($this->FOGUser->isValid());
        if ($toRender) {
            $this->loadPageClasses();
            try {
                $class = $this->getFOGPageClass();
                $method = $this->methodValue;
                if ($this->classValue == 'schemaupdater') $this->methodValue = 'index';
                if (empty($method) || !method_exists($class, $method)) $method = 'index';
                $displayScreen = trim(strtolower($_SESSION['FOG_VIEW_DEFAULT_SCREEN']));
                if (!array_key_exists($this->classValue, $this->nodes)) throw new Exception(_('No FOGPage Class found for this node'));
                if (isset($_REQUEST[$class->id]) && $_REQUEST[$class->id]) $this->arguments = array('id'=>$_REQUEST[$class->id]);
                if ($this->post) $this->setRequest();
                else $this->resetRequest();
                if ($this->classValue != 'schemaupdater' && $method == 'index' && $displayScreen != 'list' && $this->methodValue != 'list' && method_exists($class, 'search') && in_array($class->node,$this->searchPages)) $method = 'search';
                if ($this->ajax && method_exists($class, $method.'_ajax')) $method = $this->methodValue.'_ajax';
                if ($this->post && method_exists($class, $method.'_post')) $method = $this->methodValue.'_post';
            } catch (Exception $e) {
                $this->debug(_('Failed to Render Page: Node: %s, Error: %s'),array(get_class($class),$e->getMessage()));
            }
            call_user_func(array($class, $method));
            $this->resetRequest();
        }
    }
    private function register($class) {
        if (!$class) die(_('No class value sent'));
        try {
            if (!($class instanceof FOGPage)) throw new Exception($this->foglang['NotExtended']);
            if (!$class->node) throw new Exception(_('No node associated'));
            $this->info(sprintf(_('Adding FOGPage: %s, Node: %s'),get_class($class),$class->node));
            $this->nodes[$class->node] = $class;
        } catch (Exception $e) {
            $this->debug('Failed to add Page: Node: %s, Page Class: %s, Error: $s',array($class->node,get_class($class),$e->getMessage()));
        }
        return $this;
    }
    private function loadPageClasses() {
        $regext = '#^.+/pages/.*\.class\.php$#';
        $dirpath = '/pages/';
        $strlen = -strlen('.class.php');
        $plugins = '';
        $fileitems = function($element) use ($dirpath,&$plugins) {
            preg_match("#^($plugins.+/plugins/)(?=.*$dirpath).*$#",$element[0],$match);
            return $match[0];
        };
        $files = iterator_to_array($this->getClass('RegexIterator',$this->getClass('RecursiveIteratorIterator',$this->getClass('RecursiveDirectoryIterator',BASEPATH,FileSystemIterator::SKIP_DOTS)),$regext,RecursiveRegexIterator::GET_MATCH),false);
        $plugins = '?!';
        $normalfiles = array_values(array_filter(array_map($fileitems,(array)$files)));
        $plugins = '?=';
        $pluginfiles = array_values(array_filter(preg_grep(sprintf('#/(%s)/#',implode('|',$_SESSION['PluginsInstalled'])),array_map($fileitems,(array)$files))));
        $files = array_values(array_filter(array_unique(array_merge($normalfiles,$pluginfiles))));
        unset($normalfiles,$pluginfiles);
        $startClass = function($element) use ($strlen) {
            if (substr($element,$strlen) !== '.class.php') return;
            $className = substr(basename($element),0,$strlen);
            if (in_array($className,get_declared_classes())) return;
            if (($this->isMobile && !preg_match('#mobile#i',$className)) || (!$this->isMobile && preg_match('#mobile#i',$className))) return;
            $vals = get_class_vars($className);
            if ($vals['node'] !== trim(htmlentities($_REQUEST['node'],ENT_QUOTES,'utf-8'))) return;
            unset($vals);
            $this->nodes[$this->classValue] = $className;
            $this->register($this->getClass($className));
        };
        array_map($startClass,(array)$files);
    }
}
