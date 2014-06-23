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
		$arguments['main'] = array_splice($arguments['main'],0,2,true)+array($this->node => _('Access Control'))+array_slice($arguments['main'], 1, count($arguments['main']) - 1, true);
	}
	public function SubMenuData($arguments)
	{
		$this->foglang = $GLOBALS['foglang'];
		$arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
		$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Controls'));
		$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('Control'));
	}
}
$AddMenuNotesItems = new AddMenuNotesItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddMenuNotesItems, 'MenuData'));
$HookManager->register('SUB_MENULINK_DATA', array($AddMenuNotesItems, 'SubMenuData'));
