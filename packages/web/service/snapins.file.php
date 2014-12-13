<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host || !$Host->isValid() || $Host->get('pending'))
		throw new Exception('#!ih');
	// Try and get the task.
	$Task = $Host->get('task');
	// Work on the current Snapin Task.
	$SnapinTask = new SnapinTask($_REQUEST['taskid']);
	if (!$SnapinTask->isValid()) throw new Exception('#!ns');
	//Get the snapin to work off of.
	$Snapin = new Snapin($SnapinTask->get('snapinID'));
	// Find the Storage Group
	if ($Snapin && $Snapin->getStorageGroup() && $Snapin->isValid() && $Snapin->getStorageGroup()->isValid())
		$StorageGroup = $Snapin->getStorageGroup();
	// Allow plugins to enact against this. (e.g. location)
	$HookManager->processEvent('SNAPIN_GROUP',array('Host' => &$Host,'StorageGroup' => &$StorageGroup));
	// Assign the file for sending.
	if (!$StorageGroup || !$StorageGroup->isValid())
	{
		if (file_exists(rtrim($FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.$Snapin->get('file')))
			$SnapinFile = rtrim($FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.$Snapin->get('file');
		elseif (file_exists($Snapin->get('file')))
			$SnapinFile = $Snapin->get('file');
	}
	else
	{
		$StorageNode = $StorageGroup->getMasterStorageNode();
		// Allow plugins to enact against this. (e.g. location)
		$HookManager->processEvent('SNAPIN_NODE',array('Host' => &$Host,'StorageNode' => &$StorageNode));
		if ($StorageNode && $StorageNode->isValid())
			$SnapinFile = "ftp://".$StorageNode->get('user').":".$StorageNode->get('pass')."@".$FOGCore->resolveHostname($StorageNode->get('ip')).'/'.ltrim(rtrim($StorageNode->get('snapinpath'),'/'),'/').'/'.$Snapin->get('file');
	}
	// If it exists and is readable send it!
	if (file_exists($SnapinFile) && is_readable($SnapinFile))
	{
		if (ob_get_level())
			ob_end_clean();
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Description: File Transfer");
		header("Content-Type: application/octet-stream");
		header("Content-Length: ".filesize($SnapinFile));
		header('Content-Disposition: attachment; filename='.basename($Snapin->get('file')));
		@readfile($SnapinFile);
		// if the Task is deployed then update the task.
		if ($Task && $Task->isValid()) $Task->set('stateID',3)->save();
		// Update the snapin task information.
		$SnapinTask->set('stateID',1)->set('return',-1)->set('details','Pending...')->save();
		print $file;
		exit;
	}
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
