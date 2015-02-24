<?php
class AddPushbulletSubMenuItems extends Hook
{
	var $name = 'AddPushbulletSubMenuItems';
	var $description = 'Add sub menu items for Pushbullet';
	var $author = 'Joe Schmitt';
	var $active = true;
	var $node = 'pushbullet';
	
	public function SubMenuData($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
		{
			if ($_REQUEST['node'] == 'pushbullet')
			{
				$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Pushbullet Tokens'));
				$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('Pushbullet Token'));
				
			}
		}
	}
}
$AddPushbulletSubMenuItems = new AddPushbulletSubMenuItems();
// Register Hooks
$HookManager->register('SUB_MENULINK_DATA', array($AddPushbulletSubMenuItems,'SubMenuData'));
