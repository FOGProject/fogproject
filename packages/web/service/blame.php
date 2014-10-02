<?php
require('../commons/base.inc.php');
function getAllBlamedNodes($taskid,$hostid)
{
	global $FOGCore;
	$NodeFailures = $FOGCore->getClass('NodeFailureManager')->find(array('taskID' => $taskid,'hostID' => $hostid));
	$DateInterval = $FOGCore->nice_date()->modify('-5 minutes');
	foreach($NodeFailures AS $NodeFailure)
	{
		$DateTime = $FOGCore->nice_date($NodeFailure->get('failureTime'));
		if ($DateTime->format('Y-m-d H:i:s') >= $DateInterval->format('Y-m-d H:i:s'))
		{
			$node = $NodeFailure->get('id');
			if (!in_array($node,(array)$nodeRet))
				$nodeRet[] = $node;
		}
		else
			$NodeFailure->destroy();
	}
	return $nodeRet;
}
try
{
	// Error checking
	//MAC Address
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception($foglang['InvalidMAC']);
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid())
		throw new Exception(_('Invalid host'));
	//Get the task
	$Task = $Host->get('task');
	if (!$Task->isValid())
		throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'),$MACAddress));
	$imagingTasks = in_array($Task->get('typeID'),array(1,2,8,15,16,17));
	// Get the Storage Group
	$StorageGroup = $Task->getStorageGroup();
	if ($imagingTasks && !$StorageGroup->isValid())
		throw new Exception(_('Invalid Storage Group'));
	// Get the node.
	$StorageNodes = $StorageGroup->getStorageNodes();
	if ($imagingTasks && !$StorageNodes)
		throw new Exception(_('Could not find a Storage Node. Is there one enabled within this Storage Group?'));
	// Cycle through the nodes
	foreach ($StorageNodes AS $StorageNode)
	{
		// Get the nodes in blame.
		$blamed = getAllBlamedNodes($Task->get('id'),$Host->get('id'));
		if ($Task->get('NFSMemberID') && !in_array($Task->get('NFSMemberID'),(array)$blamed))
		{
			//Store the failure
			$NodeFailure = new NodeFailure(array(
				'storageNodeID' => $Task->get('NFSMemberID'),
				'taskID' => $Task->get('id'),
				'hostID' => $Host->get('id'),
				'groupID' => $Task->get('NFSGroupID'),
				'failureTime' => $FOGCore->nice_date()->format('Y-m-d H:i:s'),
			));
			if ($NodeFailure->save())
				$Task->set('stateID','1');
		}
		else
			$Task->set('stateID', '1');
	}
	if ($Task->save())
		print '##';
}
catch (Exception $e)
{
	print $e->getMessage();
}
