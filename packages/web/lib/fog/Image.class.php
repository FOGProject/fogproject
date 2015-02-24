<?php
/** \class Image
	Builds all the Image class attributes.  The way it pulls data from the database.
*/
class Image extends FOGController
{
	// Table
	public $databaseTable = 'images';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'imageID',
		'name' => 'imageName',
		'description' => 'imageDesc',
		'path' => 'imagePath',
		'createdTime' => 'imageDateTime',
		'createdBy' => 'imageCreateBy',
		'building' => 'imageBuilding',
		'size' => 'imageSize',
		'imageTypeID' => 'imageTypeID',
		'imagePartitionTypeID' => 'imagePartitionTypeID',
		'osID' => 'imageOSID',
		'size' => 'imageSize', 
		'deployed' => 'imageLastDeploy',
		'format' => 'imageFormat',
		'magnet' => 'imageMagnetUri',
		'protected' => 'imageProtect',
		'compress' => 'imageCompress',
	);
	// additionalFields
	public $additionalFields = array(
		'hosts',
		'storageGroups',
	);
	// databaseFieldClassRelationships
	public $databaseFieldClassRelationships = array(
		//'Host' => array('imageID','id','hosts'),
		'StorageGroup' => array('imageID','id','storageGroups'),
	);
	public function save()
	{
		parent::save();
		if ($this->isLoaded('hosts'))
		{
			// Reset the hosts necessary
			foreach ((array)$this->get('hosts') AS $Host)
			{
				if (($Host instanceof Host) && $Host->isValid())
					$Host->set('imageID', $this->get('id'))->save();
			}
		}
		if ($this->isLoaded('storageGroups'))
		{
			// Remove old rows
			$this->getClass('ImageAssociationManager')->destroy(array('imageID' => $this->get('id')));
			// Create Assoc
			foreach((array)$this->get('storageGroups') AS $Group)
			{
				if (($Group instanceof StorageGroup) && $Group->isValid())
				{
					$NewGroup = new ImageAssociation(array(
						'imageID' => $this->get('id'),
						'storageGroupID' => $Group->get('id'),
					));
					$NewGroup->save();
				}
			}
		}
		return $this;
	}
	public function addHost($addArray)
	{
		// Add
		foreach((array)$addArray AS $item)
			$this->add('hosts', $item);
		// Return
		return $this;
	}
	public function addGroup($addArray)
	{
		// Add
		foreach((array)$addArray AS $item)
			$this->add('storageGroups',$item);
		// Return
		return $this;
	}
	public function removeHost($removeArray)
	{
		// Iterate array (or other as array)
		foreach((array)$removeArray AS $remove)
			$this->remove('hosts', ($remove instanceof Host ? $remove : new Host((int)$remove)));
		// Return
		return $this;
	}
	public function removeGroup($removeArray)
	{
		// Iterate array (or other as array)
		foreach((array)$removeArray AS $remove)
		{
			if (count($this->get('storageGroups')) > 1)
				$this->remove('storageGroups', ($remove instanceof StorageGroup ? $remove : new StorageGroup((int)$remove)));
		}
		// Return
		return $this;
	}
	// Custom functions
	/** getStorageGroup()
		Gets the relevant StorageGroup class object for the image.
	*/
	public function getStorageGroup()
	{
		$StorageGroup = current($this->get('storageGroups'));
		try
		{
			if (!$StorageGroup || ($StorageGroup instanceof StorageGroup && !$StorageGroup->isValid()))
				throw new Exception(__class__.' '._('does not have a storage group assigned').'.');
		}
		catch (Exception $e)
		{
			$this->FOGCore->setMessage($e->getMessage());
		}
		return $StorageGroup;
	}
	/** getOS()
		Gets the relevant OS Class object for the image.
	*/
	public function getOS()
	{
		return new OS($this->get('osID'));
	}
	/** getImageType()
		Gets the relevant ImageType class object for the image.
	*/
	public function getImageType()
	{
		return new ImageType($this->get('imageTypeID'));
	}
	/** getImagePartitionType()
		Gets the relevant ImagePartitionType class object for the image.
	*/
	public function getImagePartitionType()
	{
		return new ImagePartitionType($this->get('imagePartitionTypeID'));
	}
	/** deleteFile()
		This function just deletes the file(s) via FTP.
		Only used if the user checks the Add File? checkbox.
	*/
	public function deleteFile()
	{
		if ($this->get('protected'))
			throw new Exception($this->foglang['ProtectedImage']);
		$ftp = $this->FOGFTP;
		$SN = $this->getStorageGroup()->getMasterStorageNode();
		$SNME = ($SN && $SN->get('isEnabled') == '1' ? true : false);
		if (!$SNME)
			throw new Exception($this->foglang['NoMasterNode']);
		$ftphost = $this->FOGCore->resolveHostname($SN->get('ip'));
		$ftpuser = $SN->get('user');
		$ftppass = $SN->get('pass');
		$ftproot = rtrim($SN->get('path'),'/').'/'.$this->get('path');
		$ftp->set('host',$ftphost)
			->set('username',$ftpuser)
			->set('password',$ftppass)
			->connect();
		if(!$ftp->delete($ftproot))
			throw new Exception($this->foglang['FailedDeleteImage']);
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
