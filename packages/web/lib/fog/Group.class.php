<?php
/** \class Group
	Gets groups created and handling methods.
*/
class Group extends FOGController
{
	// Table
	public $databaseTable = 'groups';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'groupID',
		'name'		=> 'groupName',
		'description'	=> 'groupDesc',
		'createdBy'	=> 'groupCreateBy',
		'createdTime'	=> 'groupDateTime',
		'building'	=> 'groupBuilding',
		'kernel'	=> 'groupKernel',
		'kernelArgs'	=> 'groupKernelArgs',
		'kernelDevice'	=> 'groupPrimaryDisk'
	);
	// Allow setting / getting of these additional fields
	public $additionalFields = array(
		'hosts',
	);
	// Custom Variables
	private $hostsLoaded = false;
	// Legacy - remove when fully converted
	private $id, $name, $description, $createTime, $createdBy, $building, $hosts, $kernel, $kernelArgs, $primaryDisk;
	public $lastError;
	public function get($key = '')
	{
		if ($this->key($key) == 'hosts' && !$this->hostsLoaded)
		{
			$this->updateHosts();
			$hostsLoaded = true;
		}
		// Get
		return parent::get($key);
	}
	// Host related functions
	public function getHostCount()
	{
		return (is_array($this->getHosts()) ? count($this->getHosts()) : 0);
	}
	public function getHosts()
	{
		return $this->get('hosts');
	}
	public function removeHost($removeHost)
	{
		foreach((array)$this->get('hosts') AS $host)
		{
			if($host->get('id') != $removeHost)
				$newHostArray[] = $host;
		}
		$this->set('hosts', (array)$newHostArray);
		return $this;
	}
	function updateHosts()
	{
		// Reset hosts
		$this->set('hosts', array());
		// Find all group members
		$Hosts = $this->FOGCore->getClass('GroupAssociationManager')->find(array('groupID' => $this->get('id')));
		foreach($Hosts AS $Host)
			$this->add('hosts',new Host($Host->get('hostID')));
		// Return
		return $this;
	}
	function doMembersHaveUniformImages()
	{
		foreach ($this->get('hosts') AS $Host)
			$images[] = $Host->get('imageID');
		$images = array_unique($images);
		return (count($images) == 1 ? true : false);
	}
}
