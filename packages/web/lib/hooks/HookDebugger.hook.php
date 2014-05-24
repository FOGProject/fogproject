<?php
/****************************************************
 * FOG Hook: HookDebugger
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

// HookDebugger class
class HookDebugger extends Hook
{
	var $name = 'HookDebugger';
	var $description = 'Prints all Hook data to the web page and/or file when a hook is encountered';
	var $author = 'Blackout';
	var $active = false;
	var $logLevel = 9;
	var $logToFile = false;		// Logs to: lib/hooks/HookDebugger.log
	var $logToBrowser = true;

	function run($arguments)
	{
		$this->log(print_r($arguments, 1));
	}
}
$HookDebugger = new HookDebugger();
// Debug all events
foreach ($HookManager->events AS $event)
	$HookManager->register($event, array($HookDebugger, 'run'));
