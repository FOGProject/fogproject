<?php
class Page extends FOGBase {
    private $pageTitle,$sectionTitle,$stylesheets=array(),$javascripts=array(),$body,$isHomepage, $menu, $media;
    public function __construct() {
        parent::__construct();
        while (@ob_end_clean());
        $isMobile = preg_match('#/mobile/#i',@$_SERVER['PHP_SELF']);
        $dispTheme = 'css/'.($_SESSION['theme'] ? $_SESSION['theme'] : 'default/fog.css');
        if (!file_exists(BASEPATH.'/'.$dispTheme)) $dispTheme = 'css/default/fog.css';
        if (!$isMobile) {
            $this->addCSS('css/jquery-ui.css');
            $this->addCSS('css/jquery.organicTabs.css');
            $this->addCSS($dispTheme);
        } else $this->addCSS('css/main.css');
        $this->addCSS('../management/css/font-awesome.css');
        $this->isHomepage = (!$_REQUEST['node'] || in_array($_REQUEST['node'], array('home', 'dashboard','schemaupdater','client','logout','login')) || in_array($_REQUEST['sub'],array('configure','authorize')) || !$this->FOGUser || !$this->FOGUser->isLoggedIn());
        if ($this->FOGUser && $this->FOGUser->isLoggedIn() && strtolower($_REQUEST['node']) != 'schemaupdater') {
            if (!$isMobile) {
                $this->main = array(
                    'home' => array($this->foglang['Home'], 'fa fa-home fa-2x'),
                    'user' => array($this->foglang['User Management'], 'fa fa-users fa-2x'),
                    'host' => array($this->foglang['Host Management'], 'fa fa-desktop fa-2x'),
                    'group' => array($this->foglang['Group Management'], 'fa fa-sitemap fa-2x'),
                    'image' => array($this->foglang['Image Management'], 'fa fa-picture-o fa-2x'),
                    'storage' => array($this->foglang['Storage Management'], 'fa fa-download fa-2x'),
                    'snapin' => array($this->foglang['Snapin Management'], 'fa fa-files-o fa-2x'),
                    'printer' => array($this->foglang['Printer Management'], 'fa fa-print fa-2x'),
                    'service' => array($this->foglang['Service Configuration'], 'fa fa-cogs fa-2x'),
                    'task' => array($this->foglang['Task Management'], 'fa fa-tasks fa-2x'),
                    'report' => array($this->foglang['Report Management'], 'fa fa-file-text fa-2x'),
                    'about' => array($this->foglang['FOG Configuration'],'fa fa-wrench fa-2x'),
                    $_SESSION['PLUGSON'] ? 'plugin' : '' => $_SESSION['PLUGSON'] ? array($this->foglang['Plugin Management'],'fa fa-cog fa-2x') : '',
                    'logout' => array($this->foglang['Logout'], 'fa fa-sign-out fa-2x'),
                );
            } else {
                $this->main = array(
                    'home' => array($this->foglang['Home'], 'fa fa-home fa-2x'),
                    'host' => array($this->foglang['Host Management'], 'fa fa-desktop fa-2x'),
                    'task' => array($this->foglang['Task Management'], 'fa fa-tasks fa-2x'),
                    'logout' => array($this->foglang['Logout'], 'fa fa-sign-out fa-2x'),
                );
            }
            $this->main = array_unique(array_filter($this->main),SORT_REGULAR);
            $this->HookManager->processEvent('MAIN_MENU_DATA',array('main' => &$this->main));
            foreach ($this->main AS $link => $title) $links[] = (!$isMobile ? $link : ($link != 'logout' ? $link.'s' : $link));
            if (!$isMobile) $links = array_merge((array)$links,array('hwinfo','client','schemaupdater'));
            if ($_REQUEST['node'] && !in_array($_REQUEST['node'],$links)) $this->FOGCore->redirect('index.php');
            $this->menu = (!$isMobile ? '<center><ul>' : '<div id="menuBar">');
            foreach($this->main AS $link => $title) {
                $activelink = (!$isMobile ? ($_REQUEST['node'] == $link || (!$_REQUEST['node'] && $link == 'home') ? $activelink = 1 : 0) : ($_REQUEST['node'] == $link.'s' || (!$_REQUEST['node'] && $link == 'home') ? 1 : 0));
                $this->menu .= (!$isMobile ? sprintf('<li><a href="?node=%s" title="%s"%s><i class="%s"></i></a></li>',$link,$title[0],($activelink ? ' class="activelink"' : ''),$title[1]) : sprintf('<a href="?node=%s"%s><i class="%s"></i></a>',($link != 'logout' ? $link.'s' : $link),($activelink ? ' class="activelink"' : ''),$title[1]));
            }
            $this->menu .= (!$isMobile ? '</ul></center>' : '</div>');
        }
        if ($this->FOGUser && $this->FOGUser->isLoggedIn() && !preg_match('#/mobile/#i',$_SERVER['PHP_SELF'])) {
            $files = array(
                'js/jquery-latest.js',
                'js/jquery-migrate-1.2.1.min.js',
                //'js/jquery.tablesorter.min.js',
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
                'js/hideShowPassword.min.js',
                'js/fog/fog.js',
                'js/fog/fog.main.js',
            );
            if ($_REQUEST['sub'] == 'membership')
                $_REQUEST['sub'] = 'edit';
            $filepaths = array(
                "js/fog/fog.{$_REQUEST['node']}.js",
                "js/fog/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js",
            );
            foreach($filepaths AS $jsFilepath)
            {
                if (file_exists($jsFilepath))
                    array_push($files,$jsFilepath);
            }
            $pluginfilepaths = array(
                BASEPATH."/lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.js",
                BASEPATH."/lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js",
            );
            foreach($pluginfilepaths AS $pluginfilepath)
            {
                if (file_exists($pluginfilepath) && !file_exists("js/fog/".basename($pluginfilepath)))
                {
                    $newfile = "js/fog/".basename($pluginfilepath);
                    file_put_contents($newfile,file_get_contents($pluginfilepath));
                }
            }
            if ($this->isHomepage)
            {
                array_push($files,'js/fog/fog.dashboard.js');
                if (preg_match('#MSIE [6|7|8|9|10|11]#',$_SERVER['HTTP_USER_AGENT']))
                    array_push($files,'js/flot/excanvas.js');
            }
        }
        else if (!preg_match('#/mobile/#i',$_SERVER['PHP_SELF']))
        {
            $files = array(
                'js/jquery-latest.js',
                'js/jquery.progressbar.js',
                'js/fog/fog.js',
                'js/fog/fog.login.js',
            );
        }
        foreach((array)$files AS $path)
        {
            if (file_exists($path))
                $this->addJavascript($path);
        }
    }
    public function setTitle($title) {
        $this->pageTitle = $title;
    }
    public function setSecTitle($title) {
        $this->sectionTitle = $title;
    }
    public function addCSS($path) {
        $this->stylesheets[] = $path;
    }
    public function addJavascript($path){
        $this->javascripts[] = $path;
    }
    public function startBody() {
        ob_start('sanitize_output');
    }
    public function endBody() {
        $this->body = ob_get_clean();
    }
    public function render($path = '') {
        if (!$path && preg_match('#/mobile/#i',$_SERVER['PHP_SELF']))
            $path = '../management/other/index.php';
        else
            $path = 'other/index.php';
        ob_start('sanitize_output',$_SESSION['chunksize']);
        require_once($path);
        while(ob_end_flush());
        session_write_close();
    }
}
