<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	if (!$_REQUEST['newService'] && $_REQUEST['version'] != 2)
		throw new Exception('#!er:Invalid Version Number, please update this module.');
	// The total number of pending macs that can be used.
	$maxPending = $FOGCore->getSetting('FOG_QUICKREG_MAX_PENDING_MACS');
	// The ignore list.  Comma Separated.
	$ignoreList = explode(',', $FOGCore->getSetting('FOG_QUICKREG_PENDING_MAC_FILTER'));
	// Get the actual host (if it is registered)
	$MACs = $FOGCore->getHostItem(true,false,false,true);
	$Host = $FOGCore->getHostItem(true,false,true,false,true);
	if (!($Host instanceof Host && $Host->isValid()) && $_REQUEST['newService'] && $_REQUEST['hostname'])
		$Host = current($FOGCore->getClass('HostManager')->find(array('name' => $_REQUEST['hostname'])));
	if($_REQUEST['newService'] && !($Host instanceof Host && $Host->isValid()))
	{
		if (!$_REQUEST['hostname'] || !HostManager::isHostnameSafe($_REQUEST['hostname']))
			throw new Exception('#!ih');
		$Host = new Host(array(
			'name' => $_REQUEST['hostname'],
			'description' => 'Pending Registration created by FOG_CLIENT',
			'pending' => 1,
		));
		foreach($FOGCore->getClass('ModuleManager')->find() AS $Module)
			$ModuleIDs[] = $Module->get('id');
		$PriMAC = ((preg_match('#|#i',$_REQUEST['mac']) ? explode('|',$_REQUEST['mac']) : $_REQUEST['mac']));
		$Host->addModule($ModuleIDs)
			 ->addPriMAC(is_array($PriMAC) ? $PriMAC[0] : $PriMAC)
			 ->save();
	}
	// Check if count is okay.
	if (count($MACs) > $maxPending + 1)
		throw new Exception('#!er:Too many MACs');
	// Cycle the MACs
	foreach($MACs AS $MAC)
		$AllMacs[] = strtolower($MAC);
	// Cycle the already known macs
	$KnownMacs = $Host->getMyMacs(false);
	if ($ignoreList)
	{
		foreach((array)$ignoreList AS $MAC)
			$MAC && $MAC->isValid() && !in_array(strtolower($MAC->get('mac')),(array)$KnownMacs) ? $KnownMacs[] = strtolower($MAC->get('mac')) : null;
	}
	$MACs = array_unique(array_diff((array)$AllMacs,(array)$KnownMacs));
	if (count($MACs))
	{
		$Host->addPendMAC($MACs);
		if ($Host->save())
			$Datatosend = "#!ok\n";
		else
			throw new Exception('#!er: Error adding MAC');
	}
	else
		$Datatosend = "#!ig\n";
	$FOGCore->sendData($Datatosend);
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
