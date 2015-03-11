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
	//if ($_REQUEST['newService'] && !$Host->get('pub_key'))
	//	throw new Exception('#!ihc');
	// Get the global values.
	$HostDisplay = current($FOGCore->getClass('HostScreenSettingsManager')->find(array('hostID' => $Host->get('id'))));
	// If hostdisplay is set, use those values, other wise, use the globally set values.
	$x = $HostDisplay ? $HostDisplay->get('width') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_X');
	$y = $HostDisplay ? $HostDisplay->get('height') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y');
	$r = $HostDisplay ? $HostDisplay->get('refresh') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANaGER_R');
	$Datatosend = $FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? "#!ok\n#x=$x\n#y=$y\n#r=$r" : base64_encode($x.'x'.$y.'x'.$r);
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
