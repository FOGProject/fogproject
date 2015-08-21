<?php
class Page extends FOGBase {
    private $pageTitle,$sectionTitle,$stylesheets=array(),$javascripts=array(),$body,$isHomepage, $menu, $media;
    public function __construct() {
        parent::__construct();
        while (@ob_end_clean());
        $isMobile = preg_match('#/mobile/#i',@$_SERVER['PHP_SELF']);
        $dispTheme = 'css/'.$_SESSION[theme];
        if (!file_exists(BASEPATH.'/management/'.$dispTheme)) $dispTheme = 'css/default/fog.css';
        if (!$isMobile) {
            $this->addCSS('css/jquery-ui.css');
            $this->addCSS('css/jquery.organicTabs.css');
            $this->addCSS($dispTheme);
        } else $this->addCSS('css/main.css');
        $this->addCSS('css/font-awesome.css');
        $this->addCSS('css/select2.min.css');
        $this->isHomepage = (!$_REQUEST[node] || in_array($_REQUEST[node], array('home', 'dashboard','schemaupdater','client','logout','login')) || in_array($_REQUEST[sub],array('configure','authorize')) || !$this->FOGUser || !$this->FOGUser->isLoggedIn());
        if ($this->FOGUser && $this->FOGUser->isLoggedIn() && strtolower($_REQUEST[node]) != 'schemaupdater') {
            if (!$isMobile) {
                $this->main = array(
                    home=>array($this->foglang[Home],'fa fa-home fa-2x'),
                    user=>array($this->foglang['User Management'],'fa fa-users fa-2x'),
                    host=>array($this->foglang['Host Management'],'fa fa-desktop fa-2x'),
                    group=>array($this->foglang['Group Management'],'fa fa-sitemap fa-2x'),
                    image=>array($this->foglang['Image Management'],'fa fa-picture-o fa-2x'),
                    storage=>array($this->foglang['Storage Management'],'fa fa-archive fa-2x'),
                    snapin=>array($this->foglang['Snapin Management'],'fa fa-files-o fa-2x'),
                    printer=>array($this->foglang['Printer Management'],'fa fa-print fa-2x'),
                    service=>array($this->foglang['Service Configuration'],'fa fa-cogs fa-2x'),
                    task=>array($this->foglang['Task Management'],'fa fa-tasks fa-2x'),
                    report=>array($this->foglang['Report Management'],'fa fa-file-text fa-2x'),
                    about=>array($this->foglang['FOG Configuration'],'fa fa-wrench fa-2x'),
                    logout=>array($this->foglang[Logout],'fa fa-sign-out fa-2x'),
                );
                if ($_SESSION[PLUGSON]) $this->main = $this->array_insert_after(about,$this->main,plugin,array($this->foglang['Plugin Management'],'fa fa-cog fa-2x'));
            } else {
                $this->main = array(
                    home=>array($this->foglang[Home],'fa fa-home fa-2x'),
                    host=>array($this->foglang['Host Management'],'fa fa-desktop fa-2x'),
                    task=>array($this->foglang['Task Management'],'fa fa-tasks fa-2x'),
                    logout=>array($this->foglang[Logout],'fa fa-sign-out fa-2x'),
                );
            }
            $this->main = array_unique(array_filter($this->main),SORT_REGULAR);
            $this->HookManager->processEvent(MAIN_MENU_DATA,array('main'=>&$this->main));
            $links = array();
            foreach ($this->main AS $link => &$title) $links[] = (!$isMobile ? $link : ($link != 'logout' ? $link.'s' : $link));
            unset($title);
            if (!$isMobile) $links = array_merge((array)$links,array('hwinfo','client','schemaupdater'));
            if ($_REQUEST[node] && !in_array($_REQUEST[node],$links)) $this->FOGCore->redirect('index.php');
            $this->menu = '<nav class="menu"><ul class="nav-list">';
            foreach($this->main AS $link => &$title) {
                if (!$_REQUEST[node]) $_REQUEST[node] = 'home'.($isMobile ? 's' : '');
                $activelink = (int)($_REQUEST[node] == ($isMobile && $_REQUEST[node] != 'logout' ? $link.'s' : $link));
                $this->menu .= sprintf('<li class="nav-item"><a href="?node=%s" class="nav-link%s" title="%s"><i class="%s"></i></a></li>',($isMobile && $link != 'logout' ? $link.'s' : $link),($activelink ? ' activelink' : ''),$title[0],$title[1]);
            }
            unset($title);
            $this->menu .= '</ul></nav>';
        }
        if ($this->FOGUser && $this->FOGUser->isLoggedIn() && !preg_match('#/mobile/#i',$_SERVER['PHP_SELF'])) {
            $files = array(
                'js/jquery-latest.js',
                'js/jquery-migrate-1.2.1.min.js',
                'js/jquery.tablesorter.min.js',
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
                'js/select2.min.js',
                'js/fog/fog.js',
                'js/fog/fog.main.js',
            );
            if ($_REQUEST[sub] == 'membership') $_REQUEST[sub] = 'edit';
            $filepaths = array(
                "js/fog/fog.{$_REQUEST[node]}.js",
                "js/fog/fog.{$_REQUEST[node]}.{$_REQUEST[sub]}.js",
            );
            foreach($filepaths AS $i => &$jsFilepath) {
                if (file_exists($jsFilepath)) array_push($files,$jsFilepath);
            }
            unset($jsFilepath);
            $pluginfilepaths = array(
                BASEPATH."/lib/plugins/{$_REQUEST[node]}/js/fog.{$_REQUEST[node]}.js",
                BASEPATH."/lib/plugins/{$_REQUEST[node]}/js/fog.{$_REQUEST[node]}.{$_REQUEST['sub']}.js",
            );
            foreach($pluginfilepaths AS $i => &$pluginfilepath) {
                if (file_exists($pluginfilepath) && !file_exists("js/fog/".basename($pluginfilepath))) {
                    $newfile = "js/fog/".basename($pluginfilepath);
                    file_put_contents($newfile,file_get_contents($pluginfilepath));
                }
            }
            unset($pluginfilepath);
            if ($this->isHomepage) {
                array_push($files,'js/fog/fog.dashboard.js');
                if (preg_match('#MSIE [6|7|8|9|10|11]#',$_SERVER[HTTP_USER_AGENT])) array_push($files,'js/flot/excanvas.js');
            }
        }
        else if (!preg_match('#/mobile/#i',$_SERVER['PHP_SELF'])) {
            $files = array(
                'js/jquery-latest.js',
                'js/jquery.progressbar.js',
                'js/fog/fog.js',
                'js/fog/fog.login.js',
            );
        }
        foreach((array)$files AS $i => &$path) {
            if (file_exists($path)) $this->addJavascript($path);
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
        $this->javascripts[] = $path;
    }
    public function startBody() {
        ob_start(array('Initiator','sanitize_output'));
    }
    public function endBody() {
        $this->body = ob_get_clean();
    }
    public function render($path = '') {
        if (!$path && preg_match('#/mobile/#i',$_SERVER['PHP_SELF'])) $path = '../management/other/index.php';
        else $path = 'other/index.php';
        ob_start(array('Initiator','sanitize_output'),$_SESSION['chunksize']);
        require_once($path);
        while(ob_end_flush());
        session_write_close();
    }
}
