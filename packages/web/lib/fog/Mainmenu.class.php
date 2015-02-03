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
			$menuItem[] = '<center><ul>';
			foreach($this->main AS $link => $title)
			{
				$activelink = false;
				if ($_REQUEST['node'] == $link || (!$_REQUEST['node'] && $link == 'home'))
					$activelink = true;
				$menuItem[] = "\n\t\t\t\t\t\t".'<li><a href="?node='.$link.'" title="'.$title[0].'" '.($activelink ? 'class="activelink"' : '').'><i class="'.$title[1].'"></i></a></li>';
			}
			$menuItem[] = sprintf("%s%s","\n\t\t\t\t\t","</ul></center>\n");
		}
		else
		{
			$menuItem[] = sprintf("%s%s","\n\t\t\t\t",'<div id="menuBar">');
			foreach($this->main AS $link => $title)
			{
				$activelink = false;
				if ($_REQUEST['node'] == $link.'s' || (!$_REQUEST['node'] && $link == 'home'))
					$activelink = true;
				$menuItem[] = sprintf("%s%s","\n\t\t\t\t\t",'<a href="?node='.$link.($link != 'logout' ? 's' : '').'" '.($activelink ? 'class="activelink"' : '').'><i class="'.$title[1].'"></i></a>');
			}
			$menuItem[] = sprintf("%s%s","\n\t\t\t\t","</div>");
		}
		return implode($menuItem);
	}
	private function mainSetting()
	{
		$plugin = $this->FOGCore->getSetting('FOG_PLUGINSYS_ENABLED');
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
			'tasks' => array($this->foglang['Task Management'], 'fa fa-tasks fa-2x'),
			'report' => array($this->foglang['Report Management'], 'fa fa-file-text fa-2x'),
			'about' => array($this->foglang['FOG Configuration'],'fa fa-wrench fa-2x'),
			$plugin ? 'plugin' : '' => $plugin ? array($this->foglang['Plugin Management'],'fa fa-cog fa-2x') : '',
			'logout' => array($this->foglang['Logout'], 'fa fa-sign-out fa-2x'),
		);
		$this->main = array_unique(array_filter($this->main),SORT_REGULAR);
		$this->HookManager->processEvent('MAIN_MENU_DATA',array('main' => &$this->main));
		foreach ($this->main AS $link => $title)
			$links[] = $link;
		$links[] = 'hwinfo';
		$links[] = 'client';
		$links[] = 'schemaupdater';
		if ($_REQUEST['node'] && !in_array($_REQUEST['node'],$links))
			$this->FOGCore->redirect('index.php');
	}
	private function mobileSetting()
	{
		$this->main = array(
			'home' => array($this->foglang['Home'], 'fa fa-home fa-2x'),
			'host' => array($this->foglang['Host'], 'fa fa-desktop fa-2x'),
			'tasks' => array($this->foglang['Task'], 'fa fa-tasks fa-2x'),
			'logout' => array($this->foglang['Logout'], 'fa fa-sign-out fa-2x'),
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
