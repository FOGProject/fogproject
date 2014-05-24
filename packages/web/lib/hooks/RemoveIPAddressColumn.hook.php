<?php
/****************************************************
 * FOG Hook: Remove 'IP Address' column
 *	Author:		Blackout
 *	Created:	1:52 PM 3/09/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

// RemoveIPAddressColumn class
class RemoveIPAddressColumn extends Hook
{
	var $name = 'RemoveIPAddressColumn';
	var $description = 'Removes the "IP Address" column from Host Lists';
	var $author = 'Blackout';
	var $active = false;
	function HostTableHeader($arguments)
	{
		// Remove IP Address column by removing its column template
		unset($arguments['headerData'][4]);
	}
	function HostData($arguments)
	{
		// Remove IP Address column by removing its column template
		unset($arguments['templates'][4]);
	}
}
// Register hooks
$HookManager->register('HOST_HEADER_DATA', array(new RemoveIPAddressColumn(), 'HostTableHeader'));
$HookManager->register('HOST_DATA', array(new RemoveIPAddressColumn(), 'HostData'));
