<?php
require('../commons/base.inc.php');
try
{
	// Error checking
	// NOTE: Most of these validity checks should never fail as checks are made during Task creation - better safe than sorry!
	// MAC Address
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception($foglang['InvalidMAC']);
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid())
		throw new Exception( _('Invalid Host') );
	// Get the task
	$Task = current($Host->get('task'));
	if (!$Task->isValid())
		throw new Exception( sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'), $MACAddress) );
	// Get the current Multicast Session
	$MulticastAssociation = current($FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
	$MultiSess = new MulticastSessions($MulticastAssociation->get('msID'));
	if ($Task->get('stateID') == 1)
	{
		// Check In Task for Host
		$Task->set('stateID',2)->set('checkInTime',date('Y-m-d H:i:s'))->save();
		// If the state is queued, meaning the client has checked in increment clients
		$MultiSess->set('clients', $MultiSess->get('clients')+1)->save();
	}
	// Get the count of total associations.
	$MSAs = $FOGCore->getClass('MulticastSessionsAssociationManager')->count(array('msID' => $MultiSess->get('id')));
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
catch (Exception $e)
{
	print $e->getMessage();
}
