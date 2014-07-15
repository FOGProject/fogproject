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
		'ip'		=> 'ngmHostname',
		'maxClients'	=> 'ngmMaxClients',
		'user'		=> 'ngmUser',
		'pass'		=> 'ngmPass',
		'key'		=> 'ngmKey',
		'interface'	=> 'ngmInterface'
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
	function getStorageGroup()
	{
		return new StorageGroup($this->get('storageGroupID'));
	}
	function getNodeFailure($Host)
	{
		$TimeZone = new DateTimeZone((!ini_get('date.timezone') ? 'GMT' : ini_get('date.timezone')));
		$DateInterval = new DateTime('-5 minutes',$TimeZone);
		$NodeFailures = $this->FOGCore->getClass('NodeFailureManager')->find(array(
			'storageNodeID'	=> $this->get('id'), 
			'hostID'	=> $this->DB->sanitize($Host instanceof Host ? $Host->get('id') : $Host),
		));
		foreach($NodeFailures AS $NodeFailure)
		{
			$DateTime = new DateTime($NodeFailure->get('failureTime'),$TimeZone);
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
		return $this->FOGCore->getClass('TaskManager')->count(array(
			'stateID'	=> 3,
			'typeID'	=> array(1,15,17),	// Just Download Tasks are "Used".
			'NFSMemberID'	=> $this->get('id')
		));
	}
	public function getQueuedSlotCount()
	{
		return $this->FOGCore->getClass('TaskManager')->count(array(
			'stateID' => array(1,2),
			'typeID' => array(1,2,8,15,16,17),
			'NFSMemberID' => $this->get('id'),
		));
	}
}
