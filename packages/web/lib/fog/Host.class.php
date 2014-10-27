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
		'useAD'		=> 'hostUseAD',
		'ADDomain'	=> 'hostADDomain',
		'ADOU'		=> 'hostADOU',
		'ADUser'	=> 'hostADUser',
		'ADPass'	=> 'hostADPass',
		'productKey' => 'hostProductKey',
		'printerLevel'	=> 'hostPrinterLevel',
		'kernel'	=> 'hostKernel',
		'kernelArgs'	=> 'hostKernelArgs',
		'kernelDevice'	=> 'hostDevice',
		'pending' => 'hostPending',
	);
	// Allow setting / getting of these additional fields
	public $additionalFields = array(
		'mac',
		'additionalMACs',
		'pendingMACs',
		'groups',
		'optimalStorageNode',
		'printers',
		'snapins',
		'modules',
		'inventory',
		'task',
		'snapinjob',
		'users',
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'name',
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
		return $this->get('mac');
	}
	public function getDefault($printerid)
	{
		$PrinterMan = current($this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id'),'printerID' => $printerid)));
		return ($PrinterMan && $PrinterMan->isValid() ? $PrinterMan->get('isDefault') : false);
	}
	public function updateDefault($printerid,$onoff)
	{
		$PrinterAssoc = $this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id')));
		foreach($PrinterAssoc AS $PrinterSet)
		{
			$PrinterSet->set('isDefault',0)->save();
			if ($PrinterSet->get('printerID') == $printerid)
				$PrinterSet->set('isDefault',$onoff)->save();
		}
		return $this;
	}
	public function getDispVals($key = '')
	{
		$keyTran = array(
			'width' => 'FOG_SERVICE_DISPLAYMANAGER_X',
			'height' => 'FOG_SERVICE_DISPLAYMANAGER_Y',
			'refresh' => 'FOG_SERVICE_DISPLAYMANAGER_R',
		);
		$HostScreen = current($this->getClass('HostScreenSettingsManager')->find(array('hostID' => $this->get('id'))));
		$Service = current($this->getClass('ServiceManager')->find(array('name' => $keyTran[$key])));
		return ($HostScreen && $HostScreen->isValid() ? $HostScreen->get($key) : ($Service && $Service->isValid() ? $Service->get('value') : ''));
	}
	public function setDisp($x,$y,$r)
	{
		$this->getClass('HostScreenSettingsManager')->destroy(array('hostID' => $this->get('id')));
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
		$HostALO = current($this->getClass('HostAutoLogoutManager')->find(array('hostID' => $this->get('id'))));
		$Service = current($this->getClass('ServiceManager')->find(array('name' => 'FOG_SERVICE_AUTOLOGOFF_MIN')));
		return ($HostALO && $HostALO->isValid() ? $HostALO->get('time') : ($Service && $Service->isValid() ? $Service->get('value') : ''));
	}
	public function setAlo($tme)
	{
		$this->getClass('HostAutoLogoutManager')->destroy(array('hostID' => $this->get('id')));
		$HostALO = new HostAutoLogout(array(
			'hostID' => $this->get('id'),
			'time' => $tme,
		));
		$HostALO->save();
	}
	public function getActiveSnapinJob()
	{
		// Find Active Snapin Task, there should never be more than one per host.
		$SnapinJob = current($this->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id'))));
		if (!$SnapinJob)
			throw new Exception(sprintf('%s: %s (%s)', $this->foglang['NoActSnapJobs'], $this->get('name'), $this->get('mac')));
		return $SnapinJob;
	}
	private function loadSnapinJob()
	{
		if (!$this->isLoaded('snapinjob'))
		{
			if ($this->get('id'))
			{
				$SnapinJobs = $this->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id'),'stateID' => array(-1,0,1)));
				foreach ($SnapinJobs AS $SnapinJob)
					$this->add('snapinjob', new SnapinJob($SnapinJob->get('id')));
			}
		}
		return $this;
	}
	private function loadPrimary()
	{
		if (!$this->isLoaded('mac'))
		{
			if ($this->get('id'))
			{
				$PriMAC = $this->getClass('MACAddressAssociationManager')->find(array('hostID' => $this->get('id'),'primary' => 1));
				foreach($PriMAC AS $MAC)
				{
					if ($MAC && $MAC->isValid())
						$this->set('mac',new MACAddress($MAC->get('mac')));
				}
			}
		}
		return $this;
	}
	private function loadAdditional()
	{
		if (!$this->isLoaded('additionalMACs'))
		{
			if ($this->get('id'))
			{
				$AdditionalMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID' => $this->get('id'),'primary' => 0,'pending' => 0));
				foreach($AdditionalMACs AS $MAC)
				{
					if ($MAC && $MAC->isValid())
						$this->add('additionalMACs', new MACAddress($MAC->get('mac')));
				}
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
				$PendMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID' => $this->get('id'),'pending' => 1));
				foreach($PendMACs AS $MAC)
				{
					if ($MAC && $MAC->isValid() && $MAC->get('pending'))
						$this->add('pendingMACs', new MACAddress($MAC->get('mac')));
				}
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
				$Printers = $this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id')));
				foreach($Printers AS $Printer)
					$this->add('printers',new Printer($Printer->get('printerID')));
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
				$Groups = $this->getClass('GroupAssociationManager')->find(array('hostID' => $this->get('id')));
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
				$Inventories = $this->getClass('InventoryManager')->find(array('hostID' => $this->get('id')));
				foreach($Inventories AS $Inventory)
				{
					if ($Inventory && $Inventory->isValid())
						$this->set('inventory',$Inventory);
					else
						$this->set('inventory',new Inventory(array('id' => -1)));
				}
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
				$Modules = $this->getClass('ModuleAssociationManager')->find(array('hostID' => $this->get('id')));
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
				$Snapins = $this->getClass('SnapinAssociationManager')->find(array('hostID' => $this->get('id')));
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
				$Task = current($this->getClass('TaskManager')->find(array('hostID' => $this->get('id'),'stateID' => array(1,2,3))));
				if ($Task && $Task->isValid())
					$this->set('task',$Task);
				else
					$this->set('task',new Task(array('id' => 0)));
			}
		}
		return $this;
	}
	private function loadUsers()
	{
		if (!$this->isLoaded('users'))
		{
			if ($this->get('id'))
			{
				$Users = $this->getClass('UserTrackingManager')->find(array('hostID' => $this->get('id')));
				foreach($Users AS $User)
				{
					if ($User && $User->isValid())
						$this->add('users', $User);
				}
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
		else if ($this->key($key) == 'pendingMACs')
			$this->loadPending();
		else if ($this->key($key) == 'mac')
			$this->loadPrimary();
		else if ($this->key($key) == 'snapins')
			$this->loadSnapins();
		else if ($this->key($key) == 'snapinjob')
			$this->loadSnapinJob();
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
		else if ($this->key($key) == 'users')
			$this->loadUsers();
		return parent::get($key);
	}
	public function set($key, $value)
	{
		// MAC Address
		if (($this->key($key) == 'mac') && !($value instanceof MACAddress))
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
		// SnapinJob
		else if ($this->key($key) == 'snapinjob')
		{
			$this->loadSnapinJob();
			foreach ((array)$value AS $snapinjob)
				$newValue[] = ($snapinjob instanceof SnapinJob ? $snapinjob : new SnapinJob($snapinjob));
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
		else if (($this->key($key) == 'inventory') && !($value instanceof Inventory))
			$value = new Inventory($value);
		// Groups
		else if ($this->key($key) == 'groups')
		{
			$this->loadGroups();
			foreach ((array)$value AS $group)
				$newValue[] = ($group instanceof Group ? $group : new Group($group));
			$value = (array)$newValue;
		}
		// Task
		else if (($this->key($key) == 'task') && !($value instanceof Task))
			$value = new Task($value);
		// Users
		else if ($this->key($key) == 'users')
		{
			$this->loadUsers();
			foreach ((array)$value AS $user)
				$newValue[] = ($user instanceof UserTracking ? $user : new UserTracking($user));
			$value = (array)$newValue;
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
		// SnapinJob
		else if ($this->key($key) == 'snapinjob' && !($value instanceof SnapinJob))
		{
			$this->loadSnapinJob();
			$value = new SnapinJob($value);
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
		// Users
		else if ($this->key($key) == 'users' && !($value instanceof UserTracking))
		{
			$this->loadUsers();
			$value = new UserTracking($value);
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
		// SnapinJob
		else if ($this->key($key) == 'snapinjob')
			$this->loadSnapinJob();
		// Modules
		else if ($this->key($key) == 'modules')
			$this->loadModules();
		// Groups
		else if ($this->key($key) == 'groups')
			$this->loadGroups();
		// Users
		else if ($this->key($key) == 'users')
			$this->loadUsers();
		// Remove
		return parent::remove($key, $object);
	}
	public function save()
	{
		// Save
		parent::save();
		// Primary MAC Addresses
		if ($this->isLoaded('mac'))
		{
			$me = current($this->getClass('MACAddressAssociationManager')->find(array('hostID' => $this->get('id'),'primary' => 1)));
			// Remove Existing Primary MAC Addresses
			$this->getClass('MACAddressAssociationManager')->destroy(array('hostID' => $this->get('id'),'primary' => 1));
			// Add new Pending MAC Addresses
			if (($this->get('mac') instanceof MACAddress) && $this->get('mac')->isValid())
			{
				$NewMAC = new MACAddressAssociation(array(
					'hostID' => $this->get('id'),
					'mac' => $this->get('mac'),
					'primary' => 1,
					'clientIgnore' => $me->get('clientIgnore'),
					'imageIgnore' => $me->get('imageIgnore'),
				));
				$NewMAC->save();
			}
		}
		// Printers
		if ($this->isLoaded('printers'))
		{
			// Find the current default
			$defPrint = current($this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id'),'isDefault' => 1)));
			// Remove old rows
			$this->getClass('PrinterAssociationManager')->destroy(array('hostID' => $this->get('id')));
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
						'isDefault' => ($defPrint && $defPrint->isValid() ? $defPrint->get('id') : ($i === 0 ? '1' : '0')),
					));
					$NewPrinter->save();
				}
				$i++;
			}
		}
		// Snapins
		if ($this->isLoaded('snapins'))
		{
			// Remove old rows
			$this->getClass('SnapinAssociationManager')->destroy(array('hostID' => $this->get('id')));
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
		if ($this->isLoaded('modules'))
		{
			// Remove old rows
			$this->getClass('ModuleAssociationManager')->destroy(array('hostID' => $this->get('id')));
			// Create assoc
			foreach ((array)$this->get('modules') AS $Module)
			{
				$moduleName = array(
					'autologout' => 'FOG_SERVICE_AUTOLOGOFF_ENABLED',
					'clientupdater' => 'FOG_SERVICE_CLIENTUPDATER_ENABLED',
					'dircleanup' => 'FOG_SERVICE_DIRECTORYCLEANER_ENABLED',
					'displaymanager' => 'FOG_SERVICE_DISPLAYMANAGER_ENABLED',
					'greenfog' => 'FOG_SERVICE_GREENFOG_ENABLED',
					'hostregister' => 'FOG_SERVICE_HOSTREGISTER_ENABLED',
					'hostnamechanger' => 'FOG_SERVICE_HOSTNAMECHANGER_ENABLED',
					'printermanager' => 'FOG_SERVICE_PRINTERMANAGER_ENABLED',
					'snapin' => 'FOG_SERVICE_SNAPIN_ENABLED',
					'snapinclient' => 'FOG_SERVICE_SNAPIN_ENABLED',
					'taskreboot' => 'FOG_SERVICE_TASKREBOOT_ENABLED',
					'usercleanup' => 'FOG_SERVICE_USERCLEANUP_ENABLED',
					'usertracker' => 'FOG_SERVICE_USERTRACKER_ENABLED',
				);
				if (($Module instanceof Module) && $Module->isValid())
				{
					if ($Module->get('isDefault') && $this->FOGCore->getSetting($moduleName[$Module->get('shortName')]))
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
		}
		// Groups
		if ($this->isLoaded('groups'))
		{
			// Remove old rows
			$this->getClass('GroupAssociationManager')->destroy(array('hostID' => $this->get('id')));
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
		// Users
		if ($this->isLoaded('users'))
		{
			// Remove old rows
			$this->getClass('UserTrackingManager')->destroy(array('hostID' => $this->get('id')));
			// Create Assoc
			foreach ((array)$this->get('users') AS $User)
			{
				if (($User instanceof UserTracking) && $User->isValid())
				{
					$NewUser = new GroupAssociation(array(
						'hostID' => $this->get('id'),
						'username' => $User->get('username'),
						'action' => $User->get('action'),
						'datetime' => $User->get('datetime'),
						'description' => $User->get('description'),
						'date' => $User->get('date'),
					));
					$NewUser->save();
				}
			}
		}
		// Return
		return $this;
	}
	public function isValid()
	{
		return (($this->get('id') != '' || !(HostManager::isHostnameSafe($this->get('name')))) && $this->getMACAddress() != '' ? true : false);
	}
	// Custom functions
	public function getActiveTaskCount()
	{
		return $this->getClass('TaskManager')->count(array('stateID' => array(1, 2, 3), 'hostID' => $this->get('id')));
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
		// TaskType: Variables
		$TaskType = new TaskType($taskTypeID);
		$isUpload = $TaskType->isUpload();
		// Image: Variables
		$Image = $this->getImage();
		$StorageGroup = $Image->getStorageGroup();
		$StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $this->getOptimalStorageNode());
		if (!$isUpload)
			$HookManager->processEvent('HOST_NEW_SETTINGS',array('Host' => &$this,'StorageNode' => &$StorageNode,'StorageGroup' => &$StorageGroup));
		if (!$StorageGroup || !$StorageGroup->isValid())
			throw new Exception(_('No Storage Group found for this image'));
		if (!$StorageNode || !$StorageNode->isValid())
			throw new Exception(_('No Storage Node found for this image'));
		if (in_array($TaskType->get('id'),array('1','8','15','17')) && in_array($Image->get('osID'), array('5', '6', '7')))
		{
			// FTP
			$this->FOGFTP->set('username',$StorageNode->get('user'))
				 ->set('password',$StorageNode->get('pass'))
				 ->set('host',$StorageNode->get('ip'));
			if ($this->FOGFTP->connect())
			{
				if(!$this->FOGFTP->chdir(rtrim($StorageNode->get('path'),'/').'/'.$Image->get('path')))
					return false;
			}
			$this->FOGFTP->close();
		}
		return true;
	}

	// Should be called: createDeployTask
	public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '')
	{
		try
		{
			// TaskType: Variables
			$TaskType = new TaskType($taskTypeID);
			// Imaging types.
			$imagingTypes = in_array($taskTypeID,array(1,2,8,15,16,17,24)) ? true : false;
			$isUpload = $TaskType->isUpload();
			// Image: Variables
			$Image = $this->getImage();
			$StorageGroup = $Image->getStorageGroup();
			$StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $this->getOptimalStorageNode());
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
					'NFSGroupID' => $imagingTypes ? ($LocPlugInst ? $StorageGroup->get('id') : $Image->getStorageGroup()->get('id')) : false,
					'NFSMemberID'	=> $imagingTypes ? ($LocPlugInst ? $StorageGroup->getOptimalStorageNode()->get('id') : ($this->get('imageID') ? $Image->getStorageGroup()->getOptimalStorageNode()->get('id') : $StorageGroup->getOptimalStorageNode()->get('id'))) : false,
					'shutdown' => $shutdown,
					'isDebug' => intval($debug),
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
					throw new Exception($this->foglang['FailedTask']);
				}
			}
			// Snapin deploy/cancel only if task type is of snapin deployment type.
			if (!$isUpload && $deploySnapins && ($taskTypeID == '12' || $taskTypeID == '13'))
			{
				$count = 0;
				foreach((array)$this->get('snapins') AS $SnapinInHost)
					$SnapinInHost && $SnapinInHost->isValid() ? $count++ : null;
				if ($count <= 0)
					throw new Exception($this->foglang['SnapNoAssoc']);
				// Task: Create Task Object
				$Task = new Task(array(
					'name'		=> $taskName,
					'createdBy'	=> ($this->FOGUser ? $this->FOGUser : ($username ? $username : 'nobody')),
					'hostID'	=> $this->get('id'),
					'isForced'	=> 0,
					'stateID'	=> 1,
					'typeID'	=> $taskTypeID, 
					'NFSGroupID' 	=> $imagingTypes ? $StorageGroup->get('id') : false,
					'NFSMemberID'	=> $imagingTypes ? $StorageGroup->getOptimalStorageNode()->get('id') : false,
					'shutdown' => $shutdown,
					'passreset' => $passreset,	
					'isDebug' => intval($debug),
				));
				$SnapinJobs = current($this->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id'),'stateID' => array(0,1))));
				if ($SnapinJobs && $SnapinJobs->isValid() && $deploySnapins == -1)
					throw new Exception($this->foglang['SnapDeploy']);
				else
				{
					// Create Snapin Job.  Only one job, but will do multiple SnapinTasks.
					$SnapinJob = new SnapinJob(array(
						'hostID' => $this->get('id'),
						'stateID' => 0,
						'createdTime' => $this->nice_date()->format('Y-m-d H:i:s'),
					));
					// Create Snapin Tasking
					if ($SnapinJob->save())
					{
						// If -1 for the snapinID sent, it needs to set a task for all of the snapins associated to that host.
						if ($deploySnapins == -1)
						{
							$SnapinAssoc = $this->getClass('SnapinAssociationManager')->find(array('hostID' => $this->get('id')));
							foreach ((array)$SnapinAssoc AS $SA)
							{
								$SnapinTask = current($this->getClass('SnapinTaskManager')->find(array('snapinID' => $SA->get('snapinID'), 'stateID' => array(-1,0,1))));
								if ($SnapinTask && $SnapinTask->isValid())
									$SnapinJobCheck = current($this->getClass('SnapinJobManager')->find(array('id' => $SnapinTask->get('jobID'), 'hostID' => $this->get('id'),'stateID' => array(-1,0,1))));
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
							$SnapinTask = current($this->getClass('SnapinTaskManager')->find(array('snapinID' => $Snapin->get('id'), 'stateID' => array(-1,0,1))));
							if ($SnapinTask && $SnapinTask->isValid())
								$SnapinJobCheck = current($this->getClass('SnapinJobManager')->find(array('id' => $SnapinTask->get('jobID'), 'hostID' => $this->get('id'),'stateID' => array(-1,0,1))));
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
								throw new Exception($this->foglang['SnapDeploy']);
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
					throw new Exception($this->foglang['FailedTask']);
				}
			}
			// Error checking
			if ($taskTypeID != 12 && $taskTypeID != 13 && $this->getActiveTaskCount())
				throw new Exception($this->foglang['InTask']);
			if (!$this->isValid())
				throw new Exception($this->foglang['HostNotValid']);
			// TaskType: Error checking
			if (!$TaskType->isValid())
				throw new Exception($this->foglang['TaskTypeNotValid']);
			// Image: Error checking
			if ($imagingTypes && !$Image->isValid())
				throw new Exception($this->foglang['ImageNotValid']);
			if ($imagingTypes && !$Image->getStorageGroup()->isValid())
				throw new Exception($this->foglang['ImageGroupNotValid']);
			// Storage Node: Error Checking
			if ($imagingTypes && (!$StorageNode || !($StorageNode instanceof StorageNode)))
				throw new Exception($this->foglang['NoFoundSG']);
			if ($imagingTypes && !$StorageNode->isValid())
				throw new Exception($this->foglang['SGNotValid']);
			// Variables
			$mac = $this->getMACAddress()->__toString();
			// Task: Create Task Object
			$Task = new Task(array(
				'name'		=> $taskName,
				'createdBy'	=> ($this->FOGUser ? $this->FOGUser : ($username ? $username : '')),
				'hostID'	=> $this->get('id'),
				'isForced'	=> '0',
				'stateID'	=> '1',
				'typeID'	=> $taskTypeID, 
				'NFSGroupID' 	=> $imagingTypes ? $StorageGroup->get('id') : false,
				'NFSMemberID'	=> $imagingTypes ? $StorageGroup->getOptimalStorageNode()->get('id') : false,
				'shutdown' => $shutdown,
				'passreset' => $passreset,
				'isDebug' => intval($debug),
			));
			// Task: Save to database
			if (!$Task->save())
				throw new Exception($this->foglang['FailedTask']);
			// If task is multicast perform the following.
			if ($TaskType->isMulticast())
			{
				$MultiSessName = current($this->getClass('MulticastSessionsManager')->find(array('name' => $taskName)));
				$MultiSessAssoc = current($this->getClass('MulticastSessionsManager')->find(array('image' => $this->getImage()->get('id'))));
				if ($MultiSessName && $MultiSessName->isValid() && !$MultiSessName->get('stateID'))
					$MulticastSession = $MultiSessName;
				else if ($MultiSessAssoc && $MultiSessAssoc->isValid() && !$MultiSessAssoc->get('stateID'))
					$MulticastSession = $MultiSessAssoc;
				else
				{
					// Create New Multicast Session Job
					$MulticastSession = new MulticastSessions(array(
						'name' => $taskName,
						'port' => $this->FOGCore->getSetting('FOG_UDPCAST_STARTINGPORT'),
						'logpath' => $this->getImage()->get('path'),
						'image' => $this->getImage()->get('id'),
						'interface' => $StorageNode->get('interface'),
						'stateID' => 0,
						'starttime' => $this->nice_date()->format('Y-m-d H:i:s'),
						'percent' => 0,
						'isDD' => $this->getImage()->get('imageTypeID'),
						'NFSGroupID' => $StorageNode->get('storageGroupID'),
					));
				}
				if ($MulticastSession->save())
				{
					// Sets a new port number so you can create multiple Multicast Tasks.
					$randomnumber = mt_rand(24576,32766)*2;
					while ($randomnumber == $MulticastSession->get('port'))
						$randomnumber = mt_rand(24576,32766)*2;
					$this->FOGCore->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
				}
				// If the image id's are the same, link the tasks, TODO:
				// Create the Association.
				$MulticastSessionAssoc = new MulticastSessionsAssociation(array(
					'msID' => $MulticastSession->get('id'),
					'taskID' => $Task->get('id'),
				));
				$MulticastSessionAssoc->save();
			}
			// Snapin deploy/cancel after deploy
			if (!$isUpload && $deploySnapins && $imagingTypes && $taskTypeID != '17')
			{
				$count = 0;
				foreach((array)$this->get('snapins') AS $SnapinInHost)
					$SnapinInHost && $SnapinInHost->isValid() ? $count++ : null;
				// Remove any exists snapin tasks
				$SnapinJobs = $this->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id')));
				foreach ((array)$SnapinJobs AS $SnapinJob)
				{
					$this->getClass('SnapinTaskManager')->destroy(array('jobID' => $SnapinJob->get('id')));
					$SnapinJob->destroy();
				}
				// Check if there's any snapins assigned to the host.
				if ($count > 0)
				{
					// now do a clean snapin deploy
					$SnapinJob = new SnapinJob(array(
						'hostID' => $this->get('id'),
						'createdTime' => $this->nice_date()->format('Y-m-d H:i:s'),
					));
					if ($SnapinJob->save())
					{
						foreach ((array)$this->get('snapins') AS $Snapin)
						{
							if ($SnapinInHost && $SnapinInHost->isValid())
							{
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
		$this->getClass('VirusManager')->destroy(array('hostMAC' => $this->getMACAddress()->__toString()));
	}
	public function wakeOnLAN()
	{
		$this->FOGCore->wakeOnLAN($this->get('mac'));
	}
	public function addPrinter($addArray)
	{
		// Check for existing.
		foreach((array)$this->get('printers') AS $Printer)
		{
			if ($Printer && $Printer->isValid())
				$PrinterIDs[] = $Printer->get('id');
		}
		$PrinterIDs = array_unique($PrinterIDs);
		// Add
		foreach ((array)$addArray AS $item)
		{
			if (!is_object($item) && !in_array($item,$PrinterIDs))
				$this->add('printers', $item);
			else if (is_object($item) && $item->isValid() && !in_array($item->get('id'),$PrinterIDs))
				$this->add('printers', $item);
		}
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
	public function addAddMAC($addArray,$pending = false,$primary = false,$clientIgnore = false,$imageIgnore = false)
	{
		foreach((array)$addArray AS $item)
		{
			if (!($item instanceof MACAddress))
				$item = new MACAddress($item);
			if (($item instanceof MACAddress) && $item->isValid())
			{
				$NewMAC = new MACAddressAssociation(array(
					'hostID' => $this->get('id'),
					'mac' => ($item instanceof MACAddress ? $item->__toString() : $item),
					'pending' => $pending,
					'primary' => $primary,
					'clientIgnore' => $clientIgnore,
					'imageIgnore' => $imageIgnore,
				));
				$NewMAC->save();
			}
		}
		// Return
		return $this;
	}
	public function addPendtoAdd($MAC)
	{	
		$this->removePendMAC($MAC);
		$this->addAddMAC($MAC);
		// Return
		return $this;
	}
	public function removeAddMAC($removeArray)
	{
		$this->getClass('MACAddressAssociationManager')->destroy(array('mac' => $removeArray));
		// Return
		return $this;
	}
	public function addPriMAC($MAC)
	{
		$this->addAddMAC($MAC,false,true);
		$this->set('mac',$MAC);
		return $this;
	}
	public function addPendMAC($MAC)
	{
		$this->addAddMAC($MAC,true);
		return $this;
	}
	public function removePendMAC($removeArray)
	{
		$this->getClass('MACAddressAssociationManager')->destroy(array('hostID' => $this->get('id'),'mac' => $removeArray,'pending' => 1));
		// Return
		return $this;
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
	public function ignore($imageIgnore,$clientIgnore)
	{
		$MyMACs[] = strtolower($this->get('mac')->__toString());
		foreach((array)$this->get('additionalMACs') AS $mac)
		{
			if ($mac && $mac->isValid())
				$MyMACs[] = strtolower($mac->__toString());
		}
		$MyMACs = array_unique($MyMACs);
		if ($imageIgnore)
		{
			$macs = $imageIgnore;
			$imageIgnore = null;
			foreach((array)$macs AS $mac)
				$imageIgnore[] = strtolower($mac);
		}
		if ($clientIgnore)
		{
			$macs = $clientIgnore;
			$clientIgnore = null;
			foreach((array)$macs AS $mac)
				$clientIgnore[] = strtolower($mac);
		}
		foreach((array)$MyMACs AS $mac)
		{
			$ignore = current($this->getClass('MACAddressAssociationManager')->find(array('mac' => $mac,'hostID' => $this->get('id'))));
			if (in_array($mac,(array)$imageIgnore))
				$ignore->set('imageIgnore',1)->save();
			else
				$ignore->set('imageIgnore',0)->save();
			if (in_array($mac,(array)$clientIgnore))
				$ignore->set('clientIgnore',1)->save();
			else
				$ignore->set('clientIgnore',0)->save();
		}
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
	public function clientMacCheck($MAC = false)
	{
		if (!$MAC)
			$MAC = $this->get('mac')->__toString();
		$mac = current($this->getClass('MACAddressAssociationManager')->find(array('mac' => $MAC,'hostID' => $this->get('id'),'clientIgnore' => 1)));
		return ($mac && $mac->isValid() ? 'checked="checked"' : '');
	}
	public function imageMacCheck($MAC = false)
	{
		if (!$MAC)
			$MAC = $this->get('mac')->__toString();
		$mac = current($this->getClass('MACAddressAssociationManager')->find(array('mac' => $MAC,'hostID' => $this->get('id'),'imageIgnore' => 1)));
		return ($mac && $mac->isValid() ? 'checked="checked"' : '');
	}
	public function setAD($useAD,$domain,$ou,$user,$pass)
	{
		$key = $this->FOGCore->getSetting('FOG_AES_ADPASS_ENCRYPT_KEY');
		if ($this->get('id'))
		{
			if ($this->FOGCore->getSetting('FOG_NEW_CLIENT') && $pass)
			{
				$decrypt = $this->aesdecrypt($pass,$key);
				if ($decrypt && mb_detect_encoding($decrypt,'UTF-8',true))
					$pass = $this->FOGCore->aesencrypt($decrypt,$key);
				else
					$pass = $this->FOGCore->aesencrypt($pass,$key);
			}
			$this->set('useAD',$useAD)
				 ->set('ADDomain',$domain)
				 ->set('ADOU',$ou)
				 ->set('ADUser',$user)
				 ->set('ADPass',$pass);
		}
		return $this;
	}

	public function destroy($field = 'id')
	{
		// Complete active tasks
		if ($this->get('task')->isValid())
			$this->get('task')->set('stateID',5)->save();
		$LocPlugInst = current($this->getClass('PluginManager')->find(array('name' => 'location')));
		if ($LocPlugInst)
			$this->getClass('LocationAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Snapinjob Associations
		foreach($this->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id'))) AS $SnapinJob)
		{
			if ($SnapinJob && $SnapinJob->isValid())
				$SnapinJob->set('stateID',5)->save();
		}
		// Remove Group associations
		$this->getClass('GroupAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Module associations
		$this->getClass('ModuleAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Snapin associations
		$this->getClass('SnapinAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Printer associations
		$this->getClass('PrinterAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Remove Additional MAC Associations
		$this->getClass('MACAddressAssociationManager')->destroy(array('hostID' => $this->get('id')));
		// Update inventory to know when it was deleted
		if ($this->get('inventory'))
			$this->get('inventory')->set('deleteDate',$this->nice_date()->format('Y-m-d H:i:s'))->save();
		// Return
		return parent::destroy($field);
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
