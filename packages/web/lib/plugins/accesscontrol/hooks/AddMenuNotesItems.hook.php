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
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			$arguments['main'] = $this->array_insert_after('user',$arguments['main'],$this->node,array(_('Access Control'),'fa fa-user-secret fa-2x'));
	}
	public function AddSubMenuData($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
		{
			$arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
			$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Controls'));
			$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('Control'));
		}
	}
	public function addSearch($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			array_push($arguments['searchPages'],$this->node);
	}
}
$AddMenuNotesItems = new AddMenuNotesItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddMenuNotesItems, 'AddMenuData'));
$HookManager->register('SUB_MENULINK_DATA', array($AddMenuNotesItems, 'AddSubMenuData'));
$HookManager->register('SEARCH_PAGES', array($AddMenuNotesItems, 'AddSearch'));
