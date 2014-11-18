<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if(!$Host || !$Host->isValid())
		throw new Exception('#!ih');
	$index = 0;
	foreach($FOGCore->getClass('GreenFogManager')->find() AS $gf)
	{
		$Datatosend .= ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#task$index=".$gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action') : base64_encode($gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action')))."\n";
		$index++;
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
