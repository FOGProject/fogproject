<?php 
class Mainmenu
{
	private $mainMenuItems, $foglang;
	private $currentUser, $HookManager;

	function __construct($currentUser)
	{
		$this->currentUser = $currentUser;
		$this->HookManager = $GLOBALS['FOGCore']->getClass('HookManager');
		$this->foglang = $GLOBALS['foglang'];
	}

	private function manageData()
	{
		$this->HookManager->processEvent('MAIN_MENU_DATA',array('data' =>&$this->mainMenuItems));
		if(!preg_match('#mobile#i',$_SERVER['PHP_SELF']))
		{
			print "\n\t\t\t<ul>";
			foreach($this->mainMenuItems AS $title => $link) 
				print "\n\t\t\t\t".'<li><a href="?node='.$link.'" title="'.$title.'"><img src="images/icon-'.$link.'.png" alt="'.$title.'" /></a></li>';
			print "\n\t\t\t</ul>";
		}
		else
		{
			print "\n\t\t\t".'<div id="menuBar">';
			foreach($this->mainMenuItems AS $title => $link)
				print "\n\t\t\t".'<a href="?node='.($link != 'logout' ? $link.'s' : $link.'').'"><img class="'.$link.'" src="images/icon-'.$link.'.png" alt="'.$title.'" /></a>';
			print "\n\t\t\t</div>";
		}
	}

	public function mainMenu()
	{
		if ($this->currentUser != null && $this->currentUser->isLoggedIn() && preg_match('#mobile#i',$_SERVER['PHP_SELF']))
		{
			foreach($this->foglang['Mobile'] AS $menu => $value)
			{
				$this->mainMenuItems[$value] = $menu;
			}
		}
		else if ($this->currentUser != null && $this->currentUser->isLoggedIn() && $this->currentUser->get('type') == 0)
		{
			foreach($this->foglang['Menu'] AS $menu => $value)
			{
				$this->mainMenuItems[$value] = $menu;
			}
		}
		else if ($this->currentUser != null && $this->currentUser->isLoggedIn() && $this->currentUser->get('type') != 0)
			$GLOBALS['FOGCore']->redirect('?node=logout');
		$this->manageData();
	}
}
