<?php
class AddMenuNotesItems extends Hook
{
	var $name = 'AddMenuNotesItems';
	var $description = 'Add menu items to the management page.';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'accesscontrol';
	public function AddMenuData($arguments)
	{
<<<<<<< HEAD
		$arguments['main'] = $this->array_insert_after('users',$arguments['main'],$this->node,_('Access Control'));
	}
	public function AddSubMenuData($arguments)
	{
		$arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
		$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Controls'));
		$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('Control'));
=======
		$plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
			$arguments['main'] = $this->array_insert_after('users',$arguments['main'],$this->node,_('Access Control'));
	}
	public function AddSubMenuData($arguments)
	{
		$plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			$arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
			$arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('Controls'));
			$arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('Control'));
		}
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
	}
}
$AddMenuNotesItems = new AddMenuNotesItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddMenuNotesItems, 'AddMenuData'));
$HookManager->register('SUB_MENULINK_DATA', array($AddMenuNotesItems, 'AddSubMenuData'));
