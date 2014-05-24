<?php
/****************************************************
 * FOG Hook: Example.SideMenuChange
 *	Author:		Blackout
 *	Created:	12:10 PM 4/09/2011
 *	Revision:	$Revision: 1438 $
 *	Last Update:	$LastChangedDate: 2014-04-08 21:08:05 -0400 (Tue, 08 Apr 2014) $
 ***/

// Hook Template
class SubMenuData extends Hook
{
	var $name = 'SubMenuData';
	var $description = 'Example showing how to manipulate SubMenu Data. Adds Menu items under "Host Management"';
	var $author = 'Blackout';
	var $active = false;
	public function SubMenuData($arguments)
	{
		$arguments['FOGSubMenu'] = new FOGSubMenu();
		if ($_REQUEST['node'] == 'host')
		{
			$arguments['FOGSubMenu']->addItems('host',array(_('New Hook Item') => 'http://www.google.com',_('New Hook Item 2')),'id');
			if ($_REQUEST['id'])
				$arguments['FOGSubMenu']->addNotes('host',array(_('New Hook Item') => 'http://www.google.com',_('New Hook Item 2')),'id');
		}
	}
}
$SubMenuData = new SubMenuData();
// Hook Event
$HookManager->register('SubMenuData', array(new SubMenuData(), 'SubMenuData'));
