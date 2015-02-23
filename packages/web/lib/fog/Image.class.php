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
		'storageinme' => 'imageStorage',
		'storagename' => 'storageName',
		'imageType' => 'imageTypeName',
		'imageOS' => 'imageOSName',
		'imagePart' => 'imagePartName',
		'imageHosts' => 'imageHosts',
	);
	public $aliasedFields = array(
		'imageType',
		'imageOS',
		'imagePart',
		'imageHosts',
		'storageinme',
		'storagename',
	);

	// Additional Fields
	public $additionalFields = array(
		'stores',
		'hosts',
		'hostsnotinme',
		'hostsnotinany',
		'storageGroups',
	);

	// Class association fields
	/** The customized query for this item template for a single call */
	public $loadQueryTemplateSingle = "SELECT *,GROUP_CONCAT(DISTINCT `nfsGroups`.`ngName` ORDER BY `nfsGroups`.`ngName`) storageName,`imageTypes`.`imageTypeName` imageTypeName,`os`.`osName` imageOSName,`imagePartitionTypes`.`imagePartitionTypeName` imagePartName,GROUP_CONCAT(DISTINCT `nfsGroups`.`ngID` ORDER BY `nfsGroups`.`ngName`) imageStorage,GROUP_CONCAT(DISTINCT `hosts`.`hostID` ORDER BY `hosts`.`hostName`) imageHosts FROM `%s` LEFT OUTER JOIN `hosts` ON `hosts`.`hostImage`=`images`.`imageID` LEFT OUTER JOIN `imageGroupAssoc` ON `imageGroupAssoc`.`igaImageID`=`images`.`imageID` LEFT OUTER JOIN `nfsGroups` ON `imageGroupAssoc`.`igaStorageGroupID`=`nfsGroups`.`ngID` LEFT OUTER JOIN `imageTypes` ON `images`.`imageTypeID`=`imageTypes`.`imageTypeID` LEFT OUTER JOIN `os` ON `os`.`osID`=`images`.`imageOSID` LEFT OUTER JOIN `imagePartitionTypes` ON `imagePartitionTypes`.`imagePartitionTypeID`=`images`.`imagePartitionTypeID` %s WHERE `%s`='%s' GROUP BY `imageName`";
	// Overrides
	private function loadHosts()
	{
		if (!$this->isLoaded('hosts') && $this->get('id'))
		{
			$Hostsinme = $this->getClass('HostManager')->find(array('id' => explode(',',$this->get('imageHosts'))));
			$Hostsnotinme = $this->getClass('HostManager')->find(array('id' => explode(',',$this->get('imageHosts'))),'','','','','',true);
			$this->set('hosts',$Hostsinme);
			$this->set('hostsnotinany',$this->getClass('HostManager')->find(array('imageID' => '')));
			$this->set('hostsnotinme',array_diff($this->get('hostsnotinany'),$Hostsnotinme));
		}
		return $this;
	}
	private function loadGroups()
	{
		if (!$this->isLoaded('storageGroups') && $this->get('id'))
			$this->set('storageGroups',$this->getClass('StorageGroupManager')->find(array('id' => explode(',',$this->get('storageinme')))));
		return $this;
	}

	public function get($key = '')
	{
		if (in_array($this->key($key),array('hosts','hostsnotinme','hostsnotinany')))
			$this->loadHosts();
		else if ($this->key($key) == 'storageGroups')
			$this->loadGroups();
		return parent::get($key);
	}

	public function set($key, $value)
	{
		if ($this->key($key) == 'hosts')
		{
			foreach((array)$value AS $Host)
				$newValue[] = ($Host instanceof Host ? $Host : new Host($Host));
			$value = (array)$newValue;
		}
		else if ($this->key($key) == 'storageGroups')
		{
			foreach((array)$value AS $Group)
				$newValue[] = ($Group instanceof StorageGroup ? $Group : new Group($Group));
			$value = (array)$newValue;
		}
		// Set
		return parent::set($key, $value);
	}

	public function add($key, $value)
	{
		if ($this->key($key) == 'hosts' && !($value instanceof Host))
		{
			$this->loadHosts();
			$value = new Host($value);
		}
		else if ($this->key($key) == 'storageGroups' && !($value instanceof StorageGroup))
		{
			$this->loadGroups();
			$value = new StorageGroup($value);
		}
		// Add
		return parent::add($key, $value);
	}

	public function remove($key, $object)
	{
		if ($this->key($key) == 'hosts')
			$this->loadHosts();
		else if ($this->key($key) == 'storageGroups')
			$this->loadGroups();
		// Remove
		return parent::remove($key, $object);
	}

	public function save()
	{
		parent::save();
		if ($this->isLoaded('hosts'))
		{
			// Unset all hosts
			foreach($this->getClass('HostManager')->find(array('imageID' => $this->get('id'))) AS $Host)
			{
				if(($Host instanceof Host) && $Host->isValid())
					$Host->set('imageID', 0)->save();
			}
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
		$StorageGroup = current((array)$this->get('storageGroups'));
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
		return $this->get('imageOSName');
	}
	/** getImageType()
		Gets the relevant ImageType class object for the image.
	*/
	public function getImageType()
	{
		return $this->get('imageType');
	}
	/** getImagePartitionType()
		Gets the relevant ImagePartitionType class object for the image.
	*/
	public function getImagePartitionType()
	{
		return $this->get('ImagePartitionType');
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
