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
            $this->imagelink = 'css/'
                . dirname($this->theme)
                . '/images/';
            if (!file_exists("../management/$dispTheme")) {
                $dispTheme = 'css/default/fog.css';
            }
        }
        $this
            ->addCSS('bower_components/bootstrap/dist/css/bootstrap.min.css')
            ->addCSS('bower_components/font-awesome/css/font-awesome.min.css')
            ->addCSS('bower_components/select2/dist/css/select2.min.css')
            ->addCSS('bower_components/Ionicons/css/ionicons.min.css')
            ->addCSS('plugins/datatables/datatables.min.css')
            ->addCSS('plugins/bootstrap-slider/slider.css')
            ->addCSS('plugins/pnotify/pnotify.min.css')
            ->addCSS('plugins/iCheck/square/blue.css')
            ->addCSS('plugins/animate/animate.css')
            ->addCSS('plugins/pace/pace.min.css')
            ->addCSS('dist/css/AdminLTE.min.css')
            ->addCSS('dist/css/skins/_all-skins.min.css')
            ->addCSS('css/font.css');
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
        FOGPage::buildMainMenuItems($this->menu);
        $files = array(
            'bower_components/jquery/dist/jquery.min.js',
            //'bower_components/jquery-ui/jquery-ui.min.js',
            'bower_components/bootstrap/dist/js/bootstrap.min.js',
            'bower_components/fastclick/lib/fastclick.js',
            'bower_components/Flot/jquery.flot.js',
            'bower_components/Flot/jquery.flot.resize.js',
            'bower_components/Flot/jquery.flot.pie.js',
            'bower_components/Flot/jquery.flot.time.js',
            'bower_components/select2/dist/js/select2.full.min.js',
            'bower_components/jquery-slimscroll/jquery.slimscroll.min.js',
            'dist/js/adminlte.min.js',
            'plugins/datatables/datatables.min.js',
            'plugins/iCheck/icheck.min.js',
            'plugins/bootbox/bootbox.min.js',
            'plugins/pnotify/pnotify.min.js',
            'plugins/pace/pace.min.js',
            'plugins/input-mask/jquery.inputmask.js',
            'plugins/input-mask/jquery.inputmask.extensions.js',
            'plugins/input-mask/jquery.inputmask.regex.extensions.js',
            'plugins/input-mask/jquery.inputmask.numeric.extensions.js',
            'plugins/input-mask/jquery.inputmask.date.extensions.js',
            'plugins/bootstrap-slider/bootstrap-slider.js',
            'js/fog/fog.common.js',
        );
        if (!self::$FOGUser->isValid()) {
            $files[] = 'js/fog/fog.login.js';
        } else {
            $subset = $sub;
            if ($sub == 'membership') {
                $subset = 'edit';
            }
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
            $filepaths = [];
            if (empty($subset)) {
                $filepaths = ["js/fog/{$node}/fog.{$node}.js"];
            } else {
                $filepaths = ["js/fog/{$node}/fog.{$node}.{$subset}.js"];
            }
        }
        array_map(
            function (&$jsFilepath) use (&$files) {
                if (file_exists($jsFilepath)) {
                    array_push($files, $jsFilepath);
                }
                unset($jsFilepath);
            },
            (array)$filepaths
        );
        if ($this->isHomepage
            && self::$FOGUser->isValid()
            && ($node == 'home'
            || !$node)
        ) {
            array_push($files, 'js/fog/dashboard/fog.dashboard.js');
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
        if ($node === 'schema') {
            array_push($files, 'js/fog/schema/fog.schema.js');
        }
        self::$HookManager->processEvent(
            'PAGE_JS_FILES',
            ['files' => &$files]
        );
        $files = array_unique((array)$files);
        array_map(
            function (&$path) {
                if (file_exists($path)) {
                    $this->addJavascript($path);
                }
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
    /**
     * Generate the search form.
     *
     * @return void
     */
    public static function getSearchForm()
    {
        global $node;
        echo '<div class="col-md-3">';
        if (in_array($node, self::$searchPages)) {
            echo '<li class="pull-left">';
            echo '<form class="navbar-form navbar-left search-wrapper" role='
                . '"search" method="post" action="'
                . '../management/index.php?node='
                . $node
                . '&sub=search">';
            echo '<div class="input-group">';
            echo '<input type="text" class="'
                . 'form-control search-input" placeholder="'
                . self::$foglang['Search']
                . '..." name="crit"/>';
            echo '<span class="input-group-addon search-submit">';
            echo '<i class="fogsearch fa fa-search">';
            echo '<span class="sr-only">';
            echo self::$foglang['Search'];
            echo '</span>';
            echo '</i>';
            echo '</span>';
            echo '</div>';
            echo '</form>';
            echo '</li>';
        }
        echo '</div>';
    }
    /**
     * Generate the logout element.
     *
     * @return void
     */
    public static function getLogout()
    {
        echo '<li class="pull-right">';
        if (self::$FOGUser->isValid()) {
            echo '<a href="../management/index.php?node=logout" '
                . 'data-toggle="tooltip" data-placement="bottom" title="'
                . _('Logout')
                . ': '
                . strtolower(
                    trim(
                        self::$FOGUser->get('name')
                    )
                )
                . '">';
            echo '<i class="fa fa-sign-out fa-2x fa-fw"></i>';
            echo '<span class="collapsedmenu-text">';
            echo ' ';
            echo _('Logout');
            echo '</span>';
            echo '</a>';
        } else {
            echo '<a href="../management/index.php"';
            echo '<i class="fa fa-sign-in fa-2x fa-fw"></i>';
            echo '<span class="collapsedmenu-text">';
            echo ' ';
            echo _('Login');
            echo '</span>';
            echo '</a>';
        }
        echo '</li>';
        echo '<li class="separator hidden-lg hidden-md"></li>';
    }
    /**
     * Get main side menu items.
     *
     * @param string $node Override main side menu
     * @param string $sub Override main sub side menu
     *
     * @return void
     */
    public static function getMainSideMenu($node = 'home', $sub = '')
    {
        echo '<!-- ' . $node . 'Woot -->';
        if (empty($node)) {
            $node = 'home';
        }
        $class = self::$FOGPageManager->getFOGPageClass($node);
        echo '<!-- ' . $class->menu . ' -->';
        if (count($class->menu) < 1) {
            return;
        }
        $FOGSub = new FOGSubMenu();
        foreach ($class->menu as $l => &$t) {
            $FOGSub->addMainItems(
                $class->node,
                array((string)$t => (string)$l),
                '',
                '',
                'mainmenu'
            );
            unset($t);
        }
        unset($class);
        echo $FOGSub->getMainItems($node);
        unset($FOGSub);
    }

    /**
     * Generates our main item, sub item, and notes tabs.
     *
     * @return void
     */
    public static function getMenuItems()
    {
        $class = self::$FOGPageManager->getFOGPageClass();
        $FOGSub = new FOGSubMenu();
        if (count($class->subMenu)) {
            foreach ($class->subMenu as $l => &$t) {
                $FOGSub->addItems(
                    $class->node,
                    array((string)$t => (string)$l),
                    $class->id,
                    sprintf(
                        self::$foglang['SelMenu'],
                        get_class($class->obj)
                    ),
                    'submenu'
                );
                unset($t);
            }
            unset($classSubMenu);
        }
        if (count($class->notes)) {
            foreach ($class->notes as $l => &$t) {
                $FOGSub->addNotes(
                    $class->node,
                    array((string)$t => (string)$l),
                    $class->id
                );
                unset($t);
            }
        }
        echo $FOGSub->get($class->node);
    }
}
