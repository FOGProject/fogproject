<?php
class SubMenuData extends Hook
{
	var $name = 'SubMenuData';
	var $description = 'Example showing how to manipulate SubMenu Data. Adds Menu items under "Host Management"';
	var $author = 'Blackout';
	var $active = false;
	var $node = 'host';
	public function SubMenuData($arguments)
	{
		if ($_REQUEST['node'] == $this->node)
		{
			$arguments['submenu'][$this->node]['http://www.google.com'] = 'Fernando Gietz Can click here now.';
			if ($_REQUEST['id'])
				$arguments['submenu'][$this->node]['id']['http://www.google.com'] = 'He can also click here now to go to google.';
		}
	}

	public function SubMenuNotes($arguments)
	{
		if ($_REQUEST['node'] == $this->node)
		{
			if ($_REQUEST['id'])
			{
				$arguments['title']['Fernando Gietz'] = _('Is Awesome');
				$arguments['title']['Description Fern'] = $arguments['object']->get('description');
			}
		}
	}
}
$SubMenuData = new SubMenuData();
// Hook Event
$HookManager->register('SUB_MENULINK_DATA', array($SubMenuData, 'SubMenuData'));
$HookManager->register('SUB_MENULINK_NOTES', array($SubMenuData, 'SubMenuNotes'));
