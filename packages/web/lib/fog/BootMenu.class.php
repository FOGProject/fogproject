<<<<<<< HEAD
<?php
/**
* \class BootMenu
* Builds the ipxe menu system.
* Serves to also generate the taskings on the fly.
* Changes are automatically adjusted as needed.
* @param $Host is the host set.  Can be null.
* @param $pxemenu builds the default pxemenu as array().
* @param $kernel sets the kernel information.
* @param $initrd sets the init information.
* @param $booturl sets the bootup url info.
* @param $memdisk sets the memdisk info
* @param $memtest sets the memtest info
* @param $Host is the host set.  Can be null.
* @param $pxemenu builds the default pxemenu as array().
* @param $kernel sets the kernel information.
* @param $initrd sets the init information.
* @param $booturl sets the bootup url info.
* @param $memtest sets the memtest info.
* @param $web sets the web address.
* @param $defaultChoice chooses the defaults.
* @param $bootexittype sets the exit type to hdd.
* @param $storage sets the storage node
* @param $shutdown sets whether shutdown is set or not.
* @param $path sets the default path.
* @param $hiddenmenu sets if hidden menu is setup.
* @param $timeout gets the timout to OS/HDD
* @param $KS gets the key sequence.
* @param $debug sets the debug information. Displays debug menu.
*/
class BootMenu extends FOGBase
{
	// Variables
	private $Host,$pxemenu,$kernel,$initrd,$booturl,$memdisk,$memtest,$web,$defaultChoice,$bootexittype;
	private $storage, $shutdown, $path;
	private $hiddenmenu, $timeout, $KS;
	public $debug;
	/** __construct($Host = null)
	* Construtor for the whole system.
	* Sets all the variables as needed.
	* @param $Host can be nothing, but is sent to
	* verify if there's a tasking for the host.
	* @return void
	*/
	public function __construct($Host = null)
	{
		parent::__construct();
		// Setups of the basic construct for the menu system.
		$StorageNode = current($this->FOGCore->getClass('StorageNodeManager')->find(array('isEnabled' => 1, 'isMaster' => 1)));
		$webserver = $this->FOGCore->resolveHostname($this->FOGCore->getSetting('FOG_WEB_HOST'));
		$webroot = '/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/';
		$this->web = "${webserver}${webroot}";
		$this->bootexittype = ($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'exit' ? 'exit' : ($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'sanboot' ? 'sanboot --no-describe --drive 0x80' : ($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'grub' ? 'chain -ar http://'.rtrim($this->web,'/').'/service/ipxe/grub.exe --config-file="rootnoverify (hd0);chainloader +1"' : 'exit')));
		$ramsize = $this->FOGCore->getSetting('FOG_KERNEL_RAMDISK_SIZE');
		$dns = $this->FOGCore->getSetting('FOG_PXE_IMAGE_DNSADDRESS');
		$keymap = $this->FOGCore->getSetting('FOG_KEYMAP');
		$timeout = $this->FOGCore->getSetting('FOG_PXE_MENU_TIMEOUT') * 1000;
		$this->timeout = $timeout;
		$memdisk = 'memdisk';
		$memtest = $this->FOGCore->getSetting('FOG_MEMTEST_KERNEL');
		if ($_REQUEST['arch'] != 'x86_64')
		{
			$bzImage = $this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL_32');
			$imagefile = $this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE_32');
		}
		else
		{
			$bzImage = $this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL');
			$imagefile = $this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE');
		}
		if ($Host && $Host->isValid())
		{
			$LA = current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id'))));
			if ($LA)
				$Location = new Location($LA->get('locationID'));
			if ($Location && $Location->isValid())
			{
				$StorageNode = $Location->get('tftp') && $Location->get('storageNodeID') ? new StorageNode($Location->get('storageNodeID')) : $this->FOGCore->getClass('StorageGroup',$Location->get('storageGroupID'))->getOptimalStorageNode();
				if ($Location->get('tftp'))
				{
					$memdisk = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/memdisk';
					$memtest = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$this->FOGCore->getSetting('FOG_MEMTEST_KERNEL');
					if ($Host->get('kernel') && $_REQUEST['arch'] != 'x86_64')
					{
						$bzImage = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$Host->get('kernel');
						$imagefile = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE_32');
					}
					else if ($Host->get('kernel') && $_REQUEST['arch'] == 'x86_64')
					{
						$bzImage = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$Host->get('kernel');
						$imagefile = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE');
					}
					else if ($_REQUEST['arch'] != 'x86_64')
					{
						$bzImage = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL_32');
						$imagefile = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE_32');
					}
					else
					{
						$bzImage = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL');
						$imagefile = 'http://'.$StorageNode->get('ip').$webroot.'service/ipxe/'.$this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE');
					}
				}

			}
			else if ($Host->get('kernel'))
				$bzImage = $Host->get('kernel');
		}
		$keySequence = $this->FOGCore->getSetting('FOG_KEY_SEQUENCE');
		if ($keySequence)
			$this->KS = new KeySequence($keySequence);
		if (!$_REQUEST['menuAccess'])
			$this->hiddenmenu = $this->FOGCore->getSetting('FOG_PXE_MENU_HIDDEN');
		$this->booturl = "http://${webserver}${webroot}service";
		$this->Host = $Host;
		$this->pxemenu = array(
			'fog.local' => 'Boot from hard disk',
			'fog.memtest' => 'Run Memtest86+',
			'fog.reginput' => 'Perform Full Host Registration and Inventory',
			'fog.keyreg' => 'Update Product Key',
			'fog.reg' => 'Quick Registration and Inventory',
			'fog.quickimage' => 'Quick Image',
			'fog.quickdel' => 'Quick Host Deletion',
			'fog.sysinfo' => 'Client System Information (Compatibility)',
			'fog.debug' => 'Debug Mode',
		);
		$CaponePlugInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'capone','state' => 1,'installed' => 1)));
		$DMISet = $CaponePlugInst ? $this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_DMI') : false;
		if ($CaponePlugInst && $DMISet)
			$this->pxemenu['fog.capone'] = 'Capone Deploy';
		if ($CaponePlugInst)
		{
			$this->storage = $StorageNode->get('ip');
			$this->path = $StorageNode->get('path');
			$this->shutdown = $this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN');
		}
		$Advanced = $this->FOGCore->getSetting('FOG_PXE_ADVANCED');
		if ($Advanced)
			$this->pxemenu['fog.advanced'] = 'Advanced Menu';
		$this->memdisk = "kernel $memdisk";
		$this->memtest = "initrd $memtest";
		$this->kernel = "kernel $bzImage initrd=$imagefile root=/dev/ram0 rw ramdisk_size=$ramsize ip=dhcp dns=$dns keymap=$keymap web=${webserver}${webroot} consoleblank=0";
		$this->initrd = "imgfetch $imagefile\n";
		$this->defaultChoice = "choose --default fog.local --timeout $timeout target && goto \${target}\n";
		if ($_REQUEST['username'] && $_REQUEST['password'])
			$this->verifyCreds();
		else if ($_REQUEST['delconf'])
			$this->delHost();
		else if ($_REQUEST['key'])
			$this->keyset();
		else if (!$Host || !$Host->isValid())
			$this->printDefault();
		else
			$this->getTasking();
	}
	/**
	* chainBoot()
	* Prints the bootmenu or hides it.  If access is not allowed but tried
	* requests login information from WEB GUI.
	* Used often for return to menu/check tasking after setting somthing.
	* $debug is a flat to indicate if we show the debug menu item.  Typically
	* you only want this after a person authenticates.
	* $shortCircuit is a flag that will shortCircuit the hiddenMenu check.
	* This is needed for quick image.
	* @param $debug set to false but if true enables access.
	* @param $shortCircuit set to false, but if true enables display.
	* @return void
	*/
	private function chainBoot($debug=false, $shortCircuit=false)
	{
	    // csyperski: added hiddenMenu check; without it entering
		// any string for username and password would show the menu, even if it was hidden
	    if (!$this->hiddenmenu || $shortCircuit)
		{
    		print "#!ipxe\n";
			print "cpuid --ext 29 && set arch x86_64 || set arch i386\n";
			print "params\n";
			print "param mac0 \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param menuAccess 1\n";
			print "param debug ".($debug ? "1\n" : "0\n");
			print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
			print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
			print ":bootme\n";
	    	print "chain -ar $this->booturl/ipxe/boot.php##params\n";
	    } 
	    else
	    {
	        print "prompt --key ".($this->KS && $this->KS->isValid() ? $this->KS->get('ascii') : '0x1b')." --timeout $this->timeout Booting... (Press ".($this->KS && $this->KS->isValid() ?  $this->KS->get('name') : 'Escape')." to access the menu) && goto menuAccess || $this->bootexittype\n";
			print ":menuAccess\n";
			print "login\n";
			print "params\n";
			print "param mac0 \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param menuaccess 1\n";
			print "param debug ".($debug ? "1\n" : "0\n");
			print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
			print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
			print ":bootme\n";
			print "chain -ar $this->booturl/ipxe/boot.php##params\n";
	    }
	}
	/**
	* delHost()
	* Deletes the host from the system.
	* If it failes will return that it failed.
	* Each interval sends back to chainBoot()
	* @return void
	*/
	private function delHost()
	{
		if($this->Host->destroy())
		{
			print "#!ipxe\n";
			print "echo Host deleted successfully\n";
			print "sleep 3\n";
			$this->chainBoot();
		}
		else
		{
			print "#!ipxe\n";
			print "echo Failed to destroy Host!\n";
			print "sleep 3\n";
			$this->chainBoot();
		}
	}
	/**
	* printTasking()
	* Sends the Tasking file.  In PXE this is equivalent to the creation
	* of the 01-XX-XX-XX-XX-XX-XX file.
	* Just tells the system it's got a task.
	* @param $kernelArgsArray sets up the tasking through the 
	* kernelArgs information.
	* @return void
	*/
	private function printTasking($kernelArgsArray)
	{
		foreach($kernelArgsArray AS $arg)
        {   
            if (!is_array($arg) && !empty($arg) || (is_array($arg) && $arg['active'] && !empty($arg)))
                $kernelArgs[] = (is_array($arg) ? $arg['value'] : $arg);
        }   
        $kernelArgs = array_unique($kernelArgs);
		print "#!ipxe\n";
        print "$this->kernel loglevel=4 ".implode(' ',(array)$kernelArgs)."\n";
        print "$this->initrd";
        print "boot";
	}
	/**
	* delConf()
	* If you're trying to delete the host, requests confirmation of deletion.
	* @return void
	*/
	public function delConf()
	{
		print "#!ipxe\n";
		print "cpuid --ext 29 && set arch x86_64 || set arch i386\n";
		print "prompt --key y Would you like to delete this host? (y/N): &&\n";
		print "params\n";
		print "param mac0 \${net0/mac}\n";
		print "param arch \${arch}\n";
		print "param delconf 1\n";
		print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
		print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
		print ":bootme\n";
		print "chain -ar $this->booturl/ipxe/boot.php##params";
	}
	/**
	* keyreg()
	* If you're trying to change the key, request what the key is.
	* @return void
	*/
	public function keyreg()
	{
		print "#!ipxe\n";
		print "cpuid --ext 29 && set arch x86_64 || set arch i386\n";
		print "echo -n Please enter the product key>\n";
		print "read key\n";
		print "params\n";
		print "param mac0 \${net0/mac}\n";
		print "param arch \${arch}\n";
		print "param key \${key}\n";
		print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
		print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
		print ":bootme\n";
		print "chain -ar $this->booturl/ipxe/boot.php##params";
	}
	/**
	*/
	public function keyset()
	{
		$this->Host->set('productKey',base64_encode($_REQUEST['key']));
		if ($this->Host->save())
		{
			print "#!ipxe\n";
			print "echo Successfully changed key\n";
			print "sleep 3\n";
			$this->chainBoot();
		}
	}
	/**
	* advLogin()
	* If advanced login is set this just passes when verifyCreds is correct
	* @return void
	*/
	public function advLogin()
	{
		print "#!ipxe\n";
		print "chain -ar $this->booturl/ipxe/advanced.php\n";
	}
	/**
	* debugAccess()
	* Set's up for debug menu as requested.
	* @return void
	*/
	private function debugAccess()
	{
		print "#!ipxe\n";
		print "$this->kernel mode=onlydebug\n";
		print "$this->initrd";
		print "boot\n";
	}
	/**
	* verifyCreds()
	* Verifies the login information is valid
	* and correct.
	* Otherwise return that it's broken.
	* @return void
	*/
	public function verifyCreds()
	{
		if ($this->FOGCore->attemptLogin($_REQUEST['username'],$_REQUEST['password']))
		{
			if ($this->FOGCore->getSetting('FOG_ADVANCED_MENU_LOGIN') && $_REQUEST['advLog'])
				$this->advLogin();
			if ($_REQUEST['delhost'])
				$this->delConf();
			else if ($_REQUEST['keyreg'])
				$this->keyreg();
			else if ($_REQUEST['qihost'])
				$this->setTasking();
			else if ($_REQUEST['menuaccess'])
			{
				unset($this->hiddenmenu);
				$this->chainBoot(true);
			}
			else if ($_REQUEST['debugAccess'])
				$this->debugAccess();
			else if (!$this->FOGCore->getSetting('FOG_NO_MENU'))
				$this->printDefault();
			else
				$this->noMenu();
		}
		else
		{
			print "#!ipxe\n";
			print "echo Invalid login!\n";
			print "sleep 3\n";
			$this->chainBoot();
		}
	}
	/**
	* setTasking()
	* If quick image tasking requested, this sets up the tasking.
	* @return void
	*/
	public function setTasking()
	{
		if($this->Host->createImagePackage(1,'AutoRegTask',false,false,true,false,$_REQUEST['username']))
			$this->chainBoot(false, true);
	}
	/**
	* noMenu()
	* If no menu option is set, just exits to harddrive if there's no tasking.
	* @return void
	*/
	public function noMenu()
	{
		print "#!ipxe\n";
		print "$this->bootexittype\n";
	}
	/**
	* getTasking()
	* Finds out if there's a tasking for the relevant host.
	* if there is, returns the printTasking, otherwise 
	* presents the menu.
	* @return void
	*/
	public function getTasking()
	{
		$Image = $this->Host->getImage();
		$Task = current($this->Host->get('task'));
		if (!$Task->isValid())
		{
			if ($this->FOGCore->getSetting('FOG_NO_MENU'))
				$this->noMenu();
			else
				$this->printDefault();
		}
		else
		{
			$TaskType = new TaskType($Task->get('typeID'));
			$imagingTasks = array(1,2,8,15,16,17);
			$LA = current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $this->Host->get('id'))));
			if ($LA)
				$Location = new Location($LA->get('locationID'));
			if ($Location && $Location->isValid())
				$StorageGroup = new StorageGroup($Location->get('storageGroupID'));
			else
				$StorageGroup = $Image->getStorageGroup();
			if (!$Location || !$Location->get('storageNodeID'))
				$StorageNode = $StorageGroup->getOptimalStorageNode();
			else
				$StorageNode = new StorageNode($Location->get('storageNodeID'));
			if ($TaskType->isUpload() || $TaskType->isMulticast())
				$StorageNode = $StorageGroup->getMasterStorageNode();
			$mac = $_REQUEST['mac'];
			$osid = $Image->get('osID');
			$storage = in_array($TaskType->get('id'),$imagingTasks) ? sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),($TaskType->isUpload() ? 'dev/' : '')) : null;
			$storageip = in_array($TaskType->get('id'),$imagingTasks) ? $StorageNode->get('ip') : null;
			$img = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('path') : null;
			$imgFormat = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('format') : null;
			$imgType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImageType()->get('type') : null;
			$imgid = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('id') : null;
			$ftp = $this->FOGCore->resolveHostname($this->FOGCore->getSetting('FOG_TFTP_HOST'));
			$chkdsk = $this->FOGCore->getSetting('FOG_DISABLE_CHKDSK') == 1 ? 0 : 1;
			$PIGZ_COMP = $this->FOGCore->getSetting('FOG_PIGZ_COMP');
			if ($TaskType->isMulticast())
			{
				$MulticastSessionAssoc = current($this->FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
				$MulticastSession = new MulticastSessions($MulticastSessionAssoc->get('msID'));
			}
			$kernelArgsArray = array(
				"mac=$mac",
				"ftp=$ftp",
				"storage=$storage",
				"storageip=$storageip",
				"web=$this->web",
				"osid=$osid",
				"loglevel=4",
				"consoleblank=0",
				"irqpoll",
				"hostname=".$this->Host->get('name'),
				array(
					'value' => "chkdsk=$chkdsk",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "img=$img",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "imgType=$imgType",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "imgid=$imgid",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "imgFormat=$imgFormat",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "PIGZ_COMP=-$PIGZ_COMP",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => 'shutdown=1',
					'active' => $Task->get('shutdown'),
				),
				array(
					'value' => 'adon=1',
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'addomain='.$this->Host->get('ADDomain'),
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'adou='.$this->Host->get('ADOU'),
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'aduser='.$this->Host->get('ADUser'),
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'adpass='.$this->Host->get('ADPass'),
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'fdrive='.$this->Host->get('kernelDevice'),
					'active' => $this->Host->get('kernelDevice'),
				),
				array(
					'value' => 'hostearly=1',
					'active' => $this->FOGCore->getSetting('FOG_CHANGE_HOSTNAME_EARLY') && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
				),
				array(
					'value' => 'pct='.(is_numeric($this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT')) && $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') >= 5 && $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') < 100 ? $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') : '5'),
					'active' => $TaskType->isUpload() && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
				),
				array(
					'value' => 'ignorepg='.($this->FOGCore->getSetting('FOG_UPLOADIGNOREPAGEHIBER') ? 1 : 0),
					'active' => $TaskType->isUpload() && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
				),
				array(
					'value' => 'port='.($TaskType->isMulticast() ? $MulticastSession->get('port') : null),
					'active' => $TaskType->isMulticast(),
				),
				array(
					'value' => 'mining=1',
					'active' => $this->FOGCore->getSetting('FOG_MINING_ENABLE'),
				),
				array(
					'value' => 'miningcores=' . $this->FOGCore->getSetting('FOG_MINING_MAX_CORES'),
					'active' => $this->FOGCore->getSetting('FOG_MINING_ENABLE'),
				),
				array(
					'value' => 'winuser='.$Task->get('passreset'),
					'active' => $TaskType->get('id') == '11' ? true : false,
				),
				array(
					'value' => 'miningpath=' . $this->FOGCore->getSetting('FOG_MINING_PACKAGE_PATH'),
					'active' => $this->FOGCore->getSetting('FOG_MINING_ENABLE'),
				),
				$TaskType->get('kernelArgs'),
				$this->FOGCore->getSetting('FOG_KERNEL_ARGS'),
				$this->Host->get('kernelArgs'),
			);
			if ($Task->get('typeID') == 12 || $Task->get('typeID') == 13)
				$this->printDefault();
			else if ($Task->get('typeID') == 4)
			{
				print "#!ipxe\n";
				print "$this->memdisk iso raw\n";
				print "$this->memtest\n";
				print "boot";
			}
			else
				$this->printTasking($kernelArgsArray);
		}
	}
	/**
	* menuItem()
	* @param $option the menu option
	* @param $desc the description of the menu item.
	* Prints the menu items.
	* @return void
	*/
	private function menuItem($option, $desc)
	{
		print "item $option $desc\n";
	}
	/**
	* menuOpt()
	* Prints the actual menu related items for booting.
	* @param $option the related menu option
	* @param $type the type of menu information.
	* @return void
	*/
	private function menuOpt($option,$type)
	{
		if ($option == 'fog.local')
		{
			print ":$option\n";
			print "$this->bootexittype || goto MENU\n";
		}
		else if ($option == 'fog.memtest')
		{
			print ":$option\n";
			print "$this->memdisk iso raw\n";
			print "$this->memtest\n";
			print "boot || goto MENU\n";
		}
		else if ($option == 'fog.quickimage')
		{
			print ":$option\n";
			print "login\n";
			print "params\n";
			print "param mac0 \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param qihost 1\n";
			print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
			print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
			print ":bootme\n";
			print "chain -ar $this->booturl/ipxe/boot.php##params ||\n";
			print "goto MENU\n";
		}
		else if ($option == 'fog.quickdel')
		{
			print ":$option\n";
			print "login\n";
			print "params\n";
			print "param mac0 \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param delhost 1\n";
			print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
			print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
			print ":bootme\n";
			print "chain -ar $this->booturl/ipxe/boot.php##params ||\n";
			print "goto MENU\n";
		}
		else if ($option == 'fog.keyreg')
		{
			print ":$option\n";
			print "login\n";
			print "params\n";
			print "param mac0 \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param keyreg 1\n";
			print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
			print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
			print ":bootme\n";
			print "chain -ar $this->booturl/ipxe/boot.php##params ||\n";
			print "goto MENU\n";

		}
		else if ($option == 'fog.advanced')
		{
			print ":$option\n";
			if ($this->FOGCore->getSetting('FOG_ADVANCED_MENU_LOGIN'))
			{
				print "login\n";
				print "params\n";
				print "param mac0 \${net0/mac}\n";
				print "param arch \${arch}\n";
				print "param username \${username}\n";
				print "param password \${password}\n";
				print "param advLog  1\n";
				print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
				print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
				print ":bootme\n";
				print "chain -ar $this->booturl/ipxe/boot.php##params ||\n";
				print "goto MENU\n";
			}
			else
				print "chain -ar $this->booturl/ipxe/advanced.php || goto MENU\n";
		}
		else if ($option == 'fog.debug') 
		{
			print ":$option\n";
			print "login\n";
			print "params\n";
			print "param mac0 \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param debugAccess 1\n";
			print "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n";
			print "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n";
			print ":bootme\n";
			print "chain -ar $this->booturl/ipxe/boot.php##params ||\n";
		}
		else
		{
			print ":$option\n";
			print "$this->kernel loglevel=4 $type\n";
			print "$this->initrd";
			print "boot || goto MENU\n";
		}
	}
	/**
	* printDefault()
	* Prints the Menu which is equivalent to the
	* old default file from PXE boot.
	* @return void
	*/
	public function printDefault()
	{
		print "#!ipxe\n";
		print "cpuid --ext 29 && set arch x86_64 || set arch i386\n";
		print "colour --rgb 0xff6600 2\n";
		print "cpair --foreground 7 --background 2 2\n";
		print "console --picture $this->booturl/ipxe/bg.png --left 100 --right 80\n";
		if (!$this->hiddenmenu)
		{
		    $showDebug = $_REQUEST["debug"] === "1";
			print ":MENU\n";
			print "menu\n";
			// Checks if the host is registered or not.
			// Displays the Host name if it is, otherwise
			// Tells the user it's not registered.
			if ($this->Host && $this->Host->isValid())
			{
				print "colour --rgb 0x00ff00 0\n";
				print "cpair --foreground 0 3\n";
				print "item --gap Host is registered as ".$this->Host->get('name')."\n";
				print "item --gap -- -------------------------------------\n";
			}
			else
			{
				print "colour --rgb 0xff0000 0\n";
				print "cpair --foreground 0 3\n";
				print "item --gap Host is NOT registered!\n";
				print "item --gap -- -------------------------------------\n";
			}
			foreach($this->pxemenu AS $option => $desc)
			{
				if (!$this->Host || !$this->Host->isValid())
				{
					if ($option != 'fog.quickdel' && $option != 'fog.quickimage' && ( $showDebug || $option != 'fog.debug' ) && $option != 'fog.keyreg')
						$this->menuItem($option, $desc);
				}
				else 
				{
					if ($option != 'fog.reg' && $option != 'fog.reginput' && ( $showDebug || $option != 'fog.debug' ))
						$this->menuItem($option, $desc);
				}
			}
			print "$this->defaultChoice";
			foreach($this->pxemenu AS $option => $desc)
			{
				if (!$this->Host || !$this->Host->isValid())
				{
					if ($option == 'fog.reg')
						$this->menuOpt($option, "mode=autoreg");
					else if ($option == 'fog.reginput')
						$this->menuOpt($option, "mode=manreg");
					else if ($option == 'fog.sysinfo')
						$this->menuOpt($option, "mode=sysinfo");
					else if ($option == 'fog.debug' && $showDebug)
							$this->menuOpt($option, "mode=onlydebug");
					else if ($option == 'fog.capone')
						$this->menuOpt($option, "mode=capone shutdown=$this->shutdown storage=$this->storage:$this->path");
					else if ($option == 'fog.local' || $option == 'fog.memtest' || $option == 'fog.advanced')
						$this->menuOpt($option, true);
				}
				else
				{
					if ($option == 'fog.sysinfo')
						$this->menuOpt($option, "mode=sysinfo");
					else if ($option == 'fog.debug' && $showDebug)
						$this->menuOpt($option, "mode=onlydebug");
					else if ($option == 'fog.capone')
						$this->menuOpt($option, "mode=capone shutdown=$this->shutdown storage=$this->storage:$this->path");
					else if ($option == 'fog.local' || $option == 'fog.memtest' || $option == 'fog.advanced' || $option == 'fog.quickdel' || $option == 'fog.quickimage' || 'fog.keyreg')
						$this->menuOpt($option, true);
				}
			}
			print "autoboot";
		}
		else
			$this->chainBoot();
	}
}
=======
<?php
/**
* \class BootMenu
* Builds the ipxe menu system.
* Serves to also generate the taskings on the fly.
* Changes are automatically adjusted as needed.
* @param $Host is the host set.  Can be null.
* @param $kernel sets the kernel information.
* @param $initrd sets the init information.
* @param $booturl sets the bootup url info.
* @param $memdisk sets the memdisk info
* @param $memtest sets the memtest info
* @param $Host is the host set.  Can be null.
* @param $kernel sets the kernel information.
* @param $initrd sets the init information.
* @param $booturl sets the bootup url info.
* @param $memtest sets the memtest info.
* @param $web sets the web address.
* @param $defaultChoice chooses the defaults.
* @param $bootexittype sets the exit type to hdd.
* @param $storage sets the storage node
* @param $shutdown sets whether shutdown is set or not.
* @param $path sets the default path.
* @param $hiddenmenu sets if hidden menu is setup.
* @param $timeout gets the timout to OS/HDD
* @param $KS gets the key sequence.
* @param $debug sets the debug information. Displays debug menu.
*/
class BootMenu extends FOGBase
{
	// Variables
	private $Host,$kernel,$initrd,$booturl,$memdisk,$memtest,$web,$defaultChoice,$bootexittype;
	private $storage, $shutdown, $path;
	private $hiddenmenu, $timeout, $KS;
	public $debug;
	/** __construct($Host = null)
	* Construtor for the whole system.
	* Sets all the variables as needed.
	* @param $Host can be nothing, but is sent to
	* verify if there's a tasking for the host.
	* @return void
	*/
	public function __construct($Host = null)
	{
		parent::__construct();
		// Setups of the basic construct for the menu system.
		$StorageNode = current($this->getClass('StorageNodeManager')->find(array('isEnabled' => 1, 'isMaster' => 1)));
		// Sets up the default values stored in the server. Lines 51 - 64
		$webserver = $this->FOGCore->resolveHostname($this->FOGCore->getSetting('FOG_WEB_HOST'));
		$webroot = '/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/';
		$this->web = "${webserver}${webroot}";
		$this->bootexittype = ($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'exit' ? 'exit' : ($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'sanboot' ? 'sanboot --no-describe --drive 0x80' : ($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'grub' ? 'chain -ar http://'.rtrim($this->web,'/').'/service/ipxe/grub.exe --config-file="rootnoverify (hd0);chainloader +1"' : 'exit')));
		$ramsize = $this->FOGCore->getSetting('FOG_KERNEL_RAMDISK_SIZE');
		$dns = $this->FOGCore->getSetting('FOG_PXE_IMAGE_DNSADDRESS');
		$keymap = $this->FOGCore->getSetting('FOG_KEYMAP');
		$timeout = $this->FOGCore->getSetting('FOG_PXE_MENU_TIMEOUT') * 1000;
		$this->timeout = $timeout;
		$memdisk = 'memdisk';
		$memtest = $this->FOGCore->getSetting('FOG_MEMTEST_KERNEL');
		// Default bzImage and imagefile based on arch received.
		$bzImage = ($_REQUEST['arch'] == 'x86_64' ? $this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL') : $this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL_32'));
		$kernel = $bzImage;
		$imagefile = ($_REQUEST['arch'] == 'x86_64' ? $this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE') : $this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE_32'));
		$initrd = $imagefile;
		// Adjust file info if host is valid.
		if ($Host && $Host->isValid())
		{
			// If the host kernel param is set, use that kernel to boot the host.
			($Host->get('kernel') ? $bzImage = $Host->get('kernel') : null);
			$kernel = $bzImage;
			$this->HookManager->processEvent('BOOT_ITEM_NEW_SETTINGS',array('Host' => &$Host,'StorageGroup' => &$StorageGroup,'StorageNode' => &$StorageNode,'memtest' => &$memtest,'memdisk' => &$memdisk,'bzImage' => &$bzImage,'initrd' => &$initrd,'webroot' => &$webroot,'imagefile' => &$imagefile));
		}
		// Sets the key sequence.  Only used if the hidden menu option is selected.
		$keySequence = $this->FOGCore->getSetting('FOG_KEY_SEQUENCE');
		if ($keySequence)
			$this->KS = new KeySequence($keySequence);
		// menu Access sets if the menu is displayed.  Menu access is a url get variable if a user has specified hidden menu it will override if menuAccess is set.
		if (!$_REQUEST['menuAccess'])
			$this->hiddenmenu = $this->FOGCore->getSetting('FOG_PXE_MENU_HIDDEN');
		// Generate the URL to boot from.
		$this->booturl = "http://${webserver}${webroot}service";
		// Store the host call into class global.
		$this->Host = $Host;
		// Capone menu setup.
		$CaponePlugInst = current($this->getClass('PluginManager')->find(array('name' => 'capone','state' => 1,'installed' => 1)));
		$DMISet = $CaponePlugInst ? $this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_DMI') : false;
		// If it is installed store the needed elements into variables.
		if ($CaponePlugInst)
		{
			$this->storage = $StorageNode->get('ip');
			$this->path = $StorageNode->get('path');
			$this->shutdown = $this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN');
		}
		// Create menu item if not exists and Capone is installed as well as the DMI is specified.
		if ($CaponePlugInst && $DMISet)
		{
			// Check for fog.capone if the pxe menu entry exists.
			$PXEMenuItem = current($this->getClass('PXEMenuOptionsManager')->find(array('name' => 'fog.capone')));
			// If it does exist, generate the updated arguments for each call.
			if ($PXEMenuItem && $PXEMenuItem->isValid())
				$PXEMenuItem->set('args',"mode=capone shutdown=$this->shutdown storage=$this->storage:$this->path");
			// If it does not exist, create the menu entry.
			else
			{
				$PXEMenuItem = new PXEMenuOptions(array(
					'name' => 'fog.capone',
					'description' => 'Capone Deploy',
					'args' => "mode=capone shutdown=$this->shutdown storage=$this->storage:$this->path",
					'params' => null,
					'default' => '0',
					'regMenu' => '2',
				));
			}
			$PXEMenuItem->save();
		}
		// Specify the default calls.
		$this->memdisk = "kernel $memdisk";
		$this->memtest = "initrd $memtest";
		$this->kernel = "kernel $bzImage initrd=$initrd root=/dev/ram0 rw ramdisk_size=$ramsize keymap=$keymap web=${webserver}${webroot} consoleblank=0";
		$this->initrd = "imgfetch $imagefile";
		// Set the default line based on all the menu entries and only the one with the default set.
		$defMenuItem = current($this->getClass('PXEMenuOptionsManager')->find(array('default' => 1)));
		$this->defaultChoice = "choose --default ".($defMenuItem && $defMenuItem->isValid() ? $defMenuItem->get('name') : 'fog.local')." --timeout $timeout target && goto \${target}";
		// Register the success of the boot to the database:
		$iPXE = current($this->getClass('iPXEManager')->find(array('product' => $_REQUEST['product'],'manufacturer' => $_REQUEST['manufacturer'],'file' => $_REQUEST['filename'])));
		if ($iPXE && $iPXE->isValid())
		{
			if ($iPXE->get('failure'))
				$iPXE->set('failure',0);
			if (!$iPXE->get('success'))
				$iPXE->set('success',1);
			if (!$iPXE->get('version'))
				$iPXE->set('version',$_REQUEST['ipxever']);
		}
		else if (!$iPXE || !$iPXE->isValid())
		{
			$iPXE = new iPXE(array(
				'product' => $_REQUEST['product'],
				'manufacturer' => $_REQUEST['manufacturer'],
				'mac' => $Host->get('mac'),
				'success' => 1,
				'file' => $_REQUEST['filename'],
				'version' => $_REQUEST['ipxever'],
			));
		}
		$iPXE->save();
		if ($_REQUEST['username'] && $_REQUEST['password'])
			$this->verifyCreds();
		else if ($_REQUEST['delconf'])
			$this->delHost();
		else if ($_REQUEST['key'])
			$this->keyset();
		else if ($_REQUEST['sessname'])
			$this->sesscheck();
		else if ($_REQUEST['aprvconf'])
			$this->approveHost();
		else if (!$Host || !$Host->isValid())
			$this->printDefault();
		else
			$this->getTasking();
	}
	/**
	* chainBoot()
	* Prints the bootmenu or hides it.  If access is not allowed but tried
	* requests login information from WEB GUI.
	* Used often for return to menu/check tasking after setting somthing.
	* $debug is a flat to indicate if we show the debug menu item.  Typically
	* you only want this after a person authenticates.
	* $shortCircuit is a flag that will shortCircuit the hiddenMenu check.
	* This is needed for quick image.
	* @param $debug set to false but if true enables access.
	* @param $shortCircuit set to false, but if true enables display.
	* @return void
	*/
	private function chainBoot($debug=false, $shortCircuit=false)
	{
	    // csyperski: added hiddenMenu check; without it entering
		// any string for username and password would show the menu, even if it was hidden
	    if (!$this->hiddenmenu || $shortCircuit)
		{
    		$Send['chainnohide'] = array(
				"#!ipxe",
				"cpuid --ext 29 && set arch x86_64 || set arch i386",
				"params",
				"param mac0 \${net0/mac}",
				"param arch \${arch}",
				"param menuAccess 1",
				"param debug ".($debug ? 1 : 0),
				"isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme",
				"isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme",
				":bootme",
	    		"chain -ar $this->booturl/ipxe/boot.php##params",
			);
	    } 
	    else
	    {
	        $Send['chainhide'] = array(
				"#!ipxe",
				"prompt --key ".($this->KS && $this->KS->isValid() ? $this->KS->get('ascii') : '0x1b')." --timeout $this->timeout Booting... (Press ".($this->KS && $this->KS->isValid() ?  $this->KS->get('name') : 'Escape')." to access the menu) && goto menuAccess || $this->bootexittype",
				":menuAccess",
				"login",
				"params",
				"param mac0 \${net0/mac}",
				"param arch \${arch}",
				"param username \${username}",
				"param password \${password}",
				"param menuaccess 1",
				"param debug ".($debug ? 1 : 0),
				"isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme",
				"isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme",
				":bootme",
				"chain -ar $this->booturl/ipxe/boot.php##params",
			);
	    }
		$this->parseMe($Send);
	}
	/**
	* delHost()
	* Deletes the host from the system.
	* If it fails will return that it failed.
	* Each interval sends back to chainBoot()
	* @return void
	*/
	private function delHost()
	{
		if($this->Host->destroy())
		{
			$Send['delsuccess'] = array(
				"#!ipxe",
				"echo Host deleted successfully",
				"sleep 3"
			);
		}
		else
		{
			$Send['delfail'] = array(
				"#!ipxe",
				"echo Failed to destroy Host!",
				"sleep 3",
			);
		}
		$this->parseMe($Send);
		$this->chainBoot();
	}
	private function printImageIgnored()
	{
		$Send['ignored'] = array(
			"#!ipxe",
			"echo The MAC Address is set to be ignored for imaging tasks",
			"sleep 15",
		);
		$this->parseMe($Send);
		$this->noMenu();
	}
	private function approveHost()
	{
		if ($this->Host->set('pending',null)->save())
		{
			$Send['approvesuccess'] = array(
				"#!ipxe",
				"echo Host approved successfully",
				"sleep 3"
			);
			$this->Host->createImagePackage(10,'Inventory',false,false,false,false,'ipxe');
		}
		else
		{
			$Send['approvefail'] = array(
				"#!ipxe",
				"echo Host approval failed",
				"sleep 3"
			);
		}
		$this->parseMe($Send);
		$this->chainBoot();
	}
	/**
	* printTasking()
	* Sends the Tasking file.  In PXE this is equivalent to the creation
	* of the 01-XX-XX-XX-XX-XX-XX file.
	* Just tells the system it's got a task.
	* @param $kernelArgsArray sets up the tasking through the 
	* kernelArgs information.
	* @return void
	*/
	private function printTasking($kernelArgsArray)
	{
		foreach($kernelArgsArray AS $arg)
        {   
            if (!is_array($arg) && !empty($arg) || (is_array($arg) && $arg['active'] && !empty($arg)))
                $kernelArgs[] = (is_array($arg) ? $arg['value'] : $arg);
        }   
        $kernelArgs = array_unique($kernelArgs);
		$Send['task'] = array(
			"#!ipxe",
        	"$this->kernel loglevel=4 ".implode(' ',(array)$kernelArgs),
        	"$this->initrd",
        	"boot",
		);
		$this->parseMe($Send);
	}
	/**
	* delConf()
	* If you're trying to delete the host, requests confirmation of deletion.
	* @return void
	*/
	public function delConf()
	{
		$Send['delconfirm'] = array(
			"#!ipxe",
			"cpuid --ext 29 && set arch x86_64 || set arch i386",
			"prompt --key y Would you like to delete this host? (y/N): &&",
			"params",
			"param mac0 \${net0/mac}",
			"param arch \${arch}",
			"param delconf 1",
			"isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme",
			"isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme",
			":bootme",
			"chain -ar $this->booturl/ipxe/boot.php##params",
		);
		$this->parseMe($Send);
	}
	/**
	* aprvConf()
	* If you're trying to approve the host, request confirmation.
	* @return void
	*/
	public function aprvConf()
	{
		$Send['aprvconfirm'] = array(
			"#!ipxe",
			"cpuid --ext 29 && set arch x86_64 || set arch i386",
			"prompt --key y Would you like to approve this host? (y/N): &&",
			"params",
			"param mac0 \${net0/mac}",
			"param arch \${arch}",
			"param aprvconf 1",
			"isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme",
			"isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme",
			":bootme",
			"chain -ar $this->booturl/ipxe/boot.php##params",
		);
		$this->parseMe($Send);
	}
	/**
	* keyreg()
	* If you're trying to change the key, request what the key is.
	* @return void
	*/
	public function keyreg()
	{
		$Send['keyreg'] = array(
			"#!ipxe",
			"cpuid --ext 29 && set arch x86_64 || set arch i386",
			"echo -n Please enter the product key>",
			"read key",
			"params",
			"param mac0 \${net0/mac}",
			"param arch \${arch}",
			"param key \${key}",
			"isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme",
			"isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme",
			":bootme",
			"chain -ar $this->booturl/ipxe/boot.php##params",
		);
		$this->parseMe($Send);
	}
	/**
	* sesscheck()
	* Verifys the name
	* @return void
	*/
	public function sesscheck()
	{
		$sesscount = current($this->getClass('MulticastSessionsManager')->find(array('name' => $_REQUEST['sessname'],'stateID' => array(0,1))));
		if (!$sesscount || !$sesscount->isValid())
		{
			$Send['checksession'] = array(
				"#!ipxe",
				"echo No session found with that name.",
				"clear sessname",
				"sleep 3",
				"cpuid --ext 29 && set arch x86_64 || set arch i386",
				"params",
				"param mac0 \${net0/mac}",
				"param arch \${arch}",
				"param sessionJoin 1",
				"isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme",
				"isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme",
				":bootme",
				"chain -ar $this->booturl/ipxe/boot.php##params",
			);
			$this->parseMe($Send);
		}
		else
			$this->multijoin($sesscount->get('id'));
	}

	/**
	* sessjoin()
	* Gets the relevant information and passes when verified.
	* @return void
	*/
	public function sessjoin()
	{
		$Send['joinsession'] = array(
			"#!ipxe",
			"cpuid --ext 29 && set arch x86_64 || set arch i386",
			"echo -n Please enter the session name to join>",
			"read sessname",
			"params",
			"param mac0 \${net0/mac}",
			"param arch \${arch}",
			"param sessname \${sessname}",
			"isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme",
			"isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme",
			":bootme",
			"chain -ar $this->booturl/ipxe/boot.php##params",
		);
		$this->parseMe($Send);
	}
	/**
	* multijoin()
	* Joins the host to an already generated multicast session
	* @return void
	*/
	public function multijoin($msid)
	{
		$MultiSess = new MulticastSessions($msid);
		if ($MultiSess && $MultiSess->isValid())
		{
			$this->Host->set('imageID',$MultiSess->get('image'));
			 // Create the host task
			if($this->Host->createImagePackage(8,$MultiSess->get('name'),false,false,true,false,'ipxe'))
				$this->chainBoot(false,true);
		}
	}
	/**
	* keyset()
	* Set's the product key using the ipxe menu.
	* @return void
	*/
	public function keyset()
	{
		$this->Host->set('productKey',base64_encode($_REQUEST['key']));
		if ($this->Host->save())
		{
			$Send['keychangesuccess'] = array(
				"#!ipxe",
				"echo Successfully changed key",
				"sleep 3",
			);
			$this->parseMe($Send);
			$this->chainBoot();
		}
	}
	/**
	* parseMe($Send)
	* @param $Send the data to be sent.
	* @return void
	*/
	private function parseMe($Send)
	{
		$this->HookManager->processEvent('IPXE_EDIT',array('ipxe' => &$Send,'Host' => &$this->Host,'kernel' => &$this->kernel,'initrd' => &$this->initrd,'booturl' => &$this->booturl, 'memdisk' => &$this->memdisk,'memtest' => &$this->memtest, 'web' => &$this->web, 'defaultChoice' => &$this->defaultChoice, 'bootexittype' => &$this->bootexittype,'storage' => &$this->storage,'shutdown' => &$this->shutdown,'path' => &$this->path,'timeout' => &$this->timeout,'KS' => $this->ks));
		foreach($Send AS $ipxe => $val)
			print implode("\n",$val)."\n";
	}
	/**
	* advLogin()
	* If advanced login is set this just passes when verifyCreds is correct
	* @return void
	*/
	public function advLogin()
	{
		$Send['advancedlogin'] = array(
			"#!ipxe",
			"chain -ar $this->booturl/ipxe/advanced.php",
		);
		$this->parseMe($Send);
	}
	/**
	* debugAccess()
	* Set's up for debug menu as requested.
	* @return void
	*/
	private function debugAccess()
	{
		$Send['debugaccess'] = array(
			"#!ipxe",
			"$this->kernel mode=onlydebug",
			"$this->initrd",
			"boot",
		);
		$this->parseMe($Send);
	}
	/**
	* verifyCreds()
	* Verifies the login information is valid
	* and correct.
	* Otherwise return that it's broken.
	* @return void
	*/
	public function verifyCreds()
	{
		if ($this->FOGCore->attemptLogin($_REQUEST['username'],$_REQUEST['password']))
		{
			if ($this->FOGCore->getSetting('FOG_ADVANCED_MENU_LOGIN') && $_REQUEST['advLog'])
				$this->advLogin();
			if ($_REQUEST['delhost'])
				$this->delConf();
			else if ($_REQUEST['keyreg'])
				$this->keyreg();
			else if ($_REQUEST['qihost'])
				$this->setTasking();
			else if ($_REQUEST['sessionJoin'])
				$this->sessjoin();
			else if ($_REQUEST['approveHost'])
				$this->aprvConf();
			else if ($_REQUEST['menuaccess'])
			{
				unset($this->hiddenmenu);
				$this->chainBoot(true);
			}
			else if ($_REQUEST['debugAccess'])
				$this->debugAccess();
			else if (!$this->FOGCore->getSetting('FOG_NO_MENU'))
				$this->printDefault();
			else
				$this->noMenu();
		}
		else
		{
			$Send['invalidlogin'] = array(
				"#!ipxe",
				"echo Invalid login!",
				"clear username",
				"clear password",
				"sleep 3",
			);
			$this->parseMe($Send);
			$this->chainBoot();
		}
	}
	/**
	* setTasking()
	* If quick image tasking requested, this sets up the tasking.
	* @return void
	*/
	public function setTasking()
	{
		if($this->Host->createImagePackage(1,'AutoRegTask',false,false,true,false,'ipxe'))
			$this->chainBoot(false, true);
	}
	/**
	* noMenu()
	* If no menu option is set, just exits to harddrive if there's no tasking.
	* @return void
	*/
	public function noMenu()
	{
		$Send['nomenu'] = array(
			"#!ipxe",
			"$this->bootexittype",
		);
		$this->parseMe($Send);
	}
	/**
	* getTasking()
	* Finds out if there's a tasking for the relevant host.
	* if there is, returns the printTasking, otherwise 
	* presents the menu.
	* @return void
	*/
	public function getTasking()
	{
		$Task = $this->Host->get('task');
		if (!$Task->isValid())
		{
			if ($this->FOGCore->getSetting('FOG_NO_MENU'))
				$this->noMenu();
			else
				$this->printDefault();
		}
		else
		{
			if ($this->Host->get('mac')->isImageIgnored())
				$this->printImageIgnored();
			$TaskType = new TaskType($Task->get('typeID'));
			$imagingTasks = array(1,2,8,15,16,17,24);
			if ($TaskType->isMulticast())
			{
				$MulticastSessionAssoc = current($this->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
				$MulticastSession = new MulticastSessions($MulticastSessionAssoc->get('msID'));
				if ($MulticastSession && $MulticastSession->isValid())
					$this->Host->set('imageID',$MulticastSession->get('image'));
			}
			$Image = $this->Host->getImage();
			$StorageGroup = $Image->getStorageGroup();
			$StorageNode = $StorageGroup->getOptimalStorageNode();
			$this->HookManager->processEvent('BOOT_TASK_NEW_SETTINGS',array('Host' => &$this->Host,'StorageNode' => &$StorageNode,'StorageGroup' => &$StorageGroup));
			if ($TaskType->isUpload() || $TaskType->isMulticast())
				$StorageNode = $StorageGroup->getMasterStorageNode();
			if ($this->Host && $this->Host->isValid())
				$mac = $this->Host->get('mac');
			else
				$mac = $_REQUEST['mac'];
			$osid = $Image->get('osID');
			$storage = in_array($TaskType->get('id'),$imagingTasks) ? sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),($TaskType->isUpload() ? 'dev/' : '')) : null;
			$clamav = in_array($TaskType->get('id'),array(21,22)) ? sprintf('%s:%s',trim($StorageNode->get('ip')),'/opt/fog/clamav') : null;
			$storageip = in_array($TaskType->get('id'),$imagingTasks) ? $StorageNode->get('ip') : null;
			$img = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('path') : null;
			$imgFormat = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('format') : null;
			$imgType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImageType()->get('type') : null;
			$imgPartitionType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImagePartitionType()->get('type') : null;
			$imgid = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('id') : null;
			$ftp = $this->FOGCore->resolveHostname($this->FOGCore->getSetting('FOG_TFTP_HOST'));
			$chkdsk = $this->FOGCore->getSetting('FOG_DISABLE_CHKDSK') == 1 ? 0 : 1;
			$PIGZ_COMP = $this->FOGCore->getSetting('FOG_PIGZ_COMP');
			$kernelArgsArray = array(
				"mac=$mac",
				"ftp=$ftp",
				"storage=$storage",
				"storageip=$storageip",
				"web=$this->web",
				"osid=$osid",
				"loglevel=4",
				"consoleblank=0",
				"irqpoll",
				"hostname=".$this->Host->get('name'),
				array(
					'value' => "clamav=$clamav",
					'active' => in_array($TaskType->get('id'),array(21,22)),
				),
				array(
					'value' => "chkdsk=$chkdsk",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "img=$img",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "imgType=$imgType",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "imgPartitionType=$imgPartitionType",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "imgid=$imgid",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "imgFormat=$imgFormat",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => "PIGZ_COMP=-$PIGZ_COMP",
					'active' => in_array($TaskType->get('id'),$imagingTasks),
				),
				array(
					'value' => 'shutdown=1',
					'active' => $Task->get('shutdown'),
				),
				array(
					'value' => 'adon=1',
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'addomain='.$this->Host->get('ADDomain'),
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'adou='.$this->Host->get('ADOU'),
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'aduser='.$this->Host->get('ADUser'),
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'adpass='.$this->Host->get('ADPass'),
					'active' => $this->Host->get('useAD'),
				),
				array(
					'value' => 'fdrive='.$this->Host->get('kernelDevice'),
					'active' => $this->Host->get('kernelDevice'),
				),
				array(
					'value' => 'hostearly=1',
					'active' => $this->FOGCore->getSetting('FOG_CHANGE_HOSTNAME_EARLY') && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
				),
				array(
					'value' => 'pct='.(is_numeric($this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT')) && $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') >= 5 && $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') < 100 ? $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') : '5'),
					'active' => $TaskType->isUpload() && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
				),
				array(
					'value' => 'ignorepg='.($this->FOGCore->getSetting('FOG_UPLOADIGNOREPAGEHIBER') ? 1 : 0),
					'active' => $TaskType->isUpload() && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
				),
				array(
					'value' => 'port='.($TaskType->isMulticast() ? $MulticastSession->get('port') : null),
					'active' => $TaskType->isMulticast(),
				),
				array(
					'value' => 'mining=1',
					'active' => $this->FOGCore->getSetting('FOG_MINING_ENABLE'),
				),
				array(
					'value' => 'miningcores=' . $this->FOGCore->getSetting('FOG_MINING_MAX_CORES'),
					'active' => $this->FOGCore->getSetting('FOG_MINING_ENABLE'),
				),
				array(
					'value' => 'winuser='.$Task->get('passreset'),
					'active' => $TaskType->get('id') == '11' ? true : false,
				),
				array(
					'value' => 'miningpath=' . $this->FOGCore->getSetting('FOG_MINING_PACKAGE_PATH'),
					'active' => $this->FOGCore->getSetting('FOG_MINING_ENABLE'),
				),
				array(
					'value' => 'isdebug=yes',
					'active' => $Task->get('isDebug'),
				),
				$TaskType->get('kernelArgs'),
				$this->FOGCore->getSetting('FOG_KERNEL_ARGS'),
				$this->Host->get('kernelArgs'),
			);
			if ($Task->get('typeID') == 12 || $Task->get('typeID') == 13)
				$this->printDefault();
			else if ($Task->get('typeID') == 4)
			{
				$Send['memtest'] = array(
					"#!ipxe",
					"$this->memdisk iso raw",
					"$this->memtest",
					"boot",
				);
				$this->parseMe($Send);
			}
			else
				$this->printTasking($kernelArgsArray);
		}
	}
	/**
	* menuItem()
	* @param $option the menu option
	* @param $desc the description of the menu item.
	* Prints the menu items.
	* @return the string as passed.
	*/
	private function menuItem($option, $desc)
	{
		return array("item ".$option->get('name')." ".$option->get('description'));
	}
	/**
	* menuOpt()
	* Prints the actual menu related items for booting.
	* @param $option the related menu option
	* @param $type the type of menu information
	* @return $Send sends the data for the menu item.
	*/
	private function menuOpt($option,$type)
	{
		if ($option->get('id') == 1)
		{
			$Send = array(
				":".$option->get('name'),
				"$this->bootexittype || goto MENU",
			);
		}
		else if ($option->get('id') == 2)
		{
			$Send = array(
				":".$option->get('name'),
				"$this->memdisk iso raw",
				"$this->memtest",
				"boot || goto MENU",
			);
		}
		else if ($option->get('id') == 11)
		{
			$Send = array(
				":".$option->get('name'),
				"chain -ar $this->booturl/ipxe/advanced.php || goto MENU",
			);
		}
		else if ($option->get('params'))
		{
			$Send = array(
				':'.$option->get('name'),
				$option->get('params'),
			);
		}
		else
		{
			$Send = array(
				":$option",
				"$this->kernel loglevel=4 $type",
				"$this->initrd",
				"boot || goto MENU",
			);
		}
		return $Send;
	}
	/**
	* printDefault()
	* Prints the Menu which is equivalent to the
	* old default file from PXE boot.
	* @return void
	*/
	public function printDefault()
	{
		// Gets all the database menu items.
		$Menus = $this->getClass('PXEMenuOptionsManager')->find('','','id');
		$Send['head'] = array(
			"#!ipxe",
			"cpuid --ext 29 && set arch x86_64 || set arch i386",
			"goto get_console",
			":console_set",
			"colour --rgb 0xff6600 2",
			"cpair --foreground 7 --background 2 2",
			"goto MENU",
			":alt_console",
			"cpair --background 0 1 && cpair --background 1 2",
			"goto MENU",
			":get_console",
			"console --picture $this->booturl/ipxe/bg.png --left 100 --right 80 && goto console_set || goto alt_console",
		);
		if (!$this->hiddenmenu)
		{
		    $showDebug = $_REQUEST["debug"] === "1";
			$Send['menustart'] = array(
				":MENU",
				"menu",
				"colour --rgb ".($this->Host && $this->Host->isValid() ? "0x00ff00" : "0xff0000")." 0",
				"cpair --foreground 0 3",
				"item --gap Host is ".($this->Host && $this->Host->isValid() ? ($this->Host->get('pending') ? 'pending ' : '')."registered as ".$this->Host->get('name') : "NOT registered!"),
				"item --gap -- -------------------------------------",
			);
			$Advanced = $this->FOGCore->getSetting('FOG_PXE_ADVANCED');
			$AdvLogin = $this->FOGCore->getSetting('FOG_ADVANCED_MENU_LOGIN');
			$ArrayOfStuff = array(($this->Host && $this->Host->isValid() ? ($this->Host->get('pending') ? 6 : 1) : 0),2);
			if ($showDebug)
				array_push($ArrayOfStuff,3);
			if ($Advanced)
				array_push($ArrayOfStuff,($AdvLogin ? 5 : 4));
			foreach($Menus AS $Menu)
			{
				if (in_array($Menu->get('regMenu'),$ArrayOfStuff))
					$Send['item-'.$Menu->get('name')] = $this->menuItem($Menu, $desc);
			}
			$Send['default'] = array(
				"$this->defaultChoice",
			);
			foreach($Menus AS $Menu)
			{
				if (in_array($Menu->get('regMenu'),$ArrayOfStuff))
					$Send['choice-'.$Menu->get('name')] = $Menu->get('args') ? $this->menuOpt($Menu,$Menu->get('args')) : $this->menuOpt($Menu,true);
			}
			$Send['bootme'] = array(
				":bootme",
				"chain -ar $this->booturl/ipxe/boot.php##params ||",
				"goto MENU",
				"autoboot",
			);
			$this->parseMe($Send);
		}
		else
			$this->chainBoot(true);
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
>>>>>>> dev-branch
