<?php
require('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host || !$Host->isValid())
		throw new Exception('#!ih');
	if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'])
	{
		$index = 0;
		foreach($FOGCore->getClass('UserCleanupManager')->find() AS $User)
		{
			$Datatosend .= ($index == 0 ? "#!ok\n" : '')."#user$index=".$User->get('name')."\n";
			$index++;
		}
	}
	else
	{
		$Datatosend = "#!start\n";
		foreach ($FOGCore->getClass('UserCleanupManager')->find() AS $User)
			$Datatosend .= base64_encode($User->get('name'))."\n";
		$Datatosend .= "#!end\n";
	}
}
catch (Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($Host && $Host->isValid() && $Host->get('pub_key') && $_REQUEST['newService'])
	print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
