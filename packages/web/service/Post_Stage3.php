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
		print '##';
	// If it's a multicast job, destroy the association and remove the client.
	if ($Task->get('typeID') == 8)
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
	}
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
}
catch (Exception $e)
{
	print $e->getMessage();
}
