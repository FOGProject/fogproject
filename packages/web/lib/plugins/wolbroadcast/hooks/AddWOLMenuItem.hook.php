<?php
class AddWOLMenuItem extends Hook
{
	var $name = 'AddWOLMenuItem';
	var $description = 'Add menu item for WOL Broadcast';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'wolbroadcast';
	public function MenuData($arguments)
	{
		if ($_SESSION[$this->node])
			$arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('WOL Broadcast Management'),'fa fa-plug fa-2x'));
	}
	public function addSearch($arguments)
	{
		if ($_SESSION[$this->node])
			array_push($arguments['searchPages'],$this->node);
	}
}
$AddWOLMenuItem = new AddWOLMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddWOLMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES', array($AddWOLMenuItem, 'addSearch'));
