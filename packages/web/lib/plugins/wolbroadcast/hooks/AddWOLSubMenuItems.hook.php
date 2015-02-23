<?php
class AddWOLSubMenuItems extends Hook
{
	var $name = 'AddWOLSubMenuItems';
	var $description = 'Add sub menu items for WOL Broadcast';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'wolbroadcast';
	public function SubMenuData($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'wolbroadcast')
			{
				$arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
				$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Broadcast Addresses'));
				$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('Broadcast Address'));
				if ($_REQUEST['id'])
				{
					$WOLBroadcast = new Wolbroadcast($_REQUEST['id']);
					$arguments['id'] = 'id';
					$arguments['submenu'][$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=delete&id='.$_REQUEST['id']] = $this->foglang['Delete'];
				}
			}
		}
	}
	public function SubMenuNotes($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'wolbroadcast' && $_REQUEST['id'])
			{
				$arguments['name'] = sprintf($this->foglang['SelMenu'],$this->foglang['Home']);
				$arguments['object'] = new Wolbroadcast($_REQUEST['id']);
				$arguments['title'] = array(
					_('Broadcast Name') => $arguments['object']->get('name'),
					_('IP Address') => $arguments['object']->get('broadcast'),
				);
			}
		}
	}
}
$AddWOLSubMenuItems = new AddWOLSubMenuItems();
// Register Hooks
$HookManager->register('SUB_MENULINK_NOTES', array($AddWOLSubMenuItems,'SubMenuNotes'));
$HookManager->register('SUB_MENULINK_DATA', array($AddWOLSubMenuItems,'SubMenuData'));
