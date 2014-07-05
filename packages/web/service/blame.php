<?php
require('../commons/base.inc.php');
function getAllBlamedNodes($taskid,$hostid)
{
	$NodeFailures = $GLOBALS['FOGCore']->getClass('NodeFailureManager')->find(array('taskID' => $taskid,'hostID' => $hostid));
	$TimeZone = new DateTimeZone((!ini_get('date.timezone') ? 'GMT' : ini_get('date.timezone')));
	$DateInterval = new DateTime('-5 minutes',$TimeZone);
	foreach($NodeFailures AS $NodeFailure)
	{
		$DateTime = new DateTime($NodeFailure->get('failureTime'),$TimeZone);
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
	// Get the MAC
	$MACAddress = new MACAddress($_REQUEST['mac']);
	if (!$MACAddress->isValid())
		throw new Exception(_('Invalid MAC address'));
	// Get the host
	$Host = $MACAddress->getHost();
	if (!$Host->isValid())
		throw new Exception(_('Invalid host'));
	//Get the task
	$Task = current($Host->get('task'));
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
				'failureTime' => date('Y-m-d H:i:s'),
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
