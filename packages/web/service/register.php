<?php
require_once('../commons/base.inc.php');
try {
	$HostManager = new HostManager();
	if (!$_REQUEST['newService'] && $_REQUEST['version'] != 2)
		throw new Exception('#!er:Invalid Version Number, please update this module.');
	// The total number of pending macs that can be used.
	$maxPending = $FOGCore->getSetting('FOG_QUICKREG_MAX_PENDING_MACS');
	// Get the actual host (if it is registered)
	$MACs = $FOGCore->getHostItem(true,false,false,true);
	$Host = $FOGCore->getHostItem(true,false,true,false,true);
	if (!($Host instanceof Host && $Host->isValid()) && $_REQUEST['newService'] && $_REQUEST['hostname']) $Host = current($FOGCore->getClass('HostManager')->find(array('name' => $_REQUEST['hostname'])));
	if($_REQUEST['newService'] && !($Host instanceof Host && $Host->isValid())) {
		if (!$_REQUEST['hostname'] || !HostManager::isHostnameSafe($_REQUEST['hostname'])) throw new Exception('#!ih');
		foreach($FOGCore->getClass('ModuleManager')->find() AS $Module) $ModuleIDs[] = $Module->get('id');
		$MACs = explode('|',$_REQUEST['mac']);
		$PriMAC = array_shift($MACs);
		$Host = $this->getClass('Host')
			 ->set('name', $_REQUEST['hostname'])
			 ->set('description','Pending Registration created by FOG_CLIENT')
			 ->set('pending',1)
			 ->addModule($ModuleIDs)
			 ->addPriMAC($PriMAC)
			 ->save();
	}
	// Check if count is okay.
	if (count($MACs) > $maxPending + 1) throw new Exception('#!er:Too many MACs');
	// Cycle the MACs
	foreach($MACs AS $MAC) $AllMacs[] = strtolower($MAC);
	// Cycle the already known macs
	$KnownMacs = $Host->getMyMacs(false);
	$MACs = array_unique(array_diff((array)$AllMacs,(array)$KnownMacs));
	if (count($MACs)) {
		$Host->addPendMAC($MACs);
		if ($Host->save()) $Datatosend = "#!ok\n";
		else throw new Exception('#!er: Error adding MAC');
	} else $Datatosend = "#!ig\n";
	$FOGCore->sendData($Datatosend);
} catch (Exception $e) {
	print $e->getMessage();
	exit;
}
