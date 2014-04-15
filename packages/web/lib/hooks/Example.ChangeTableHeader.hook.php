<?php
/****************************************************
 * FOG Hook: Example Change Table Header
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

// Example class
class TestHookChangeTableHeader extends Hook
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
// Example: Change Table Header and Data
$HookManager->register('HOST_HEADER_DATA', array(new TestHookChangeTableHeader(), 'HostTableHeader'));
