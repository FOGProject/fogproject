<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid())
		throw new Exception('#!ih');
	// Only worry about if the Task is queued, in line, or in progress (for reporting reasons).
	$Task = current($Host->get('task'));
	// If the task is Valid and is not of type 12 or 13 report that it's waiting for other tasks.
	if ($Task && $Task->isValid())
	{
		if ($Task->get('typeID') != 12 && $Task->get('typeID') != 13)
			throw new Exception('#!it');
	}
	//Get the snapin job.
	$SnapinJob = current($FOGCore->getClass('SnapinJobManager')->find(array('hostID' => $Host->get('id'),'stateID' => array(0,1))));
	if (!$SnapinJob)
		throw new Exception('#!ns');
	// Work on the current Snapin Task.
	$SnapinTask = current($FOGCore->getClass('SnapinTaskManager')->find(array('stateID' => array(-1,0,1),'jobID' => $SnapinJob->get('id'))));
	if (!$SnapinTask)
		throw new Exception('#!ns');
	// Get the information (the Snapin itself)
	$Snapin = new Snapin($SnapinTask->get('snapinID'));
	// Check for task status.  If it's got a numeric exitcode
	if (strlen($_REQUEST['exitcode']) > 0 && is_numeric($_REQUEST['exitcode']))
	{
		// Place the task for records, but outside of recognizable as Complete or Done!
		$SnapinTask->set('stateID','2')
				   ->set('return',$_REQUEST['exitcode'])
				   ->set('details',$_REQUEST['exitdesc'])
				   ->set('complete',date('Y-m-d H:i:s'));
		if ($SnapinTask->save())
			print "#!ok";
		// Get the current count of snapin tasks.
		$cnt = count($FOGCore->getClass('SnapinTaskManager')->find(array('stateID' => array(-1,0,1),'jobID' => $SnapinJob->get('id'))));
		// If that was the last task, delete the job.
		if ($cnt == 0)
		{
			// If it's part of a task deployment update the task information.
			$SnapinJob->set('stateID',2)->save();
			if ($Task && $Task->isValid())
				$Task->set('stateID',4)->save();
		}
	}
	else
	{
		$SnapinJob->set('stateID',1)->save();
		// If it's part of a task deployment update the task information.
		if ($Task && $Task->isValid())
		{
			$Task->set('stateID',3)
				 ->set('checkInTime',date('Y-m-d H:i:s'))->save();
		}
		//If not from above, update the Task information.
		$SnapinTask->set('stateID',0)
				   ->set('checkin',date('Y-m-d H:i:s'));
		// As long as things update, send the information.
		if ($SnapinTask->save())
		{
			print "#!ok\n";
			print "JOBTASKID=".$SnapinJob->get('id')."\n";
			print "JOBCREATION=".$SnapinJob->get('createTime')."\n";
			print "SNAPINNAME=".$Snapin->get('name')."\n";
			print "SNAPINARGS=".$Snapin->get('args')."\n";
			print "SNAPINBOUNCE=".$Snapin->get('reboot')."\n";
			print "SNAPINFILENAME=".basename($Snapin->get('file'))."\n";
			print "SNAPINRUNWITH=".$Snapin->get('runWith')."\n";
			print "SNAPINRUNWITHARGS=".$Snapin->get('runWithArgs');
		}
	}
}
catch(Exception $e)
{
	print $e->getMessage();	
}
