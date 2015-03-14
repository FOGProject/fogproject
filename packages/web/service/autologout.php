<?php
require_once('../commons/base.inc.php');
try
{
	$Host = $FOGCore->getHostItem();
	// Poll the manager to see if it's set per host.
	$HaloMan = current($FOGCore->getClass('HostAutoLogoutManager')->find(array('hostID' => $Host->get('id'))));
	// Set the time.  If host is set, use it, if not use global.
	$HaloMan ? $time = $HaloMan->get('time') : $time = $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
	// Send it.
	$Datatosend = ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] && $time >= 5 ? "#!ok\n#time=".($time * 60) : ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] && $time < 5 ? "#!time\n" : base64_encode($time)));
	$FOGCore->sendData($Datatosend);
}
catch(Exception $e)
{
	print $e->getMessage();
	exit;
}
