<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	// Get the MAC
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if(!$MACs)
		throw new Exception('#!im');
	// Get the host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid())
		throw new Exception('#!ih');
	// Get the Jobs if possible
	$SnapinJobs = $FOGCore->getClass('SnapinJobManager')->find(array('hostID' => $Host->get('id')));
	// Cycle through all the host jobs that have awaiting snapin tasks.
	foreach($SnapinJobs AS $SnapinJob)
		// Get the tasks of the job so long as they're active.
		$SnapinTasks += $FOGCore->getClass('SnapinTaskManager')->count(array('stateID' => array(-1,0,1), 'jobID' => $SnapinJob->get('id')));
	print ($SnapinTasks ? 1 : 0);
}
catch (Exception $e)
{
	print $e->getMessage();
}
