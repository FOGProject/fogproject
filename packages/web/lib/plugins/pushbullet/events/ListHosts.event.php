<?php

/****************************************************
 * Host List Exame
 *	Author:		Jbob
 ***/
class ListHosts extends Event {
	// Class variables
	var $name = 'ListHosts';
	var $description = 'Triggers when the hosts are listed';
	var $author = 'Jbob';
	var $active = true;
}
// Register hooks with EventManager on desired events
$EventManager->register('HOST_LIST_EVENT', new ListHosts());
