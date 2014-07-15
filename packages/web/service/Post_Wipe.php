<?php
require('../commons/base.inc.php');
try
{
	// Error checking
	//MAC Address
<<<<<<< HEAD
	$MACAddress = new MACAddress($_REQUEST['mac']);
	if (!$MACAddress->isValid())
		throw new Exception(_('Invalid MAC address'));
	// Host for MAC Address
	$Host = $MACAddress->getHost();
=======
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception($foglang['InvalidMAC']);
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
	if (!$Host->isValid())
		throw new Exception(_('Invalid Host'));
	// Task for Host
	$Task = current($Host->get('task'));
	if (!$Task->isValid())
		throw new Exception(sprintf('%s: %s (%s)',_('No Active Task found for Host'),$Host->get('name'),$MACAddress));
	$Task->set('stateID',4);
	if ($Task->save())
	{
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
