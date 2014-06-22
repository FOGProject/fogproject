<?php 
class Mainmenu extends FOGBase
{
	private $mainMenuItems,$mobile,$main,$menuItem;

	public function __construct()
	{
		parent::__construct();
	}
	private function manageData()
	{
		if(!preg_match('#mobile#i',$_SERVER['PHP_SELF']))
		{
			$menuItem[] = sprintf("%s%s","\n\t\t\t","<ul>");
			foreach($this->main AS $link => $title)
				$menuItem[] = sprintf("%s%s","\n\t\t\t\t",'<li><a href="?node='.$link.'" title="'.$title.'"><img src="images/icon-'.$link.'.png" alt="'.$title.'" /></a></li>');
			$menuItem[] = sprintf("%s%s","\n\t\t\t","</ul>");
		}
		else
		{
			$menuItem[] = sprintf("%s%s","\n\t\t\t",'<div id="menuBar">');
			foreach($this->main AS $link => $title)
				$menuItem[] = sprintf("%s%s","\n\t\t\t\t",'<a href="?node='.$link.($link != 'logout' ? 's' : '').'"><img class="'.$link.'" src="images/icon-'.$link.'.png" alt="'.$title.'" /></a>');
			$menuItem[] = sprintf("%s%s","\n\t\t\t","</div>");
		}
		print implode($menuItem);
	}
	private function mainSetting()
	{
		$location = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location', 'installed' => 1)));
		$plugin = $this->FOGCore->getSetting('FOG_PLUGINSYS_ENABLED');
		$menu = array(
			'home' => $this->foglang['Home'],
			'users' => $this->foglang['User'].' '.$this->foglang['Management'],
			'host' => $this->foglang['Host'].' '.$this->foglang['Management'],
			'group' => $this->foglang['Group'].' '.$this->foglang['Management'],
			'images' => $this->foglang['Image'].' '.$this->foglang['Management'],
			'storage' => $this->foglang['Storage'].' '.$this->foglang['Management'],
			'snapin' => $this->foglang['Snapin'].' '.$this->foglang['Management'],
			'printer' => $this->foglang['Printer'].' '.$this->foglang['Management'],
			'service' => $this->foglang['Service'].' '.$this->foglang['Management'],
			'tasks' => $this->foglang['Task'].' '.$this->foglang['Management'],
			'report' => $this->foglang['Reports'].' '.$this->foglang['Management'],
			'about' => $this->foglang['FOG'].' '.$this->foglang['Configuration'],
			$location ? 'location' : '' => $location ? $this->foglang['Location'].' '.$this->foglang['Management'] : '',
			$plugin ? 'plugin' : '' => $plugin ? $this->foglang['Plugin'].' '.$this->foglang['Management'] : '',
			'logout' => $this->foglang['Logout'],
		);
		$menu = array_unique(array_filter($menu));
		foreach ($menu AS $link => $title)
			$this->main[$link] = $title;
		$this->HookManager->processEvent('MAIN_MENU_DATA',array('main' => &$this->main));
	}
	private function mobileSetting()
	{
		$menu = array(
			'home' => $this->foglang['Home'],
			'host' => $this->foglang['Host'],
			'tasks' => $this->foglang['Task'],
			'logout' => $this->foglang['Logout'],
		);
		$menu = array_unique(array_filter($menu));
		$links = array();
		foreach ($menu AS $link => $title)
			$this->main[$link] = $title;
		$this->HookManager->processEvent('MAIN_MENU_DATA',array('main' => &$this->main));
		foreach ($this->main AS $link => $title)
			array_push($links,($link != 'logout' ? $link.'s' : $link));
		if ($_REQUEST['node'] && !in_array($_REQUEST['node'],$links))
			$this->FOGCore->redirect('index.php');
	}
	public function mainMenu()
	{
		try
		{
			if ($this->FOGUser != null && $this->FOGUser->isLoggedIn() && preg_match('#mobile#i',$_SERVER['PHP_SELF']))
				$this->mobileSetting();
			else if ($this->FOGUser != null && $this->FOGUser->isLoggedIn() && $this->FOGUser->get('type') == 0)
				$this->mainSetting();
			else if ($this->FOGUser != null && $this->FOGUser->isLoggedIn() && $this->FOGUser->get('type') != 0)
				throw new Exception('Not Allowed Here!');
			foreach($this->main AS $menu => $value)
				$this->mainMenuItems[$value] = $menu;
		}
		catch (Exception $e)
		{
			$this->FOGCore->redirect('?node=logout');
		}
		$this->manageData();
	}
}
