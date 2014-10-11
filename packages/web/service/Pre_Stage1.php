<<<<<<< HEAD
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
	$Host->getImage()->set('size','0')->save();
	// Task for Host
	$Tasks = $Host->get('task');
	foreach($Tasks AS $Task)
	{
		if ($Task->isValid() && !in_array($Task->get('typeID'),array(4,12,13)))
			break;
	}
	if (!$Task->isValid())
		throw new Exception( sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'), $MACAddress) );
	// Check-in Host
	if ($Task->get('stateID') == 1)
		$Task->set('stateID', '2')->set('checkInTime', time())->save();
	$imagingTasks = in_array($Task->get('typeID'),array(1,2,8,15,16,17));
	// Storage Group
	$StorageGroup = $Task->getStorageGroup();
	if ($imagingTasks && !$StorageGroup->isValid())
		throw new Exception( _('Invalid StorageGroup') );
	// Storage Node
	$StorageNodes = $StorageGroup->getStorageNodes();
	if ($imagingTasks && !$StorageNodes)
		throw new Exception( _('Could not find a Storage Node. Is there one enabled within this Storage Group?') );
	// Forced to start
	if ($Task->get('isForced'))
	{
		$winner = $StorageGroup->getOptimalStorageNode();
		if (!in_array($Task->get('typeID'),array(12,13)))
		{
			if (!$Task->set('stateID', '3' )->save())
				throw new Exception(_('Forced Task: Failed to update Task'));
		}
	}
	// Queue checks
	$totalSlots = $StorageGroup->getTotalSupportedClients();
	$usedSlots = $StorageGroup->getUsedSlotCount();
	$inFrontOfMe = $Task->getInFrontOfHostCount();
	$groupOpenSlots = $totalSlots - $usedSlots;
	// Fail if all Slots are used
	if ($imagingTasks && !$Task->get('isForced'))
	{
	    if ($usedSlots >= $totalSlots)
		    throw new Exception(sprintf('%s, %s %s', _('Waiting for a slot'), $inFrontOfMe, _('PCs are in front of me.')));
	    // At this point we know there are open slots, but are we next in line for that slot (or has the next is line timed out?)
	    if ($groupOpenSlots <= $inFrontOfMe)
		    throw new Exception(sprintf('%s %s %s', _('There are open slots, but I am waiting for'), $inFrontOfMe, _('PCs in front of me.')));
		// Determine the best Storage Node to use - based off amount of clients connected
		$messageArray = array();
		$winner = null;
		foreach($StorageNodes AS $StorageNode)
		{
			$nodeAvailableSlots = $StorageNode->get('maxClients') - $StorageNode->getUsedSlotCount();
		    if ($StorageNode->get('maxClients') > 0 && $nodeAvailableSlots > 0)
			{
		        if ($winner == null)
		            $winner = $StorageNode;
		        else if ( $StorageNode->getClientLoad() < $winner->getClientLoad() )
				{
		        	if ($StorageNode->getNodeFailure($Host) === null)
   	                	$winner = $StorageNode;
			    }
			    else
				    $messageArray[] = sprintf("%s '%s' (%s) %s", _('Storage Node'), $StorageNode->get('name'), $StorageNode->get('ip'), _('is open, but has recently failed for this Host'));
			}
		}
	}
	// Failed to find a Storage Node - this should only occur if all Storage Nodes in this Storage Group have failed
	if (!isset($winner) || !$winner->isValid())
	{
		// Print failed node messages if we are unable to find a valid node
		if (count($messageArray))
			print implode(PHP_EOL, $messageArray) . PHP_EOL;
		throw new Exception(_("Unable to find a suitable Storage Node for transfer!"));
	}
	$Task->set('NFSMemberID', $winner->get('id'));
	// All tests passed! Almost there!
	$Task->set('stateID', '3');
	// Update Task State ID -> Update Storage Node ID -> Save
	if (!$Task->save())
		throw new Exception(_('Failed to update Task'));
	// Success!
	$il = new ImagingLog(array(
		'hostID' => $Host->get('id'),
		'start' => date('Y-m-d H:i:s'),
		'image' => $Host->getImage()->get('name'),
		'type' => $_REQUEST['type'],
	));
	$il->save();
	// Task Logging.
	$TaskLog = new TaskLog(array(
		'taskID' => $Task->get('id'),
		'taskStateID' => $Task->get('stateID'),
		'createTime' => $Task->get('createTime'),
		'createdBy' => $Task->get('createdBy'),
	));
	$TaskLog->save();
	print '##@GO';
}
catch (Exception $e)
{
	print $e->getMessage();
}
=======
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
	$Host->getImage()->set('size','0')->save();
	// Task for Host
	$Task = $Host->get('task');
	if (!$Task->isValid())
		throw new Exception( sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'), $MACAddress) );
	// Check-in Host
	if ($Task->get('stateID') == 1)
		$Task->set('stateID', '2')->set('checkInTime', $FOGCore->nice_date()->format('Y-m-d H:i:s'))->save();
	$imagingTasks = in_array($Task->get('typeID'),array(1,2,8,15,16,17));
	// Storage Group
	$StorageGroup = $Task->getStorageGroup();
	if ($imagingTasks && !$StorageGroup->isValid())
		throw new Exception( _('Invalid StorageGroup') );
	// Storage Node
	$StorageNodes = $StorageGroup->getStorageNodes();
	if ($imagingTasks && !$StorageNodes)
		throw new Exception( _('Could not find a Storage Node. Is there one enabled within this Storage Group?') );
	// Forced to start
	if ($Task->get('isForced'))
	{
		$winner = $StorageGroup->getOptimalStorageNode();
		if (!in_array($Task->get('typeID'),array(12,13)))
		{
			if (!$Task->set('stateID', '3' )->save())
				throw new Exception(_('Forced Task: Failed to update Task'));
		}
	}
	// Queue checks
	$totalSlots = $StorageGroup->getTotalSupportedClients();
	$usedSlots = $StorageGroup->getUsedSlotCount();
	$inFrontOfMe = $Task->getInFrontOfHostCount();
	$groupOpenSlots = $totalSlots - $usedSlots;
	// Fail if all Slots are used
	if ($imagingTasks && !$Task->get('isForced'))
	{
	    if ($usedSlots >= $totalSlots)
		    throw new Exception(sprintf('%s, %s %s', _('Waiting for a slot'), $inFrontOfMe, _('PCs are in front of me.')));
	    // At this point we know there are open slots, but are we next in line for that slot (or has the next is line timed out?)
	    if ($groupOpenSlots <= $inFrontOfMe)
		    throw new Exception(sprintf('%s %s %s', _('There are open slots, but I am waiting for'), $inFrontOfMe, _('PCs in front of me.')));
		// Determine the best Storage Node to use - based off amount of clients connected
		$messageArray = array();
		$winner = null;
		foreach($StorageNodes AS $StorageNode)
		{
			$nodeAvailableSlots = $StorageNode->get('maxClients') - $StorageNode->getUsedSlotCount();
		    if ($StorageNode->get('maxClients') > 0 && $nodeAvailableSlots > 0)
			{
		        if ($winner == null)
		            $winner = $StorageNode;
		        else if ( $StorageNode->getClientLoad() < $winner->getClientLoad() )
				{
		        	if ($StorageNode->getNodeFailure($Host) === null)
   	                	$winner = $StorageNode;
			    }
			    else
				    $messageArray[] = sprintf("%s '%s' (%s) %s", _('Storage Node'), $StorageNode->get('name'), $StorageNode->get('ip'), _('is open, but has recently failed for this Host'));
			}
		}
	}
	// Failed to find a Storage Node - this should only occur if all Storage Nodes in this Storage Group have failed
	if (!isset($winner) || !$winner->isValid())
	{
		// Print failed node messages if we are unable to find a valid node
		if (count($messageArray))
			print implode(PHP_EOL, $messageArray) . PHP_EOL;
		throw new Exception(_("Unable to find a suitable Storage Node for transfer!"));
	}
	$Task->set('NFSMemberID', $winner->get('id'));
	// All tests passed! Almost there!
	$Task->set('stateID', '3');
	// Update Task State ID -> Update Storage Node ID -> Save
	if (!$Task->save())
		throw new Exception(_('Failed to update Task'));
	if ($imagingTasks)
	{
		// Success!
		$il = new ImagingLog(array(
			'hostID' => $Host->get('id'),
			'start' => $FOGCore->nice_date()->format('Y-m-d H:i:s'),
			'image' => $Host->getImage()->get('name'),
			'type' => $_REQUEST['type'],
		));
		$il->save();
	}
	// Task Logging.
	$TaskLog = new TaskLog(array(
		'taskID' => $Task->get('id'),
		'taskStateID' => $Task->get('stateID'),
		'createdTime' => $Task->get('createdTime'),
		'createdBy' => $Task->get('createdBy'),
	));
	$TaskLog->save();
	print '##@GO';
}
catch (Exception $e)
{
	print $e->getMessage();
}
>>>>>>> dev-branch
