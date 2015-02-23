<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = FOGCore::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host || !$Host->isValid() || $Host->get('pending'))
		throw new Exception('#!ih');
	if ($_REQUEST['newService'] && !$Host->get('pub_key'))
		throw new Exception('#!ihc');
	$index = 0;
	foreach($FOGCore->getClass('GreenFogManager')->find() AS $gf)
	{
		$Datatosend .= ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#task$index=".$gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action') : base64_encode($gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action')))."\n";
		$index++;
	}
	if ($_REQUEST['newService'])
		print "#!enkey=".$FOGCore->certEncrypt($Datasend,$Host);
	else
		print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
