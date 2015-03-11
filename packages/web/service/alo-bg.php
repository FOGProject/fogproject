<?php
require_once('../commons/base.inc.php');
try
{
	if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'])
	{
		$HostManager = new HostManager();
		$MACs = FOGCore::parseMacList($_REQUEST['mac']);
		if (!$MACs)
			throw new Exception('#!im');
		// Get the Host
		$Host = $HostManager->getHostByMacAddresses($MACs);
		if (!$Host || !$Host->isValid() || $Host->get('pending'))
			throw new Exception('#!ih');
		if (!$Host->get('pub_key'))
			throw new Exception('#!ihc');
		$Datatosend = $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE');
	}
	else
		$Datatosend = base64_encode($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE'));
//	if ($_REQUEST['newService'])
//		print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
//	else
		print $Datatosend;
}
catch(Exception $e)
{
	print $e->getMessage();
	exit;
}
