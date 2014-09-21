<?php
require('../commons/base.inc.php');
try
{
	// Send the Dir's to the client.
	foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
		print base64_encode($Dir->get('path'))."\n";
}
catch (Exception $e)
{
	print $e->getMessage();
}
