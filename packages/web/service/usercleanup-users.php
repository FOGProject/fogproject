<?php
require('../commons/base.inc.php');
try
{
	print "#!start\n";
	foreach ($FOGCore->getClass('UserCleanupManager')->find() AS $User)
		print base64_encode($User->get('name'))."\n";
	print "#!end\n";
}
catch (Exception $e)
{
	print $e->getMessage();
}
