<?php

// Blackout - 6:04 PM 28/09/2011
class Snapin extends FOGController
{
	// Table
	public $databaseTable = 'snapins';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'sID',
		'name'		=> 'sName',
		'description'	=> 'sDesc',
		'file'		=> 'sFilePath',
		'args'		=> 'sArgs',
		'createdTime'	=> 'sCreateDate',
		'createdBy'	=> 'sCreator',
		'reboot'	=> 'sReboot',
		'storageGroupID' => 'snapinNFSGroupID',
		'runWith'	=> 'sRunWith',
		'runWithArgs'	=> 'sRunWithArgs',
		'anon3'		=> 'sAnon3'
	);

	// Allow setting / getting of these additional fields
	public $additionalFields = array(
		'hosts',
	);

	// Overides
	private function loadHosts()
	{
		if (!$this->isLoaded('hosts'))
		{
			if ($this->get('id'))
			{
				$SnapinAssocs = $this->getClass('SnapinAssociationManager')->find(array('snapinID' => $this->get('id')));
				foreach($SnapinAssocs AS $SnapinAssoc)
					$this->add('hosts', new Host($SnapinAssoc->get('hostID')));
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
			// Remove all old entries.
			$this->getClass('SnapinAssociationManager')->destroy(array('snapinID' => $this->get('id')));
			// Create new Assocs
			foreach ((array)$this->get('hosts') AS $Host)
			{
				if (($Host instanceof Host) && $Host->isValid())
				{
					$NewSnapin = new SnapinAssociation(array(
						'snapinID' => $this->get('id'),
						'hostID' => $Host->get('id'),
					));
					$NewSnapin->save();
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
	
	public function removeHost($removeArray)
	{
		// Iterate array (or other as array)
		foreach ((array)$removeArray AS $remove)
		 	$this->remove('hosts', ($remove instanceof Host ? $remove : new Host((int)$remove)));
		// Return
		return $this;
	}
	public function destroy($field = 'id')
	{
		// Remove all associations
		$this->getClass('SnapinAssociationManager')->destroy(array('snapinID' => $this->get('id')));
		foreach($this->getClass('SnapinTaskManager')->find(array('snapinID' => $this->get('id'))) AS $SnapJob)
		{
			$this->getClass('SnapinJobManager')->destroy(array('jobID' => $SnapJob->get('jobID')));
			$SnapJob->destroy();
		}
		// Return
		return parent::destroy($field);
	}
	public function getStorageGroup()
	{
		$StorageGroup = new StorageGroup($this->get('storageGroupID'));
		if (!$StorageGroup || !$StorageGroup->isValid())
			throw new Exception(__class__.' '._('does not have a storage group assigned').'.');
		return $StorageGroup;
	}
	/** deleteFile()
		This function just deletes the file(s) via FTP.
		Only used if the user checks the Add File? checkbox.
	*/
	public function deleteFile()
	{
		$ftp = $this->FOGFTP;
		$SN = $this->getStorageGroup()->getMasterStorageNode();
		$SNME = ($SN && $SN->get('isEnabled') == '1' ? true : false);
		if (!$SNME)
			throw new Exception($this->foglang['NoMasterNode']);
		$ftphost = $SN->get('ip');
		$ftpuser = $SN->get('user');
		$ftppass = $SN->get('pass');
		$ftproot = rtrim($SN->get('snapinpath'),'/').'/'.$this->get('file');
		$ftp->set('host',$ftphost)
			->set('username',$ftpuser)
			->set('password',$ftppass)
			->connect();
		if(!$ftp->delete($ftproot))
			throw new Exception($this->foglang['FailedDelete']);
	}
}
