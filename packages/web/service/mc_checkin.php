<?php
require('../commons/base.inc.php');
try
{
	// Get the MAC
	$MACAddress = new MACAddress($_REQUEST['mac']);
	if (!$MACAddress->isValid())
		throw new Exception(_('#!im'));
	// Get the host
	$Host = $MACAddress->getHost();
	if (!$Host->isValid())
		throw new Exception(_('#!ih'));
	// Get the task
	$Task = current($Host->get('task'));
	if (!$Task->isValid())
		throw new Exception( sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'), $MACAddress) );
	// Get the current Multicast Session
	$MultiSess = new MulticastSessions(current($GLOBALS['FOGCore']->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))))->get('msID'));
	if ($Task->get('stateID') == 1)
	{
		// Check In Task for Host
		$Task->set('stateID',2)->set('checkInTime',date('Y-m-d H:i:s'))->save();
		// If the state is queued, meaning the client has checked in increment clients
		$MultiSess->set('clients', $MultiSess->get('clients')+1);
	}
	// Get the count of total associations.
	$MSAs = count($GLOBALS['FOGCore']->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $MultiSess->get('id'))));
	// This should not matter, but just in case.
	if ($MSAs)
	{
		// Set the task state for this host as in-progress.
		$Task->set('stateID',3);
		// If client count is equal, place session task in-progress as it will likely start soon.
		if ($MSAs == $MultiSess->get('clients'))
			$MultiSess->set('stateID',3);
		else
			$MultiSess->set('stateID',1);
		// Save the info.
		if ($Task->save() && $MultiSess->save())
		{
			$il = new ImagingLog(array(
				'hostID' => $Host->get('id'),
				'start' => date('Y-m-d H:i:s'),
				'image' => $Host->getImage()->get('name'),
				'type' => $_REQUEST['type'],
			));
			$il->save();
			$TaskLog = new TaskLog(array(
				'taskID' => $Task->get('id'),
				'taskStateID' => $Task->get('stateID'),
				'createTime' => $Task->get('createTime'),
				'createdBy' => $Task->get('createdBy'),
			));
			$TaskLog->save();
			print '##@GO';
		}
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
