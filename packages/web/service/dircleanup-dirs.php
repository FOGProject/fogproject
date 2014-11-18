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
	$index = 0;
	foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
	{
		$Datatosend .= ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#dir$index=".base64_encode($Dir->get('path'))."\n" : base64_encode($Dir->get('path')))."\n";
		$index++;
	}
}
catch (Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($Host && $Host->isValid() && $Host->get('pub_key') && $_REQUEST['newService'])
	print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
else if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
