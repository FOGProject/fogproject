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
	if ((!$Host && !$Host->isValid()) || ($Host->get('pending') && $_REQUEST['moduleid'] != 'hostregister'))
		throw new Exception('#!ih');
	if ($_REQUEST['newService'] && !$Host->get('pub_key'))
		throw new Exception('#!ihc');
	// Get the true module ID for comparing what the host has.
	$moduleID = current($FOGCore->getClass('ModuleManager')->find(array('shortName' => $_REQUEST['moduleid'])));
	// get the module id
	if (!$moduleID || !$moduleID->isValid())
	{
		if ($_REQUEST['moduleid'] == 'dircleaner' || $_REQUEST['moduleid'] == 'dircleanup')
			$_REQUEST['moduleid'] = array('dircleaner','dircleanup');
		if ($_REQUEST['moduleid'] == 'snapin' || $_REQUEST['moduleid'] == 'snapinclient')
			$_REQUEST['moduleid'] = array('snapin','snapinclient');
		$moduleID = current($FOGCore->getClass('ModuleManager')->find(array('shortName' => $_REQUEST['moduleid']),'OR'));
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
	if (!$moduleName[$moduleID->get('shortName')])
		throw new Exception('#!ng');
	$ModOns = $FOGCore->getClass('ModuleAssociationManager')->find(array('hostID' => $Host->get('id')),'','','','','','','moduleID');
	$Datatosend = (in_array($moduleID->get('id'),(array)$ModOns) ? '#!ok' : '#!nh')."\n";
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
