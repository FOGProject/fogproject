<?php
/**
 *	Author:		Jbob
**/
class AddPushbulletSubMenuItems extends Hook
{
	var $name = 'AddPushbulletSubMenuItems';
	var $description = 'Add sub menu items for Pushbullet';
	var $author = 'Joe Schmitt';
	var $active = true;
	var $node = 'pushbullet';
	
	public function SubMenuData($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'pushbullet')
			{
				$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Pushbullet Accounts'));
				$arguments['submenu'][$this->node]['add'] = 'Link Pushbullet Account';
				
			}
		}
	}
}
$AddPushbulletSubMenuItems = new AddPushbulletSubMenuItems();
// Register Hooks
$HookManager->register('SUB_MENULINK_DATA', array($AddPushbulletSubMenuItems,'SubMenuData'));
