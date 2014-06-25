<?php
class AddMenuNotesItems extends Hook
{
	var $name = 'AddMenuNotesItems';
	var $description = 'Add menu items to the management page.';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'accesscontrol';
	public function __construct()
	{
		parent::__construct();
	}
	public function MenuData($arguments)
	{
		global $MainMenu;
		$MainMenu->main = $this->array_insert_after('users',$MainMenu->main,$this->node,_('Access Control'));
		$arguments['main'] = $arguments['main'];
	}
	public function SubMenuData($arguments)
	{
		global $foglang;
		$arguments['submenu'][$this->node]['search'] = $foglang['NewSearch'];
		$arguments['submenu'][$this->node]['list'] = sprintf($foglang['ListAll'],_('Controls'));
		$arguments['submenu'][$this->node]['add'] = sprintf($foglang['CreateNew'],_('Control'));
	}
}
$AddMenuNotesItems = new AddMenuNotesItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddMenuNotesItems, 'MenuData'));
$HookManager->register('SUB_MENULINK_DATA', array($AddMenuNotesItems, 'SubMenuData'));
