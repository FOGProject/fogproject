<?php
/**
 * Manages and presents the page items
 *
 * PHP version 7.4+
 *
 * @category FOGPageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

use FOG\Auth\Authorization;

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
        global $id;
        $nodes = [
            'client',
            'schema'
        ];
        if (!self::$FOGUser->isValid()
            && !in_array($node, $nodes)
        ) {
            return;
        }
        $method = $this->methodValue;
        try {
            if (!array_key_exists($this->classValue, $this->_nodes)) {
                // Dispatch owns the unknown-node case.
                //
                // It used to be caught upstream: buildMainMenuItems() compared
                // $node against the known node list and redirected to the
                // dashboard, and that ran from Page::__construct() -- before
                // this dispatch. The menu build now happens at page-render
                // time so AJAX requests stop building a menu they discard,
                // which puts it AFTER dispatch, so the guard can no longer be
                // relied on to get here first.
                //
                // Redirect rather than throw. Throwing landed in the catch
                // below, which called get_class($class) with $class still
                // unassigned -- on PHP 8 a TypeError, uncaught, so an unknown
                // node returned a bare HTTP 500 with an empty body. Matching
                // the old 308-to-dashboard keeps the behaviour users and any
                // stale bookmarks already expect.
                self::redirect('../management/index.php');
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
            // The schema deploy endpoint must run before any user/session or
            // database exists (fresh install), so it cannot satisfy
            // checkAuthAndCSRF(). Allow it without auth ONLY when a valid
            // bootstrap credential is presented: the installer's request
            // header, or the URL token while the install is still userless.
            // Every other node -- and schema without one -- still requires
            // auth (#825). An admin upgrading needs no bypass; 'schema' is an
            // Authorization::EXEMPT_NODES entry, so they pass the gate below.
            $schemaBootstrap = ($node === 'schema'
                && self::validSchemaBootstrap());
            if (self::$ajax && method_exists($class, $method.'Ajax')) {
                $method .= 'Ajax';
                if (!$schemaBootstrap) {
                    self::checkAuthAndCSRF();
                }
            }
            if (self::$post && method_exists($class, $method.'Post')) {
                $method .= 'Post';
                if (!$schemaBootstrap) {
                    self::checkAuthAndCSRF();
                }
            }
            // Role-based permission gate: every page load, AJAX fragment,
            // and POST funnels through here. Resolution uses the base sub
            // (not the Ajax/Post-suffixed method) plus the actual request
            // method. Denial responds/redirects and exits.
            if (!$schemaBootstrap) {
                Authorization::requirePagePermission(
                    $this->classValue,
                    $this->methodValue
                );
                // Object-scope boundary (optional, plugin-enforced): when a
                // single object is addressed by URL id, confirm it is within
                // the acting user's scope. Inert unless a listener registers
                // for OBJECT_SCOPE_CHECK.
                Authorization::requirePageObjectScope(
                    $this->classValue,
                    $id
                );
            }
            if (self::$post) {
                self::setRequest();
            } else {
                self::resetRequest();
            }
        } catch (\Exception $e) {
            $this->debug(
                _('Failed to Render Page: Node: %s, Error: %s'),
                [
                    // The node name, not get_class($class): $class is assigned
                    // inside the try, so anything thrown before that left it
                    // undefined and get_class(null) fatally errored *inside the
                    // error handler*, replacing the real diagnostic with an
                    // uncaught TypeError. classValue is always set and is what
                    // "Node: %s" actually wants.
                    $this->classValue,
                    $e->getMessage()
                ]
            );
        }
        // Nothing to dispatch to if the try never got as far as assigning
        // $class. Calling a method on null here would be a second fatal, in
        // the path that is supposed to be recovering from the first.
        if (!isset($class) || !is_object($class)) {
            return;
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
                throw new \Exception(self::$foglang['NotExtended']);
            }
            if (!$class->node) {
                throw new \Exception(_('No node associated'));
            }
            self::info(
                sprintf(
                    _('Adding FOGPage: %s, Node: %s'),
                    self::shortName($class),
                    $class->node
                )
            );
            $this->_nodes[$class->node] = $class;
        } catch (\Exception $e) {
            $this->debug(
                _('Failed to add Page: Node: %s, Page Class: %s, Error: %s'),
                [
                    $class->node,
                    self::shortName($class),
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
            // The same two failure shapes FOGBase::startClassFromFiles()
            // already guards, for the same reason -- and the consequence is
            // worse here, because this runs on the way to rendering EVERY
            // page rather than only while registering listeners.
            //
            // get_class_vars() on a name no class declares is an uncaught
            // TypeError in PHP 8, thrown out of FOGPageManager's constructor,
            // which management/index.php builds before it can render
            // anything. So one bad file is a bodyless 500 on the whole admin
            // UI, not just on that plugin's own page -- and deleting the file
            // does NOT clear it, because the class-file list is a TTL-cached
            // snapshot that keeps naming it for up to five more minutes.
            //
            // Reachable from outside the tree since ADR 0009: a third-party
            // plugin whose page file does not declare the class its name
            // promises (a namespace with no class_alias back to the global
            // name is the easy way to get there) takes the whole UI down for
            // everyone, and the only way out is a shell. Observed exactly
            // that way while verifying the PSR-4 move -- a probe plugin
            // uploaded through Plugin Management, 500 on every page, and the
            // fix was rm plus clearing /opt/fog/cache/filelist.*.json.
            //
            // Skipped and logged rather than swallowed, and error_log() for
            // the reasons startClassFromFiles() sets out: a page that does
            // not register is a feature that silently stops happening, and
            // this can run before FOG can write its own log.
            if (!is_file($file)) {
                error_log(
                    sprintf(
                        'FOG loadPageClasses: %s is in the cached class file'
                        . ' list but no longer exists; skipping %s. Harmless'
                        . ' if a plugin was just updated -- the list refreshes'
                        . ' on its own.',
                        $file,
                        $className
                    )
                );
                continue;
            }
            if (!class_exists($className)) {
                error_log(
                    sprintf(
                        'FOG loadPageClasses: %s does not declare %s, so its'
                        . ' page cannot be registered. A page file must'
                        . ' declare a class named after the file, reachable'
                        . ' under that bare name -- a namespaced class needs'
                        . ' class_alias(__NAMESPACE__ . \'\\%s\', \'%s\');'
                        . ' see ADR 0013.',
                        $file,
                        $className,
                        $className,
                        $className
                    )
                );
                continue;
            }
            $vals = get_class_vars($className);
            // ?? null, not a bare read: a class with no $node property is an
            // undefined-key warning here, and the answer is the same as a
            // mismatch -- this is not the page being asked for.
            if (($vals['node'] ?? null) !== trim($node)) {
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\FOGPageManager', 'FOGPageManager');
