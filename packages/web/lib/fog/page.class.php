<?php
class Page extends FOGBase {
    private $body;
    private $menu;
    private $media;
    private $theme;
    private $isHomepage;
    private $pageTitle;
    private $sectionTitle;
    private $stylesheets = array();
    private $javascripts = array();
    private $headJavascripts = array();
    public function __construct() {
        parent::__construct();
        if (!$this->theme) {
            $this->theme = $this->getSetting('FOG_THEME');
            $this->theme = $this->theme ? $this->theme : 'default/fog.css';
            if (!file_exists("../management/css/$this->theme")) $this->theme = 'default/fog.css';
            $dispTheme = "css/$this->theme";
            $this->imagelink = sprintf('css/%simages/',(!$this->isMobile ? sprintf('%s/',dirname($this->theme)) : ''));
            if (!file_exists("../management/$dispTheme")) $dispTheme = 'css/default/fog.css';
        }
        if (!$this->isMobile) {
            $this->addCSS('css/jquery-ui.css');
            $this->addCSS('css/jquery-ui.theme.css');
            $this->addCSS('css/jquery-ui.structure.css');
            $this->addCSS('css/jquery-ui-timepicker-addon.css');
            $this->addCSS('css/jquery.organicTabs.css');
            $this->addCSS('css/jquery.tipsy.css');
            $this->addCSS($dispTheme);
        } else $this->addCSS('../mobile/css/main.css');
        $this->addCSS('css/font-awesome.min.css');
        $this->addCSS('css/select2.min.css');
        $this->addCSS('css/theme.blue.css');
        if (!isset($_REQUEST['node']) || !$_REQUEST['node']) $_REQUEST['node'] = 'home';
        $this->isHomepage = (in_array($_REQUEST['node'], array('home', 'dashboard','schemaupdater','client','logout','login')) || in_array($_REQUEST['sub'],array('configure','authorize','requestClientInfo')) || !self::$FOGUser->isValid());
        if (self::$FOGUser->isValid() && strtolower($_REQUEST['node']) != 'schemaupdater') {
            if (!$this->isMobile) {
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
                if ($this->getSetting('FOG_PLUGINSYS_ENABLED')) $this->array_insert_after('about',$this->main,'plugin',array(self::$foglang['Plugin Management'],'fa fa-cog fa-2x'));
            } else {
                $this->main = array(
                    'home'=>array(self::$foglang['Home'],'fa fa-home fa-2x'),
                    'host'=>array(self::$foglang['Host Management'],'fa fa-desktop fa-2x'),
                    'task'=>array(self::$foglang['Task Management'],'fa fa-tasks fa-2x'),
                    'logout'=>array(self::$foglang['Logout'],'fa fa-sign-out fa-2x'),
                );
            }
            $this->main = array_unique(array_filter($this->main),SORT_REGULAR);
            self::$HookManager->processEvent('MAIN_MENU_DATA',array('main'=>&$this->main));
            $links = array();
            foreach ($this->main AS $link => &$title) $links[] = $link;
            unset($title);
            if (!$this->isMobile) $links = array_merge((array)$links,array('hwinfo','client','schemaupdater'));
            if ($_REQUEST['node'] && !in_array($_REQUEST['node'],$links)) $this->redirect('index.php');
            ob_start();
            echo '<nav class="menu"><ul class="nav-list">';
            foreach($this->main AS $link => &$title) {
                if (!$_REQUEST['node'] && $link == 'home') $_REQUEST['node'] = $link;
                $activelink = (int)($_REQUEST['node'] == $link);
                printf('<li class="nav-item"><a href="?node=%s" class="nav-link%s" title="%s"><i class="%s"></i></a></li>',
                    $link,
                    ($activelink ? ' activelink' : ''),
                    $title[0],
                    $title[1]
                );
                unset($title);
            }
            echo '</ul></nav>';
            $this->menu = ob_get_clean();
        }
        if (self::$FOGUser->isValid() && !$this->isMobile) {
            $files = array(
                'js/jquery-latest.js',
                'js/jquery.tablesorter.combined.js',
                'js/select2.min.js',
                'js/jquery-migrate-1.2.1.min.js',
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
            if ($_REQUEST['sub'] == 'membership') $_REQUEST['sub'] = 'edit';
            $node = preg_replace('#_#','-',$_REQUEST['node']);
            $sub = preg_replace('#_#','-',$_REQUEST['sub']);
            $filepaths = array(
                "js/fog/fog.{$_REQUEST['node']}.js",
                "js/fog/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js",
                "js/fog/fog.$node.js",
                "js/fog/fog.$node.$sub.js",
            );
            foreach($filepaths AS $i => &$jsFilepath) {
                if (file_exists($jsFilepath)) array_push($files,$jsFilepath);
                unset($jsFilepath);
            }
            unset($jsFilepath);
            $pluginfilepaths = array(
                "../lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.js",
                "../lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js",
                "../lib/plugins/$node/js/fog.$node.js",
                "../lib/plugins/$node/js/fog.$node.$sub.js",
            );
            foreach($pluginfilepaths AS $i => &$pluginfilepath) {
                $filename = basename($pluginfilepath);
                if (file_exists($pluginfilepath)) array_push($files,$pluginfilepath);
                unset($pluginfilepath);
            }
            unset($pluginfilepaths);
            if ($this->isHomepage && ($_REQUEST['node'] == 'home' || !$_REQUEST['node'])) {
                array_push($files,'js/fog/fog.dashboard.js');
                if (preg_match('#MSIE [6|7|8|9|10|11]#',$_SERVER['HTTP_USER_AGENT'])) array_push($files,'js/flot/excanvas.js');
            }
        } else if (!$this->isMobile) {
            $files = array(
                'js/jquery-latest.js',
                'js/jquery.tipsy.js',
                'js/jquery.progressbar.js',
                'js/fog/fog.js',
                'js/fog/fog.login.js',
            );
        }
        $files = array_unique((array)$files);
        foreach((array)$files AS $i => &$path) {
            $this->addJavascript($path);
            unset($path);
        }
        unset($files);
    }
    public function setTitle($title) {
        $this->pageTitle = $title;
    }
    public function setSecTitle($title) {
        $this->sectionTitle = $title;
    }
    public function addCSS($path) {
        $this->stylesheets[] = "../management/$path";
    }
    public function addJavascript($path) {
        $this->javascripts[] = $path;
    }
    public function startBody() {
        ob_start();
    }
    public function endBody() {
        $this->body = ob_get_clean();
    }
    public function render($path = '') {
        require('../management/other/index.php');
        while(ob_get_level()) ob_end_flush();
    }
}
