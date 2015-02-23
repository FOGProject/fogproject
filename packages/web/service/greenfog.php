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
	$Datatosend = '';
	foreach($FOGCore->getClass('GreenFogManager')->find() AS $gf)
		$Datatosend .= base64_encode($gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action'));
	print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
