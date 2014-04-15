<?php
/****************************************************
 * FOG Hook: Example Change Hostname
 *	Author:		$Author: Blackout $
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/
// Example class
class TestHookChangeHostname extends Hook
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
// Example: Test by changing all hostnames in Host Management
// $HookManager->register('REPLACE_DATA', array(ClassNameCall), 'FunctionWithinClass')
$HookManager->register('HOST_DATA', array(new TestHookChangeHostname(), 'HostData'));
