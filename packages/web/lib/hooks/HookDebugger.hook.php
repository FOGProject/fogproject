<?php
/****************************************************
 * FOG Hook: HookDebugger
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision: 2438 $
 *	Last Update:	$LastChangedDate: 2014-10-19 10:09:51 -0400 (Sun, 19 Oct 2014) $
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
	public function run($arguments)
	{
		$this->log(print_r($arguments['event'],1));
	}
}
$HookDebugger = new HookDebugger();
if (!$HookManager->events)
	$HookManager->getEvents();
foreach($HookManager->events AS $event)
	$HookManager->register($event,array($HookDebugger,'run'));
