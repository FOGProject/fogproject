<?php
/**
 * The page display/modifier
 *
 * PHP version 5
 *
 * @category Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The page display/modifier
 *
 * @category Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Page extends FOGBase
{
    /**
     * The title element.
     *
     * @var string
     */
    protected $title;
    /**
     * The body container
     *
     * @var string
     */
    protected $body;
    /**
     * The menu container
     *
     * @var mixed
     */
    protected $menu;
    /**
     * The menu hook container
     *
     * @var mixed
     */
    protected $menuHook;
    /**
     * The media container
     *
     * @var mixed
     */
    protected $media;
    /**
     * The theme container
     *
     * @var mixed
     */
    protected $theme;
    /**
     * If this is homepage
     *
     * @var bool
     */
    protected $isHomepage;
    /**
     * The page title container
     *
     * @var string
     */
    protected $pageTitle;
    /**
     * The section title container
     *
     * @var string
     */
    protected $sectionTitle;
    /**
     * The stylesheets to add
     *
     * @var array
     */
    protected $stylesheets = [];
    /**
     * The javascripts to add
     *
     * @var array
     */
    protected $javascripts = [];
    /**
     * Stylesheets that are common to every authenticated page.
     *
     * @var array
     */
    protected static $commonStylesheets = [
        'css/bootstrap5.min.css',
        'css/tempus-dominus.min.css',
        'css/font-awesome.min.css',
        'css/select2.min.css',
        'css/select2-bootstrap-5-theme.min.css',
        'css/ionicons.min.css',
        'css/datatables.min.css',
        'css/slider.css',
        'css/pnotify.min.css',
        'css/animate.css',
        'css/pace.min.css',
        'css/adminlte4.min.css',
        'css/fog-default-ui.min.css'
    ];
    /**
     * Stylesheets for unauthenticated pages (login, schema, client).
     * Kept slim so the login page loads fast on slow/mobile connections.
     *
     * @var array
     */
    protected static $loginStylesheets = [
        'css/bootstrap5.min.css',
        'css/font-awesome.min.css',
        'css/select2.min.css',
        'css/select2-bootstrap-5-theme.min.css',
        'css/pnotify.min.css',
        'css/animate.css',
        'css/pace.min.css',
        'css/adminlte4.min.css',
        'css/fog-default-ui.min.css'
    ];
    /**
     * Javascripts that are common to every page.
     * Currently, the contents of this array is added to $javascripts for output.
     *
     * @var array
     */
    protected static $commonJavascripts = [
        'js/jquery.min.js',
        'js/lodash.min.js',
        'js/bootstrap5.bundle.min.js',
        'js/fog/bootstrap-jquery-shim.js',
        'js/bootstrap-slider.min.js',
        'js/moment.min.js',
        'js/popper.min.js',
        'js/tempus-dominus.min.js',
        'js/fog/datetimepicker-shim.js',
        'js/vfs_fonts.js',
        'js/fastclick.js',
        'js/jquery-cron.min.js',
        'js/select2.full.min.js',
        'js/jquery.slimscroll.min.js',
        'js/adminlte4.min.js',
        'js/datatables.min.js',
        'js/bootbox.min.js',
        'js/pnotify.min.js',
        'js/pace.min.js',
        'js/input-mask/jquery.inputmask.js',
        'js/input-mask/jquery.inputmask.extensions.js',
        'js/input-mask/jquery.inputmask.regex.extensions.js',
        'js/input-mask/jquery.inputmask.numeric.extensions.js',
        'js/input-mask/jquery.inputmask.date.extensions.js',
        'js/fog/bootstrap-csrf.js',
        'js/fog/fog.common.js',
        'js/fog/theme.js'
    ];
    /**
     * Javascripts for unauthenticated pages (login, schema, client).
     * fog.common.js guards its DataTables/inputmask/tooltip integrations so it
     * runs cleanly without the heavy libraries the full app list carries.
     * adminlte4.min.js stays: the client download page renders card-collapse
     * buttons whose behavior lives in its CardWidget.
     *
     * @var array
     */
    protected static $loginJavascripts = [
        'js/jquery.min.js',
        'js/lodash.min.js',
        'js/select2.full.min.js',
        'js/adminlte4.min.js',
        'js/pnotify.min.js',
        'js/pace.min.js',
        'js/fog/bootstrap-csrf.js',
        'js/fog/fog.common.js',
        'js/fog/theme.js'
    ];
    /**
     * Initializes the page element
     *
     * @throws Exception
     * @return void
     */
    public function __construct()
    {
        global $node;
        global $sub;
        parent::__construct();
        $stylesheets = self::$FOGUser->isValid()
            ? self::$commonStylesheets
            : self::$loginStylesheets;
        foreach ($stylesheets as $stylesheet) {
            $this->addCSS($stylesheet);
        }
        if (!$this->theme) {
            $this->theme = self::getSetting('FOG_THEME');
            if (!$this->theme) {
                $this->theme = 'default/fog.css';
            } elseif (!file_exists("../management/css/$this->theme")) {
                $this->theme = 'default/fog.css';
            }
            $dispTheme = "css/$this->theme";
            $this->imagelink = 'css/'
                . dirname($this->theme)
                . '/images/';
            if (!file_exists("../management/$dispTheme")) {
                $dispTheme = 'css/default/fog.css';
            }
        }
        if (!isset($node)
            || !$node
        ) {
            $node = 'home';
        }
        $homepages = [
            'home',
            'dashboard',
            'schema',
            'client',
            'ipxe',
            'login',
            'logout'
        ];
        $this->isHomepage = in_array($node, $homepages)
            || !self::$FOGUser->isValid();
        FOGPage::buildMainMenuItems($this->menu, $this->menuHook);
        $files = [];
        if (!self::_isContentOnly()) {
            $files = self::$FOGUser->isValid()
                ? self::$commonJavascripts
                : self::$loginJavascripts;
        }
        if (!self::$FOGUser->isValid()) {
            $files[] = 'js/fog/fog.login.js';
        } else {
            $subset = $sub;
            $node = preg_replace(
                '#_#',
                '-',
                $node
            );
            $subset = preg_replace(
                '#_#',
                '-',
                $subset
            );
            $filepaths = '';
            if (empty($subset)) {
                $filepaths = "js/fog/{$node}/fog.{$node}.js";
            } else {
                $filepaths = "js/fog/{$node}/fog.{$node}.{$subset}.js";
            }
            $jscolorNodeSubArray = [
                'about' => ['settings'],
                'storagenode' => [
                    'list',
                    'add',
                    'edit'
                ]
            ];
            $jscolorneeded = false;
            switch ($node) {
                case 'about':
                    if ('settings' == $sub) {
                        $jscolorneeded = true;
                    }
                    break;
                case 'storagenode':
                    if (in_array($sub, $jscolorNodeSubArray[$node])) {
                        $jscolorneeded = true;
                    }
                    break;
                default:
                    $jscolorneeded = false;
            }
            if ($jscolorneeded) {
                $files[] = 'js/jscolor.js';
            }
        }
        if (isset($filepaths) && $filepaths && !file_exists($filepaths)) {
            $listpath = "js/fog/{$node}/fog.{$node}.list.js";
            if (file_exists($listpath)) {
                $files[] = $listpath;
            }
        }
        if (isset($filepaths) && file_exists($filepaths)) {
            $files[] = $filepaths;
        }
        if ($this->isHomepage
            && self::$FOGUser->isValid()
            && ($node == 'home'
            || !$node)
        ) {
            $files[] = 'js/Chart/chart.umd.min.js';
            $files[] = 'js/Chart/chartjs-adapter-moment.min.js';
            $files[] = 'js/fog/dashboard/fog.dashboard.js';
        }
        if ($node === 'schema') {
            $files[] = 'js/fog/schema/fog.schema.js';
        }
        self::$HookManager->processEvent(
            'PAGE_JS_FILES',
            ['files' => &$files]
        );
        $files = array_unique((array)$files);
        foreach ($files as &$path) {
            if (!file_exists($path)) {
                continue;
            }
            $this->addJavascript($path);
            unset($path);
        }
    }
    /**
     * Sets the title
     *
     * @param string $title the title to set
     *
     * @return object
     */
    public function setTitle($title)
    {
        $this->pageTitle = $title;
        return $this;
    }
    /**
     * Sets the section title
     *
     * @param string $title the title to set
     *
     * @return object
     */
    public function setSecTitle($title)
    {
        $this->sectionTitle = $title;
        return $this;
    }
    /**
     * Adds a css path
     *
     * @param string $path the path to add
     *
     * @return object
     */
    public function addCSS($path)
    {
        $this->stylesheets[] = "../management/$path";
        return $this;
    }
    /**
     * Adds a javascript path
     *
     * @param string $path the path to add
     *
     * @return object
     */
    public function addJavascript($path)
    {
        $this->javascripts[] = $path;
        return $this;
    }
    /**
     * Starts the body
     *
     * @return object
     */
    public function startBody()
    {
        ob_start();
        return $this;
    }
    /**
     * Ends the body
     *
     * @return object
     */
    public function endBody()
    {
        $this->body = ob_get_clean();
        return $this;
    }
    /**
     * Renders the index page
     *
     * @return object
     */
    public function render()
    {
        if (true !== self::$showhtml) {
            echo $this->body;
            exit;
        }
        $contentOnly = (int)self::_isContentOnly();
        switch ($contentOnly) {
            case 0:
                include '../management/other/index.php';
                break;
            case 1:
                $userValid = (int)self::$FOGUser->isValid();
                switch ($userValid) {
                    case 0:
                        echo '<noscript>';
                        echo '<p>';
                        echo _('The current user is invalid.');
                        echo '</p>';
                        echo '</noscript>';
                        echo '<script src="js/fog/redirect.js?ver=' . FOG_BCACHE_VER . '"></script>';
                        break;
                    case 1:
                        header(
                            'X-FOG-PageTitle: '
                            . $this->pageTitle
                            . ' | '
                            . _('FOG Project')
                        );
                        header(
                            'X-FOG-Memory-Usage: '
                            . self::formatByteSize(
                                memory_get_usage(true)
                            )
                        );
                        header(
                            'X-FOG-Memory-Peak: '
                            . self::formatByteSize(
                                memory_get_peak_usage()
                            )
                        );
                        header(
                            'X-FOG-Stylesheets: '
                            . json_encode(
                                $this->stylesheets
                            )
                        );
                        header(
                            'X-FOG-JavaScripts: '
                            . json_encode(
                                $this->javascripts
                            )
                        );
                        header(
                            'X-FOG-Common-JavaScripts: '
                            . json_encode(
                                self::$commonJavascripts
                            )
                        );
                        header(
                            'X-FOG-BCacheVer: ' . FOG_BCACHE_VER
                        );
                        echo '<div class="app-content-header">';
                        echo '<div class="container-fluid">';
                        echo '<h1 id="sectionTitle">';
                        echo Initiator::e($this->sectionTitle);
                        echo '<small id="pageTitle">';
                        echo Initiator::e($this->pageTitle);
                        echo '</small>';
                        echo '</h1>';
                        echo '</div>';
                        echo '</div>';
                        echo '<div class="app-content">';
                        echo '<div class="container-fluid">';
                        echo $this->body;
                        echo '</div>';
                        echo '</div>';
                        break;
                }
                break;
        }
        foreach (array_keys(get_defined_vars()) as $var) {
            unset($$var);
        }
        return $this;
    }
    /**
     * Determines whether or not the current request is only for content.
     *
     * @return bool
     */
    private static function _isContentOnly()
    {
        self::$FOGUser->isLoggedIn();
        return (bool)filter_input(INPUT_GET, 'contentOnly');
    }
}
