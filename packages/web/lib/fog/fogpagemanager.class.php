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
    private $_nodes = [];
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
     * Replaces the variable passed with nicer names
     *
     * @param string $value the valu
     *
     * @return string
     */
    public static function replaceVariable(&$value)
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
            $this->classValue = self::replaceVariable($node);
        } else {
            $this->classValue = 'home';
        }
        $this->loadPageClasses();
        $this->methodValue = self::replaceVariable($sub);
        self::$HookManager->processEvent(
            'SEARCH_PAGES',
            ['searchPages' => &self::$searchPages]
        );
    }
    /**
     * Gets the page class
     *
     * @param string $override The sting to use in case.
     *
     * @return object
     */
    public function getFOGPageClass($override = '')
    {
        if (empty($override)) {
            $override = $this->classValue;
        }
        return $this->_nodes[$override];
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
     * Prints the data to the browser/screen
     *
     * @return void
     */
    public function render()
    {
        global $node;
        global $sub;
        $nodes = [
            'client',
            'schema',
            'ipxe'
        ];
        if (!self::$FOGUser->isValid()
            && !in_array($node, $nodes)
        ) {
            return;
        }
        $method = $this->methodValue;
        try {
            if (!array_key_exists($this->classValue, $this->_nodes)) {
                throw new Exception(_('No FOGPage Class found for this node'));
            }
            $class = $this->getFOGPageClass();
            if ($this->classValue == 'schema'
                || !method_exists($class, $method)
                || empty($method)
            ) {
                $method = 'index';
                self::getClass('Page')
                    ->addJavascript("js/fog/{$node}/fog.{$node}.list.js");
            }
            if (self::$ajax && method_exists($class, $method.'Ajax')) {
                $method .= 'Ajax';
            }
            if (self::$post && method_exists($class, $method.'Post')) {
                $method .= 'Post';
            }
            if (self::$post) {
                self::setRequest();
            } else {
                self::resetRequest();
            }
        } catch (Exception $e) {
            $this->debug(
                _('Failed to Render Page: Node: %s, Error: %s'),
                [
                    get_class($class),
                    $e->getMessage()
                ]
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
                [
                    $class->node,
                    get_class($class),
                    $e->getMessage()
                ]
            );
        }
        return $this;
    }
    /**
     * Loads the page class for us
     *
     * @return void
     */
    public function loadPageClasses()
    {
        global $node;
        $extension = '.page.php';
        $strlen = -strlen($extension);
        $files = self::fileitems(
            $extension,
            'pages'
        );

        foreach ($files as &$file) {
            $elementsub = substr($file, $strlen);
            if (!in_array($elementsub, ['.page.php','.report.php'], true)) {
                continue;
            }
            $className = substr(basename($file), 0, $strlen);
            if ($node == 'report') {
                $f = filter_input(INPUT_GET, 'f');
                if ($f) {
                    $className = str_replace(
                        ' ',
                        '_',
                        base64_decode(
                            $f
                        )
                    );
                }
            }
            if (!$className || !isset($className)) {
                continue;
            }
            if (in_array($className, get_declared_classes())
                || class_exists($className, false)
            ) {
                continue;
            }
            $vals = get_class_vars($className);
            if ($vals['node'] !== trim($node)) {
                continue;
            }
            unset($vals);
            $class = new $className;
            $this->_nodes[$this->classValue] = $class;
            $this->_register($class);
            unset($class);
        }
    }
}
