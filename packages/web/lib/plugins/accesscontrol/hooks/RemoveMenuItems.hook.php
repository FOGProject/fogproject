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
		$this->getLoggedIn();
	}
	public function getLoggedIn()
	{
		if ($this->FOGUser && $this->FOGUser->isLoggedIn())
		{
			if (in_array($this->FOGUser->get('type'),array(1)))
				$this->linksToFilter = array('home','host','tasks','logout');
			elseif(in_array($this->FOGUser->get('type'),array(0)))
				$this->linksToFilter = array('accesscontrol','printer','service','about');
		}
	}
	public function MenuData($arguments)
	{
		foreach($this->linksToFilter AS $link)
			unset($arguments['main'][$link]);
	}
	public function SubMenuData($arguments)
	{
		foreach($arguments['submenu'] AS $node => $link)
		{
			if (in_array($node,(array)$this->linksToFilter))
			{
				$linkformat = $_SERVER['PHP_SELF'].'?node='.$node.'&sub=edit&id='.$_REQUEST['id'];
				$delformat = $_SERVER['PHP_SELF'].'?node='.$node.'&sub=delete&id='.$_REQUEST['id'];
				unset($arguments['submenu'][$node]['id'][$linkformat.'#host-printers']);
				unset($arguments['submenu'][$node]['id'][$linkformat.'#host-service']);
				unset($arguments['submenu'][$node]['id'][$linkformat.'#host-virus-history']);
				if(!in_array($this->FOGUser->get('name'),array('fog')))
					unset($arguments['submenu'][$node]['id'][$delformat]);
			}
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
