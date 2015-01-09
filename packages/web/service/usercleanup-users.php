<?php
require_once('../commons/base.inc.php');
try
{
	if ($_REQUEST['newService'])
	{
		$HostManager = new HostManager();
		$MACs = HostManager::parseMacList($_REQUEST['mac']);
		if (!$MACs)
			throw new Exception('#!im');
		// Get the Host
		$Host = $HostManager->getHostByMacAddresses($MACs);
		if (!$Host || !$Host->isValid() || $Host->get('pending'))
			throw new Exception('#!ih');
		if ($_REQUEST['newService'] && !$Host->get('pub_key'))
			throw new Exception('#!ihc');
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
	if ($_REQUEST['newService'])
		print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
	else
		print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
