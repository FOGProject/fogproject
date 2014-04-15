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
		$NodeFailures = $this->FOGCore->getClass('NodeFailureManager')->find(array(
			'storageNodeID'	=> $this->get('id'), 
			'hostID'	=> $this->DB->sanitize($Host instanceof Host ? $Host->get('id') : $Host)
		));
		return (count($NodeFailures) ? $NodeFailures[0] : null);
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
