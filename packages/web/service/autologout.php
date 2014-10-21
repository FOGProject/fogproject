<<<<<<< HEAD
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
=======
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
	// Poll the manager to see if it's set per host.
	$HaloMan = current($FOGCore->getClass('HostAutoLogoutManager')->find(array('hostID' => $Host->get('id'))));
	// Set the time.  If host is set, use it, if not use global.
	$HaloMan ? $time = $HaloMan->get('time') : $time = $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
	// Send it.
	throw new Exception(($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] && $time >= 5 ? "#!ok\n#time=".($time * 60) : ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] && $time < 5 ? "#!time\n" : base64_encode($time))));
}
catch(Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
>>>>>>> dev-branch
