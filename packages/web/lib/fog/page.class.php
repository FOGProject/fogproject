<?php
class Page extends FOGBase
{
    private $body;
    private $menu;
    private $media;
    private $theme;
    private $isHomepage;
    private $pageTitle;
    private $sectionTitle;
    private $stylesheets = array();
    private $javascripts = array();
    public function __construct()
    {
        parent::__construct();
        if (!$this->theme) {
            $this->theme = self::getSetting('FOG_THEME');
            $this->theme = $this->theme ? $this->theme : 'default/fog.css';
            if (!file_exists("../management/css/$this->theme")) {
                $this->theme = 'default/fog.css';
            }
            $dispTheme = "css/$this->theme";
            $this->imagelink = sprintf('css/%simages/', (!self::$isMobile ? sprintf('%s/', dirname($this->theme)) : ''));
            if (!file_exists("../management/$dispTheme")) {
                $dispTheme = 'css/default/fog.css';
            }
        }
        if (!self::$isMobile) {
            $this->addCSS('css/jquery-ui.css');
            $this->addCSS('css/jquery-ui.theme.css');
            $this->addCSS('css/jquery-ui.structure.css');
            $this->addCSS('css/jquery-ui-timepicker-addon.css');
            $this->addCSS('css/jquery.organicTabs.css');
            $this->addCSS('css/jquery.tipsy.css');
            $this->addCSS($dispTheme);
        } else {
            $this->addCSS('../mobile/css/main.css');
        }
        $this->addCSS('css/font-awesome.min.css');
        $this->addCSS('css/select2.min.css');
        $this->addCSS('css/theme.blue.css');
        if (!isset($_REQUEST['node']) || !$_REQUEST['node']) {
            $_REQUEST['node'] = 'home';
        }
        $this->isHomepage = (in_array($_REQUEST['node'], array('home', 'dashboard', 'schema', 'client', 'logout', 'login')) || !self::$FOGUser->isValid());
        if (self::$FOGUser->isValid() && strtolower($_REQUEST['node']) != 'schema') {
            if (!self::$isMobile) {
                $this->main = array(
                    'home'=>array(self::$foglang['Home'],'fa fa-home fa-2x'),
                    'user'=>array(self::$foglang['User Management'],'fa fa-users fa-2x'),
                    'host'=>array(self::$foglang['Host Management'],'fa fa-desktop fa-2x'),
                    'group'=>array(self::$foglang['Group Management'],'fa fa-sitemap fa-2x'),
                    'image'=>array(self::$foglang['Image Management'],'fa fa-picture-o fa-2x'),
                    'storage'=>array(self::$foglang['Storage Management'],'fa fa-archive fa-2x'),
                    'snapin'=>array(self::$foglang['Snapin Management'],'fa fa-files-o fa-2x'),
                    'printer'=>array(self::$foglang['Printer Management'],'fa fa-print fa-2x'),
                    'service'=>array(self::$foglang['Service Configuration'],'fa fa-cogs fa-2x'),
                    'task'=>array(self::$foglang['Task Management'],'fa fa-tasks fa-2x'),
                    'report'=>array(self::$foglang['Report Management'],'fa fa-file-text fa-2x'),
                    'about'=>array(self::$foglang['FOG Configuration'],'fa fa-wrench fa-2x'),
                    'logout'=>array(self::$foglang['Logout'],'fa fa-sign-out fa-2x'),
                );
                if (self::getSetting('FOG_PLUGINSYS_ENABLED')) {
                    $this->arrayInsertAfter('about', $this->main, 'plugin', array(self::$foglang['Plugin Management'], 'fa fa-cog fa-2x'));
                }
            } else {
                $this->main = array(
                    'home'=>array(self::$foglang['Home'],'fa fa-home fa-2x'),
                    'host'=>array(self::$foglang['Host Management'],'fa fa-desktop fa-2x'),
                    'task'=>array(self::$foglang['Task Management'],'fa fa-tasks fa-2x'),
                    'logout'=>array(self::$foglang['Logout'],'fa fa-sign-out fa-2x'),
                );
            }
            $this->main = array_unique(array_filter($this->main), SORT_REGULAR);
            self::$HookManager->processEvent('MAIN_MENU_DATA', array('main'=>&$this->main));
            $links = array();
            array_walk($this->main, function (&$title, &$link) use (&$links) {
                $links[] = $link;
                unset($title, $link);
            });
            if (!self::$isMobile) {
                $links = array_merge((array)$links, array('hwinfo', 'client', 'schema'));
            }
            if ($_REQUEST['node'] && !in_array($_REQUEST['node'], $links)) {
                $this->redirect('index.php');
            }
            ob_start();
            echo '<nav class="menu"><ul class="nav-list">';
            array_walk($this->main, function (&$title, &$link) {
                if (!$_REQUEST['node'] && $link == 'home') {
                    $_REQUEST['node'] = $link;
                }
                $activelink = ($_REQUEST['node'] == $link);
                printf(
                    '<li class="nav-item"><a href="?node=%s" class="nav-link%s" title="%s"><i class="%s"></i></a></li>',
                    $link,
                    ($activelink ? ' activelink' : ''),
                    array_shift($title),
                    array_shift($title)
                );
                unset($title, $link);
            });
            echo '</ul></nav>';
            $this->menu = ob_get_clean();
        }
        if (self::$FOGUser->isValid() && !self::$isMobile) {
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
            );
            if ($_REQUEST['sub'] == 'membership') {
                $_REQUEST['sub'] = 'edit';
            }
            $node = preg_replace('#_#', '-', $_REQUEST['node']);
            $sub = preg_replace('#_#', '-', $_REQUEST['sub']);
            $filepaths = array(
                "js/fog/fog.{$_REQUEST['node']}.js",
                "js/fog/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js",
                "js/fog/fog.$node.js",
                "js/fog/fog.$node.$sub.js",
            );
            array_map(function (&$jsFilepath) use (&$files) {
                if (file_exists($jsFilepath)) {
                    array_push($files, $jsFilepath);
                }
                unset($jsFilepath);
            }, (array)$filepaths);
            $pluginfilepaths = array(
                "../lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.js",
                "../lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js",
                "../lib/plugins/$node/js/fog.$node.js",
                "../lib/plugins/$node/js/fog.$node.$sub.js",
            );
            array_map(function (&$pluginfilepath) use (&$files) {
                if (file_exists($pluginfilepath)) {
                    array_push($files, $pluginfilepath);
                }
                unset($pluginfilepath);
            }, (array)$pluginfilepaths);
            if ($this->isHomepage && ($_REQUEST['node'] == 'home' || !$_REQUEST['node'])) {
                array_push($files, 'js/fog/fog.dashboard.js');
                if (preg_match('#MSIE [6|7|8|9|10|11]#', $_SERVER['HTTP_USER_AGENT'])) {
                    array_push($files, 'js/flot/excanvas.js');
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
            );
            if ($_REQUEST['node'] === 'schema') {
                array_push($files, 'js/fog/fog.schema.js');
            }
        }
        $files = array_unique((array)$files);
        array_map(function (&$path) {
            $this->addJavascript($path);
            unset($path);
        }, (array)$files);
    }
    public function setTitle($title)
    {
        $this->pageTitle = $title;
    }
    public function setSecTitle($title)
    {
        $this->sectionTitle = $title;
    }
    public function addCSS($path)
    {
        $this->stylesheets[] = "../management/$path";
    }
    public function addJavascript($path)
    {
        $this->javascripts[] = $path;
    }
    public function startBody()
    {
        ob_start();
    }
    public function endBody()
    {
        $this->body = ob_get_clean();
    }
    public function render($path = '')
    {
        require '../management/other/index.php';
    }
}
