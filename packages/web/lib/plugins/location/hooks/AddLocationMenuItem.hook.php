<?php
class AddLocationMenuItem extends Hook
{
	var $name = 'AddLocationMenuItem';
	var $description = 'Add menu item for location';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'location';
<<<<<<< HEAD
	public function __construct()
	{
		parent::__construct();
	}
	public function MenuData($arguments)
	{
		global $MainMenu,$foglang;
		$MainMenu->menu = $this->array_insert_after('storage',$MainMenu->main,$this->node,_('Location Management'));
		$arguments['main'] = $MainMenu->menu;
=======
	public function MenuData($arguments)
	{
		$plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
			$arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,_('Location Management'));
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
	}
}
$AddLocationMenuItem = new AddLocationMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddLocationMenuItem, 'MenuData'));
