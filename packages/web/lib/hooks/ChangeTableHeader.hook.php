<?php
/****************************************************
 * FOG Hook: Example Change Table Header
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision: 1438 $
 *	Last Update:	$LastChangedDate: 2014-04-08 21:08:05 -0400 (Tue, 08 Apr 2014) $
 ***/

// Example class
class ChangeTableHeader extends Hook
{
	var $name = 'ChangeTableHeader';
	var $description = 'Remove & add table header columns';
	var $author = 'Blackout';
	var $active = false;
	function HostTableHeader($arguments)
	{
		// Rename column 'Host Name' -> 'Chicken Sandwiches'
		$arguments['headerData'][3] = 'Chicken Sandwiches';
	}
}
$ChangeTableHeader = new ChangeTableHeader();
// Example: Change Table Header and Data
if ($ChangeTableHeader->active)
	$HookManager->register('HOST_HEADER_DATA', array($ChangeTableHeader, 'HostTableHeader'));
