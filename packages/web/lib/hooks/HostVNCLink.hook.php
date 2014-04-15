<?php
/****************************************************
 * FOG Hook: HostVNCLink
 *	Author:		Blackout
 *	Created:	9:26 AM 3/09/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/
// HostVNCLink - custom hook class
class HostVNCLink extends Hook
{
	// Class variables
	var $name = 'HostVNCLink';
	var $description = 'Adds a "VNC" link to the Host Lists';
	var $author = 'Blackout';
	var $active = false;
	// Custom variable
	var $port = 5800;
	function HostData($arguments)
	{
		// Add column template into 'templates' array
		$arguments['templates'][8] = sprintf('<a href="http://%s:%d" target="_blank">VNC</a>', '${host_name}', $this->port);
		// Add these HTML attributes to that column
		$arguments['attributes'][8] = array('class' => 'c');
	}
	function HostTableHeader($arguments)
	{
		// Add new Header column with the content 'VNC'
		$arguments['headerData'][8] = 'VNC';
	}
}
// Init
$HostVNCLink = new HostVNCLink();
// Register hooks with HookManager on desired events
$HookManager->register('HOST_DATA', array(new HostVNCLink(), 'HostData'));
$HookManager->register('HOST_HEADER_DATA', array(new HostVNCLink(), 'HostTableHeader'));
