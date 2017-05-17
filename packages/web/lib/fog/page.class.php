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
    protected $stylesheets = array();
    /**
     * The javascripts to add
     *
     * @var array
     */
    protected $javascripts = array();
    /**
     * Initializes the page element
     *
     * @return void
     */
    public function __construct()
    {
        global $node;
        global $sub;
        parent::__construct();
        if (!$this->theme) {
            $this->theme = self::getSetting('FOG_THEME');
            if (!$this->theme) {
                $this->theme = 'default/fog.css';
            } elseif (!file_exists("../management/css/$this->theme")) {
                $this->theme = 'default/fog.css';
            }
            $dispTheme = "css/$this->theme";
            $this->imagelink = sprintf(
                'css/%simages/',
                (
                    !self::$isMobile ?
                    sprintf(
                        '%s/',
                        dirname($this->theme)
                    ) :
                    ''
                )
            );
            if (!file_exists("../management/$dispTheme")) {
                $dispTheme = 'css/default/fog.css';
            }
        }
        if (!self::$isMobile) {
            $this
                ->addCSS('css/jquery-ui.css')
                ->addCSS('css/jquery-ui.theme.css')
                ->addCSS('css/jquery-ui.structure.css')
                ->addCSS('css/jquery-ui-timepicker-addon.css')
                ->addCSS('css/jquery.organicTabs.css')
                ->addCSS('css/jquery.tipsy.css')
                ->addCSS($dispTheme);
        } else {
            $this->addCSS('../mobile/css/main.css');
            $this->title = sprintf(
                'FOG :: %s :: %s %s',
                _('Mobile Manager'),
                _('Version'),
                FOG_VERSION
            );
        }
        $this
            ->addCSS('css/font-awesome.min.css')
            ->addCSS('css/select2.min.css')
            ->addCSS('css/theme.blue.css');
        if (!isset($node)
            || !$node
        ) {
            $node = 'home';
        }
        $homepages = array(
            'home',
            'dashboard',
            'schema',
            'client',
            'ipxe',
            'login',
            'logout'
        );
        $this->isHomepage = in_array($node, $homepages)
            || !self::$FOGUser->isValid();
        if (self::$FOGUser->isValid()
            && strtolower($node) != 'schema'
        ) {
            if (!self::$isMobile) {
                $this->main = array(
                    'home' => array(
                        self::$foglang['Home'],
                        'fa fa-home fa-2x'
                    ),
                    'user' => array(
                        self::$foglang['User Management'],
                        'fa fa-users fa-2x'
                    ),
                    'host' => array(
                        self::$foglang['Host Management'],
                        'fa fa-desktop fa-2x'
                    ),
                    'group' => array(
                        self::$foglang['Group Management'],
                        'fa fa-sitemap fa-2x'
                    ),
                    'image' => array(
                        self::$foglang['Image Management'],
                        'fa fa-picture-o fa-2x'
                    ),
                    'storage' => array(
                        self::$foglang['Storage Management'],
                        'fa fa-archive fa-2x'
                    ),
                    'snapin' => array(
                        self::$foglang['Snapin Management'],
                        'fa fa-files-o fa-2x'
                    ),
                    'printer' => array(
                        self::$foglang['Printer Management'],
                        'fa fa-print fa-2x'
                    ),
                    'service' => array(
                        self::$foglang['Service Configuration'],
                        'fa fa-cogs fa-2x'
                    ),
                    'task' => array(
                        self::$foglang['Task Management'],
                        'fa fa-tasks fa-2x'
                    ),
                    'report' => array(
                        self::$foglang['Report Management'],
                        'fa fa-file-text fa-2x'
                    ),
                    'about' => array(
                        self::$foglang['FOG Configuration'],
                        'fa fa-wrench fa-2x'
                    ),
                    'logout' => array(
                        self::$foglang['Logout'],
                        'fa fa-sign-out fa-2x'
                    ),
                );
                if (self::getSetting('FOG_PLUGINSYS_ENABLED')) {
                    self::arrayInsertAfter(
                        'about',
                        $this->main,
                        'plugin',
                        array(
                            self::$foglang['Plugin Management'],
                            'fa fa-cog fa-2x'
                        )
                    );
                }
            } else {
                $this->main = array(
                    'home' => array(
                        self::$foglang['Home'],
                        'fa fa-home fa-2x'
                    ),
                    'host' => array(
                        self::$foglang['Host Management'],
                        'fa fa-desktop fa-2x'
                    ),
                    'task' => array(
                        self::$foglang['Task Management'],
                        'fa fa-tasks fa-2x'
                    ),
                    'logout' => array(
                        self::$foglang['Logout'],
                        'fa fa-sign-out fa-2x'
                    ),
                );
            }
            $this->main = array_unique(
                array_filter($this->main),
                SORT_REGULAR
            );
            self::$HookManager
                ->processEvent(
                    'MAIN_MENU_DATA',
                    array(
                        'main' => &$this->main
                    )
                );
            $links = array();
            if (count($this->main) > 0) {
                array_walk(
                    $this->main,
                    function (
                        &$title,
                        &$link
                    ) use (&$links) {
                        $links[] = $link;
                        unset($title, $link);
                    }
                );
            }
            if (!self::$isMobile) {
                $links = self::fastmerge(
                    (array)$links,
                    array(
                        'hwinfo',
                        'client',
                        'schema',
                        'ipxe'
                    )
                );
            }
            if ($node
                && !in_array($node, $links)
            ) {
                self::redirect('index.php');
            }
            ob_start();
            echo '<nav class="menu"><ul class="nav-list">';
            if (count($this->main) > 0) {
                array_walk(
                    $this->main,
                    function (
                        &$title,
                        &$link
                    ) use (&$node) {
                        if (!$node
                            && $link == 'home'
                        ) {
                            $node = $link;
                        }
                        $activelink = ($node == $link);
                        printf(
                            '<li class="nav-item"><a href="?node=%s" '
                            . 'class="nav-link%s" title="%s"><i class="%s">'
                            . '</i></a></li>',
                            $link,
                            (
                                $activelink ?
                                ' activelink' :
                                ''
                            ),
                            array_shift($title),
                            array_shift($title)
                        );
                        unset($title, $link);
                    }
                );
            }
            echo '</ul></nav>';
            $this->menu = ob_get_clean();
        }
        if (self::$FOGUser->isValid()
            && !self::$isMobile
        ) {
            $files = array(
                'js/jquery-latest.min.js',
                'js/jquery.validate.min.js',
                'js/additional-methods.min.js',
                'js/jquery.tablesorter.combined.js',
                'js/select2.min.js',
                'js/jquery-migrate-latest.min.js',
                'js/jquery.tipsy.js',
                'js/jquery.progressbar.js',
                'js/jquery.tmpl.js',
                'js/jquery.organicTabs.js',
                'js/jquery.placeholder.js',
                'js/jquery-ui.min.js',
                'js/flot/jquery.flot.js',
                'js/flot/jquery.flot.time.js',
                'js/flot/jquery.flot.pie.js',
                'js/flot/jquery.flot.JUMlib.js',
                'js/flot/jquery.flot.gantt.js',
                'js/jquery-ui-timepicker-addon.js',
                'js/fog/fog.js',
                'js/fog/fog.main.js',
                'js/jscolor.min.js'
            );
            $subset = $sub;
            if ($sub == 'membership') {
                $subset = 'edit';
            }
            $node = preg_replace('#_#', '-', $node);
            $subset = preg_replace('#_#', '-', $subset);
            $filepaths = array(
                "js/fog/fog.{$node}.js",
                "js/fog/fog.{$node}.{$subset}.js",
            );
            array_map(
                function (&$jsFilepath) use (&$files) {
                    if (file_exists($jsFilepath)) {
                        array_push($files, $jsFilepath);
                    }
                    unset($jsFilepath);
                },
                (array)$filepaths
            );
            $pluginfilepaths = array(
                "../lib/plugins/{$node}/js/fog.{$node}.js",
                "../lib/plugins/{$node}/js/fog.{$node}.{$subset}.js",
            );
            array_map(
                function (&$pluginfilepath) use (&$files) {
                    if (file_exists($pluginfilepath)) {
                        array_push($files, $pluginfilepath);
                    }
                    unset($pluginfilepath);
                },
                (array)$pluginfilepaths
            );
            if ($this->isHomepage
                && ($node == 'home'
                || !$node)
            ) {
                array_push($files, 'js/fog/fog.dashboard.js');
                $test = preg_match(
                    '#MSIE [6|7|8|9|10|11]#',
                    self::$useragent
                );
                if ($test) {
                    array_push(
                        $files,
                        'js/flot/excanvas.js'
                    );
                }
            }
        } elseif (!self::$isMobile) {
            $files = array(
                'js/jquery-latest.min.js',
                'js/jquery.tipsy.js',
                'js/jquery.validate.min.js',
                'js/additional-methods.min.js',
                'js/jquery-migrate-latest.min.js',
                'js/jquery.progressbar.js',
                'js/fog/fog.js',
                'js/fog/fog.login.js',
                'js/jscolor.min.js'
            );
            if ($node === 'schema') {
                array_push($files, 'js/fog/fog.schema.js');
            }
        }
        $files = array_unique((array)$files);
        array_map(
            function (&$path) {
                $this->addJavascript($path);
                unset($path);
            },
            (array)$files
        );
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
        if (!self::$isMobile) {
            $this->title = sprintf(
                '%s%s &gt; FOG &gt; %s',
                (
                    $this->pageTitle ?
                    sprintf(
                        '%s &gt; ',
                        $this->pageTitle
                    ) :
                    ''
                ),
                $this->sectionTitle,
                self::$foglang['Slogan']
            );
        }
        if (true === self::$showhtml) {
            include '../management/other/index.php';
        } else {
            echo $this->body;
            exit;
        }
        foreach (array_keys(get_defined_vars()) as $var) {
            unset($$var);
        }
        return $this;
    }
}
