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
		$plugin = current($this->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
			$arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,_('WOL Broadcast Management'));
	}
}
$AddWOLMenuItem = new AddWOLMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddWOLMenuItem, 'MenuData'));
