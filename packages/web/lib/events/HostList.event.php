<?php

/****************************************************
 * Host List Exame
 *	Author:		Jbob
 ***/
class HostList extends Event {
	// Class variables
	var $name = 'HostListEvent';
	var $description = 'Triggers when the hosts are listed';
	var $author = 'Jbob';
	var $active = false;
}

// Register hooks with HookManager on desired events
$EventManager->register('HOST_LIST_EVENT', new HostList());
