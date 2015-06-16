<?php
require_once('../commons/base.inc.php');
try
{
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
	$moduleName = $FOGCore->getClass('HostManager')->getGlobalModuleStatus();
	// If it's globally disabled, return that so the client doesn't keep trying it.
	if (!$moduleName[$moduleID->get('shortName')])
		throw new Exception('#!ng');
	$Host = $FOGCore->getHostItem();
	foreach((array)$Host->get('modules') AS $Module) {
		if ($Module && $Module->isValid()) $activeIDs[] = $Module->get('id');
	}
	$Datatosend = (in_array($moduleID->get('id'),(array)$activeIDs) ? '#!ok' : '#!nh')."\n";
	if (!in_array($_REQUEST['moduleid'],array('autologout','displaymanager'))) $FOGCore->sendData($Datatosend);
	else print $Datatosend;
}
catch(Exception $e) {
	print $e->getMessage();
	exit;
}
