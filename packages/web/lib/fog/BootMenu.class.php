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
		$CaponePlugInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'capone','state' => 1,'installed' => 1)));
		$DMISet = $CaponePlugInst ? $this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_DMI') : false;
		if ($CaponePlugInst)
		{
			$this->storage = $StorageNode->get('ip');
			$this->path = $StorageNode->get('path');
			$this->shutdown = $this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN');
		}
		if ($CaponePlugInst && $DMISet)
		{
			// Check for fog.capone if the pxe menu entry exists.
			$PXEMenuItem = current($this->FOGCore->getClass('PXEMenuOptionsManager')->find(array('name' => 'fog.capone')));
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
		$this->memdisk = "kernel $memdisk";
		$this->memtest = "initrd $memtest";
		$this->kernel = "kernel $bzImage initrd=$imagefile root=/dev/ram0 rw ramdisk_size=$ramsize ip=dhcp dns=$dns keymap=$keymap web=${webserver}${webroot} consoleblank=0";
		$this->initrd = "imgfetch $imagefile";
		// Set the default line based on all the menu entries and only the one with the default set.
		$defMenuItem = current($this->FOGCore->getClass('PXEMenuOptionsManager')->find(array('default' => 1)));
		$this->defaultChoice = "choose --default ".($defMenuItem && $defMenuItem->isValid() ? $defMenuItem->get('name') : 'fog.local')." --timeout $timeout target && goto \${target}";
		if ($_REQUEST['username'] && $_REQUEST['password'])
			$this->verifyCreds();
		else if ($_REQUEST['delconf'])
			$this->delHost();
		else if ($_REQUEST['key'])
			$this->keyset();
		else if ($_REQUEST['sessname'])
			$this->sesscheck();
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
    		$Send[] = array(
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
	        $Send[] = array(
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
			$Send[] = array(
				"#!ipxe",
				"echo Host deleted successfully",
				"sleep 3"
			);
		}
		else
		{
			$Send[] = array(
				"#!ipxe",
				"echo Failed to destroy Host!",
				"sleep 3",
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
		$Send[] = array(
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
		$Send[] = array(
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
	* keyreg()
	* If you're trying to change the key, request what the key is.
	* @return void
	*/
	public function keyreg()
	{
		$Send[] = array(
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
		$sesscount = current($this->FOGCore->getClass('MulticastSessionsManager')->find(array('name' => $_REQUEST['sessname'])));
		if (!$sesscount || !$sesscount->isValid())
		{
			$Send[] = array(
				"#!ipxe",
				"echo no session found with that name.",
				"sleep 3",
				"cpuid --ext 29 && set arch x86_64 || set arch i386",
				"params",
				"param mac0 \${net0/mac}",
				"param arch \${arch}",
				"param sessionjoin 1",
				"isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme",
				"isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme",
				":bootme",
				"chain -ar $this->booturl/ipxe/boot.php##params",
			);
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
		$Send[] = array(
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
		// Create the host task
		if($this->Host->createImagePackage(8,$MultiSess->get('name'),false,false,true,false,$_REQUEST['username']))
			$this->chainBoot(false,true);
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
			$Send[] = array(
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
		$Send[] = array(
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
		$Send[] = array(
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
			else if ($_REQUEST['sessionjoin'])
				$this->sessjoin();
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
			$Send[] = array(
				"#!ipxe",
				"echo Invalid login!",
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
		$Send[] = array(
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
			if ($this->Host && $this->Host->isValid())
				$mac = $this->Host->get('mac');
			else
				$mac = $_REQUEST['mac'];
			$osid = $Image->get('osID');
			$storage = in_array($TaskType->get('id'),$imagingTasks) ? sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),($TaskType->isUpload() ? 'dev/' : '')) : null;
			$storageip = in_array($TaskType->get('id'),$imagingTasks) ? $StorageNode->get('ip') : null;
			$img = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('path') : null;
			$imgFormat = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('format') : null;
			$imgType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImageType()->get('type') : null;
			$imgPartitionType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImagePartitionType()->get('type') : null;
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
				$TaskType->get('kernelArgs'),
				$this->FOGCore->getSetting('FOG_KERNEL_ARGS'),
				$this->Host->get('kernelArgs'),
			);
			if ($Task->get('typeID') == 12 || $Task->get('typeID') == 13)
				$this->printDefault();
			else if ($Task->get('typeID') == 4)
			{
				$Send[] = array(
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
		$Menus = $this->FOGCore->getClass('PXEMenuOptionsManager')->find('','','id');
		$IPXE['head'] = array(
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
			$IPXE['menustart'] = array(
				":MENU",
				"menu",
				"colour --rgb ".($this->Host && $this->Host->isValid() ? "0x00ff00" : "0xff0000")." 0",
				"cpair --foreground 0 3",
				"item --gap Host is ".($this->Host && $this->Host->isValid() ? "registered as ".$this->Host->get('name') : "NOT registered!"),
				"item --gap -- -------------------------------------",
			);
			$Advanced = $this->FOGCore->getSetting('FOG_PXE_ADVANCED');
			$AdvLogin = $this->FOGCore->getSetting('FOG_ADVANCED_MENU_LOGIN');
			$ArrayOfStuff = array(($this->Host && $this->Host->isValid() ? 1 : 0),2);
			if ($Advanced)
				array_push($ArrayOfStuff,($AdvLogin ? 5 : 4));
			foreach($Menus AS $Menu)
			{
				if (in_array($Menu->get('regMenu'),$ArrayOfStuff))
					$IPXE['menuitem'.$Menu->get('id')] = $this->menuItem($Menu, $desc);
			}
			$IPXE[] = array(
				"$this->defaultChoice",
			);
			foreach($Menus AS $Menu)
			{
				if (in_array($Menu->get('regMenu'),$ArrayOfStuff))
					$IPXE['menuchoice'.$Menu->get('id')] = $Menu->get('args') ? $this->menuOpt($Menu,$Menu->get('args')) : $this->menuOpt($Menu,true);
			}
			$IPXE[] = array(
				":bootme",
				"chain -ar $this->booturl/ipxe/boot.php##params ||",
				"goto MENU",
				"autoboot",
			);
			$this->parseMe($IPXE);
		}
		else
			$this->chainBoot();
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
