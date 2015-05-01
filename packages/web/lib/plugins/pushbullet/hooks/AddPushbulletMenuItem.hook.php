<?php
/**
 *	Author:		Jbob
**/
class AddPushbulletMenuItem extends Hook
{
	var $name = 'AddPushbulletMenuItem';
	var $description = 'Add menu item for pushbullet';
	var $author = 'Joe Schmitt';
	var $active = true;
	var $node = 'pushbullet';
	public function MenuData($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			$arguments['main'] = $this->array_insert_after('task',$arguments['main'],$this->node,array(_('Pushbullet Management'),'fa fa-bell fa-2x'));
	}
}
$AddPushbulletMenuItem = new AddPushbulletMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddPushbulletMenuItem, 'MenuData'));
