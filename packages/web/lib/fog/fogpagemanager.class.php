<?php
class FOGPageManager extends FOGBase
{
    private $pageTitle;
    private $nodes = array();
    protected $classValue;
    protected $methodValue;
    private $arguments;
    private $plugin_checked;
    private function replaceVariable(&$value)
    {
        $value = trim(preg_replace('#[^\w]#', '_', urldecode(trim($value))));
        return $value;
    }
    public function __construct()
    {
        parent::__construct();
        $this->classValue = isset($_REQUEST['node']) && $_REQUEST['node'] ? $this->replaceVariable($_REQUEST['node']) : 'home';
        unset($value);
        $this->methodValue = $this->replaceVariable($_REQUEST['sub']);
        self::$HookManager->processEvent('SEARCH_PAGES', array('searchPages'=>&self::$searchPages));
    }
    public function getFOGPageClass()
    {
        return $this->nodes[$this->classValue];
    }
    public function getFOGPageName()
    {
        return $this->getFOGPageClass()->name;
    }
    public function getFOGPageTitle()
    {
        return $this->getFOGPageClass()->title;
    }
    public function isFOGPageTitleEnabled()
    {
        return (bool)$this->getFOGPageClass()->titleEnabled == true && !empty($this->FOGPageClass()->title);
    }
    public function getSideMenu()
    {
        if (self::$FOGUser->isValid()) {
            $class = $this->getFOGPageClass();
            self::$FOGSubMenu = self::getClass('FOGSubMenu');
            array_walk($class->menu, function (&$title, &$link) {
                self::$FOGSubMenu->addItems($this->classValue, array((string)$title=>(string)$link));
                unset($title, $link);
            });
            if (is_object($class->obj)) {
                array_walk($class->subMenu, function (&$title, &$link) use ($class) {
                    self::$FOGSubMenu->addItems($this->classValue, array((string)$title=>(string)$link), $class->id, sprintf(self::$foglang['SelMenu'], get_class($class->obj)));
                    unset($title, $link);
                });
                array_walk($class->notes, function (&$title, &$link) use ($class) {
                    self::$FOGSubMenu->addNotes($this->classValue, array((string)$title=>(string)$link), $class->id, sprintf(self::$foglang['SelMenu'], get_class($class->obj)));
                    unset($title, $link);
                });
            }
            return sprintf('<div id="sidebar">%s</div>', self::$FOGSubMenu->get($this->classValue));
        }
    }
    public function render()
    {
        if (!(in_array($_REQUEST['node'], array('client', 'schema')) || self::$FOGUser->isValid())) {
            return;
        }
        $this->loadPageClasses();
        $method = $this->methodValue;
        try {
            $class = $this->getFOGPageClass();
            if ($this->classValue == 'schema') {
                $this->methodValue = 'index';
            }
            if (empty($method) || !method_exists($class, $method)) {
                $method = 'index';
            }
            $displayScreen = trim(strtolower($_SESSION['FOG_VIEW_DEFAULT_SCREEN']));
            if (!array_key_exists($this->classValue, $this->nodes)) {
                throw new Exception(_('No FOGPage Class found for this node'));
            }
            if (isset($_REQUEST[$class->id]) && $_REQUEST[$class->id]) {
                $this->arguments = array('id'=>$_REQUEST[$class->id]);
            }
            if (self::$post) {
                $this->setRequest();
            } else {
                $this->resetRequest();
            }
            if ($this->classValue != 'schema' && $method == 'index' && $displayScreen != 'list' && $this->methodValue != 'list' && method_exists($class, 'search') && in_array($class->node, self::$searchPages)) {
                $method = 'search';
            }
            if (self::$ajax && method_exists($class, $method.'Ajax')) {
                $method = $this->methodValue.'Ajax';
            }
            if (self::$post && method_exists($class, $method.'Post')) {
                $method = $this->methodValue.'Post';
            }
        } catch (Exception $e) {
            $this->debug(_('Failed to Render Page: Node: %s, Error: %s'), array(get_class($class), $e->getMessage()));
        }
        $class->$method();
        $this->resetRequest();
    }
    private function register($class)
    {
        if (!$class) {
            die(_('No class value sent'));
        }
        try {
            if (!($class instanceof FOGPage)) {
                throw new Exception(self::$foglang['NotExtended']);
            }
            if (!$class->node) {
                throw new Exception(_('No node associated'));
            }
            $this->info(sprintf(_('Adding FOGPage: %s, Node: %s'), get_class($class), $class->node));
            $this->nodes[$class->node] = $class;
        } catch (Exception $e) {
            $this->debug('Failed to add Page: Node: %s, Page Class: %s, Error: $s', array($class->node, get_class($class), $e->getMessage()));
        }
        return $this;
    }
    private function loadPageClasses()
    {
        $regext = '#^.+/pages/.*\.class\.php$#';
        $dirpath = '/pages/';
        $strlen = -strlen('.class.php');
        $plugins = '';
        $fileitems = function ($element) use ($dirpath, &$plugins) {
            preg_match("#^($plugins.+/plugins/)(?=.*$dirpath).*$#", $element[0], $match);
            return $match[0];
        };
        $RecursiveDirectoryIterator = new RecursiveDirectoryIterator(
            BASEPATH,
            FileSystemIterator::SKIP_DOTS
        );
        $RecursiveIteratorIterator = new RecursiveIteratorIterator(
            $RecursiveDirectoryIterator
        );
        $RegexIterator = new RegexIterator(
            $RecursiveIteratorIterator,
            $regext,
            RegexIterator::GET_MATCH
        );
        $files = iterator_to_array($RegexIterator, false);
        $plugins = '?!';
        $normalfiles = array_values(array_filter(array_map($fileitems, (array)$files)));
        $plugins = '?=';
        $pluginfiles = array_values(array_filter(preg_grep(sprintf('#/(%s)/#', implode('|', $_SESSION['PluginsInstalled'])), array_map($fileitems, (array)$files))));
        $files = array_values(array_filter(array_unique(array_merge($normalfiles, $pluginfiles))));
        unset($normalfiles, $pluginfiles);
        $startClass = function ($element) use ($strlen) {
            if (substr($element, $strlen) !== '.class.php') {
                return;
            }
            $className = substr(basename($element), 0, $strlen);
            if (!$className || !isset($className)) {
                return;
            }
            if (in_array($className, get_declared_classes()) || class_exists($className, false)) {
                return;
            }
            if ((self::$isMobile && !preg_match('#mobile#i', $className)) || (!self::$isMobile && preg_match('#mobile#i', $className))) {
                return;
            }
            $vals = get_class_vars($className);
            if ($vals['node'] !== trim($_REQUEST['node'])) {
                return;
            }
            unset($vals);
            $this->nodes[$this->classValue] = $className;
            $this->register(self::getClass($className));
        };
        array_map($startClass, (array)$files);
    }
}
