<?php
require_once('../commons/base.inc.php');
try
{
	// Get the global values.
	$HostDisplay = current($FOGCore->getClass('HostScreenSettingsManager')->find(array('hostID' => $FOGCore->getHostItem()->get('id'))));
	// If hostdisplay is set, use those values, other wise, use the globally set values.
	$x = $HostDisplay ? $HostDisplay->get('width') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_X');
	$y = $HostDisplay ? $HostDisplay->get('height') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y');
	$r = $HostDisplay ? $HostDisplay->get('refresh') : $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANaGER_R');
	$Datatosend = $_REQUEST['newService'] ? "#!ok\n#x=$x\n#y=$y\n#r=$r" : base64_encode($x.'x'.$y.'x'.$r);
	$FOGCore->sendData($Datatosend);
}
catch(Exception $e)
{
	print $e->getMessage();
	exit;
}
