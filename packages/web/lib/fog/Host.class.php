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
		'pub_key' => 'hostPubKey',
	);
	// Allow setting / getting of these additional fields
	public $additionalFields = array(
		'mac',
		'image',
		'additionalMACs',
		'pendingMACs',
		'groups',
		'groupsnotinme',
		'optimalStorageNode',
		'printers',
		'printersnotinme',
		'snapins',
		'snapinsnotinme',
		'modules',
		'hardware',
		'inventory',
		'inv',
		'task',
		'snapinjob',
		'users',
		'fingerprint',
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'name',
	);
	// Class to field relationships
	public $databaseFieldClassRelationships = array(
		'Image' => array('id','imageID','image'),
		'Inventory' => array('hostID','id','inv'),
	);
	// Custom functons
	public function isHostnameSafe()
	{
		return (strlen($this->get('name')) > 0 && strlen($this->get('name')) <= 15 && preg_replace('#[0-9a-zA-Z_\-]#', '', $this->get('name')) == '');
	}
	// Load the items
	public function load($field = 'id')
	{
		parent::load($field);
		$this->getMACAddress();
		$this->getActiveSnapinJob();
		foreach(get_class_methods($this) AS $method)
		{
			if (strlen($method) > 5 && (strpos($method,'load') !== false))
				$this->$method();
		}
	}
	// Snapins
	public function getImage()
	{
		return ($this->get('image') ? $this->get('image') : new Image($this->get('imageID')));
	}
	public function getOS()
	{
		return $this->getImage()->getOS();
	}
	public function getMACAddress()
	{
		$this->set('mac', new MACAddress($this->get('mac')));
		return $this->get('mac');
	}
	public function getDefault($printerid)
	{
		$PrinterMan = current($this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id'),'printerID' => $printerid)));
		return $PrinterMan && $PrinterMan->isValid() && $PrinterMan->get('isDefault');
	}
	public function updateDefault($printerid,$onoff)
	{
		$PrinterAssoc = $this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id')));
		// Set all to not default
		foreach((array)$PrinterAssoc AS $PrinterSet)
		{
			if ($PrinterSet && $PrinterSet->isValid())
				$PrinterSet->set('isDefault',0)->save();
		}
		foreach((array)$printerid AS $printer)
		{
			$Printer = new Printer($printer);
			if ($Printer && $Printer->isValid())
				$SetDefault = current($this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id'),'printerID' => $Printer->get('id'))));
			// Set the current sent printer to it's on/off state.
			if ($SetDefault && $SetDefault->isValid())
				$SetDefault->set('isDefault',$onoff)->save();
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
		$HostScreen = current((array)$this->getClass('HostScreenSettingsManager')->find(array('hostID' => $this->get('id'))));
		$Service = current((array)$this->getClass('ServiceManager')->find(array('name' => $keyTran[$key])));
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
		// Clear Current setting
		$this->getClass('HostAutoLogoutManager')->destroy(array('hostID' => $this->get('id')));
		// Set new setting
		$HostALO = new HostAutoLogout(array(
			'hostID' => $this->get('id'),
			'time' => $tme,
		));
		$HostALO->save();
		unset($HostALO);
		return $this;
	}
	public function getActiveSnapinJob()
	{
		// Find Active Snapin Task, there should never be more than one per host.
		if (!$this->get('snapinjob'))
			throw new Exception(sprintf('%s: %s (%s)', $this->foglang['NoActSnapJobs'], $this->get('name'), $this->get('mac')));
		return $this->get('snapinjob');
	}
	private function loadSnapinJob()
	{
		if (!$this->isLoaded('snapinjob') && $this->get('id'))
			$this->set('snapinjob',current($this->getClass('SnapinJobManager')->find(array('stateID' => array(-1,0,1),'hostID' => $this->get('id')))));
		return $this;
	}
	private function loadPrimary()
	{
		if (!$this->isLoaded('mac') && $this->get('id'))
		{
			foreach($this->getClass('MACAddressAssociationManager')->find(array('primary' => 1,'hostID' => $this->get('id'))) AS $PriMAC)
				$this->set('mac',new MACAddress($PriMAC));
		}
		return $this;
	}
	private function loadAdditional()
	{
		if (!$this->isLoaded('additionalMACs') && $this->get('id'))
		{
			foreach($this->getClass('MACAddressAssociationManager')->find(array('hostID' => $this->get('id'),'primary' => 0,'pending' => 0)) AS $MACAdd)
				$this->add('additionalMACs',new MACAddress($MACAdd));
		}
		return $this;
	}
	private function loadPending()
	{
		if (!$this->isLoaded('pendingMACs') && $this->get('id'))
		{
			foreach($this->getClass('MACAddressAssociationManager')->find(array('hostID' => $this->get('id'),'primary' => 0,'pending' => 1)) AS $MACAdd)
				$this->add('pendingMACs',new MACAddress($MACAdd));
		}
		return $this;
	}
	private function loadPrinters()
	{
		if (!$this->isLoaded('printers') && $this->get('id'))
		{
			// Printers I have
			$PrinterIDs = array_unique($this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id')),'','','','','','','printerID'));
			$this->set('printers', $this->getClass('PrinterManager')->find(array('id' => $PrinterIDs)));
			$this->set('printersnotinme',$this->getClass('PrinterManager')->find(array('id' => $PrinterIDs),'','','','','',true));
			unset($PrinterIDs);
		}
		return $this;
	}
	private function loadGroups()
	{
		if (!$this->isLoaded('groups') && $this->get('id'))
		{
			// Groups I am in
			$GroupIDs = $this->getClass('GroupAssociationManager')->find(array('hostID' => $this->get('id')),'','','','','','','groupID');
			$this->set('groups',$this->getClass('GroupManager')->find(array('id' => $GroupIDs)));
			$this->set('groupsnotinme',$this->getClass('GroupManager')->find(array('id' => $GroupIDs),'','','','','',true));
		}
		return $this;
	}
	private function loadInventory()
	{
		if (!$this->isLoaded('inventory') && $this->get('id'))
			$this->set('inventory',$this->get('inv'));
		return $this;
	}
	private function loadModules()
	{
		if (!$this->isLoaded('modules') && $this->get('id'))
		{
			$ModuleIDs = $this->getClass('ModuleAssociationManager')->find(array('hostID' => $this->get('id')),'','','','','','','moduleID');
			foreach($this->getClass('ModuleManager')->find(array('id' => $ModuleIDs)) AS $Module)
				$this->add('modules', $Module);
		}
		return $this;
	}
	private function loadSnapins()
	{
		if (!$this->isLoaded('snapins') && $this->get('id'))
		{
			$SnapinIDs = $this->getClass('SnapinAssociationManager')->find(array('hostID' => $this->get('id')),'','','','','','','snapinID');
			$this->set('snapins',$this->getClass('SnapinManager')->find(array('id' => $SnapinIDs)));
			$this->set('snapinsnotinme',$this->getClass('SnapinManager')->find(array('id' => $SnapinIDs),'','','','','',true));
		}
		return $this;
	}
	private function loadTask()
	{
		if (!$this->isLoaded('task') && $this->get('id'))
		{
			$Task = current($this->getClass('TaskManager')->find(array('hostID' => $this->get('id'),'stateID' => array(1,2,3))));
			if ($Task && $Task->isValid())
				$this->set('task',$Task);
			else
				$this->set('task',new Task(array('id' => 0)));
		}
		return $this;
	}
	private function loadUsers()
	{
		if (!$this->isLoaded('users') && $this->get('id'))
		{
			foreach($this->getClass('UserTrackingManager')->find(array('hostID' => $this->get('id'),'action' => array(null,0,1)),'','datetime') AS $User)
			{
				if ($User && $User->isValid() && $User->get('username') != 'Array')
					$this->add('users', $User);
			}
		}
		return $this;
	}
	private function loadOptimalStorageNode()
	{
		if (!$this->isLoaded('optimalStorageNode') && $this->get('id'))
		{
			if ($this->get('image') && $this->get('image')->isValid())
				$this->set('optimalStorageNode',$this->getImage()->getStorageGroup()->getOptimalStorageNode());
			else
				$this->set('optimalStorageNode',$this->getClass('StorageNode',array('id' => 0)));
		}
	}
	// Overrides
	public function get($key = '')
	{
		if ($this->key($key) == 'mac')
			$this->loadPrimary();
		else if ($this->key($key) == 'additionalMACs')
			$this->loadAdditional();
		else if ($this->key($key) == 'pendingMACs')
			$this->loadPending();
		else if (in_array($this->key($key),array('printers','printersnotinme')))
			$this->loadPrinters();
		else if (in_array($this->key($key),array('snapins','snapinsnotinme')))
			$this->loadSnapins();
		else if ($this->key($key) == 'snapinjob')
			$this->loadSnapinJob();
		else if ($this->key($key) == 'modules')
			$this->loadModules();
		else if ($this->key($key) == 'inventory')
			$this->loadInventory();
		else if (in_array($this->key($key),array('groups','groupsnotinme')))
			$this->loadGroups();
		else if ($this->key($key) == 'task')
			$this->loadTask();
		else if ($this->key($key) == 'users')
			$this->loadUsers();
		else if ($this->key($key) == 'optimalStorageNode')
			$this->loadOptimalStorageNode();
		return parent::get($key);
	}
	public function set($key, $value)
	{
		// MAC Address
		if ($this->key($key) == 'mac')
		{
			$this->loadPrimary();
			if (!($value instanceof MACAddress))
				$value = new MACAddress($value);
		}
		// Additional MACs
		else if ($this->key($key) == 'additionalMACs')
		{
			$this->loadAdditional();
			foreach((array)$value AS $mac)
				$newValue[] = ($mac instanceof MACAddress ? $mac : new MACAddress($mac));
			$value = (array)$newValue;
		}
		// Pending MACs
		else if ($this->key($key) == 'pendingMACs')
		{
			$this->loadPending();
			foreach((array)$value AS $mac)
				$newValue[] = ($mac instanceof MACAddress ? $mac : new MACAddress($mac));
			$value = (array)$newValue;
		}
		// Printers
		else if (in_array($this->key($key),array('printers','printersnotinme')))
		{
			$this->loadPrinters();
			foreach ((array)$value AS $printer)
				$newValue[] = ($printer instanceof Printer ? $printer : new Printer($printer));
			$value = (array)$newValue;
		}
		// Snapins
		else if (in_array($this->key($key),array('snapins','snapinsnotinme')))
		{
			$this->loadSnapins();
			foreach ((array)$value AS $snapin)
				$newValue[] = ($snapin instanceof Snapin ? $snapin : new Snapin($snapin));
			$value = (array)$newValue;
		}
		// SnapinJob
		else if ($this->key($key) == 'snapinjob' && !($value instanceof SnapinJob))
		{
			$this->loadSnapinJob();
			if (!($value instanceof SnapinJob))
				$value = new SnapinJob($value);
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
		else if (($this->key($key) == 'inventory'))
		{
			$this->loadInventory();
			if (!($value instanceof Inventory))
				$value = new Inventory($value);
		}
		// Groups
		else if (in_array($this->key($key),array('groups','groupsnotinme')))
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
			if (!($value instanceof Task))
				$value = new Task($value);
		}
		// Users
		else if ($this->key($key) == 'users')
		{
			$this->loadUsers();
			foreach ((array)$value AS $user)
				$newValue[] = ($user instanceof UserTracking ? $user : new UserTracking($user));
			$value = (array)$newValue;
		}
		else if ($this->key($key) == 'image')
		{
			if (!($value instanceof Image))
				$value = new Image($value);
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
		else if (($this->key($key) == 'printers' || $this->key($key) == 'printersnotinme') && !($value instanceof Printer))
		{
			$this->loadPrinters();
			$value = new Printer($value);
		}
		// Snapins
		else if (($this->key($key) == 'snapins' || $this->key($key) == 'snapinsnotinme') && !($value instanceof Snapin))
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
		else if (in_array($this->key($key),array('groups','groupsnotinme')) && !($value instanceof Group))
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
		// Primary MAC
		if ($this->key($key) == 'mac')
			$this->loadPrimary();
		// Additional MACs
		else if ($this->key($key) == 'additionalMACs')
			$this->loadAdditional();
		// Pending MACs
		else if ($this->key($key) == 'pendingMACs')
			$this->loadPending();
		// Printers
		else if (in_array($this->key($key),array('printers','printersnotinme')))
			$this->loadPrinters();
		// Snapins
		else if (in_array($this->key($key),array('snapins','snapinsnotinme')))
			$this->loadSnapins();
		// SnapinJob
		else if ($this->key($key) == 'snapinjob')
			$this->loadSnapinJob();
		// Modules
		else if ($this->key($key) == 'modules')
			$this->loadModules();
		// Groups
		else if (in_array($this->key($key),array('groups','groupsnotinme')))
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
		// MAC Addresses
		$maxid = max($this->get('id') ? $this->getClass('MACAddressAssociationManager')->find(array('hostID' => $this->get('id')),'','','','','','','id') : $this->getClass('MACAddressAssociationManager')->find('','','','','','','','id'));
		if ($this->isLoaded('mac') || $this->isLoaded('additionalMACs') || $this->isLoaded('pendingMACs'))
		{
			// Remove Existing MAC Addresses
			if ($this->get('id'))
				$this->getClass('MACAddressAssociationManager')->destroy(array('hostID' => $this->get('id'),'primary' => 1));
			// Add new Primary MAC Address
			if (($this->getMACAddress() instanceof MACAddress) && $this->getMACAddress()->isValid())
			{
				$NewMAC = new MACAddressAssociation(array(
					'id' => ++$maxid,
					'hostID' => $this->get('id'),
					'mac' => strtolower($this->get('mac')),
					'primary' => 1,
					'clientIgnore' => $this->get('mac')->isClientIgnored(),
					'imageIgnore' => $this->get('mac')->isImageIgnored(),
				));
				$NewMAC->save();
			}
			if ($this->get('id'))
				$this->getClass('MACAddressAssociationManager')->destroy(array('hostID' => $this->get('id'),'primary' => 0));
			// Add new Additional MACs
			foreach((array)$this->get('additionalMACs') AS $me)
			{
				if (($me instanceof MACAddress) && $me->isValid())
				{
					$NewMAC = new MACAddressAssociation(array(
						'id' => ++$maxid,
						'hostID' => $this->get('id'),
						'mac' => strtolower($me),
						'clientIgnore' => $me->isClientIgnored(),
						'imageIgnore' => $me->isImageIgnored(),
					));
					$NewMAC->save();
				}
			}
			// Add new Pending MACs
			foreach((array)$this->get('pendingMACs') AS $me)
			{
				if (($me instanceof MACAddress) && $me->isValid())
				{
					$NewMAC = new MACAddressAssociation(array(
						'id' => ++$maxid,
						'hostID' => $this->get('id'),
						'mac' => strtolower($me),
						'pending' => 1,
						'clientIgnore' => $me->isClientIgnored(),
						'imageIgnore' => $me->isImageIgnored(),
					));
					$NewMAC->save();
				}
			}
		}
		// Modules
		else if ($this->isLoaded('modules'))
		{
			// Remove old rows
			$this->getClass('ModuleAssociationManager')->destroy(array('hostID' => $this->get('id')));
			// Create assoc
			foreach((array)$this->get('modules') AS $Module)
			{
				$moduleName = $this->getGlobalModuleStatus();
				if (($Module instanceof Module) && $Module->isValid())
				{
					if ($moduleName[$Module->get('shortName')])
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
		// Printers
		else if ($this->isLoaded('printers'))
		{
			// Find the current default
			$defPrint = current((array)$this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id'),'isDefault' => 1)));
			$totalPrinters = $this->getClass('PrinterAssociationManager')->count(array('hostID' => $this->get('id')));
			// Remove all printers
			$this->getClass('PrinterAssociationManager')->destroy(array('hostID' => $this->get('id')));
			// Create assoc
			$i = 0;
			foreach ((array)$this->get('printers') AS $Printer)
			{
				if(($Printer instanceof Printer) && $Printer->isValid())
				{
					$PrinterAssoc = current((array)$this->getClass('PrinterAssociationManager')->find(array('hostID' => $this->get('id'), 'printerID' => $Printer->get('id'))));
					if (!$PrinterAssoc || !$PrinterAssoc->isValid())
					{
						$NewPrinter = new PrinterAssociation(array(
							'printerID' => $Printer->get('id'),
							'hostID' => $this->get('id'),
							'isDefault' => ($defPrint && $defPrint->isValid() && $defPrint->get('printerID') == $Printer->get('id') ? 1 : ($totalPrinters ? 0 : ($i === 0 ? 1 : 0))),
						));
						$NewPrinter->save();
					}
				}
				$i++;
			}
		}
		// Snapins
		else if ($this->isLoaded('snapins'))
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
		// Groups
		else if ($this->isLoaded('groups'))
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
		else if ($this->isLoaded('users'))
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
		return $this->get('id') && HostManager::isHostnameSafe($this->get('name')) && $this->getMACAddress();
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
			$this->HookManager->processEvent('HOST_NEW_SETTINGS',array('Host' => &$this,'StorageNode' => &$StorageNode,'StorageGroup' => &$StorageGroup));
		if (!$StorageGroup || !$StorageGroup->isValid())
			throw new Exception(_('No Storage Group found for this image'));
		if (!$StorageNode || !$StorageNode->isValid())
			throw new Exception(_('No Storage Node found for this image'));
		if (in_array($TaskType->get('id'),array('1','8','15','17')) && in_array($Image->get('osID'), array('5', '6', '7')))
		{
			// FTP
			$this->FOGFTP->set('username',$StorageNode->get('user'))
				 ->set('password',$StorageNode->get('pass'))
				 ->set('host',$this->FOGCore->resolveHostname($StorageNode->get('ip')));
			if ($this->FOGFTP->connect())
			{
				if(!$this->FOGFTP->chdir(rtrim($StorageNode->get('path'),'/').'/'.$Image->get('path')))
					return false;
			}
			$this->FOGFTP->close();
		}
		return true;
	}
	/** createTasking creates the tasking so I don't have to keep typing it in for each element.
      * @param $taskName the name to assign to the tasking
	  * @param $taskTypeID the task type id to set the tasking
	  * @param $username the username to associate with the tasking
	  * @param $groupID the Storage Group ID to associate with
	  * @param $memID the Storage Node ID to associate with
	  * @param $imagingTask if the task is an imaging type, defaults as true.
	  * @param $shutdown if the task is to be shutdown once completed, defaults as false.
	  * @param $passreset if the task is a password reset task, defaults as false.
	  * @param $debug if the task is a debug task, defaults as false.
	  * @return $Task returns the tasking generated to be saved later
	  */
	private function createTasking($taskName, $taskTypeID, $username, $groupID, $memID, $imagingTask = true,$shutdown = false, $passreset = false, $debug = false)
	{
		$Task = new Task(array(
			'name' => $taskName,
			'createdBy' => $username,
			'hostID' => $this->get('id'),
			'isForced' => 0,
			'stateID' => 1,
			'typeID' => $taskTypeID,
			'NFSGroupID' => $groupID,
			'NFSMemberID' => $memID,
		));
		if ($imagingTask)
			$Task->set('imageID', $this->getImage()->get('id'));
		if ($shutdown)
			$Task->set('shutdown', $shutdown);
		if ($debug)
			$Task->set('isDebug', $debug);
		if ($passreset)
			$Task->set('passreset', $passreset);
		return $Task;
	}
	/** cancelJobsSnapinsForHost cancels all jobs and tasks that are snapins associate
	  * with this particular host
	  * @return void
	  */
	private function cancelJobsSnapinsForHost()
	{
		foreach($this->getClass('SnapinJobManager')->find(array('hostID' => $this->get('id'),'stateID' => array(-1,0,1))) AS $SJ)
		{
			foreach($this->getClass('SnapinTaskManager')->find(array('jobID' => $SJ->get('id'),'stateID' => array(-1,0,1))) AS $ST)
				$ST->set('stateID',2)->save();
			$SJ->set('stateID',2)->set('return',-9999)->save();
		}
	}
	/** createSnapinTasking creates the snapin tasking or taskings as needed
	  * @param $snapin usually -1 or the valid snapin identifier, defaults to all snapins (-1)
	  * @return void
	  */
	private function createSnapinTasking($snapin = -1)
	{
		// Error Checking
		// If there are no snapins associated to the host fail out.
		try
		{
			if (in_array($this->get('task')->get('id'),array(12,13)) && !$this->getClass('SnapinAssociationManager')->count(array('hostID' => $this->get('id'))))
				throw new Exception($this->foglang['SnapNoAssoc']);
			// Create Snapin Job.  Only one job, but will do multiple SnapinTasks.
			else if ($this->getClass('SnapinAssociationManager')->count(array('hostID' => $this->get('id'))))
			{
				$SnapinJob = new SnapinJob(array(
					'hostID' => $this->get('id'),
					'stateID' => 0,
					'createdTime' => $this->nice_date()->format('Y-m-d H:i:s'),
				));
				// Create Snapin Tasking
				if ($SnapinJob->save())
				{
					// If -1 for the snapinID sent, it needs to set a task for all of the snapins associated to that host.
					if ($snapin == -1)
					{
						foreach ((array)$this->get('snapins') AS $Snapin)
						{
							$ST = new SnapinTask(array(
								'jobID' => $SnapinJob->get('id'),
								'stateID' => 0,
								'snapinID' => $Snapin->get('id'),
							));
							$ST->save();
						}
					}
					else
					{
						$Snapin = new Snapin($snapin);
						$ST = new SnapinTask(array(
							'jobID' => $SnapinJob->get('id'),
							'stateID' => 0,
							'snapinID' => $Snapin->get('id'),
						));
						$ST->save();
					}
				}
			}
		}
		catch (Exception $e)
		{
			print $e->getMessage();
			return false;
		}
	}

	// Should be called: createDeployTask
	public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false)
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
			$username = ($this->FOGUser ? $this->FOGUser->get('name') : ($username ? $username : ''));
			if ($imagingTypes && $Image && $Image->isValid())
			{
				$StorageGroup = $Image->getStorageGroup();
				$StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $this->getOptimalStorageNode());
			}
			// Cancel any tasks and jobs that the host hasn't completed
			$this->cancelJobsSnapinsForHost();
			// Task type wake on lan, deploy only this part.
			if ($taskTypeID == '14')
			{
				$Task = $this->createTasking($taskName, $taskTypeID, $username, 0, 0, $imagingTypes);
				if ($Task->save())
				{
					$this->wakeOnLAN();
					$this->FOGCore->logHistory(sprintf('Task Created: Task ID: %s, Task Name: %s, Host ID: %s, Host Name: %s, Host MAC: %s', $Task->get('id'), $Task->get('name'), $this->get('id'), $this->get('name'), $this->getMACAddress()));
					$Task->destroy();
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
			// Snapin deploy/cancel after deploy
			if (!$isUpload && $deploySnapins && (($imagingTypes && $taskTypeID != 17) || in_array($taskTypeID,array(12,13))))
				$this->createSnapinTasking($deploySnapins);
			// Task: Create Task Object
			$Task = $this->createTasking($taskName, $taskTypeID, $username, $imagingTypes ? $StorageGroup->get('id') : 0, $imagingTypes ? $StorageGroup->getOptimalStorageNode()->get('id') : 0, $imagingTypes,$shutdown,$passreset,$debug);
			// Task: Save to database
			if (!$Task->save())
				throw new Exception($this->foglang['FailedTask']);
			// If task is multicast create the tasking for multicast
			if ($TaskType->isMulticast())
			{
				$assoc = false;
				$MultiSessName = current((array)$this->getClass('MulticastSessionsManager')->find(array('name' => $taskName,'stateID' => array(0,1,2,3))));
				$MultiSessAssoc = current((array)$this->getClass('MulticastSessionsManager')->find(array('image' => $this->getImage()->get('id'),'stateID' => 0)));
				if ($sessionjoin && $MultiSessName && $MultiSessName->isValid())
				{
					$MulticastSession = $MultiSessName;
					$assoc = true;
				}
				else if ($MultiSessAssoc && $MultiSessAssoc->isValid())
				{
					$MulticastSession = $MultiSessAssoc;
					$assoc = true;
				}
				else
				{
					// Create New Multicast Session Job
					$MulticastSession = new MulticastSessions(array(
						'name' => $taskName,
						'port' => $this->FOGCore->getSetting('FOG_MULTICAST_PORT_OVERRIDE') ? $this->FOGCore->getSetting('FOG_MULTICAST_PORT_OVERRIDE') : $this->FOGCore->getSetting('FOG_UDPCAST_STARTINGPORT'),
						'logpath' => $this->getImage()->get('path'),
						'image' => $this->getImage()->get('id'),
						'interface' => $StorageNode->get('interface'),
						'stateID' => 0,
						'starttime' => $this->nice_date()->format('Y-m-d H:i:s'),
						'percent' => 0,
						'isDD' => $this->getImage()->get('imageTypeID'),
						'NFSGroupID' => $StorageNode->get('storageGroupID'),
					));
					if ($MulticastSession->save())
					{
						// Sets a new port number so you can create multiple Multicast Tasks.
						if (!$this->FOGCore->getSetting('FOG_MULTICAST_PORT_OVERRIDE'))
						{
							$randomnumber = mt_rand(24576,32766)*2;
							while ($randomnumber == $MulticastSession->get('port'))
								$randomnumber = mt_rand(24576,32766)*2;
							$this->FOGCore->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
						}
					}
					$assoc = true;
				}
				if ($assoc)
				{
					// Create the Association.
					$MulticastSessionAssoc = new MulticastSessionsAssociation(array(
						'msID' => $MulticastSession->get('id'),
						'taskID' => $Task->get('id'),
					));
					$MulticastSessionAssoc->save();
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
		$MACs[] = $this->get('mac');
		foreach($this->get('additionalMACs') AS $MAC)
			$MACs[] = $MAC;
		$MACs = array_unique($MACs);
		foreach((array)$MACs AS $MAC)
			$this->FOGCore->wakeOnLAN($MAC);
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
	public function addAddMAC($addArray,$pending = false)
	{
		if ($pending)
		{
			foreach((array)$addArray AS $item)
				$this->add('pendingMACs',(($item instanceof MACAddress) ? $item : new MACAddress($item)));
		}
		else
		{
			foreach((array)$addArray AS $item)
				$this->add('additionalMACs',(($item instanceof MACAddress) ? $item : new MACAddress($item)));
		}
		// Return
		return $this;
	}
	public function addPendtoAdd($MACs)
	{
		foreach((array)$MACs AS $MAC)
		{
			$this->add('additionalMACs',(($MAC instanceof MACAddress) ? $MAC : new MACAddress($MAC)));
			$this->remove('pendingMACs',(($MAC instanceof MACAddress) ? $MAC : new MACAddress($MAC)));
		}
		// Return
		return $this;
	}
	public function removeAddMAC($removeArray)
	{
		foreach((array)$removeArray AS $item)
			$this->remove('additionalMACs',(($item instanceof MACAddress) ? $item : new MACAddress($item)));
		// Return
		return $this;
	}
	public function addPriMAC($MAC)
	{
		$this->set('mac',$MAC);
		return $this;
	}
	public function addPendMAC($MAC)
	{
		$this->addAddMAC($MAC,true);
		return $this;
	}
	public function addSnapin($addArray)
	{
		$Snapins = $this->get('snapins');
		$Snapins = array_filter((array)$Snapins);
		$limit = $this->FOGCore->getSetting('FOG_SNAPIN_LIMIT');
		if ($limit > 0)
		{
			if (count($Snapins) >= $limit || count($addArray) > $limit)
				throw new Exception(sprintf('%s %d %s',_('You are only allowed to assign'),$limit,$limit == 1 ? _('snapin per host') : _('snapins per host')));
		}
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
		// Remove the modules
		$this->getClass('ModuleAssociationManager')->destroy(array('hostID' => $this->get('id'),'moduleID' => $removeArray));
		// Iterate array (or other as array)
		foreach ((array)$removeArray AS $remove)
			$this->remove('modules', ($remove instanceof Module ? $remove : new Module((int)$remove)));
		// Return
		return $this;
	}
	public function getMyMacs($justme = true)
	{
		$KnownMacs[] = strtolower($this->get('mac'));
		foreach((array)$this->get('additionalMACs') AS $MAC)
			$MAC && $MAC->isValid() ? $KnownMacs[] = strtolower($MAC) : null;
		foreach((array)$this->get('pendingMACs') AS $MAC)
			$MAC && $MAC->isValid() ? $KnownMacs[] = strtolower($MAC) : null;
		if ($justme)
			return $KnownMacs;
		foreach((array)$this->getClass('MACAddressAssociationManager')->find() AS $MAC)
			$MAC && $MAC->isValid() && !in_array(strtolower($MAC->get('mac')),(array)$KnownMacs) ? $KnownMacs[] = strtolower($MAC->get('mac')) : null;
		return array_unique($KnownMacs);
	}

	public function ignore($imageIgnore,$clientIgnore)
	{
		$MyMACs = $this->getMyMacs();
		foreach((array)$imageIgnore AS $igMAC)
			$igMACs[] = strtolower($igMAC);
		foreach((array)$clientIgnore AS $cgMAC)
			$cgMACs[] = strtolower($cgMAC);
		foreach((array)$MyMACs AS $MAC)
		{
			$ignore = current((array)$this->getClass('MACAddressAssociationManager')->find(array('mac' => $MAC,'hostID' => $this->get('id'))));
			$ME = new MACAddress($ignore);
			if ($ME->isValid())
			{
				$mac = strtolower($MAC);
				$ignore->set('imageIgnore',in_array($mac,(array)$igMACs))->save();
				$ignore->set('clientIgnore',in_array($mac,(array)$cgMACs))->save();
			}
		}
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
		$mac = current((array)$this->getClass('MACAddressAssociationManager')->find(array('mac' => $MAC ? $MAC : $this->get('mac')->__toString(),'hostID' => $this->get('id'),'clientIgnore' => 1)));
		return ($mac && $mac->isValid() ? 'checked' : '');
	}
	public function imageMacCheck($MAC = false)
	{
		$mac = current((array)$this->getClass('MACAddressAssociationManager')->find(array('mac' => $MAC ? $MAC : $this->get('mac')->__toString(),'hostID' => $this->get('id'),'imageIgnore' => 1)));
		return ($mac && $mac->isValid() ? 'checked' : '');
	}
	public function setAD($useAD = '',$domain = '',$ou = '',$user = '',$pass = '',$override = false)
	{
		if ($this->get('id'))
		{
			if (!$override)
			{
				if (empty($useAD))
					$useAD = $this->get('useAD');
				if (empty($domain))
					$domain = $this->get('ADDomain');
				if (empty($ou))
					$ou = $this->get('ADOU');
				if (empty($user))
					$user = $this->get('ADUser');
				if (empty($pass))
					$pass = $this->get('ADPass');
			}
			if ($this->FOGCore->getSetting('FOG_NEW_CLIENT') && $pass)
				$pass = $this->encryptpw($pass);
			$this->set('useAD',$useAD)
				 ->set('ADDomain',$domain)
				 ->set('ADOU',$ou)
				 ->set('ADUser',$user)
				 ->set('ADPass',$pass)
				 ->save();
		}
		return $this;
	}

	public function destroy($field = 'id')
	{
		// Complete active tasks
		if ($this->get('task') && $this->get('task')->isValid())
			$this->get('task')->set('stateID',5)->save();
		// Remove Snapinjob Associations
		if ($this->get('snapinjob') && $this->get('snapinjob')->isValid())
			$this->get('snapinjob')->set('stateID',5)->save();
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
		// Remove Stored Fingerprints
		$this->getClass('FingerprintAssociationManager')->destroy(array('id' => $this->get('id')));
		// Remove Queued Items
		$this->getClass('QueueManager')->destroy(array('hostID' => $this->get('id')));
		// Update inventory to know when it was deleted
		if ($this->get('inventory'))
			$this->get('inventory')->set('deleteDate',$this->nice_date()->format('Y-m-d H:i:s'))->save();
		$this->HookManager->processEvent('DESTROY_HOST',array('Host' => &$this));
		// Return
		return parent::destroy($field);
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
