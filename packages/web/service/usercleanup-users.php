<?php
require_once('../commons/base.inc.php');
try
{
	$Datatosend = "#!start\n";
	foreach ($FOGCore->getClass('UserCleanupManager')->find() AS $User)
		$Datatosend .= base64_encode($User->get('name'))."\n";
	$Datatosend .= "#!end";
	print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
