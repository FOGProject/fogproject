<?php

// Blackout - 11:15 AM 1/10/2011
class Host extends FOGController
{
	// Table
	public $databaseTable = 'hosts';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'hostID',
		'name'		=> 'hostName',
		'description'	=> 'hostDesc',
		'ip'		=> 'hostIP',
		'imageID'	=> 'hostImage',
		'building'	=> 'hostBuilding',
		'createdTime'	=> 'hostCreateDate',
		'deployed'	=> 'hostLastDeploy',
		'createdBy'	=> 'hostCreateBy',
		'mac'		=> 'hostMAC',
		'useAD'		=> 'hostUseAD',
		'ADDomain'	=> 'hostADDomain',
		'ADOU'		=> 'hostADOU',
		'ADUser'	=> 'hostADUser',
		'ADPass'	=> 'hostADPass',
		'printerLevel'	=> 'hostPrinterLevel',
		'kernel'	=> 'hostKernel',
		'kernelArgs'	=> 'hostKernelArgs',
		'kernelDevice'	=> 'hostDevice'
	);
	// Allow setting / getting of these additional fields
	public $additionalFields = array(
		'additionalMACs',
		'pendingMACs',
		'groups',
		'optimalStorageNode',
		'printers',
		'snapins',
		'modules',
		'inventory',
		'task',
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'name',
		'mac'
	);
	// Database field to Class relationships
	public $databaseFieldClassRelationships = array(
		'imageID'	=> 'Image'
	);
	// Custom functons
	public function isHostnameSafe()
	{
		return (strlen($this->get('name')) > 0 && strlen($this->get('name')) <= 15 && preg_replace('#[0-9a-zA-Z_\-]#', '', $this->get('name')) == '');
	}
	// Snapins
	public function getImage()
	{
		return new Image($this->get('imageID'));
	}
	public function getOS()
	{
		return $this->getImage()->getOS();
	}
	public function getMACAddress()
	{
		return new MACAddress($this->get('mac'));
	}
	public function updateInventory($user,$other1,$other2)
	{
		$Inventory = current($this->FOGCore->getClass('InventoryManager')->find(array('hostID' => $this->get('id'))));
		if ($Inventory && $Inventory->isValid())
		{
			$Inventory->set('primaryuser', $user)
					  ->set('other1', $other1)
					  ->set('other2', $other2)
					  ->save();
		}
		return null;
	}
	public function getDefault($printerid)
	{
		$PrinterMan = current($this->FOGCore->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id'),'printerID' => $printerid)));
		return ($PrinterMan && $PrinterMan->isValid() ? $PrinterMan->get('isDefault') : false);
	}
	public function updateDefault($printerid)
	{
		$PrinterAssoc = $this->FOGCore->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id')));
		foreach($PrinterAssoc AS $PrinterSet)
		{
				$PrinterSet->set('isDefault', 0)->save();
			if ($PrinterSet->get('printerID') == $printerid)	
				$PrinterSet->set('isDefault', 1)->save();
		}
	}
	public function getDispVals($key = '')
	{
		$keyTran = array(
			'width' => 'FOG_SERVICE_DISPLAYMANAGER_X',
			'height' => 'FOG_SERVICE_DISPLAYMANAGER_Y',
			'refresh' => 'FOG_SERVICE_DISPLAYMANAGER_R',
		);
		$HostScreen = current($this->FOGCore->getClass('HostScreenSettingsManager')->find(array('hostID' => $this->get('id'))));
		$Service = current($this->FOGCore->getClass('ServiceManager')->find(array('name' => $keyTran[$key])));
		return ($HostScreen && $HostScreen->isValid() ? $HostScreen->get($key) : ($Service && $Service->isValid() ? $Service->get('value') : ''));
	}
	public function setDisp($x,$y,$r)
	{
		$this->FOGCore->getClass('HostScreenSettingsManager')->destroy(array('hostID' => $this->get('id')));
		$HostScreen = new HostScreenSettings(array(
			'hostID' => $this->get('id'),
			'width' => $x,
			'height' => $y,
			'refresh' => $r,
		));
		$HostScreen->save();
	}
	public function getAlo()
	{
		$HostALO = current($this->FOGCore->getClass('HostAutoLogoutManager')->find(array('hostID' => $this->get('id'))));
		$Service = current($this->FOGCore->getClass('ServiceManager')->find(array('name' => 'FOG_SERVICE_AUTOLOGOFF_MIN')));
		return ($HostALO && $HostALO->isValid() ? $HostALO->get('time') : ($Service && $Service->isValid() ? $Service->get('value') : ''));
	}
	public function setAlo($tme)
	{
		$this->FOGCore->getClass('HostAutoLogoutManager')->destroy(array('hostID' => $this->get('id')));
		$HostALO = new HostAutoLogout(array(
			'hostID' => $this->get('id'),
			'time' => $tme,
		));
		$HostALO->save();
	}
	public function getActiveSnapinJob()
	{
		// Find Active Snapin Task, there should never be more than one per host.
		$SnapinJob = current($this->FOGCore->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id'))));
		if (!$SnapinJob)
			throw new Exception(sprintf('%s: %s (%s)', _('No Active Snapin Jobs found for Host'), $this->get('name'), $this->get('mac')));
		return $SnapinJob;
	}
	private function loadAdditional()
	{
		if (!$this->isLoaded('additionalMACs'))
		{
			if ($this->get('id'))
			{
				$AdditionalMACs = $this->FOGCore->getClass('MACAddressAssociationManager')->find(array('hostID' => $this->get('id')));
				foreach($AdditionalMACs AS $MAC)
					$this->add('additionalMACs', new MACAddress($MAC->get('mac')));
			}
		}
		return $this;
	}
	private function loadPrinters()
	{
		if (!$this->isLoaded('printers'))
		{
			if ($this->get('id'))
			{
				$Printers = $this->FOGCore->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id')));
				foreach($Printers AS $Printer)
					$this->add('printers',new Printer($Printer->get('printerID')));
			}
		}
		return $this;
	}
	private function loadPending()
	{
		if (!$this->isLoaded('pendingMACs'))
		{
			if ($this->get('id'))
			{
				$PendingMACs = $this->FOGCore->getClass('PendingMACManager')->find(array('hostID' => $this->get('id')));
				foreach ($PendingMACs AS $MAC)
					$this->add('pendingMACs', new MACAddress($MAC->get('pending')));
			}
		}
		return $this;
	}
	private function loadGroups()
	{
		if (!$this->isLoaded('groups'))
		{
			if ($this->get('id'))
			{
				$Groups = $this->FOGCore->getClass('GroupAssociationManager')->find(array('hostID' => $this->get('id')));
				foreach($Groups AS $Group)
					$this->add('groups',new Group($Group->get('groupID')));
			}
		}
		return $this;
	}
	private function loadInventory()
	{
		if (!$this->isLoaded('inventory'))
		{
			if ($this->get('id'))
			{
				$Inventories = $this->FOGCore->getClass('InventoryManager')->find(array('hostID' => $this->get('id')));
				foreach($Inventories AS $Inventory)
					$this->add('inventory',$Inventory);
			}
		}
		return $this;
	}
	private function loadModules()
	{
		if (!$this->isLoaded('modules'))
		{
			if ($this->get('id'))
			{
				$Modules = $this->FOGCore->getClass('ModuleAssociationManager')->find(array('hostID' => $this->get('id')));
				foreach($Modules AS $Module)
					$this->add('modules', new Module($Module->get('moduleID')));
			}
		}
		return $this;
	}
	private function loadSnapins()
	{
		if (!$this->isLoaded('snapins'))
		{
			if ($this->get('id'))
			{
				$Snapins = $this->FOGCore->getClass('SnapinAssociationManager')->find(array('hostID' => $this->get('id')));
				foreach($Snapins AS $Snapin)
					$this->add('snapins',new Snapin($Snapin->get('snapinID')));
			}
		}
		return $this;
	}
	private function loadTask()
	{
		if (!$this->isLoaded('task'))
		{
			if ($this->get('id'))
			{
				$Task = current($this->FOGCore->getClass('TaskManager')->find(array('hostID' => $this->get('id'),'stateID' => array(1,2,3))));
				if ($Task && $Task->isValid())
					$this->add('task',$Task);
				else
					$this->add('task',new Task(array('id' => 0)));
			}
		}
		return $this;
	}
	// Overrides
	public function get($key = '')
	{
		if ($this->key($key) == 'printers')
			$this->loadPrinters();
		else if ($this->key($key) == 'additionalMACs')
			$this->loadAdditional();
		else if ($this->key($key) == 'snapins')
			$this->loadSnapins();
		else if ($this->key($key) == 'optimalStorageNode' && !$this->isLoaded('optimalStorageNode'))
			$this->set($key, $this->getImage()->getStorageGroup()->getOptimalStorageNode());
		else if ($this->key($key) == 'modules')
			$this->loadModules();
		else if ($this->key($key) == 'inventory')
			$this->loadInventory();
		else if ($this->key($key) == 'groups')
			$this->loadGroups();
		else if ($this->key($key) == 'task')
			$this->loadTask();
		else if ($this->key($key) == 'pendingMACs')
			$this->loadPending();
		return parent::get($key);
	}
	public function set($key, $value)
	{
		// MAC Address
		if (($this->key($key) == 'mac' || $this->key($key) == 'additionalMACs' || $this->key($key) == 'pendingMACs') && !($value instanceof MACAddress))
			$value = new MACAddress($value);
		// Printers
		else if ($this->key($key) == 'printers')
		{
			$this->loadPrinters();
			foreach ((array)$value AS $printer)
				$newValue[] = ($printer instanceof Printer ? $printer : new Printer($printer));
			$value = (array)$newValue;
		}
		// Snapins
		else if ($this->key($key) == 'snapins')
		{
			$this->loadSnapins();
			foreach ((array)$value AS $snapin)
				$newValue[] = ($snapin instanceof Snapin ? $snapin : new Snapin($snapin));
			$value = (array)$newValue;
		}
		// Modules
		else if ($this->key($key) == 'modules')
		{
			$this->loadModules();
			foreach((array)$value AS $module)
				$newValue[] = ($module instanceof Module ? $module : new Module($module));
			$value = (array)$newValue;
		}
		// Inventory
		else if ($this->key($key) == 'inventory')
		{
			$this->loadInventory();
			foreach((array)$value AS $inventory)
				$newValue[] = ($inventory instanceof Inventory ? $inventory : new Inventory($inventory));
			$value = (array)$newValue;
		}
		// Groups
		else if ($this->key($key) == 'groups')
		{
			$this->loadGroups();
			foreach ((array)$value AS $group)
				$newValue[] = ($group instanceof Group ? $group : new Group($group));
			$value = (array)$newValue;
		}
		// Task
		else if ($this->key($key) == 'task')
		{
			$this->loadTask();
			foreach((array)$value AS $task)
				$newValue[] = ($task instanceof Task ? $task : new Task($task));
			$value[] = (array)$newValue;
		}
		// Set
		return parent::set($key, $value);
	}
	public function add($key, $value)
	{
		// Additional MAC Addresses
		if ($this->key($key) == 'additionalMACs' && !($value instanceof MACAddress))
		{
			$this->loadAdditional();
			$value = new MACAddress($value);
		}
		// Pending MAC Addresses
		else if ($this->key($key) == 'pendingMACs' && !($value instanceof MACAddress))
		{
			$this->loadPending();
			$value = new MACAddress($value);
		}
		// Printers
		else if ($this->key($key) == 'printers' && !($value instanceof Printer))
		{
			$this->loadPrinters();
			$value = new Printer($value);
		}
		// Snapins
		else if ($this->key($key) == 'snapins' && !($value instanceof Snapin))
		{
			$this->loadSnapins();
			$value = new Snapin($value);
		}
		// Modules
		else if ($this->key($key) == 'modules' && !($value instanceof Module))
		{
			$this->loadModules();
			$value = new Module($value);
		}
		// Inventory
		else if ($this->key($key) == 'inventory' && !($value instanceof Inventory))
		{
			$this->loadInventory();
			$value = new Inventory($value);
		}
		// Groups
		else if ($this->key($key) == 'groups' && !($value instanceof Group))
		{
			$this->loadGroups();
			$value = new Group($value);
		}
		// Task
		else if ($this->key($key) == 'task' && !($value instanceof Task))
		{
			$this->loadTask();
			$value = new Task($value);
		}
		// Add
		return parent::add($key, $value);
	}
	public function remove($key, $object)
	{
		// Printers
		if ($this->key($key) == 'printers')
			$this->loadPrinters();
		// Snapins
		else if ($this->key($key) == 'snapins')
			$this->loadSnapins();
		// Modules
		else if ($this->key($key) == 'modules')
			$this->loadModules();
		// Inventory
		else if ($this->key($key) == 'inventory')
			$this->loadInventory();
		// Task
		else if ($this->key($key) == 'task')
			$this->loadTask();
		// Groups
		else if ($this->key($key) == 'groups')
			$this->loadGroups();
		// Pending MAC Addresses
		else if ($this->key($key) == 'pendingMACs')
			$this->loadPending();
		// Additional MAC Addresses
		else if ($this->key($key) == 'additionalMACs')
			$this->loadAdditional();
		// Remove
		return parent::remove($key, $object);
	}
	public function save()
	{
		// Save
		parent::save();
		// Additional MAC Addresses
		if ($this->isLoaded('additionalMACs'))
		{
			// Remove existing Additional MAC Addresses
			$this->FOGCore->getClass('MACAddressAssociationManager')->destroy(array('hostID' => $this->get('id')));
			// Add new Additional MAC Addresses
			foreach ((array)$this->get('additionalMACs') AS $MAC)
			{
				if (($MAC instanceof MACAddress) && $MAC->isValid())
				{
					$NewMAC = new MACAddressAssociation(array(
						'hostID' => $this->get('id'),
						'mac' => $MAC,
					));
					$NewMAC->save();
				}
			}
		}
		// Pending MAC Addresses
		else if ($this->isLoaded('pendingMACs'))
		{
			// Remove Existing Pending MAC Addresses
			$this->FOGCore->getClass('PendingMACManager')->destroy(array('hostID' => $this->get('id')));
			// Add new Pending MAC Addresses
			foreach ((array)$this->get('pendingMACs') AS $MAC)
			{
				if (($MAC instanceof MACAddress) && $MAC->isValid())
				{
					$NewMAC = new PendingMAC(array(
						'hostID' => $this->get('id'),
						'pending' => $MAC,
					));
					$NewMAC->save();
				}
			}
		}
		// Printers
		else if ($this->isLoaded('printers'))
		{
			// Remove old rows
			$this->FOGCore->getClass('PrinterAssociationManager')->destroy(array('hostID' => $this->get('id')));
			// Add Default Printer
			// Create assoc
			$i = 0;
			foreach ((array)$this->get('printers') AS $Printer)
			{
				if(($Printer instanceof Printer) && $Printer->isValid())
				{
					$NewPrinter = new PrinterAssociation(array(
						'printerID' => $Printer->get('id'),
						'hostID' => $this->get('id'),
						'isDefault' => ($i === 0 ? '1' : '0'),
					));
					$NewPrinter->save();
				}
				$i++;
			}
		}
		// Snapins
		else if ($this->isLoaded('snapins'))
		{
			// Remove old rows
			$this->FOGCore->getClass('SnapinAssociationManager')->destroy(array('hostID' => $this->get('id')));
			// Create assoc
			foreach ((array)$this->get('snapins') AS $Snapin)
			{
				if (($Snapin instanceof Snapin) && $Snapin->isValid())
				{
					$NewSnapin = new SnapinAssociation(array(
						'hostID' => $this->get('id'),
						'snapinID' => $Snapin->get('id')
					));
					$NewSnapin->save();
				}
			}
		}
		// Modules
		else if ($this->isLoaded('modules'))
		{
			// Remove old rows
			$this->FOGCore->getClass('ModuleAssociationManager')->destroy(array('hostID' => $this->get('id')));
			// Create assoc
			foreach ((array)$this->get('modules') AS $Module)
			{
				if (($Module instanceof Module) && $Module->isValid())
				{
					$ModuleInsert = new ModuleAssociation(array(
						'hostID' => $this->get('id'),
						'moduleID' => $Module->get('id'),
						'state' => 1,
					));
					$ModuleInsert->save();
				}
			}
		}
		// Groups
		else if ($this->isLoaded('groups'))
		{
			// Remove old rows
			$this->FOGCore->getClass('GroupAssociationManager')->destroy(array('hostID' => $this->get('id')));
			// Create assoc
			foreach ((array)$this->get('groups') AS $Group)
			{
				if(($Group instanceof Group) && $Group->isValid())
				{
					$NewGroup = new GroupAssociation(array(
						'hostID' => $this->get('id'),
						'groupID' => $Group->get('id'),
					));
					$NewGroup->save();
				}
			}
		}
		// Return
		return $this;
	}
	public function isValid()
	{
		return (($this->get('id') != '' || $this->get('name') != '') && $this->getMACAddress() != '' ? true : false);
	}
	// Custom functions
	public function getActiveTaskCount()
	{
		return $this->FOGCore->getClass('TaskManager')->count(array('stateID' => array(1, 2, 3), 'hostID' => $this->get('id')));
	}
	public function isValidToImage()
	{
		$Image = $this->getImage();
		$OS = $this->getOS();
		$StorageGroup = $Image->getStorageGroup();
		$StorageNode = $StorageGroup->getStorageNode();
		//return ($Image->isValid() && $OS->isValid() && $StorageGroup->isValid() && $StorageNode->isValid() ? true : false);
		// TODO: Use this version when class caching has been finialized
		return ($this->getImage()->isValid() && $this->getImage()->getOS()->isValid() && $this->getImage()->getStorageGroup()->isValid() && $this->getImage()->getStorageGroup()->getStorageNode()->isValid() ? true : false);
	}
	public function getOptimalStorageNode()
	{
		return $this->get('optimalStorageNode');
	}
	public function checkIfExist($taskTypeID)
	{
		$LocPlugInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location')));
		// TaskType: Variables
		$TaskType = new TaskType($taskTypeID);
		$isUpload = $TaskType->isUpload();
		// Image: Variables
		$Image = $this->getImage();
		if ($LocPlugInst)
		{
			$LA = current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $this->get('id'))));
			if ($LA)
			{
				$Location = new Location($LA->get('locationID'));
				$StorageGroup = new StorageGroup($Location->get('storageGroupID'));
				$StorageNode = ($isUpload ? $StorageGroup->getMasterStorageNode() : ($Location->get('storageNodeID') ? new StorageNode($Location->get('storageNodeID')) : $StorageGroup->getOptimalStorageNode()));
			}
			else
			{
				$StorageGroup = $Image->getStorageGroup();
				$StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $Image->getStorageGroup()->getMasterStorageNode());
			}
		}
		else
		{
			$StorageGroup = $Image->getStorageGroup();
			$StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $this->getOptimalStorageNode());
		}
		if (in_array($TaskType->get('id'),array('1','8','15','17')) && in_array($Image->get('osID'), array('5', '6') ) )
		{
			// FTP
			$ftp = $this->FOGFTP;
			$ftp->set('username',$StorageNode->get('user'))
				->set('password',$StorageNode->get('pass'))
				->set('host',$StorageNode->get('ip'));
			if ($ftp->connect())
			{
				if(!$ftp->chdir(rtrim($StorageNode->get('path'),'/').'/'.$Image->get('path')))
					return false;
			}
			$ftp->close();
		}
		return true;
	}

	// Should be called: createDeployTask
	function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '')
	{
		try
		{
			$LocPlugInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location')));
			// TaskType: Variables
			$TaskType = new TaskType($taskTypeID);
			$isUpload = $TaskType->isUpload();
			// Image: Variables
			$Image = $this->getImage();
			if ($LocPlugInst)
			{
				$LA = current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $this->get('id'))));
				if ($LA)
				{
					$Location = new Location($LA->get('locationID'));
					$StorageGroup = new StorageGroup($Location->get('storageGroupID'));
					$StorageNode = ($isUpload ? $StorageGroup->getMasterStorageNode() : ($Location->get('storageNodeID') ? new StorageNode($Location->get('storageNodeID')) : $StorageGroup->getOptimalStorageNode()));
				}
				else
				{
					$StorageGroup = $Image->getStorageGroup();
					$StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $Image->getStorageGroup()->getMasterStorageNode());
				}
			}
			else
			{
				$StorageGroup = $Image->getStorageGroup();
				$StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $this->getOptimalStorageNode());
			}
			// Task type wake on lan, deploy only this part.
			if ($taskTypeID == '14')
			{
				$Task = new Task(array(
					'name'		=> $taskName,
					'createdBy' => ($this->FOGUser ? $this->FOGUser : ($username ? $username : '')),
					'hostID'	=> $this->get('id'),
					'isForced'	=> 0,
					'stateID'	=> 1,
					'typeID'	=> $taskTypeID,
					'NFSGroupID'	=> ($LocPlugInst ? $StorageGroup->get('id') : $Image->getStorageGroup()->get('id')),
					'NFSMemberID'	=> ($LocPlugInst ? $StorageGroup->getOptimalStorageNode()->get('id') : $Image->getStorageGroup()->getOptimalStorageNode()->get('id')),
					'shutdown' => $shutdown,
				));
				if ($Task->save())
				{
					$this->wakeOnLAN();
					$this->FOGCore->logHistory(sprintf('Task Created: Task ID: %s, Task Name: %s, Host ID: %s, Host Name: %s, Host MAC: %s, Image ID: %s, Image Name: %s', $Task->get('id'), $Task->get('name'), $this->get('id'), $this->get('name'), $this->getMACAddress(), $this->getImage()->get('id'), $this->getImage()->get('name')));
					$Task->destroy();
					return $Task;
				}
				else
				{
					$this->FOGCore->logHistory(sprintf('Task failed: Task ID: %s, Task Name: %s, Host ID: %s, HostName: %s, Host MAC: %s',$Task->get('id'),$Task->get('name'),$this->get('id'),$this->get('name'),$this->getMACAddress()));
					throw new Exception(_('Failed to create task.'));
				}
			}
			// Snapin deploy/cancel only if task type is of snapin deployment type.
			if (!$isUpload && $deploySnapins && ($taskTypeID == '12' || $taskTypeID == '13'))
			{
				// Task: Create Task Object
				$Task = new Task(array(
					'name'		=> $taskName,
					'createdBy'	=> ($this->FOGUser ? $this->FOGUser : ($username ? $username : '')),
					'hostID'	=> $this->get('id'),
					'isForced'	=> 0,
					'stateID'	=> 1,
					'typeID'	=> $taskTypeID, 
					'NFSGroupID' 	=> $StorageGroup->get('id'),
					'NFSMemberID'	=> $StorageGroup->getOptimalStorageNode()->get('id'),
					'shutdown' => $shutdown,
					'passreset' => $passreset,	
				));
				$SnapinJobs = current($this->FOGCore->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id'),'stateID' => array(0,1))));
				if ($SnapinJobs && $SnapinJobs->isValid() && $deploySnapins == -1)
					throw new Exception('Snapins Are already deployed to this host.');
				else
				{
					// Create Snapin Job.  Only one job, but will do multiple SnapinTasks.
					$SnapinJob = new SnapinJob(array(
						'hostID' => $this->get('id'),
						'stateID' => 0,
						'createTime' => date('Y-m-d H:i:s'),
					));
					// Create Snapin Tasking
					if ($SnapinJob->save())
					{
						// If -1 for the snapinID sent, it needs to set a task for all of the snapins associated to that host.
						if ($deploySnapins == -1)
						{
							$SnapinAssoc = $this->FOGCore->getClass('SnapinAssociationManager')->find(array('hostID' => $this->get('id')));
							foreach ($SnapinAssoc AS $SA)
							{
								$SnapinTask = current($this->FOGCore->getClass('SnapinTaskManager')->find(array('snapinID' => $SA->get('snapinID'), 'stateID' => array(-1,0,1))));
								if ($SnapinTask && $SnapinTask->isValid())
									$SnapinJobCheck = current($this->FOGCore->getClass('SnapinJobManager')->find(array('id' => $SnapinTask->get('jobID'), 'hostID' => $this->get('id'),'stateID' => array(-1,0,1))));
								if (!$SnapinJobCheck || !$SnapinJobCheck->isValid())
								{
									$ST = new SnapinTask(array(
										'jobID' => $SnapinJob->get('id'),
										'stateID' => 0,
										'snapinID' => $SA->get('snapinID'),
									));
									$ST->save();
								}
								else
								{
									$SnapinTask->set('jobID', $SnapinJob->get('id'))
											   ->set('stateID', 0)
											   ->set('snapinID', $SA->get('snapinID'))
											   ->save();
								}
							}
						}
						else
						{
							$Snapin = new Snapin($deploySnapins);
							$SnapinTask = current($this->FOGCore->getClass('SnapinTaskManager')->find(array('snapinID' => $Snapin->get('id'), 'stateID' => array(-1,0,1))));
							if ($SnapinTask && $SnapinTask->isValid())
								$SnapinJobCheck = current($this->FOGCore->getClass('SnapinJobManager')->find(array('id' => $SnapinTask->get('jobID'), 'hostID' => $this->get('id'),'stateID' => array(-1,0,1))));
							if (!$SnapinJobCheck || !$SnapinJobCheck->isValid())
							{
								$ST = new SnapinTask(array(
									'jobID' => $SnapinJob->get('id'),
									'stateID' => 0,
									'snapinID' => $Snapin->get('id'),
								));
								$ST->save();
							}
							else
								throw new Exception(_('Snapin is already setup for tasking.'));
						}
					}
				}
				if ($Task->save())
				{
					$this->FOGCore->logHistory(sprintf('Task Created: Task ID: %s, Task Name: %s, Host ID: %s, Host Name: %s, Host MAC: %s, Image ID: %s, Image Name: %s', $Task->get('id'), $Task->get('name'), $this->get('id'), $this->get('name'), $this->getMACAddress(), $this->getImage()->get('id'), $this->getImage()->get('name')));
					return $Task;
				}
				else
				{
					$this->FOGCore->logHistory(sprintf('Task failed: Task ID: %s, Task Name: %s, Host ID: %s, HostName: %s, Host MAC: %s',$Task->get('id'),$Task->get('name'),$this->get('id'),$this->get('name'),$this->getMACAddress()));
					throw new Exception(_('Failed to create task.'));
				}
			}
			// Error checking
			if ($taskTypeID != 12 && $taskTypeID != 13 && $this->getActiveTaskCount())
				throw new Exception('Host is already a member of a active task');
			if (!$this->isValid())
				throw new Exception('Host is not valid');
			// TaskType: Error checking
			if (!$TaskType->isValid())
				throw new Exception('Task Type is not valid');
			// Image: Error checking
			if (!$Image->isValid())
				throw new Exception('Image is not valid');
			if (!$Image->getStorageGroup()->isValid())
				throw new Exception('The Image\'s associated Storage Group is not valid');
			// Storage Node: Error Checking
			if (!$StorageNode || !($StorageNode instanceof StorageNode))
				throw new Exception( _('Could not find a Storage Node. Is there one enabled within this Storage Group?') );
			if (!$StorageNode->isValid())
				throw new Exception(_('The Storage Group\'s associated Storage Node is not valid'));
			// Variables
			$mac = $this->getMACAddress()->getMACWithColon();
			// Task: Create Task Object
			$Task = new Task(array(
				'name'		=> $taskName,
				'createdBy'	=> ($this->FOGUser ? $this->FOGUser : ($username ? $username : '')),
				'hostID'	=> $this->get('id'),
				'isForced'	=> '0',
				'stateID'	=> '1',
				'typeID'	=> $taskTypeID, 
				'NFSGroupID' 	=> ($Location ? $StorageGroup->get('id') : $Image->getStorageGroup()->get('id')),
				'NFSMemberID'	=> ($Location ? $StorageGroup->getOptimalStorageNode()->get('id') : $Image->getStorageGroup()->getOptimalStorageNode()->get('id')),
				'shutdown' => $shutdown,
				'passreset' => $passreset,
			));
			// Task: Save to database
			if (!$Task->save())
				throw new Exception(_('Task creation failed'));
			// If task is multicast perform the following.
			if ($TaskType->isMulticast())
			{
				$MultiSessAssoc = current($this->FOGCore->getClass('MulticastSessionsManager')->find(array('image' => $this->getImage()->get('id'),'stateID' => 0)));
				// If no Associations, create new job and association.
				if (!$MultiSessAssoc || !$isGroupTask)	
				{
					// Create New Multicast Session Job
					$MulticastSession = new MulticastSessions(array(
						'name' => $taskName,
						'port' => $this->FOGCore->getSetting('FOG_UDPCAST_STARTINGPORT'),
						'logpath' => $this->getImage()->get('path'),
						'image' => $this->getImage()->get('id'),
						'interface' => $StorageNode->get('interface'),
						'stateID' => '0',
						'starttime' => date('Y-m-d H:i:s'),
						'percent' => '0',
						'isDD' => $this->getImage()->get('imageTypeID'),
						'NFSGroupID' => $StorageNode->get('storageGroupID'),
					));
					if ($MulticastSession->save())
					{
						// Sets a new port number so you can create multiple Multicast Tasks.
						$randomnumber = mt_rand(24576,32766)*2;
						while ($randomnumber == $MulticastSession->get('port'))
						{
							$randomnumber = mt_rand(24576,32766)*2;
						}
						$this->FOGCore->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
						// Create the Association.
						$MulticastSessionAssoc = new MulticastSessionsAssociation(array(
							'msID' => $MulticastSession->get('id'),
							'tID' => $Task->get('id'),
						));
						$MulticastSessionAssoc->save();
					}
				}
				// Otherwise find the associations and link them as necessary.
				else
				{
					$MulticastSession = $MultiSessAssoc;
					// If the image id's are the same, link the tasks, TODO:
					// Means of kill current task created to start new task
					// with new associations.
					$MulticastSessionAssoc = new MulticastSessionsAssociation(array(
						'msID' => $MultiSessAssoc->get('id'),
						'taskID' => $Task->get('id'),
					));
					$MulticastSessionAssoc->save();
					$MulticastSession->set('stateID',1);
				}
			}
			// Snapin deploy/cancel after deploy
			if (!$isUpload && $deploySnapins && $taskTypeID != '12' && $taskTypeID != '13' && $taskTypeID != '17')
			{
				// Remove any exists snapin tasks
				$SnapinJobs = $this->FOGCore->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id')));
				foreach ($SnapinJobs AS $SnapinJob)
				{
					$SnapinTasks = $this->FOGCore->getClass('SnapinTaskManager')->find(array('jobID' => $SnapinJob->get('id')));
					foreach ($SnapinTasks AS $SnapinTask)
						$SnapinTask->destroy();
					$SnapinJob->destroy();
				}
				// Check if there's any snapins assigned to the host.
				$SnapinAssoc = $this->FOGCore->getClass('SnapinAssociationManager')->find(array('hostID' => $this->get('id')));
				if ($this->FOGCore->getClass('SnapinAssociationmanager')->count(array('hostID' => $this->get('id'))) > 0)
				{
					// now do a clean snapin deploy
					$SnapinJob = new SnapinJob(array(
						'hostID' => $this->get('id'),
						'createTime' => date('Y-m-d H:i:s'),
					));
					if ($SnapinJob->save())
					{
						$SnapinAssoc = $this->FOGCore->getClass('SnapinAssociationManager')->find(array('hostID' => $this->get('id')));
						foreach ($SnapinAssoc AS $SA)
						{
							$Snapin = new Snapin($SA->get('snapinID'));
							$SnapinTask = new SnapinTask(array(
								'jobID' => $SnapinJob->get('id'),
								'stateID' => -1,
								'snapinID' => $Snapin->get('id'),
							));
							$SnapinTask->save();
						}
					}
				}
			}
			// Wake Host
			$this->wakeOnLAN();
			// Log History event
			$this->FOGCore->logHistory(sprintf('Task Created: Task ID: %s, Task Name: %s, Host ID: %s, Host Name: %s, Host MAC: %s, Image ID: %s, Image Name: %s', $Task->get('id'), $Task->get('name'), $this->get('id'), $this->get('name'), $this->getMACAddress(), $this->getImage()->get('id'), $this->getImage()->get('name')));
			return $Task;
		}
		catch (Exception $e)
		{
			// Failure
			throw new Exception($e->getMessage());
		}
	}
	function createSingleRunScheduledPackage($taskTypeID, $taskName = '', $scheduledDeployTime, $enableShutdown = false, $enableSnapins = true, $isGroupTask = false, $username = '',$passreset = null)
	{
		try
		{
			// Varaibles
			$findWhere = array(
				'isActive' 	=> '1',
				'isGroupTask' 	=> $isGroupTask,
				'taskType' 	=> $taskTypeID,
				'type' 		=> 'S',		// S = Single Schedule Deployment, C = Cron-style Schedule Deployment
				'hostID' 	=> $this->get('id'),
				'scheduleTime'	=> $scheduledDeployTime,
				'other3'		=> $username,
			);
			// Error checking
			if ($scheduledDeployTime < time())
				throw new Exception(sprintf(_('Scheduled date is in the past. Date: %s'), date('Y/d/m H:i', $scheduledDeployTime)));
			if ($this->FOGCore->getClass('ScheduledTaskManager')->count($findWhere))
				throw new Exception(_('A task already exists for this Host at this scheduled date & time'));
			// TaskType: Variables
			$TaskType = new TaskType($taskTypeID);
			$isUpload = $TaskType->isUpload();
			// TaskType: Error checking
			if (!$TaskType->isValid())
				throw new Exception(_('Task Type is not valid'));
			// Task: Merge $findWhere array with other Task data -> Create ScheduledTask Object
			$Task = new ScheduledTask(array_merge($findWhere, array(
				'name'		=> 'Scheduled Task',
				'shutdown'	=> ($enableShutdown ? '1' : '0'),
				'other1'	=> ($isUpload && $enableSnapins ? '1' : '0'),
				'other2'	=> ($enableSnapins ? $enableSnapins : ''),
				'other3'	=> ($username ? $username : ($passreset ? $passreset : '')),
			)));
			// Save
			if (!$Task->save())
				throw new Exception(_('Task creation failed'));
			// Log History event
			$this->FOGCore->logHistory(sprintf('Scheduled Task Created: Task ID: %s, Task Name: %s, Host ID: %s, Host Name: %s, Host MAC: %s, Image ID: %s, Image Name: %s', $Task->get('id'), $Task->get('name'), $this->get('id'), $this->get('name'), $this->getMACAddress(), $this->getImage()->get('id'), $this->getImage()->get('name')));
			// Return
			return $Task;
		}
		catch (Exception $e)
		{
			// Failure
			throw new Exception($e->getMessage());
		}
	}
	public function getImageMemberFromHostID()
	{
		try
		{
			$Image = $this->getImage();
			if(!$Image->get('id'))
				throw new Exception('No Image defined for this host');
			$StorageGroup = $Image->getStorageGroup();
			if(!$StorageGroup->get('id'))
				throw new Exception('No StorageGroup defined for this host');
			$Task = new Task(array(
				'hostID' => $this->get('id'),
				'NFSGroupID' => $StorageGroup->get('id'),
				'NFSMemberID' => $StorageGroup->getOptimalStorageNode()->get('id')
			));
			return $Task;
		}
		catch (Exception $e)
		{
			$this->FOGCore->error(sprintf('%s():xError: %s', __FUNCTION__, $e->getMessage()));
		}
		return false;
	}
	public function clearAVRecordsForHost()
	{
		$this->FOGCore->getClass('VirusManager')->destroy(array('hostMAC' => $this->getMACAddress()->getMACWithColon()));
	}
	function createCronScheduledPackage($taskTypeID, $taskName = '', $minute = 1, $hour = 23, $dayOfMonth = '*', $month = '*', $dayOfWeek = '*', $enableShutdown = false, $enableSnapins = true, $isGroupTask = false, $username = '')
	{
		try
		{
			// Error checking
			if ($minute != '*' && ($minute < 0 || $minute > 59))
				throw new Exception(_('Minute value is not valid'));
			if ($hour != '*' && ($hour < 0 || $hour > 23))
				throw new Exception(_('Hour value is not valid'));
			if ($dayOfMonth != '*' && ($dayOfMonth < 0 || $dayOfMonth > 31))
				throw new Exception(_('Day of Month value is not valid'));
			if ($month != '*' && ($month < 0 || $month > 12))
				throw new Exception(_('Month value is not valid'));
			if ($dayOfWeek != '*' && ($dayOfWeek < 0 || $dayOfWeek > 6))
				throw new Exception(_('Day of Week value is not valid'));
			// Variables
			$findWhere = array(
				'isActive' 	=> '1',
				'isGroupTask' 	=> $isGroupTask,
				'taskType' 	=> $taskTypeID,
				'type' 		=> 'C',		// S = Single Schedule Deployment, C = Cron-style Schedule Deployment
				'hostID' 	=> $this->get('id'),
				'minute' 	=> $minute,
				'hour' 		=> $hour,
				'dayOfMonth' 	=> $dayOfMonth,
				'month' 	=> $month,
				'dayOfWeek' 	=> $dayOfWeek,
				'other3' => $username,
			);
			// Error checking: Active Scheduled Task
			if ($this->FOGCore->getClass('ScheduledTaskManager')->count($findWhere))
				throw new Exception(_('A task already exists for this Host at this cron schedule'));
			// TaskType: Variables
			$TaskType = new TaskType($taskTypeID);
			$isUpload = $TaskType->isUpload();
			// TaskType: Error checking
			if (!$TaskType->isValid())
				throw new Exception(_('Task Type is not valid'));
			// Task: Merge $findWhere array with other Task data -> Create ScheduledTask Object
			$Task = new ScheduledTask(array_merge($findWhere, array(
				'name'		=> 'Scheduled Task',
				'shutdown'	=> ($enableShutdown ? '1' : '0'),
				'other1'	=> ($isUpload && $enableSnapins ? '1' : '0'),
				'other2'	=> ($enableSnapins ? $enableSnapins : '')
			)));
			// Task: Save
			if (!$Task->save())
				throw new Exception(_('Task creation failed'));
			// Log History event
			$this->FOGCore->logHistory(sprintf('Cron Task Created: Task ID: %s, Task Name: %s, Host ID: %s, Host Name: %s, Host MAC: %s, Image ID: %s, Image Name: %s', $Task->get('id'), $Task->get('name'), $this->get('id'), $this->get('name'), $this->getMACAddress(), $this->getImage()->get('id'), $this->getImage()->get('name')));
			// Return
			return $Task;
		}
		catch (Exception $e)
		{
			// Failure
			throw new Exception($e->getMessage());
		}
	}
	public function wakeOnLAN()
	{
		$this->FOGCore->wakeOnLAN($this->get('mac'));
	}
	// Printer Management
	public function getPrinters()
	{
		return $this->get('printers');
	}
	public function addPrinter($addArray)
	{
		// Add
		foreach ((array)$addArray AS $item)
			$this->add('printers', $item);
		// Return
		return $this;
	}
	public function removePrinter($removeArray)
	{
		// Iterate array (or other as array)
		foreach ((array)$removeArray AS $remove)
			$this->remove('printers', ($remove instanceof Printer ? $remove : new Printer((int)$remove)));
		// Return
		return $this;
	}
	public function addAddMAC($addArray)
	{
		// Add
		foreach ((array)$addArray AS $item)
		{
			$NewMACAdd = new MACAddressAssociation(array(
				'hostID' => $this->get('id'),
				'mac' => $item,
			));
			$NewMACAdd->save();
			$this->add('additionalMACs',$NewMACAdd);
		}
		// Return
		return $this;
	}
	public function addPendtoAdd($MAC)
	{
		$NewMACAdd = new MACAddressAssociation(array(
			'hostID' => $this->get('id'),
			'mac' => $MAC,
		));
		$NewMACAdd->save();
		$this->FOGCore->getClass('PendingMACManager')->destroy(array('hostID' => $this->get('id'),'pending' => $MAC));
	}
	public function removeAddMAC($removeArray)
	{
		$this->FOGCore->getClass('MACAddressAssociationManager')->destroy(array('hostID' => $this->get('id'),'mac' => $removeArray));
		$this->remove('additionalMACs', $removeArray);
		// Return
		return $this;
	}
	public function addPendMAC($MAC)
	{
		$NewMACAdd = new PendingMAC(array(
			'hostID' => $this->get('id'),
			'pending' => $MAC,
		));
		return ($NewMACAdd->save());
	}
	public function removePendMAC($removeArray)
	{
		$this->FOGCore->getClass('PendingMACManager')->destroy(array('hostID' => $this->get('id'),'pending' => $removeArray));
		$this->remove('pendingMACs', $removeArray);
		// Return
		return $this;
	}
	// Snapin Management
	public function getSnapins()
	{
		return $this->get('snapins');
	}
	public function addSnapin($addArray)
	{
		// Add
		foreach ((array)$addArray AS $item)
			$this->add('snapins', $item);
		// Return
		return $this;
	}
	public function removeSnapin($removeArray)
	{
		// Iterate array (or other as array)
		foreach ((array)$removeArray AS $remove)
			$this->remove('snapins', ($remove instanceof Snapin ? $remove : new Snapin((int)$remove)));
		// Return
		return $this;
	}
	// Modules
	public function getModules()
	{
		return $this->get('modules');
	}
	// Groups
	public function getGroups()
	{
		return $this->get('groups');
	}
	public function addModule($addArray)
	{
		// Add
		foreach ((array)$addArray AS $item)
			$this->add('modules', $item);
		// Return
		return $this;
	}
	public function removeModule($removeArray)
	{
		// Iterate array (or other as array)
		foreach ((array)$removeArray AS $remove)
			$this->remove('modules', ($remove instanceof Module ? $remove : new Module((int)$remove)));
		// Return
		return $this;
	}
	public function addGroup($addArray)
	{
		// Add
		foreach((array)$addArray AS $item)
			$this->add('groups', $item);
		// Return
		return $this;
	}
	public function removeGroup($removeArray)
	{
		// Iterate array (or other as array)
		foreach ((array)$removeArray AS $remove)
			$this->remove('groups', ($remove instanceof Group ? $remove : new Group((int)$remove)));
		// Return
		return $this;
	}
	public function destroy($field = 'id')
	{
		// Complete active tasks
		if (current($this->get('task'))->isValid())
			current($this->get('task'))->set('stateID',5)->save();
		// Remove Group associations
		$this->FOGCore->getClass('GroupAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Module associations
		$this->FOGCore->getClass('ModuleAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Snapin associations
		$this->FOGCore->getClass('SnapinAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Printer associations
		$this->FOGCore->getClass('PrinterAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Pending MAC Associations
		$this->FOGCore->getClass('PendingMACManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Additional MAC Associations
		$this->FOGCore->getClass('MACAddressAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Update inventory to know when it was deleted
		if ($this->get('inventory'))
			current($this->get('inventory'))->set('deleteDate',date('Y-m-d H:i:s'))->save();
		// Return
		return parent::destroy($field);
	}
}
