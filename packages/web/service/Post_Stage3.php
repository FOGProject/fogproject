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
	// Set the task as complete.
	if ($Task->get('stateID') < 4)
		$Task->set('stateID',4);
	// If it's not a multicast job, send it the ok marks.
	if ($Task->get('typeID') != 8)
	{
		if (!$Task->save())
			throw new Excaption(_('Failed to update task'));
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
		print '##';
	}
	// If it's a multicast job, destroy the association and remove the client.
	else
	{
		// Get this systems Multicast Association.
		$MSA = current($FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
		// Get the session itself.
		$MS = new MulticastSessions($MSA->get('msID'));
		// Remove the assoc
		$MSA->destroy();
		// Decrement the client.
		$MS->set('clients',$MS->get('clients')-1);
		// If it's zero (or less)
		if($MS->get('clients') <= 0)
			// Remove the session so ports can be reused.
			$MS->destroy();
		if (!$Task->save())
			throw new Exception(_('Failed to update task'));
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
		print '##';
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
