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
	if (!$Host || !$Host->isValid() || $Host->get('pending'))
		throw new Exception('#!ih');
	if ($_REQUEST['newService'] && !$Host->get('pub_key'))
		throw new Exception('#!ihc');
	// Poll the manager to see if it's set per host.
	$HaloMan = current($FOGCore->getClass('HostAutoLogoutManager')->find(array('hostID' => $Host->get('id'))));
	// Set the time.  If host is set, use it, if not use global.
	$HaloMan ? $time = $HaloMan->get('time') : $time = $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
	// Send it.
	throw new Exception(($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] && $time >= 5 ? "#!ok\n#time=".($time * 60) : ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] && $time < 5 ? "#!time\n" : base64_encode($time))));
	if ($_REQUEST['newService'])
		print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
	else
		print $Datatosend;
}
catch(Exception $e)
{
	print $e->getMessage();
	exit;
}
