<?php
class AddMenuNotesItems extends Hook
{
	var $name = 'AddMenuNotesItems';
	var $description = 'Add menu items to the management page.';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'accesscontrol';
	public function AddMenuData($arguments)
	{
		$arguments['main'] = $this->array_insert_after('users',$arguments['main'],$this->node,_('Access Control'));
	}
	public function AddSubMenuData($arguments)
	{
		$arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
		$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Controls'));
		$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('Control'));
	}
}
$AddMenuNotesItems = new AddMenuNotesItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddMenuNotesItems, 'AddMenuData'));
$HookManager->register('SUB_MENULINK_DATA', array($AddMenuNotesItems, 'AddSubMenuData'));
