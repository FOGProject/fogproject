<?php
require('../commons/base.inc.php');
try
{
	// Error checking
	//MAC Address
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception($foglang['InvalidMAC']);
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if ($Host && $Host->isValid())
	{
		// Task for Host
		$Task = $Host->get('task');
		if ($Task && $Task->isValid())
		{
			if (!in_array($Task->get('typeID'),array(12,13)))
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
			}
		}
	}
	print '##';
}
catch (Exception $e)
{
	print $e->getMessage();
}
