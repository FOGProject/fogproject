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
	// Just send the image.  It will probably fail as it was originally written for XP!
	throw new Exception($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE') : base64_encode($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE')));
}
catch(Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($Host && $Host->isValid() && $Host->get('pub_key') && $_REQUEST['newService'])
	print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
else if ($_REQUEST['newService'] && $FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
