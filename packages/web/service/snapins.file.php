<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid()) throw new Exception('#!ih');
	// Try and get the task.
	$Task = current($Host->get('task'));
	// Work on the current Snapin Task.
	$SnapinTask = new SnapinTask($_REQUEST['taskid']);
	if (!$SnapinTask->isValid()) throw new Exception('#!er: Something went wrong with getting the snapin.');
	//Get the snapin to work off of.
	$Snapin = new Snapin($SnapinTask->get('snapinID'));
	// Assign the file for sending.
	if (file_exists(rtrim($FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.$Snapin->get('file')))
		$SnapinFile = rtrim($FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.$Snapin->get('file');
	elseif (file_exists($Snapin->get('file')))
		$SnapinFile = $Snapin->get('file');
	// If it exists and is readable send it!
	if (file_exists($SnapinFile) && is_readable($SnapinFile))
	{
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Description: File Transfer");
		header("Content-Type: application/octet-stream");
		header("Content-Length: ".filesize($SnapinFile));
		header("Content-Disposition: attachment; filename=".basename($Snapin->get('file')));
		@readfile($SnapinFile);
		// if the Task is deployed then update the task.
		if ($Task && $Task->isValid()) $Task->set('stateID',3)->save();
		// Update the snapin task information.
		$SnapinTask->set('stateID',1)->set('return',-1)->set('details','Pending...');
		// Save and return!
		if ($SnapinTask->save()) print "#!ok";
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
