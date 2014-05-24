<?php
/****************************************************
 * FOG Hook: Example Change Hostname
 *	Author:		$Author: Blackout $
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision: 1438 $
 *	Last Update:	$LastChangedDate: 2014-04-08 21:08:05 -0400 (Tue, 08 Apr 2014) $
 ***/
// Example class
class ChangeHostname extends Hook
{
	var $name = 'ChangeHostname';
	var $description = 'Appends "Chicken-" to all hostnames ';
	var $author = 'Blackout';
	var $active = false;
	function HostData($arguments)
	{
		foreach ($arguments['data'] AS $i => $data)
			$arguments['data'][$i]['host_name'] = 'Chicken-' . $data['host_name'];
	}
}
// $HookManager->register('REPLACE_DATA', array(ClassNameCall), 'FunctionWithinClass')
$HookManager->register('HOST_DATA', array(new ChangeHostname(), 'HostData'));
