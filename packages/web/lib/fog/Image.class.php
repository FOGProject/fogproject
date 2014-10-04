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
		'storageGroupID' => 'imageNFSGroupID',
		'osID' => 'imageOSID',
		'size' => 'imageSize', 
		'deployed' => 'imageLastDeploy',
		'format' => 'imageFormat',
		'magnet' => 'imageMagnetUri',
		'protected' => 'imageProtect',
	);

	// Additional Fields
	public $additionalFields = array(
		'hosts',
	);

	// Overrides
	private function loadHosts()
	{
		if (!$this->isLoaded('hosts'))
		{
			if ($this->get('id'))
			{
				$Hosts = $this->getClass('HostManager')->find(array('imageID' => $this->get('id')));
				foreach($Hosts AS $Host)
					$this->add('hosts', $Host);
			}
		}
		return $this;
	}

	public function get($key = '')
	{
		if ($this->key($key) == 'hosts')
			$this->loadHosts();
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
		// Add
		return parent::add($key, $value);
	}

	public function remove($key, $object)
	{
		if ($this->key($key) == 'hosts')
			$this->loadHosts();
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

	public function removeHost($removeArray)
	{
		// Iterate array (or other as array)
		foreach((array)$removeArray AS $remove)
			$this->remove('hosts', ($remove instanceof Host ? $remove : new Host((int)$remove)));
		// Return
		return $this;
	}
	
	// Custom functions
	/** getStorageGroup()
		Gets the relevant StorageGroup class object for the image.
	*/
	public function getStorageGroup()
	{
		return new StorageGroup($this->get('storageGroupID'));
	}
	/** getOS()
		Gets the relevant OS Class object for the image.
	*/
	public function getOS()
	{
		if ($this->get('osID'))
			return new OS($this->get('osID'));
		else
			return new OS(array('id' => '0'));
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
	/** deleteImageFile()
		This function just deletes the image file via FTP.
		Only used if the user checks the Add File? checkbox.
	*/
	public function deleteImageFile()
	{
		if ($this->get('protected'))
			throw new Exception($this->foglang['ProtectedImage']);
		$ftp = $this->FOGFTP;
		$SN = $this->getStorageGroup()->getMasterStorageNode();
		$SNME = ($SN && $SN->get('isEnabled') == '1' ? true : false);
		if (!$SNME)
			throw new Exception($this->foglang['NoMasterNode']);
		$ftphost = $SN->get('ip');
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
