<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if(!$Host || !$Host->isValid())
		throw new Exception('#!ih');
	// Only worry about if the Task is queued, in line, or in progress (for reporting reasons).
	$Task = $Host->get('task');
	// If the task is Valid and is not of type 12 or 13 report that it's waiting for other tasks.
	if ($Task && $Task->isValid() && $Task->get('typeID') != 12 && $Task->get('typeID') != 13) throw new Exception('#!it');
	//Get the snapin job. There should be tasks if the Job is still viable.
	$SnapinJob = $Host->get('snapinjob');
	if (!$SnapinJob || !$SnapinJob->isValid()) throw new Exception('#!ns');
	// Work on the current Snapin Task.
	$SnapinTask = current($FOGCore->getClass('SnapinTaskManager')->find(array('jobID' => $SnapinJob->get('id'),'stateID' => array(-1,0,1)),'','name'));
	if ($SnapinTask && $SnapinTask->isValid())
	{
		// Get the information (the Snapin itself)
		$Snapin = new Snapin($SnapinTask->get('snapinID'));
		// Check for task status. If it's got a numeric exitcode
		if (strlen($_REQUEST['exitcode']) > 0 && is_numeric($_REQUEST['exitcode']))
		{
			// Place the task for records, but outside of recognizable as Complete or Done!
			$SnapinTask->set('stateID','2')->set('return',$_REQUEST['exitcode'])->set('details',$_REQUEST['exitdesc'])->set('complete',$FOGCore->nice_date()->format('Y-m-d H:i:s'));
			if ($SnapinTask->save()) print "#!ok";
			// If that was the last task, delete the job.
			if ($FOGCore->getClass('SnapinTaskManager')->count(array('stateID' => array(-1,0,1),'jobID' => $SnapinJob->get('id'))) < 1)
			{
				// If it's part of a task deployment update the task information.
				$SnapinJob->set('stateID',2)->save();
				if ($Task->isValid()) $Task->set('stateID',4)->save();
			}
		}
		else
		{
			$SnapinJob->set('stateID',1)->save();
			// If it's part of a task deployment update the task information.
			if ($Task && $Task->isValid()) $Task->set('stateID',3)->set('checkInTime',$FOGCore->nice_date()->format('Y-m-d H:i:s'))->save();
			//If not from above, update the Task information.
			$SnapinTask->set('stateID',0)->set('checkin',$FOGCore->nice_date()->format('Y-m-d H:i:s'));
			// As long as things update, send the information.
			if ($SnapinTask->save())
			{
				$goodSnapin = array(
					"#!ok\n",
					"JOBTASKID=".$SnapinTask->get('id')."\n",
					"JOBCREATION=".$SnapinJob->get('createdTime')."\n",
					"SNAPINNAME=".$Snapin->get('name')."\n",
					"SNAPINARGS=".$Snapin->get('args')."\n",
					"SNAPINBOUNCE=".$Snapin->get('reboot')."\n",
					"SNAPINFILENAME=".basename($Snapin->get('file'))."\n",
					"SNAPINRUNWITH=".$Snapin->get('runWith')."\n",
					"SNAPINRUNWITHARGS=".$Snapin->get('runWithArgs'),
				);
				$Datatosend = implode($goodSnapin);
			}
		}
	}
}
catch(Exception $e)
{
	$Datatosend = $e->getMessage();	
}
if ($Host && $Host->isValid() && $Host->get('pub_key') && $_REQUEST['newService'])
	print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
else if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
