<?php
/**
 * Manages and presents the page items
 *
 * PHP version 5
 *
 * @category FOGPageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manages and presents the page items
 *
 * @category FOGPageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGPageManager extends FOGBase
{
    /**
     * Pages node reference point
     *
     * @var array
     */
    private $_nodes = array();
    /**
     * The pages class value
     *
     * @var string
     */
    protected $classValue;
    /**
     * The pages method to use
     *
     * @var string
     */
    protected $methodValue;
    /**
     * Any arguments
     *
     * @var mixed
     */
    private $_arguments;
    /**
     * Replaces the variable passed with nicer names
     *
     * @param string $value the valu
     *
     * @return string
     */
    private function _replaceVariable(&$value)
    {
        $value = trim($value);
        $value = preg_replace(
            '#[^\w]#',
            '_',
            urldecode($value)
        );
        $value = trim($value);
        return $value;
    }
    /**
     * Initializes the pages
     */
    public function __construct()
    {
        parent::__construct();
        global $node;
        global $sub;
        if (!empty($node)) {
            $this->classValue = $this->_replaceVariable($node);
        } else {
            $this->classValue = 'home';
        }
        $this->methodValue = $this->_replaceVariable($sub);
        self::$HookManager->processEvent(
            'SEARCH_PAGES',
            array('searchPages' => &self::$searchPages)
        );
    }
    /**
     * Gets the page class
     *
     * @return object
     */
    public function getFOGPageClass()
    {
        return $this->_nodes[$this->classValue];
    }
    /**
     * Gets the name of the page
     *
     * @return string
     */
    public function getFOGPageName()
    {
        return $this->getFOGPageClass()
            ->name;
    }
    /**
     * Gets the page title
     *
     * @return string
     */
    public function getFOGPageTitle()
    {
        return $this->getFOGPageClass()
            ->title;
    }
    /**
     * Gets the side menu
     *
     * @return void
     */
    public function getSideMenu()
    {
        if (!self::$FOGUser->isValid()) {
            return $this;
        }
        $class = $this->getFOGPageClass();
        self::$FOGSubMenu = self::getClass('FOGSubMenu');
        foreach ((array)$class->menu as $link => &$title) {
            self::$FOGSubMenu
                ->addItems(
                    $this->classValue,
                    array((string)$title => (string)$link)
                );
            unset($title, $link);
        }
        if (!is_object($class->obj)) {
            return sprintf(
                '<div id="sidebar">%s</div>',
                self::$FOGSubMenu->get($this->classValue)
            );
        }
        foreach ((array)$class->subMenu as $link => &$title) {
            self::$FOGSubMenu->addItems(
                $this->classValue,
                array((string)$title => (string)$link),
                $class->id,
                sprintf(
                    self::$foglang['SelMenu'],
                    get_class($class->obj)
                )
            );
            unset($title, $link);
        }
        foreach ((array)$class->notes as $link => &$title) {
            self::$FOGSubMenu->addNotes(
                $this->classValue,
                array((string)$title => (string)$link),
                $class->id
            );
            unset($title, $link);
        }
        return sprintf(
            '<div id="sidebar">%s</div>',
            self::$FOGSubMenu->get($this->classValue)
        );
    }
    /**
     * Prints the data to the browser/screen
     *
     * @return void
     */
    public function render()
    {
        global $node;
        $nodes = array(
            'client',
            'schema',
            'ipxe'
        );
        if (!self::$FOGUser->isValid()
            && !in_array($node, $nodes)
        ) {
            return;
        }
        $this->_loadPageClasses();
        $method = $this->methodValue;
        try {
            $class = $this->getFOGPageClass();
            if ($this->classValue == 'schema') {
                $this->methodValue = 'index';
            }
            if (empty($method) || !method_exists($class, $method)) {
                $method = 'index';
            }
            $displayScreen = self::$defaultscreen;
            $displayScreen = strtolower($displayScreen);
            $displayScreen = trim($displayScreen);
            if (!array_key_exists($this->classValue, $this->_nodes)) {
                throw new Exception(_('No FOGPage Class found for this node'));
            }
            if (isset($_REQUEST[$class->id])
                && $_REQUEST[$class->id]
            ) {
                $this->_arguments = array('id' => $_REQUEST[$class->id]);
            }
            if (self::$post) {
                self::setRequest();
            } else {
                self::resetRequest();
            }
            if ($this->classValue != 'schema'
                && $method == 'index'
                && $displayScreen != 'list'
                && $this->methodValue != 'list'
                && method_exists($class, 'search')
                && in_array($class->node, self::$searchPages)
            ) {
                $method = 'search';
            }
            if (self::$ajax && method_exists($class, $method.'Ajax')) {
                $method = $this->methodValue.'Ajax';
            }
            if (self::$post && method_exists($class, $method.'Post')) {
                $method = $this->methodValue.'Post';
            }
        } catch (Exception $e) {
            $this->debug(
                _('Failed to Render Page: Node: %s, Error: %s'),
                array(
                    get_class($class),
                    $e->getMessage()
                )
            );
        }
        /**
         * As a new method is being called, ensure the
         * alternate methods are clean of their constructed
         * data of header, attributes, data, and templates.
         */
        $nonresetmethods = array(
            'index',
            'search',
            'active',
            'pending',
        );
        $test = str_replace('Post', '', $method);
        $methodTest = preg_grep("#$test#i", $nonresetmethods);
        global $node;
        if ($node !== 'plugin'
            && count($methodTest) < 1
        ) {
            unset(
                $class->headerData,
                $class->data,
                $class->templates,
                $class->attributes
            );
        }
        $class->{$method}();
        self::resetRequest();
    }
    /**
     * Registers the class for display
     *
     * @param object $class the page to register
     *
     * @return void
     */
    private function _register($class)
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
            $this->info(
                sprintf(
                    _('Adding FOGPage: %s, Node: %s'),
                    get_class($class),
                    $class->node
                )
            );
            $this->_nodes[$class->node] = $class;
        } catch (Exception $e) {
            $this->debug(
                'Failed to add Page: Node: %s, Page Class: %s, Error: $s',
                array(
                    $class->node,
                    get_class($class),
                    $e->getMessage()
                )
            );
        }
        return $this;
    }
    /**
     * Loads the page class for us
     *
     * @return void
     */
    private function _loadPageClasses()
    {
        global $node;
        $regext = '#^.+/pages/.*\.class\.php$#';
        $dirpath = '/pages/';
        $strlen = -strlen('.class.php');
        $plugins = '';
        $fileitems = function ($element) use ($dirpath, &$plugins) {
            preg_match(
                "#^($plugins.+/plugins/)(?=.*$dirpath).*$#",
                $element[0],
                $match
            );
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
        unset(
            $RecursiveDirectoryIterator,
            $RecursiveIteratorIterator,
            $RegexIterator
        );
        $plugins = '?!';
        $normalfiles = array_values(
            array_filter(
                array_map(
                    $fileitems,
                    (array)$files
                )
            )
        );
        $plugins = '?=';
        $pluginfiles = array_values(
            array_filter(
                preg_grep(
                    sprintf(
                        '#/(%s)/#',
                        implode('|', self::$pluginsinstalled)
                    ),
                    array_map(
                        $fileitems,
                        (array)$files
                    )
                )
            )
        );
        $files = array_values(
            array_filter(
                array_unique(
                    self::fastmerge(
                        $normalfiles,
                        $pluginfiles
                    )
                )
            )
        );
        unset($normalfiles, $pluginfiles);
        $startClass = function ($element) use ($strlen, $node) {
            if (substr($element, $strlen) !== '.class.php'
                && substr($element, $strlen) !== '.report.php'
            ) {
                return;
            }
            $className = substr(basename($element), 0, $strlen);
            if ($node == 'report'
                && filter_input(INPUT_GET, 'f')
            ) {
                $className = str_replace(
                    ' ',
                    '_',
                    base64_decode(
                        filter_input(INPUT_GET, 'f')
                    )
                );
            }
            if (!$className || !isset($className)) {
                return;
            }
            if (in_array($className, get_declared_classes())
                || class_exists($className, false)
            ) {
                return;
            }
            if ((self::$isMobile && !preg_match('#mobile#i', $className))
                || (!self::$isMobile && preg_match('#mobile#i', $className))
            ) {
                return;
            }
            $vals = get_class_vars($className);
            if ($vals['node'] !== trim($node)) {
                return;
            }
            unset($vals);
            $class = new $className;
            $this->_nodes[$this->classValue] = $class;
            $this->_register($class);
            unset($class);
        };
        array_map($startClass, (array)$files);
    }
}
