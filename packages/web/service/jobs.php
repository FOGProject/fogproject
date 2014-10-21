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
	if(!$Host || !$Host->isValid())
		throw new Exception('#!er:No Host Found');
	// Find out about tasks in queue.
	$Task = $Host->get('task');
	// If there is no task, or it's of snapin deploy type, don't reboot.
	if (!$Task->isValid() || ($Task->get('typeID') == 12 || $Task->get('typeID') == 13))
		throw new Exception('#!nj');
	else
		$Datatosend = "#!ok";
}
catch (Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
