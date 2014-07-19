<?php
require('../commons/base.inc.php');
try
{
	// Get the MAC
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception($foglang['InvalidMAC']);
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid()) throw new Exception(_('Invalid host'));
	// Task for Host
	$Task = current($Host->get('task'));
	if (!$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)',_('No Active Task found for Host'), $Host->get('name'),$MACAddress));
	$TaskType = new TaskType($Task->get('typeID'));
	// Set the task to state 4
	$Task->set('stateID',4);
	// Log it
	$ImagingLogs = $FOGCore->getClass('ImagingLogManager')->find(array('hostID' => $Host->get('id')));
	foreach($ImagingLogs AS $ImagingLog) $id[] = $ImagingLog->get('id');
	// Update Last deploy
	$Host->set('deployed',date('Y-m-d H:i:s'))->save();
	$il = new ImagingLog(max($id));
	$il->set('finish',date('Y-m-d H:i:s'))->save();
	// Task Logging.
	$TaskLog = new TaskLog($Task);
	$TaskLog->set('taskID',$Task->get('id'))->set('taskStateID',$Task->get('stateID'))->set('createdTime',$Task->get('createdTime'))->set('createdBy',$Task->get('createdBy'))->save();
	if (!$Task->save()) throw new Exception('Failed to update task.');
	print '##';
	// If it's a multicast job, decrement the client count, though not fully needed.
	if ($TaskType->isMulticast())
	{
		$MyMulticastTask = current($FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
		if ($MyMulticastTask && $MyMulticastTask->isValid())
		{
			$MulticastSession = new MulticastSessions($MyMulticastTask->get('msID'));
			$MulticastSession->set('clients',($MulticastSession->get('clients') - 1))->save();
		}
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
