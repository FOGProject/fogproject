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
	if(!$Host->isValid())
		throw new Exception('#!ih');
	// Get the global values.
	$HostDisplay = current($FOGCore->getClass('HostScreenSettingsManager')->find(array('hostID' => $Host->get('id'))));
	// If hostdisplay is set, use those values, other wise, use the globally set values.
	$x = $HostDisplay ? $HostDisplay->get('width') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_X');
	$y = $HostDisplay ? $HostDisplay->get('height') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y');
	$r = $HostDisplay ? $HostDisplay->get('refresh') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANaGER_R');
	$string = $x.'x'.$y.'x'.$r;
	// Send it.
	print base64_encode($string);
}
catch(Exception $e)
{
	print $e->getMessage();
}
