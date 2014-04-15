<?php
/****************************************************
 * FOG Hook: Template
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/
// Hook Template
class HookTemplate extends Hook
{
	var $name = 'Hook Name';
	var $description = 'Hook Description';
	var $author = 'Hook Author';
	var $active = false;
	function HostData($arguments)
	{
		$this->log(print_r($arguments, 1));
	}
}
// Init class
$HookTemplate = new HookTemplate();
// Hook Event
$HookManager->register('HOST_DATA', array(new HookTemplate(), 'HostData'));
