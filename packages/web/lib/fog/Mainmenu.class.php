<?php 
class Mainmenu extends FOGBase
{
	public $main;
    /** Sets the Variables to use later on. **/
    public $FOGCore, $DB, $Hookmanager, $FOGUser, $FOGPageManager, $foglang;
	public function __construct()
	{
		parent::__construct();
	}
	private function manageData()
	{
		if(!preg_match('#mobile#i',$_SERVER['PHP_SELF']))
		{
			$menuItem[] = '<ul>';
			foreach($this->main AS $link => $title)
				$menuItem[] = sprintf("%s%s","\n\t\t\t\t\t\t",'<li><a href="?node='.$link.'" title="'.$title.'"><img src="images/icon-'.$link.'.png" alt="'.$title.'" /></a></li>');
			$menuItem[] = sprintf("%s%s","\n\t\t\t\t\t","</ul>\n");
		}
		else
		{
			$menuItem[] = sprintf("%s%s","\n\t\t\t\t",'<div id="menuBar">');
			foreach($this->main AS $link => $title)
				$menuItem[] = sprintf("%s%s","\n\t\t\t\t\t",'<a href="?node='.$link.($link != 'logout' ? 's' : '').'"><img class="'.$link.'" src="images/icon-'.$link.'.png" alt="'.$title.'" /></a>');
			$menuItem[] = sprintf("%s%s","\n\t\t\t\t","</div>");
		}
		return implode($menuItem);
	}
	private function mainSetting()
	{
		$plugin = $this->FOGCore->getSetting('FOG_PLUGINSYS_ENABLED');
		$this->main = array(
			'home' => $this->foglang['Home'],
			'user' => $this->foglang['User Management'],
			'host' => $this->foglang['Host Management'],
			'group' => $this->foglang['Group Management'],
			'image' => $this->foglang['Image Management'],
			'storage' => $this->foglang['Storage Management'],
			'snapin' => $this->foglang['Snapin Management'],
			'printer' => $this->foglang['Printer Management'],
			'service' => $this->foglang['Service Configuration'],
			'tasks' => $this->foglang['Task Management'],
			'report' => $this->foglang['Report Management'],
			'about' => $this->foglang['FOG Configuration'],
			$plugin ? 'plugin' : '' => $plugin ? $this->foglang['Plugin Management'] : '',
			'logout' => $this->foglang['Logout'],
		);
		$this->main = array_unique(array_filter($this->main));
		$this->HookManager->processEvent('MAIN_MENU_DATA',array('main' => &$this->main));
		foreach ($this->main AS $link => $title)
			$links[] = $link;
		$links[] = 'hwinfo';
		$links[] = 'client';
		if ($_REQUEST['node'] && !in_array($_REQUEST['node'],$links))
			$this->FOGCore->redirect('index.php');
	}
	private function mobileSetting()
	{
		$this->main = array(
			'home' => $this->foglang['Home'],
			'host' => $this->foglang['Host'],
			'tasks' => $this->foglang['Task'],
			'logout' => $this->foglang['Logout'],
		);
		foreach ($this->main AS $link => $title)
			$links[] = ($link != 'logout' ? $link.'s' :$link);
		if ($_REQUEST['node'] && !in_array($_REQUEST['node'],$links))
			$this->FOGCore->redirect('index.php');
	}
	public function mainMenu()
	{
		try
		{
			if ($this->FOGUser && $this->FOGUser->isValid() && $this->FOGUser->isLoggedIn() && preg_match('#mobile#i',$_SERVER['PHP_SELF']))
				$this->mobileSetting();
			else if ($this->FOGUser && $this->FOGUser->isValid() && $this->FOGUser->isLoggedIn() && $this->FOGUser->get('type') == 0)
				$this->mainSetting();
			else if ($this->FOGUser && $this->FOGUser->isValid() && $this->FOGUser->isLoggedIn() && $this->FOGUser->get('type') != 0)
				throw new Exception('Not Allowed Here!');
		}
		catch (Exception $e)
		{
			$this->FOGCore->redirect('?node=logout');
		}
		return $this->manageData();
	}
}
