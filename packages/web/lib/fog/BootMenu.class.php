<?php
/**	Class Name: BootMenu
	Builds the ipxe menu system.
	Serves to also generate the taskings on the fly.
	Changes are automatically adjusted as needed.
*/
class BootMenu 
{
	// Variables
	private $Host,$pxemenu,$kernel,$initrd,$booturl,$memtest,$web,$defaultChoice;
	private $storage, $shutdown, $path;
	private $hiddenmenu, $timeout, $KS;
	private $debug, $FOGCore;
	/** \function __construct
		Construtor for the whole system.
	 	Sets all the variables as needed.
	*/
	public function __construct($Host = null)
	{
		// Sets the 
		$this->FOGCore = $GLOBALS['FOGCore'];
		$StorageNode = current($this->FOGCore->getClass('StorageNodeManager')->find(array('isEnabled' => 1, 'isMaster' => 1)));
		$webserver = $this->FOGCore->resolveHostname($this->FOGCore->getSetting('FOG_WEB_HOST'));
		$webroot = $this->FOGCore->getSetting('FOG_WEB_ROOT');
		$this->web = "${webserver}${webroot}";
		$ramsize = $this->FOGCore->getSetting('FOG_KERNEL_RAMDISK_SIZE');
		$dns = $this->FOGCore->getSetting('FOG_PXE_IMAGE_DNSADDRESS');
		$keymap = $this->FOGCore->getSetting('FOG_KEYMAP');
		$timeout = $this->FOGCore->getSetting('FOG_PXE_MENU_TIMEOUT') * 1000;
		$this->timeout = $timeout;
		if ($Host && $Host->isValid() && $Host->get('kernel'))
			$bzImage = $Host->get('kernel');
		else if ($_REQUEST['arch'] != 'x86_64')
			$bzImage = $this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL_32');
		else
			$bzImage = $this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL');
		$memtest = $this->FOGCore->getSetting('FOG_MEMTEST_KERNEL');
		if ($_REQUEST['arch'] != 'x86_64')
			$imagefile = $this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE_32');
		else
			$imagefile = $this->FOGCore->getSetting('FOG_PXE_BOOT_IMAGE');
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
			'fog.reg' => 'Quick Registration and Inventory',
			'fog.quickimage' => 'Quick Image',
			'fog.quickdel' => 'Quick Host Deletion',
			'fog.sysinfo' => 'Client System Information (Compatibility)',
			'fog.debug' => 'Debug Mode',
		);
		$CaponePlugInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'capone','installed' => 1)));
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
		$this->memtest = "initrd $memtest";
		$this->kernel = "kernel $bzImage root=/dev/ram0 rw ramdisk_size=$ramsize ip=dhcp dns=$dns keymap=$keymap web=${webserver}${webroot} consoleblank=0";
		$this->initrd = "imgfetch $imagefile\n";
		$this->defaultChoice = "choose --default fog.local --timeout $timeout target && goto \${target}\n";
		if ($_REQUEST['username'] && $_REQUEST['password'])
			$this->verifyCreds();
		else if ($_REQUEST['delconf'])
			$this->delHost();
		else if (!$Host || !$Host->isValid())
			$this->printDefault();
		else
			$this->getTasking();
	}

	// Used often for return to menu/check tasking after setting something.
	// $debug is a flat to indicate if we should show the debug menu item; typically you only want to do this after
	// a person has authenticated
	// $shortCircuit is a flag that will shortCircuit the hiddenMenu check; this is need for quick image
	private function chainBoot($debug=false, $shortCircuit=false)
	{
	    // csyperski: added hiddenMenu check; without it entering
		// any string for username and password would show the menu, even if it was hidden
	    if (!$this->hiddenmenu || $shortCircuit)
		{
    		print "#!ipxe\n";
			print "cpuid --ext 29 && set arch x86_64 || set arch i386\n";
			print "params\n";
			print "param mac \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param menuAccess 1\n";
			print "param debug ".($debug ? "1\n" : "0\n");
	    	print "chain $this->booturl/ipxe/boot.php##params\n";
	    } 
	    else
	    {
	        print "prompt --key ".($this->KS && $this->KS->isValid() ? $this->KS->get('ascii') : '0x1b')." --timeout $this->timeout Booting... (Press ".($this->KS && $this->KS->isValid() ?  $this->KS->get('name') : 'Escape')." to access the menu) && goto menuAccess || sanboot --no-describe --drive 0x80\n";
			print ":menuAccess\n";
			print "login\n";
			print "params\n";
			print "param mac \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param menuaccess 1\n";
			print "param debug ".($debug ? "1\n" : "0\n");
			print "chain $this->booturl/ipxe/boot.php##params\n";
			print "exit\n";
	    }
	}

	// Deletes the Host
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

	// Send the 01-XX-XX-XX-XX-XX-XX
	// This just tells the system it's got a task
	// and performs the task.
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

	// Confirm Deletion
	public function delConf()
	{
		print "#!ipxe\n";
		print "cpuid --ext 29 && set arch x86_64 || set arch i386\n";
		print "prompt --key y Would you like to delete this host? (y/N): &&\n";
		print "params\n";
		print "param mac \${net0/mac}\n";
		print "param arch \${arch}\n";
		print "param delconf 1\n";
		print "chain $this->booturl/ipxe/boot.php##params";
	}

	private function debugAccess()
	{
		print "#!ipxe\n";
		print "$this->kernel mode=onlydebug\n";
		print "$this->initrd";
		print "boot\n";
	}

	// Verifies the credentials and logs in.
	// Based on the FOG GUI Login information.
	public function verifyCreds()
	{
		if ($this->FOGCore->attemptLogin($_REQUEST['username'],$_REQUEST['password']))
		{
			if ($_REQUEST['delhost'])
				$this->delConf();
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

	// Set's the quick image task.
	public function setTasking()
	{
		if($this->Host->createImagePackage(1,'AutoRegTask',false,false,true,false,$_REQUEST['username']))
			$this->chainBoot(false, true);
	}

	// If the no menu option is sent:
	public function noMenu()
	{
		print "#!ipxe\n";
		print "sanboot --no-describe --drive 0x80\n";
	}

	// Get if the tasking is present or not.
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
			$LA = current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $this->Host->get('id'))));
			if ($LA)
				$Location = new Location($LA->get('locationID'));
			if ($Location && $Location->isValid())
				$StorageGroup = new StorageGroup($Location->get('storageGroupID'));
			else
				$StorageGroup = $Image->getStorageGroup();
			if ($TaskType->isUpload() || (!$Location || !$Location->get('storageNodeID')))
				$StorageNode = $StorageGroup->getOptimalStorageNode();
			else
				$StorageNode = new StorageNode($Location->get('storageNodeID'));
			$mac = $this->Host->get('mac');
			$osid = $Image->get('osID');
			$storage = sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),($TaskType->isUpload() ? 'dev/' : ''));
			$storageip = $StorageNode->get('ip');
			$img = $Image->get('path');
			$imgLegacy = $Image->get('legacy');
			$imgType = $Image->getImageType()->get('type');
			$imgid = $Image->get('id');
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
				"chkdsk=$chkdsk",
				"img=$img",
				"imgType=$imgType",
				"imgid=$imgid",
				"imgLegacy=$imgLegacy",
				"PIGZ_COMP=-$PIGZ_COMP",
				array(
					'value' => 'shutdown=1',
					'active' => $Task->get('shutdown'),
				),
				array(
					'value' => 'fdrive='.$this->Host->get('kernelDevice'),
					'active' => $this->Host->get('kernelDevice'),
				),
				array(
					'value' => 'hostname='.$this->Host->get('name'),
					'active' => $this->FOGCore->getSetting('FOG_CHANGE_HOSTNAME_EARLY'),
				),
				array(
					'value' => 'pct='.(is_numeric($this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT')) && $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') >= 5 && $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') < 100 ? $this->FOGCore->getSetting('FOG_UPLOADRESIZEPCT') : '5'),
					'active' => $TaskType->isUpload(),
				),
				array(
					'value' => 'ignorepg='.($this->FOGCore->getSetting('FOG_UPLOADIGNOREPAGEHIBER') ? 1 : 0),
					'active' => $TaskType->isUpload(),
				),
				array(
					'value' => 'port='.($TaskType->isMulticast() ? $MulticastSession->get('port') : null),
					'active' => $TaskType->isMulticast(),
				),
				array(
					'value' => 'mining=1',
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
				print "kernel memdisk iso raw\n";
				print "$this->memtest\n";
				print "boot";
			}
			else
				$this->printTasking($kernelArgsArray);
		}
	}

	// Just does the menu items.
	private function menuItem($option, $desc)
	{
		print "item $option $desc\n";
	}

	// Just does the options per the menu items.
	private function menuOpt($option,$type)
	{
		if ($option == 'fog.local')
		{
			print ":$option\n";
			print "sanboot --no-describe --drive 0x80 || goto MENU\n";
		}
		else if ($option == 'fog.memtest')
		{
			print ":$option\n";
			print "kernel memdisk iso raw\n";
			print "$this->memtest\n";
			print "boot || goto MENU\n";
		}
		else if ($option == 'fog.quickimage')
		{
			print ":$option\n";
			print "login\n";
			print "params\n";
			print "param mac \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param qihost 1\n";
			print "chain $this->booturl/ipxe/boot.php##params\n ||";
			print "goto MENU\n";
		}
		else if ($option == 'fog.quickdel')
		{
			print ":$option\n";
			print "login\n";
			print "params\n";
			print "param mac \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param delhost 1\n";
			print "chain $this->booturl/ipxe/boot.php##params ||\n";
			print "goto MENU\n";
		}
		else if ($option == 'fog.advanced')
		{
			print ":$option\n";
			print "chain $this->booturl/ipxe/advanced.php || goto MENU\n";
		}
		else if ($option == 'fog.debug') 
		{
			print ":$option\n";
			print "login\n";
			print "params\n";
			print "param mac \${net0/mac}\n";
			print "param arch \${arch}\n";
			print "param username \${username}\n";
			print "param password \${password}\n";
			print "param debugAccess 1\n";
			print "chain $this->booturl/ipxe/boot.php##params ||\n";
		}
		else
		{
			print ":$option\n";
			print "$this->kernel loglevel=4 $type\n";
			print "$this->initrd";
			print "boot || goto MENU\n";
		}
	}

	// Print the Default
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
				print "item --gap Host is registered as ".$this->Host->get('name')."!\n";
			}
			else
			{
				print "colour --rgb 0xff0000 0\n";
				print "cpair --foreground 0 3\n";
				print "item --gap Host is NOT registered!\n";
			}
			foreach($this->pxemenu AS $option => $desc)
			{
				if (!$this->Host || !$this->Host->isValid())
				{
					if ($option != 'fog.quickdel' && $option != 'fog.quickimage' && ( $showDebug || $option != 'fog.debug' )  )
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
					else if ($option == 'fog.local' || $option == 'fog.memtest' || $option == 'fog.advanced' || $option == 'fog.quickdel' || $option == 'fog.quickimage')
						$this->menuOpt($option, true);
				}
			}
			print "autoboot";
		}
		else
			$this->chainBoot();
	}
}
