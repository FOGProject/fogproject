<?php
class Page extends FOGBase {
	private $pageTitle,$sectionTitle,$stylesheets=array(),$javascripts=array(),$body,$isHomepage, $menu, $submenu, $media;
	public function __construct() {
		parent::__construct();
		global $theme;
		while (@ob_end_clean());
		$dispTheme = 'css/'.($theme ? $theme : 'default/fog.css');
		if (!preg_match('#/mobile/#i',$_SERVER['PHP_SELF']))
		{
			$this->addCSS('css/jquery-ui.css');
			$this->addCSS('css/jquery.organicTabs.css');
			$this->addCSS($dispTheme);
		}
		else
			$this->addCSS('css/main.css');
		$this->addCSS('../management/css/font-awesome.css');
		$this->isHomepage = (!$_REQUEST['node'] || in_array($_REQUEST['node'], array('home', 'dashboard','schemaupdater','client','logout','login')) || in_array($_REQUEST['sub'],array('configure','authorize')) || !$this->FOGUser || !$this->FOGUser->isLoggedIn());
		if ($this->FOGUser && $this->FOGUser->isLoggedIn() && $_REQUEST['node'] != 'schemaupdater')
		{
			$this->menu = $this->getClass('Mainmenu')->mainMenu();
		}
		if ($this->FOGUser && $this->FOGUser->isLoggedIn() && !preg_match('#/mobile/#i',$_SERVER['PHP_SELF']))
		{
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
			foreach(array("js/fog/fog.{$_REQUEST['node']}.js","js/fog/fog.{$_REQUEST['node']}.{$_REQUEST['sub']}.js") AS $jsFilepath)
			{
				if (file_exists($jsFilepath))
					array_push($files,$jsFilepath);
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
		ob_implicit_flush(1);
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
		ob_implicit_flush(1);
		require_once($path);
		ob_end_flush();
	}
}
