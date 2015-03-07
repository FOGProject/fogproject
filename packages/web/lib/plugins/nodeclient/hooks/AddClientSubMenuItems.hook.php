<?php
class AddClientSubMenuItems extends Hook
{
	var $name = 'AddClientSubMenuItems';
	var $description = 'Add sub menu items for node client service';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'nodeclient';
	public function SubMenuData($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
		{
			if ($_REQUEST['node'] == 'nodeclient')
			{
				$arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
				$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Node Servers'));
				$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('Node Server'));
				if ($_REQUEST['id'])
				{
					$NodeConf = new NodeJS($_REQUEST['id']);
					$arguments['id'] = 'id';
					$arguments['submenu'][$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=delete&id='.$_REQUEST['id']] = $this->foglang['Delete'];
				}
			}
		}
	}
}
$AddClientSubMenuItems = new AddClientSubMenuItems();
// Register Hooks
$HookManager->register('SUB_MENULINK_DATA', array($AddClientSubMenuItems,'SubMenuData'));
