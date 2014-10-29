<?php

class LocationAssociation extends FOGController
{
	// Table
	public $databaseTable = 'locationAssoc';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'laID',
		'locationID'		=> 'laLocationID',
		'hostID' => 'laHostID',
	);

	// Get the storageNode for this location
	public function getStorageNode()
	{
		$Location = new Location($this->get('locationID'));
		if ($Location && $Location->isValid())
		{
			if ($Location->get('storageNodeID'))
				$StorageNode = new StorageNode($Location->get('storageNodeID'));
			else
				$StorageNode = $this->getStorageGroup()->getOptimalStorageNode();
		}
		return $StorageNode;
	}
	// Get the storageGroup for this location
	public function getStorageGroup()
	{
		$Location = new Location($this->get('locationID'));
		if ($Location && $Location->isValid())
			$StorageGroup = new StorageGroup($Location->get('storageGroupID'));
		return $StorageGroup;
	}
	// Get if the location is tftp or not.
	public function isTFTP()
	{
		$Location = new Location($this->get('locationID'));
		if ($Location && $Location->isValid())
			return $Location->get('tftp');
	}
}
