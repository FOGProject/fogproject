<?php
class AddLocationMenuItem extends Hook
{
	var $name = 'AddLocationMenuItem';
	var $description = 'Add menu item for location';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'location';
	public function __construct()
	{
		parent::__construct();
	}
	public function MenuData($arguments)
	{
		global $MainMenu,$foglang;
		$MainMenu->menu = $this->array_insert_after('storage',$MainMenu->main,$this->node,_('Location Management'));
		$arguments['main'] = $MainMenu->menu;
	}
}
$AddLocationMenuItem = new AddLocationMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddLocationMenuItem, 'MenuData'));
