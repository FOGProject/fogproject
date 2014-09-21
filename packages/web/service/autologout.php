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
	if (!$Host->isValid())
		throw new Exception('#!ih');
	// Poll the manager to see if it's set per host.
	$HaloMan = current($FOGCore->getClass('HostAutoLogoutManager')->find(array('hostID' => $Host->get('id'))));
	// Set the time.  If host is set, use it, if not use global.
	$HaloMan ? $time = $HaloMan->get('time') : $time = $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
	// Send it.
	throw new Exception(base64_encode($time));
}
catch(Exception $e)
{
	print $e->getMessage();
}
