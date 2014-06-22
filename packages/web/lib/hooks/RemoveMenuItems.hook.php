<?php
class RemoveMenuItems extends Hook
{
	var $name = 'RemoveMenuItems';
	var $description = 'Removes the "IP Address" column from Host Lists';
	var $author = 'Blackout';
	var $active = false;
	private $linkToFilter;
	public function __construct()
	{
		parent::__construct();
		$this->linksToFilter = array('home','host','plugin','storage','images');
	}
	public function MenuData($arguments)
	{
		foreach($arguments['main'] AS $link => $title)
		{
			if (in_array($link,$this->linksToFilter))
				unset($arguments['main'][$link]);
		}
	}
	public function NotAllowed($arguments)
	{
		foreach($this->linksToFilter AS $link)
		{
			if (preg_match('#node='.$link.'#i',$_SERVER['REQUEST_URI']))
			{
				$this->FOGCore->setMessage('You are not allowed here.');
				$this->FOGCore->redirect('index.php');
			}
		}
	}
}
$RemoveMenuItems = new RemoveMenuItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($RemoveMenuItems, 'MenuData'));
$HookManager->register('CONTENT_DISPLAY', array($RemoveMenuItems, 'NotAllowed'));
