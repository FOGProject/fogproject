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
	$TaskType = new TaskType($Task->get('typeID'));
	// Set the task to state 4
	$Task->set('stateID',4);
	// If it's a multicast job, destroy the association and remove the client.
	if ($TaskType->isMulticast())
	{
		$MSA = current($FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
		if ($MSA && $MSA->isValid())
			$MulticastSession = new MulticastSessions($MSA->get('msID'));
		if ($MulticastSession && $MulticastSession->isValid())
		{
			$MulticastSession->set('clients',(int)$MulticastSession->get('clients')-1)->save();
			$MulticastSession = new MulticastSessions($MSA->get('msID'));
			if ($MulticastSession->get('clients') <= 0)
				$MulticastSession->set('stateID',4)->set('completetime',date('Y-m-d H:i:s'))->save();
		}
	}
	// Log it
	$ImagingLogs = $FOGCore->getClass('ImagingLogManager')->find(array('hostID' => $Host->get('id')));
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
catch (Exception $e)
{
	print $e->getMessage();
}
