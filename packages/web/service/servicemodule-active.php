<?php
require('../commons/base.inc.php');
try
{
	if ($_REQUEST['newService'])
		throw new Exception("#!ok\n#sleep=".$FOGCore->getSetting('FOG_SERVICE_CHECKIN_TIME')."\n#force=".$FOGCore->getSetting('FOG_TASK_FORCE_REBOOT')."\n#maxsize=".$FOGCore->getSetting('FOG_CLIENT_MAXSIZE'));
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid())
		throw new Exception('#!er:No Host Found');
	// Get the true module ID for comparing what the host has.
	$moduleID = current($FOGCore->getClass('ModuleManager')->find(array('shortName' => $_REQUEST['moduleid'])));
	// get the module id
	if (empty($_REQUEST['moduleid']) || !$moduleID || !$moduleID->isValid())
	{
		if ($_REQUEST['moduleid'] == 'snapin')
			$_REQUEST['moduleid'] = 'snapinclient';
		else if ($_REQUEST['moduleid'] == 'snapinclient')
			$_REQUEST['moduleid'] = 'snapin';
		$moduleID = current($FOGCore->getClass('ModuleManager')->find(array('shortName' => $_REQUEST['moduleid'])));
		if (!$moduleID || !$moduleID->isValid())
			throw new Exception('#!um');
	}
	// Associate the moduleid param with the global name.
	$moduleName = array(
		'dircleanup' => $FOGCore->getSetting('FOG_SERVICE_DIRECTORYCLEANER_ENABLED'),
		'usercleanup' => $FOGCore->getSetting('FOG_SERVICE_USERCLEANUP_ENABLED'),
		'displaymanager' => $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_ENABLED'),
		'autologout' => $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_ENABLED'),
		'greenfog' => $FOGCore->getSetting('FOG_SERVICE_GREENFOG_ENABLED'),
		'hostnamechanger' => $FOGCore->getSetting('FOG_SERVICE_HOSTNAMECHANGER_ENABLED'),
		'snapin' => $FOGCore->getSetting('FOG_SERVICE_SNAPIN_ENABLED'),
		'snapinclient' => $FOGCore->getSetting('FOG_SERVICE_SNAPIN_ENABLED'),
		'clientupdater' => $FOGCore->getSetting('FOG_SERVICE_CLIENTUPDATER_ENABLED'),
		'hostregister' => $FOGCore->getSetting('FOG_SERVICE_HOSTREGISTER_ENABLED'),
		'printermanager' => $FOGCore->getSetting('FOG_SERVICE_PRINTERMANAGER_ENABLED'),
		'taskreboot' => $FOGCore->getSetting('FOG_SERVICE_TASKREBOOT_ENABLED'),
		'usertracker' => $FOGCore->getSetting('FOG_SERVICE_USERTRACKER_ENABLED'),
	);
	// If it's globally disabled, return that so the client doesn't keep trying it.
	if (!$moduleName[$_REQUEST['moduleid']])
		throw new Exception('#!ng');
	foreach((array)$Host->get('modules') AS $Module)
	{
		if ($Module && $Module->isValid())
			$activeIDs[] = $Module->get('id');
	}
	$Datatosend = (in_array($moduleID->get('id'),(array)$activeIDs) ? '#!ok' : '#!nh')."\n";
}
catch(Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!ok\n#en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print ($FOGCore->getSetting('FOG_NEW_CLIENT') ? "#!ok\n" : '').$Datatosend;
