<?php
/** Class Name: StorageNode
	Extends the FOGController class
	Gets the storage nodes.
*/
class StorageNode extends FOGController
{
	// Table
	public $databaseTable = 'nfsGroupMembers';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'ngmID',
		'name'		=> 'ngmMemberName',
		'description'	=> 'ngmMemberDescription',
		'isMaster'	=> 'ngmIsMasterNode',
		'storageGroupID'=> 'ngmGroupID',
		'isEnabled'	=> 'ngmIsEnabled',
		'isGraphEnabled'=> 'ngmGraphEnabled',
		'path'		=> 'ngmRootPath',
		'snapinpath'		=> 'ngmSnapinPath',
		'ip'		=> 'ngmHostname',
		'maxClients'	=> 'ngmMaxClients',
		'user'		=> 'ngmUser',
		'pass'		=> 'ngmPass',
		'key'		=> 'ngmKey',
		'interface'	=> 'ngmInterface',
		'bandwidth' => 'ngmBandwidthLimit',
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'ip',
		'path'
	);
	// Overrides
	public function get($key = '')
	{
		// Path: Always remove trailing slash on NFS path
		if ($this->key($key) == 'path')
			return rtrim(parent::get($key), '/');
		// FOGController get()
		return parent::get($key);
	}
	public function getStorageGroup()
	{
		return new StorageGroup($this->get('storageGroupID'));
	}
	public function getNodeFailure($Host)
	{
		$DateInterval = $this->nice_date('-5 minutes');
		$NodeFailures = $this->getClass('NodeFailureManager')->find(array(
			'storageNodeID'	=> $this->get('id'), 
			'hostID'	=> $this->DB->sanitize($Host instanceof Host ? $Host->get('id') : $Host),
		));
		foreach($NodeFailures AS $NodeFailure)
		{
			$DateTime = $this->nice_date($NodeFailure->get('failureTime'));
			if ($DateTime->format('Y-m-d H:i:s') >= $DateInterval->format('Y-m-d H:i:s'))
				return $NodeFailure;
		}
	}
	public function getClientLoad() {
        $max = $this->get('maxClients');
	    if ( $max > 0 ) {
    	    return (($this->getUsedSlotCount() + $this->getQueuedSlotCount()) / $max);
	    }
	    return 0;
	}
	public function getUsedSlotCount()
	{
		$UsedTasks = explode(',',$this->FOGCore->getSetting('FOG_USED_TASKS'));
		$countTasks = 0;
		if (in_array(8,(array)$UsedTasks))
		{
			foreach($UsedTasks AS $ind => $val)
			{
				if ($val = 8)
					unset($UsedTasks[$ind]);
			}
			foreach ($this->getClass('TaskManager')->find(array('stateID' => 3,'typeID' => 8)) AS $MulticastTask)
			{
				$Multicast = current($this->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $MulticastTask->get('id'))));
				if ($Multicast && $Multicast->isValid())
				{
					$MulticastJob = new MulticastSession($Multicast->get('jobID'));
					if ($MulticastJob && $MulticastJob->isValid())
						$countTasks++;
				}
			}
			$UsedTasks = array_values($UsedTasks);
		}
		$countTasks += $this->getClass('TaskManager')->count(array(
			'stateID' => 3,
			'typeID' => $UsedTasks,
			'NFSMemberID' => $this->get('id'),
		));
		return $countTasks;
	}
	public function getQueuedSlotCount()
	{
		return $this->getClass('TaskManager')->count(array(
			'stateID' => array(1,2),
			'typeID' => array(1,2,8,15,16,17),
			'NFSMemberID' => $this->get('id'),
		));
	}
}
