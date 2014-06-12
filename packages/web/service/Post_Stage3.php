<?php
require('../commons/base.inc.php');
try
{
	// Get the MAC
	$MACAddress = new MACAddress($_REQUEST['mac']);
	if (!$MACAddress->isValid())
		throw new Exception(_('Invalid MAC Address'));
	// Host for MAC Address
	$Host = $MACAddress->getHost();
	if (!$Host->isValid())
		throw new Exception(_('Invalid host'));
	// Task for Host
	$Task = current($Host->get('task'));
	if (!$Task->isValid())
		throw new Exception(sprintf('%s: %s (%s)',_('No Active Task found for Host'), $Host->get('name'),$MACAddress));
	// If it's a multicast job, destroy the association and remove the client.
	if ($Task->get('typeID') == 8)
	{
		// Get the session itself.
		$MS = $FOGCore->getClass('MulticastSessionsManager')->find(array('stateID' => array(0,1,2,3)));
		// Find the associated tasks based on JOB and TASK ID's
		foreach ($MS AS $MultiSession)
		{
			$MSA = current($FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $MultiSession->get('id'),'taskID' => $Task->get('id'))));
			if ($MSA && $MSA->isValid())
				break;
		}
		// Decrement the client.
		$MultiSession->set('clients',$MultiSession->get('clients')-1)->save();
		// If it's zero (or less)
		if($MultiSession->get('clients') <= 0)
		{
			// Set the tasks to complete.
			foreach($MS AS $MulticastTask)
			{
				$MSAs = $FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $MulticastTask->get('id')));
				foreach($MSAs AS $MSA)
					$FOGCore->getClass('Task',$MSA->get('taskID'))->set('stateID',4)->save();
			}
			// Delete all associations.
			$FOGCore->getClass('MulticastSessionsAssociationManager')->destroy(array('msID' => $MultiSession->get('id')));
			$ImagingLogs = $FOGCore->getClass('ImagingLogManager')->find(array('hostID' => $Host->get('id')));
			// Log it
			foreach($ImagingLogs AS $ImagingLog)
				$id[] = $ImagingLog->get('id');
			// Update Last deploy
			$Host->set('deployed',date('Y-m-d H:i:s'))->save();
			$il = new ImagingLog(max($id));
			$il->set('finish',date('Y-m-d H:i:s'))->save();
			// Task Logging.
			$TaskLog = new TaskLog($Task);
			$TaskLog->set('taskID',$Task->get('id'))
					->set('taskStateID',$Task->get('stateID'))
					->set('createdTime',$Task->get('createdTime'))
					->set('createdBy',$Task->get('createdBy'))
					->save();
			$MultiSession->set('stateID',4)->save();
		}
		print '##';
	}
	else
	{
		// Set the task as complete.
		if ($Task->get('stateID') < 4)
			$Task->set('stateID',4);
		$ImagingLogs = $FOGCore->getClass('ImagingLogManager')->find(array('hostID' => $Host->get('id')));
		// Log it
		foreach($ImagingLogs AS $ImagingLog)
			$id[] = $ImagingLog->get('id');
		// Update Last deploy
		$Host->set('deployed',date('Y-m-d H:i:s'))->save();
		$il = new ImagingLog(max($id));
		$il->set('finish',date('Y-m-d H:i:s'))->save();
		// Task Logging.
		$TaskLog = new TaskLog($Task);
		$TaskLog->set('taskID',$Task->get('id'))
				->set('taskStateID',$Task->get('stateID'))
				->set('createdTime',$Task->get('createdTime'))
				->set('createdBy',$Task->get('createdBy'))
				->save();
		if (!$Task->save())
			throw new Exception('Failed to update task.');
		print '##';
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
