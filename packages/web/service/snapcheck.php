<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	// Get the MAC
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception($foglang['InvalidMAC']);
	// Get the host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid())
		throw new Exception('#!ih');
	// Get the Jobs if possible
	$SnapinJobs = $FOGCore->getClass('SnapinJobManager')->find(array('hostID' => $Host->get('id')));
	// Cycle through all the host jobs that have awaiting snapin tasks.
	if ($_REQUEST['getSnapnames'])
	{
		foreach($SnapinJobs AS $SnapinJob)
		{
			$SnapinTasks = $FOGCore->getClass('SnapinTaskManager')->find(array('stateID' => array(-1,0,1),'jobID' => $SnapinJob->get('id')));
			foreach($SnapinTasks AS $SnapinTask)
			{
				$Snapin = new Snapin($SnapinTask->get('snapinID'));
				$SnapinNames[] = $Snapin->get('name');
			}
		}
		$Snapins = implode(' ',(array)$SnapinNames);
	}
	else
	{
		foreach($SnapinJobs AS $SnapinJob)
			// Get the tasks of the job so long as they're active.
			$SnapinTasks += $FOGCore->getClass('SnapinTaskManager')->count(array('stateID' => array(-1,0,1), 'jobID' => $SnapinJob->get('id')));
		$Snapins = ($SnapinTasks ? 1 : 0);
	}
	print $Snapins;
}
catch (Exception $e)
{
	print $e->getMessage();
}
