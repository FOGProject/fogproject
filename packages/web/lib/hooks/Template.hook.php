<?php
/****************************************************
 * FOG Hook: Template
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision: 1732 $
 *	Last Update:	$LastChangedDate: 2014-05-24 15:37:43 -0400 (Sat, 24 May 2014) $
 ***/
// Hook Template
class Template extends Hook
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
// Hook Event
$HookManager->register('HOST_DATA', array(new Template(), 'HostData'));
