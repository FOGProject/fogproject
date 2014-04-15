<?php

// Blackout - 10:59 AM 30/09/2011
class Task extends FOGController
{
	// Table
	public $databaseTable = 'tasks';
	// Name -> Database field name
	public $databaseFields = array(
		'id'			=> 'taskID',
		'name'			=> 'taskName',
		'checkInTime'		=> 'taskCheckIn',
		'hostID'		=> 'taskHostID',
		'stateID'		=> 'taskStateID',
		'createdTime'		=> 'taskCreateTime',
		'createdBy'		=> 'taskCreateBy',
		'isForced'		=> 'taskForce',
		'scheduledStartTime'	=> 'taskScheduledStartTime',
		'typeID'		=> 'taskTypeID',
		'pct'			=> 'taskPCT',
		'bpm'			=> 'taskBPM',
		'timeElapsed'		=> 'taskTimeElapsed',
		'timeRemaining'		=> 'taskTimeRemaining',
		'dataCopied'		=> 'taskDataCopied',
		'percent'		=> 'taskPercentText',
		'dataTotal'		=> 'taskDataTotal',
		'NFSGroupID'		=> 'taskNFSGroupID',
		'NFSMemberID'		=> 'taskNFSMemberID',
		'NFSFailures'		=> 'taskNFSFailures',
		'NFSLastMemberID'	=> 'taskLastMemberID',
		'shutdown'			=> 'taskShutdown',
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'typeID',
		'hostID',
		'NFSGroupID',
		'NFSMemberID'
	);
	// Custom Functions
	public function getHost()
	{
		return new Host($this->get('hostID'));
	}
	public function getStorageGroup()
	{
		return new StorageGroup($this->get('NFSGroupID'));
	}
	public function getStorageNode()
	{
		return new StorageNode($this->get('NFSMemberID'));
	}
	public function getImage()
	{
		return $this->getHost()->getImage();
	}
	public function getInFrontOfHostCount()
	{
		$Tasks = $this->FOGCore->getClass('TaskManager')->find(array(
			'stateID' => array(1,2),
			'typeID' => array(1,15,17),
			'NFSGroupID' => $this->get('NFSGroupID'),
			'id' => $this->get('id'),
		));
		$count = 0;
		$curTime = strtotime(date('Y-m-d H:i:s'));
		foreach($Tasks AS $Task)
		{
			if ($curTime - strtotime($Task->get('createdTime')) < $this->FOGCore->getSetting('FOG_CHECKIN_TIMEOUT'))
				$count++;
		}
		return $count;
	}
	public function cancel()
	{
		// Set State to User Cancelled
		$this->set('stateID', '5')->save();
	}
	// Overrides
	public function set($key, $value)
	{
		// Check in time: Convert Unix time to MySQL datetime
		if ($this->key($key) == 'checkInTime' && is_numeric($value) && strlen($value) == 10)
			$value = date('Y-m-d H:i:s', $value);
		// Return
		return parent::set($key, $value);
	}
	public function destroy($field = 'id')
	{
	    $Host = new Host($this->get('hostID'));
		$SnapinJobs = $this->FOGCore->getClass('SnapinJobManager')->find(array('hostID' => $Host->get('id')));
		if($SnapinJobs)
		{
			foreach($SnapinJobs AS $SnapinJob)
				$SnapinTasks[]= $this->FOGCore->getClass('SnapinTaskManager')->find(array('jobID' => $SnapinJob->get('id'),'state' => array(0,1)));
		}
		// cancel's all the snapin tasks for that host.
		if ($SnapinTasks)
		{
			foreach($SnapinTasks AS $ST)
			{
				foreach($ST AS $SnapinTask)
					$SnapinTask->set('state', -1)->save();
			}
		}
		// FOGController destroy
		return parent::destroy($field);
	}
	public function setHost($Host)
	{
		if ($Host instanceof Host)
			$this->set('hostID', $Host->get('id'));
		else
			$this->set('hostID', $Host);
		return $this;
	}
	public function hasTransferData()
	{
		return $this->getPercent() != '' && strlen( trim($this->getPercent() ) ) > 0 &&
			$this->getTransferRate() != '' && strlen( trim($this->getTransferRate() ) ) > 0 &&
			$this->getTimeElapsed() != '' && strlen( trim($this->getTimeElapsed() ) ) > 0 &&
			$this->getTimeRemaining() != '' && strlen( trim($this->getTimeRemaining() ) ) > 0 &&
			$this->getDataCopied() != '' && strlen( trim($this->getDataCopied() ) ) > 0 &&
			$this->getTaskPercentText() != '' && strlen( trim($this->getTaskPercentText() ) ) > 0 &&
			$this->getTaskDataTotal() != '' && strlen( trim($this->getTaskDataTotal() ) ) > 0;
	}
	public function getTaskType()
	{
		return new TaskType($this->get('typeID'));
	}
	public function getTaskTypeText()
	{
		return (string)($this->getTaskType()->get('name') ? $this->getTaskType()->get('name') : _('Unknown'));
	}
	public function getTaskState()
	{
		return new TaskState($this->get('stateID'));
	}
	public function getTaskStateText()
	{
		return (string)($this->getTaskState()->get('name') ? $this->getTaskState()->get('name') : _('Unknown'));
	}
}
