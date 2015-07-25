<?php
class AddPushbulletMenuItem extends Hook {
	public function __construct() {
		parent::__construct();
		$this->name = 'AddPushbulletMenuItem';
		$this->description = 'Add menu item for pushbullet';
		$this->author = 'Joe Schmitt';
		$this->active = true;
		$this->node = 'pushbullet';
	}
	public function MenuData($arguments) {
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			$arguments['main'] = $this->array_insert_after('task',$arguments['main'],$this->node,array(_('Pushbullet Management'),'fa fa-bell fa-2x'));
	}
}
$AddPushbulletMenuItem = new AddPushbulletMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddPushbulletMenuItem, 'MenuData'));
