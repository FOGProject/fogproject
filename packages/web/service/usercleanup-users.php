<?php
require_once('../commons/base.inc.php');
try
{
	if ($_REQUEST['newService'])
	{
		if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'])
		{
			$index = 0;
			foreach($FOGCore->getClass('UserCleanupManager')->find() AS $User)
			{
				$Datatosend .= ($index == 0 ? "#!ok\n" : '')."#user$index=".$User->get('name')."\n";
				$index++;
			}
		}
	}
	else
	{
		$Datatosend = "#!start\n";
		foreach ($FOGCore->getClass('UserCleanupManager')->find() AS $User)
			$Datatosend .= base64_encode($User->get('name'))."\n";
		$Datatosend .= "#!end";
	}
	$FOGCore->sendData($Datatosend);
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
