<?php
require_once('../commons/base.inc.php');
try
{
	$index = 0;
	foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
	{
		$Datatosend .= ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#dir$index=".base64_encode($Dir->get('path'))."\n" : base64_encode($Dir->get('path')))."\n";
		$index++;
	}
	$FOGCore->sendData($Datatosend);
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
