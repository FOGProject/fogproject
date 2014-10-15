<?php
class AddLocationSubMenuItems extends Hook
{
	var $name = 'AddLocationSubMenuItems';
	var $description = 'Add sub menu items for location';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'location';
	public function SubMenuData($arguments)
	{
		$plugin = current($this->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1, 'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			$arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
			$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],$this->foglang['Locations']);
			$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],$this->foglang['Location']);
			if ($_REQUEST['id'])
			{
				$arguments['id'] = 'id';
				$arguments['submenu'][$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=edit&id='.$_REQUEST['id']] = $this->foglang['General'];
				$arguments['submenu'][$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=delete&id='.$_REQUEST['id']] = $this->foglang['Delete'];
			}
		}
	}
	public function SubMenuNotes($arguments)
	{
		$plugin = current($this->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1, 'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			if ($_REQUEST['id'])
			{
				$arguments['name'] = sprintf($this->foglang['SelMenu'],$this->foglang['Location']);
				$arguments['object'] = new Location($_REQUEST['id']);
				$arguments['title'] = array(
					$this->foglang['Location'] => $arguments['object']->get('name'),
					$this->foglang['Storage'].' '.$this->foglang['Group'] => $this->getClass('StorageGroup',$arguments['object']->get('storageGroupID'))->get('name'),
				);
			}
		}
	}
}
$AddLocationSubMenuItems = new AddLocationSubMenuItems();
// Register Hooks
$HookManager->register('SUB_MENULINK_NOTES', array($AddLocationSubMenuItems,'SubMenuNotes'));
$HookManager->register('SUB_MENULINK_DATA', array($AddLocationSubMenuItems,'SubMenuData'));
