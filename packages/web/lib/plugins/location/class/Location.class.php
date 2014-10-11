<<<<<<< HEAD
<?php

class Location extends FOGController
{
	// Table
	public $databaseTable = 'location';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'lID',
		'name'		=> 'lName',
		'description' => 'lDesc',
		'createdBy'	=> 'lCreatedBy',
		'createdTime' => 'lCreatedTime',
		'storageGroupID'		=> 'lStorageGroupID',
		'storageNodeID' => 'lStorageNodeID',
		'tftp' => 'lTftpEnabled',
	);

	public $databaseFieldsRequired = array(
		'name',
		'storageGroupID',
	);
}
=======
<?php

class Location extends FOGController
{
	// Table
	public $databaseTable = 'location';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'lID',
		'name'		=> 'lName',
		'description' => 'lDesc',
		'createdBy'	=> 'lCreatedBy',
		'createdTime' => 'lCreatedTime',
		'storageGroupID'		=> 'lStorageGroupID',
		'storageNodeID' => 'lStorageNodeID',
		'tftp' => 'lTftpEnabled',
	);

	public $databaseFieldsRequired = array(
		'name',
		'storageGroupID',
	);
	public function destroy($field = 'id')
	{
		$this->getClass('LocationAssociationManager')->find(array('locationID' => $this->get('id')));
		return parent::destroy($field);
	}
}
>>>>>>> dev-branch
