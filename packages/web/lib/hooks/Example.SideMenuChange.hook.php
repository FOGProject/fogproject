<?php
/****************************************************
 * FOG Hook: Example.SideMenuChange
 *	Author:		Blackout
 *	Created:	12:10 PM 4/09/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

// Hook Template
class HookSubMenuData extends Hook
{
	var $name = 'SubMenuData';
	var $description = 'Example showing how to manipulate SubMenu Data. Adds Menu items under "Host Management"';
	var $author = 'Blackout';
	var $active = false;
	function SubMenuData($arguments)
	{
		if ($_REQUEST['node'] == 'host')
		{
			$arguments['FOGSubMenu']->addItems('host',array(_('New Hook Item') => 'http://www.google.com',_('New Hook Item 2')),'id');
			if ($_REQUEST['id'])
				$arguments['FOGSubMenu']->addItems('host',array(_('New Hook Item') => 'http://www.google.com',_('New Hook Item 2')),'id');
		}
	}
}
// Hook Event
$HookManager->register('SubMenuData', array(new HookSubMenuData(), 'SubMenuData'));
