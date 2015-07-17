<?php
/****************************************************
 * FOG Hook: Remove 'IP Address' column
 *	Author:		Blackout
 *	Created:	1:52 PM 3/09/2011
 *	Revision:	$Revision: 3563 $
 *	Last Update:	$LastChangedDate: 2015-06-16 12:02:44 -0400 (Tue, 16 Jun 2015) $
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
