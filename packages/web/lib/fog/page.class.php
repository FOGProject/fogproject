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
            $this->imagelink = !$this->isMobile ? sprintf('css/%s%simages/',dirname($this->theme),'/') : '';
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
        if (!$_REQUEST['node']) $_REQUEST['node'] = 'home';
        $this->isHomepage = (in_array($_REQUEST['node'],array('home','dashboard','schemaupdater','client','logout','login')) || in_array($_REQUEST['sub'],array('configure','authorize')) || !$_SESSION['FOG_USERNAME']);
        if ($_SESSION['FOG_USERNAME'] && strtolower($_REQUEST['node']) != 'schemaupdater') {
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
                if ($this->getSetting('FOG_PLUGINSYS_ENABLED')) $this->main = $this->array_insert_after('about',$this->main,'plugin',array($this->foglang['Plugin Management'],'fa fa-cog fa-2x'));
            } else if ($this->isMobile) {
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
            foreach ($this->main AS $link => &$title) {
                $links[] = $link;
                unset($title);
            }
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
            }
            unset($title);
            echo '</ul></nav>';
            $this->menu = ob_get_clean();
        }
        if ($_SESSION['FOG_USERNAME'] && !$this->isMobile) {
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
            foreach ((array)$filepaths AS $i => &$jsFilepath) {
                if (file_exists($jsFilepath)) array_push($files,$jsFilepath);
                unset($jsFilepath);
            }
            unset($filepaths);
            $pluginfilepaths = array(
                "../lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.js",
                "../lib/plugins/{$_REQUEST['node']}/js/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js",
                "../lib/plugins/$node/js/fog.$node.js",
                "../lib/plugins/$node/js/fog.$node.$sub.js",
            );
            foreach ((array)$pluginfilepaths AS $i => &$jsFilepath) {
                if (file_exists($jsFilepath)) array_push($files,$jsFilepath);
                unset($jsFilepath);
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
        foreach ((array)$files AS $i => &$path) {
            $this->addJavascript($path);
            unset($path);
        }
    }
    private function setTitle($title) {
        $this->pageTitle = $title;
    }
    private function setSecTitle($title) {
        $this->sectionTitle = $title;
    }
    private function addCSS($path) {
        $this->stylesheets[] = '../management/'.$path;
    }
    private function addJavascript($path){
        $this->javascripts[] = $path;
    }
    private function buildHead() {
        ob_start();
        if (!$this->isMobile) {
            $meta = '<meta http-equiv="X-UA-Compatible" content="IE=Edge"/><meta http-equiv="content-type" content="text/json; charset=utf-8"/>';
            $title = sprintf('<title>%s%sFOG &gt; %s</title>',
                ($this->pageTitle ? "$this->pageTitle &gt; " : ''),
                ($this->sectionTitle ? " $this->sectionTitle &gt; " : ''),
                $this->foglang['Slogan']
            );
        } else {
            $meta = '<meta name="viewport" content="width=device-width"/><meta name="viewport" content="initial-scale=1.0"/>';
            $title = sprintf('<title>FOG :: %s :: %s %s</title>',_('Mobile Manager'),_('Version'),FOG_VERSION);
        }
        $this->HookManager->processEvent('CSS',array('stylesheets'=>&$this->stylesheets));
        ob_start();
        foreach ($this->stylesheets AS $i => &$stylesheet) {
            printf('<link href="%s?ver=%s" rel="stylesheet" type="text/css"/>',$stylesheet,FOG_BCACHE_VER);
            unset($stylesheet);
        }
        echo '<link rel="shortcut icon" href="../favicon.ico type="image/x-icon"/>';
        $link = ob_get_clean();
        printf('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head>%s%s%s</head>',
            $meta,
            $title,
            $link
        );
        return ob_get_clean();
    }
    private function buildBody() {
        ob_start();
        echo '<body>';
        if (!$this->isMobile) {
            printf('<div class="fog-variable" id="FOGPingActive">%s</div>%s<div id="loader-wrapper"><div id="loader"></div><div id="progress"></div></div><div id="wrapper"><header><div id="header"%s><div id="logo"><h1><a href="../management/index.php"><img src="%sfog-logo.png" title="%s"/><sup>%s</sup></a></h1><h2>%s</h2></div>%s</div>%s</header><div id="content"%s>%s<div id="content-inner">%s',
                intval($_SESSION['FOGPingActive']),
                $this->getMessages(),
                (!$_SESSION['FOG_USERNAME'] ? ' class="login"' : ''),
                $this->imagelink,
                $this->foglang['Home'],
                FOG_VERSION,
                $this->foglang['Slogan'],
                ($_SESSION['FOG_USERNAME'] ? $this->menu : ''),
                ($_SESSION['FOG_USERNAME'] && !$this->isHomepage ? $this->FOGPageManager->getSideMenu() : ''),
                ($this->isHomepage ? ' class="dashboard"' : ''),
                ($_SESSION['FOG_USERNAME'] ? ($this->sectionTitle ? "<h1>$this->sectionTitle</h1>" : '') : ''),
                ($_SESSION['FOG_USERNAME'] ? ($this->pageTitle ? "<h2>$this->pageTitle</h2>" : '') : '')
            );
            echo "$this->body</div></div></div>";
        } else {
            printf('<div id="header"></div><div id="mainContainer"><div class="mainContent">%s%s<div id="mobile_content">%s</div></div></div>',$this->menu,($this->pageTitle ? "<h2>$this->pageTitle</h2>" : ''),$this->body);
        }
        return ob_get_clean();
    }
    private function buildFooter() {
        ob_start();
        if (!$this->isMobile) {
            printf('<div id="footer"><a href="http:/fogproject.org/wiki/index.php/Credits">%s</a>&nbsp;&nbsp;<a href="?node=client">%s</a></div><!-- <div id="footer"><a href="http://fogproject.org/wiki/index.php/Credits">Credits</a>&nbsp;&nbsp;<a href="?node=client">FOG Client/FOG Prep</a> Memory Usage: %s</div> -->',
                _('Credits'),
                _('FOG Client/FOG Prep'),
                $this->formatByteSize(memory_get_usage(true))
            );
            $this->HookManager->processEvent('JAVASCRIPT',array('javascripts'=>&$this->javascripts));
            foreach ((array)$this->javascripts AS $i => &$javascript) {
                printf('<script src="%s?ver=%s" language="javascript" type="text/javascript" defer></script>',
                    $javascript,
                    FOG_BCACHE_VER
                );
                unset($javascript);
            }
        }
        echo '</body></html>';
        return ob_get_clean();
    }
    private function getContents() {
        ob_start();
        if ($_SESSION['FOG_USERNAME'] || $_REQUEST['node'] == 'schemaupdater') $this->FOGPageManager->render();
        else if ($this->isMobile) $this->getClass('ProcessLogin')->mobileLoginForm();
        else if (!$this->isMobile) $this->getClass('ProcessLogin')->mainLoginForm();
        $this->setTitle($this->FOGPageManager->getFOGPageTitle());
        $this->setSecTitle($this->FOGPageManager->getFOGPageName());
        $this->body = ob_get_clean();
    }
    public function render($path = '') {
        $this->getContents();
        $this->HookManager->processEvent('CONTENT_DISPLAY',array('content'=>&$this->body,'sectionTitle'=>&$this->sectionTitle,'pageTitle'=>&$this->pageTitle));
        if ($this->ajax) {
            echo $this->body;
            exit;
        }
        ob_start(array('Initiator','sanitize_output'));
        echo $this->buildHead();
        echo $this->buildBody();
        echo $this->buildFooter();
        ob_flush();
        flush();
        ob_end_flush();
    }
}
