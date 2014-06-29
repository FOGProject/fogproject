<?php
class RemoveMenuItems extends Hook
{
	var $name = 'RemoveMenuItems';
	var $description = 'Removes menu items and restricts the links from the page.';
	var $author = 'Tom Elliott';
	var $active = true;
	public function __construct()
	{
		parent::__construct();
		//$this->FOGUser = (!empty($_SESSION['FOG_USER']) ? unserialize($_SESSION['FOG_USER']) : null);
		$this->getLoggedIn();
	}
	public function getLoggedIn()
	{
		if ($this->FOGUser)
		{
			if(!in_array($this->FOGUser->get('name'),array('fog')))
				$this->linksToFilter = array('accesscontrol','images','storage','location','snapin','printer','service','about');
			elseif(in_array($this->FOGUser->get('name'),array('someother1','someother2')))
				$this->linksToFilter = array('accesscontrol','printer','service','about');
			else
				$this->linksToFilter = array('printer','service');
		}
	}
	public function MenuData($arguments)
	{
		foreach($arguments['main'] AS $link => $title)
		{
			if (in_array($link,$this->linksToFilter))
				unset($arguments['main'][$link]);
		}
	}
	public function SubMenuData($arguments)
	{
		foreach($arguments['submenu'] AS $node => $link)
		{
			if (in_array($node,$this->linksToFilter))
				unset($arguments['submenu'][$node]);
		}
	}
	public function NotAllowed($arguments)
	{
		if (in_array($_REQUEST['node'],(array)$this->linksToFilter))
		{
			$this->FOGCore->setMessage('Not Allowed!');
			$this->FOGCore->redirect('index.php');
		}
	}
}
$RemoveMenuItems = new RemoveMenuItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($RemoveMenuItems, 'MenuData'));
$HookManager->register('SUB_MENULINK_DATA', array($RemoveMenuItems, 'SubMenuData'));
$HookManager->register('CONTENT_DISPLAY', array($RemoveMenuItems, 'NotAllowed'));
