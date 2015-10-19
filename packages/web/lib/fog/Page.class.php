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
            $this->theme = $this->FOGCore->getSetting('FOG_THEME');
            $this->theme = $this->theme ? $this->theme : 'default/fog.css';
            if (!file_exists(BASEPATH.'/management/css/'.$this->theme)) $this->theme = 'default/fog.css';
            $dispTheme = 'css/'.$this->theme;
            $this->imagelink = 'css/'.(!$this->isMobile ? dirname($this->theme).DIRECTORY_SEPARATOR : '').'images/';
            if (!file_exists(BASEPATH.'/management/'.$dispTheme)) $dispTheme = 'css/default/fog.css';
        }
        if (!$this->isMobile) {
            $this->addCSS('css/jquery-ui.css');
            $this->addCSS('css/jquery.organicTabs.css');
            $this->addCSS('css/jquery.tipsy.css');
            $this->addCSS($dispTheme);
        } else $this->addCSS('../mobile/css/main.css');
        $this->addCSS('css/font-awesome.css');
        $this->addCSS('css/select2.min.css');
        $this->addCSS('css/theme.blue.css');
        if (!$_REQUEST['node']) $_REQUEST['node'] = 'home';
        $this->isHomepage = (in_array($_REQUEST['node'], array('home', 'dashboard','schemaupdater','client','logout','login')) || in_array($_REQUEST['sub'],array('configure','authorize')) || !$this->FOGUser->isValid());
        if ($this->FOGUser->isValid() && strtolower($_REQUEST['node']) != 'schemaupdater') {
            if (!$this->isMobile) {
                $this->main = array(
                    'home'=>array($this->foglang['Home'],'fa fa-home fa-2x'),
                    'user'=>array($this->foglang['User Management'],'fa fa-users fa-2x'),
                    'host'=>array($this->foglang['Host Management'],'fa fa-desktop fa-2x'),
                    'group'=>array($this->foglang['Group Management'],'fa fa-sitemap fa-2x'),
                    'image'=>array($this->foglang['Image Management'],'fa fa-picture-o fa-2x'),
                    'storage'=>array($this->foglang['Storage Management'],'fa fa-archive fa-2x'),
                    'snapin'=>array($this->foglang['Snapin Management'],'fa fa-files-o fa-2x'),
                    'printer'=>array($this->foglang['Printer Management'],'fa fa-print fa-2x'),
                    'service'=>array($this->foglang['Service Configuration'],'fa fa-cogs fa-2x'),
                    'task'=>array($this->foglang['Task Management'],'fa fa-tasks fa-2x'),
                    'report'=>array($this->foglang['Report Management'],'fa fa-file-text fa-2x'),
                    'about'=>array($this->foglang['FOG Configuration'],'fa fa-wrench fa-2x'),
                    'logout'=>array($this->foglang['Logout'],'fa fa-sign-out fa-2x'),
                );
                if ($this->FOGCore->getSetting('FOG_PLUGINSYS_ENABLED')) $this->main = $this->array_insert_after('about',$this->main,'plugin',array($this->foglang['Plugin Management'],'fa fa-cog fa-2x'));
            } else {
                $this->main = array(
                    'home'=>array($this->foglang['Home'],'fa fa-home fa-2x'),
                    'host'=>array($this->foglang['Host Management'],'fa fa-desktop fa-2x'),
                    'task'=>array($this->foglang['Task Management'],'fa fa-tasks fa-2x'),
                    'logout'=>array($this->foglang['Logout'],'fa fa-sign-out fa-2x'),
                );
            }
            $this->main = array_unique(array_filter($this->main),SORT_REGULAR);
            $this->HookManager->processEvent('MAIN_MENU_DATA',array('main'=>&$this->main));
            $links = array();
            foreach ($this->main AS $link => &$title) $links[] = $link;
            unset($title);
            if (!$this->isMobile) $links = array_merge((array)$links,array('hwinfo','client','schemaupdater'));
            if ($_REQUEST['node'] && !in_array($_REQUEST['node'],$links)) $this->redirect('index.php');
            $this->menu = '<nav class="menu"><ul class="nav-list">';
            foreach($this->main AS $link => &$title) {
                if (!$_REQUEST['node'] && $link == 'home') $_REQUEST['node'] = $link;
                $activelink = (int)($_REQUEST['node'] == $link);
                $this->menu .= sprintf('<li class="nav-item"><a href="?node=%s" class="nav-link%s" title="%s"><i class="%s"></i></a></li>',$link,($activelink ? ' activelink' : ''),$title[0],$title[1]);
            }
            unset($title);
            $this->menu .= '</ul></nav>';
        }
        if ($this->FOGUser->isValid() && !$this->isMobile) {
            $files = array(
                'hjs/jquery-latest.js',
                'hjs/jquery.tablesorter.combined.js',
                'hjs/select2.min.js',
                'js/jquery-migrate-1.2.1.min.js',
                'js/jquery.tipsy.js',
                'js/jquery.progressbar.js',
                'js/jquery.tmpl.js',
                'js/jquery.organicTabs.js',
                'js/jquery.placeholder.js',
                'js/jquery.disableSelection.js',
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
            }
            unset($jsFilepath);
            $pluginfilepaths = array(
                BASEPATH."/lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.js",
                BASEPATH."/lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js",
                BASEPATH."/lib/plugins/$node/js/fog.$node.js",
                BASEPATH."/lib/plugins/$node/js/fog.$node.$sub.js",
            );
            foreach($pluginfilepaths AS $i => &$pluginfilepath) {
                if (file_exists($pluginfilepath) && !file_exists("js/fog/".basename($pluginfilepath))) {
                    $newfile = "js/fog/".basename($pluginfilepath);
                    file_put_contents($newfile,file_get_contents($pluginfilepath));
                }
            }
            unset($pluginfilepath);
            if ($this->isHomepage && ($_REQUEST['node'] == 'home' || !$_REQUEST['node'])) {
                array_push($files,'js/fog/fog.dashboard.js');
                if (preg_match('#MSIE [6|7|8|9|10|11]#',$_SERVER['HTTP_USER_AGENT'])) array_push($files,'js/flot/excanvas.js');
            }
        }
        else if (!$this->isMobile) {
            $files = array(
                'js/jquery-latest.js',
                'js/jquery.progressbar.js',
                'js/jquery.tipsy.js',
                'js/fog/fog.js',
                'js/fog/fog.login.js',
            );
        }
        foreach((array)$files AS $i => &$path) {
            if (file_exists(preg_replace('#^h#','',$path))) $this->addJavascript($path);
        }
        unset($path);
    }
    public function setTitle($title) {
        $this->pageTitle = $title;
    }
    public function setSecTitle($title) {
        $this->sectionTitle = $title;
    }
    public function addCSS($path) {
        $this->stylesheets[] = '../management/'.$path;
    }
    public function addJavascript($path){
        if (preg_match('#^h#',$path)) $this->headJavascripts[] = preg_replace('#^h#','',$path);
        else $this->javascripts[] = $path;
    }
    public function startBody() {
        ob_start(array('Initiator','sanitize_output'));
    }
    public function endBody() {
        $this->body = ob_get_clean();
    }
    public function render($path = '') {
        ob_start(array('Initiator','sanitize_output'),8192);
        require_once '../management/other/index.php';
        ob_end_flush();
        ob_flush();
        flush();
    }
}
