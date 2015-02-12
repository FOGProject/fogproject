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
		if ($_SESSION[$this->node])
			$arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('Location Management'),'fa fa-globe fa-2x'));
	}
	public function addSearch($arguments)
	{
		if ($_SESSION[$this->node])
			array_push($arguments['searchPages'],$this->node);
	}
}
$AddLocationMenuItem = new AddLocationMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddLocationMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES', array($AddLocationMenuItem, 'addSearch'));
