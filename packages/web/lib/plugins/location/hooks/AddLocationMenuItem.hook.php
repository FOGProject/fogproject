<?php
class AddLocationMenuItem extends Hook
{
	var $name = 'AddLocationMenuItem';
	var $description = 'Add menu item for location';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'location';
	public function MenuData($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			$arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('Location Management'),'fa fa-globe fa-2x'));
	}
	public function addSearch($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			array_push($arguments['searchPages'],$this->node);
	}
}
$AddLocationMenuItem = new AddLocationMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddLocationMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES', array($AddLocationMenuItem, 'addSearch'));
