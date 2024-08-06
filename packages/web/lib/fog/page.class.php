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
     * Javascripts that are common to every page.
     * Currently, the contents of this array is added to $javascripts for output.
     *
     * @var array
     */
    protected static $commonJavascripts = [
        'js/jquery.min.js',
        'js/jquery.color.min.js',
        'js/lodash.min.js',
        'js/bootstrap.min.js',
        'js/bootstrap-slider.min.js',
        'js/moment.min.js',
        'js/bootstrap-datetimepicker.min.js',
        'js/vfs_fonts.js',
        'js/fastclick.js',
        'js/Flot/jquery.flot.js',
        'js/Flot/jquery.flot.pie.js',
        'js/Flot/jquery.flot.time.js',
        'js/Flot/jquery.flot.resize.js',
        'js/jquery-cron.min.js',
        'js/select2.full.min.js',
        'js/jquery.slimscroll.min.js',
        'js/adminlte.min.js',
        'js/datatables.min.js',
        'js/icheck.min.js',
        'js/bootbox.min.js',
        'js/pnotify.min.js',
        'js/pace.min.js',
        'js/input-mask/jquery.inputmask.js',
        'js/input-mask/jquery.inputmask.extensions.js',
        'js/input-mask/jquery.inputmask.regex.extensions.js',
        'js/input-mask/jquery.inputmask.numeric.extensions.js',
        'js/input-mask/jquery.inputmask.date.extensions.js',
        'js/fog/fog.common.js'
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
        $this
            ->addCSS('css/bootstrap.min.css')
            ->addCSS('css/bootstrap-datetimepicker.min.css')
            ->addCSS('css/font-awesome.min.css')
            ->addCSS('css/select2.min.css')
            ->addCSS('css/ionicons.min.css')
            ->addCSS('css/datatables.min.css')
            ->addCSS('css/slider.css')
            ->addCSS('css/pnotify.min.css')
            ->addCSS('css/icheck-square-blue.css')
            ->addCSS('css/animate.css')
            ->addCSS('css/pace.min.css')
            ->addCSS('css/AdminLTE.min.css')
            ->addCSS('css/adminlte-skins.min.css')
            ->addCSS('css/fog-default-ui.min.css?v=' . microtime());
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
            $files = self::$commonJavascripts;
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
        if (isset($subset) && $subset && !file_exists($filepaths)) {
            $files[] = "js/fog/{$node}/fog.{$node}.list.js";
        }
        if (isset($filepaths) && file_exists($filepaths)) {
            $files[] = $filepaths;
        }
        if ($this->isHomepage
            && self::$FOGUser->isValid()
            && ($node == 'home'
            || !$node)
        ) {
            $files[] = 'js/fog/dashboard/fog.dashboard.js';
            $test = preg_match(
                '#MSIE [6|7|8|9|10|11]#',
                self::$useragent
            );
            if ($test) {
                $files[] = 'js/flot/excanvas.js';
            }
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
                        echo '<script>window.location.href = "/";</script>';
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
                        echo '<section class="content-header">';
                        echo '<h1 id="sectionTitle">';
                        echo $this->sectionTitle;
                        echo '<small id="pageTitle">';
                        echo $this->pageTitle;
                        echo '</small>';
                        echo '</h1>';
                        echo '</section>';
                        echo '<section class="content">';
                        echo $this->body;
                        echo '</section>';
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
