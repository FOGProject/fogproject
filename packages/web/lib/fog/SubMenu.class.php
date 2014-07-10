<?php 
class SubMenu extends FOGBase
{
	private $node, $id, $name, $object, $title, $FOGSubMenu, $subMenu;
	public function __construct()
	{
		parent::__construct();
		$this->node = $_REQUEST['node'];
		$this->FOGSubMenu = new FOGSubMenu();
		if ($this->node == 'group' && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Group']);
			$this->object = new Group($_GET['id']);
			$this->title = array($this->foglang['Group'] => $this->object->get('name'),
								 $this->foglang['Members'] => count($this->object->get('hosts')),
			);
		}
		else if ($this->node == 'host' && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Host']);
			$this->object = new Host($_GET['id']);
			$this->title = array($this->foglang['Host'] => $this->object->get('name'),
								 $this->foglang['MAC']	=> stripslashes($this->object ? $this->object->get('mac') : ''),
								 $this->foglang['Image'] => stripslashes($this->object->getImage()->get('name')),
								 $this->foglang['OS']	=> stripslashes($this->object->getOS()->get('name')),
								 _('Last Deployed') => stripslashes($this->object->get('deployed')),
			);
			$GA = $this->FOGCore->getClass('GroupAssociationManager')->find(array('hostID' => $this->object->get('id')));
			if ($GA[0])
				$this->title[$this->foglang['PrimaryGroup']] = $this->FOGCore->getClass('Group',$GA[0]->get('groupID'))->get('name');
		}
		else if ($this->node == 'images' && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Image']);
			$this->object = new Image($_GET['id']);
			$imageType = $this->object->get('imageTypeID') ? new ImageType($this->object->get('imageTypeID')) : null;
			$this->title = array($this->foglang['Images'] => $this->object->get('name'),
								_('Last Uploaded') => stripslashes($this->object->get('deployed')),
								_('Deploy Method') => ($this->object->get('format') == 1 ? 'Partimage' : ($this->object->get('format') == 0 ? 'Partclone' : 'N/A')),
								_('Image Type') => ($imageType && $imageType->isValid() ? $imageType->get('name') : _('Not Available')),
			);
		}
		else if (($this->node == 'printer' || $this->node == 'print') && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Printer']);
			$this->object = new Printer($_GET['id']);
			$this->title = array($this->foglang['Printer'] => $this->object->get('name'),
								 $this->foglang['Type'] => $this->object->get('config')
			);
			$this->object->get('model') ? $this->title[$this->foglang['Model']] = $this->object->get('model') : null;
		}
		else if (($this->node == 'snapin' || $this->node == 'snap') && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Snapin']);
			$this->object = new Snapin($_GET['id']);
			$this->title = array($this->foglang['Snapin'] => $this->object->get('name'),
								 $this->foglang['File'] => $this->object->get('file')
			);
		}
		else if ($this->node == 'storage' && $_GET['sub'] == 'edit' && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Storage']);
			$this->object = new StorageNode($_GET['id']);
			$this->title = array($this->foglang['Storage'].' '.$this->foglang['Node'] => $this->object->get('name'),
								 $this->foglang['Path'] => $this->object->get('path')
			);
		}
		else if ($this->node == 'storage' && $_GET['sub'] == 'edit-storage-group' && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Storage']);
			$this->object = new StorageGroup($_GET['id']);
			$this->title = array($this->foglang['Storage'].' '.$this->foglang['Group'] => $this->object->get('name'));
		}
		else if ($this->node == 'users' && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['User']);
			$this->object = new User($_GET['id']);
			$this->title = array($this->foglang['User'] => $this->object->get('name'));
		}
		else if ($this->node == 'location' && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Location']);
			$this->object = new Location($_GET['id']);
			$this->title = array($this->foglang['Location'] => $this->object->get('name'),
							     $this->foglang['Storage'].' '.$this->foglang['Group'] => 
								 		$this->FOGCore->getClass('StorageGroup',$this->object->get('storageGroupID'))->get('name')
			);
		}
		else if ($this->node == 'hwinfo' && $_GET['id'])
		{
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Home']);
			$this->object = new StorageNode($_GET['id']);
			$this->title = array($this->foglang['Storage'].' '.$this->foglang['Node'] => $this->object->get('name'),
								 'IP' => $this->object->get('ip'),
								 $this->foglang['Path'] => $this->object->get('path')
			);
		}
	}
	private function nodeAndID()
	{
		$this->nodeOnly();
		foreach($this->subMenu[$this->node][$this->id] AS $link => $menu)
		{
			if ((string)$menu != 'Array')
				$this->FOGSubMenu->addItems($this->node,array((string)$menu => (string)$link,),$this->id,$this->name);
		}
	}
	private function nodeOnly()
	{
		foreach($this->subMenu[$this->node] AS $link => $menu)
		{
			if ((string)$menu != 'Array')
				$this->FOGSubMenu->addItems($this->node,array((string)$menu => (string)$link));
		}
	}
	private function buildMenuStruct()
	{
		if ($this->node && $this->id)
			$this->nodeAndID();
		else
			$this->nodeOnly();
		if ($this->title)
		{
			foreach($this->title AS $title => $item)
			{
				if((string)$title != 'Array')
					$this->FOGSubMenu->addNotes($this->node,array((string)$title => (string)$item),$this->id,$this->name);
			}
		}
		print $this->FOGSubMenu->get($this->node);
	}
	private function buildMenuLinks()
	{
		// This checks values for sub/sub menu item generation.
		$delformat = $_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=delete&id='.$_REQUEST['id'];
		$linkformat = $_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=edit&id='.$_REQUEST['id'];
		// Group Sub/Sub menu items.
		if ($this->node == 'group')
		{
			$this->subMenu[$this->node]['search'] = $this->foglang['NewSearch'];
			$this->subMenu[$this->node]['list'] = sprintf($this->foglang['ListAll'],$this->foglang['Groups']);
			$this->subMenu[$this->node]['add'] = sprintf($this->foglang['CreateNew'],$this->foglang['Group']);
			if ($_REQUEST['id'])
			{
        		$this->subMenu[$this->node]['id'][$linkformat.'#group-general'] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$linkformat.'#group-tasks'] = $this->foglang['BasicTasks'];
				$this->subMenu[$this->node]['id'][$linkformat.'#group-membership'] = $this->foglang['Membership'];
				$this->subMenu[$this->node]['id'][$linkformat.'#group-image'] = $this->foglang['ImageAssoc'];
				$this->subMenu[$this->node]['id'][$linkformat.'#group-snap-add'] = $this->foglang['Add'].' '.$this->foglang['Snapins'];
				$this->subMenu[$this->node]['id'][$linkformat.'#group-snap-del'] = $this->foglang['Remove'].' '.$this->foglang['Snapins'];
				$this->subMenu[$this->node]['id'][$linkformat.'#group-service'] = $this->foglang['Service'].' '.$this->foglang['Settings'];
				$this->subMenu[$this->node]['id'][$linkformat.'#group-active-directory'] = $this->foglang['AD'];
				$this->subMenu[$this->node]['id'][$linkformat.'#group-printers'] = $this->foglang['Printers'];
				$this->subMenu[$this->node]['id'][$delformat] = $this->foglang['Delete'];
			}
		}
		// Host Sub/Sub menu items.
		if ($this->node == 'host')
		{
			$this->subMenu[$this->node]['search'] = $this->foglang['NewSearch'];
			$this->subMenu[$this->node]['list'] = sprintf($this->foglang['ListAll'],$this->foglang['Hosts']);
			$this->subMenu[$this->node]['add'] = sprintf($this->foglang['CreateNew'],$this->foglang['Host']);
			$this->subMenu[$this->node]['export'] = _('Export Hosts');
			$this->subMenu[$this->node]['import'] = _('Import Hosts');
			if($_REQUEST['id'])
			{
				$this->subMenu[$this->node]['id'][$linkformat.'#host-general'] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-grouprel'] = $this->foglang['Groups'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-tasks'] = $this->foglang['BasicTasks'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-active-directory'] = $this->foglang['AD'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-printers'] = $this->foglang['Printers'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-snapins'] = $this->foglang['Snapins'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-service'] = $this->foglang['Service'].' '.$this->foglang['Settings'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-hardware-inventory'] = $this->foglang['Inventory'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-virus-history'] = $this->foglang['VirusHistory'];
				$this->subMenu[$this->node]['id'][$linkformat.'#host-login-history'] = $this->foglang['LoginHistory'];
				$this->subMenu[$this->node]['id'][$delformat] = $this->foglang['Delete'];
			}
		}
		// Image Sub/Sub menu items.
		if ($this->node == 'images')
		{
			$this->subMenu[$this->node]['search'] = $this->foglang['NewSearch'];
			$this->subMenu[$this->node]['list'] = sprintf($this->foglang['ListAll'],$this->foglang['Images']);
			$this->subMenu[$this->node]['add'] = sprintf($this->foglang['CreateNew'],$this->foglang['Image']);
			if ($_REQUEST['id'])
			{
				$this->subMenu[$this->node]['id'][$linkformat.'#image-gen'] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$linkformat.'#image-host'] = $this->foglang['Host'];
				$this->subMenu[$this->node]['id'][$delformat] = $this->foglang['Delete'];
			}
		}
		// Printer Sub/Sub menu items.
		if ($this->node == 'printer' || $this->node == 'print')
		{
			$this->subMenu[$this->node]['search'] = $this->foglang['NewSearch'];
			$this->subMenu[$this->node]['list'] = sprintf($this->foglang['ListAll'],$this->foglang['Printers']);
			$this->subMenu[$this->node]['add'] = sprintf($this->foglang['CreateNew'],$this->foglang['Printer']);
			if ($_REQUEST['id'])
			{
				$this->subMenu[$this->node]['id'][$linkformat] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$delformat] = $this->foglang['Delete'];
			}
		}
		// Configuration Sub/Sub menu items.
		if ($this->node == 'about')
		{
			$this->subMenu[$this->node]['license'] = $this->foglang['License'];
			$this->subMenu[$this->node]['kernel-update'] = $this->foglang['KernelUpdate'];
			$this->subMenu[$this->node]['pxemenu'] = $this->foglang['PXEBootMenu'];
			$this->subMenu[$this->node]['client-updater'] = $this->foglang['ClientUpdater'];
			$this->subMenu[$this->node]['mac-list'] = $this->foglang['MACAddrList'];
			$this->subMenu[$this->node]['settings'] = $this->foglang['FOGSettings'];
			$this->subMenu[$this->node]['log'] = $this->foglang['LogViewer'];
			$this->subMenu[$this->node]['config'] = $this->foglang['ConfigSave'];
			$this->subMenu[$this->node]['http://www.sf.net/projects/freeghost'] = $this->foglang['FOGSFPage'];
			$this->subMenu[$this->node]['http://fogproject.org'] = $this->foglang['FOGWebPage'];
		}
		// Report Sub/Sub menu items, created Dynamically.
		if ($this->node == 'report')
		{
			$this->subMenu[$this->node]['home'] = $this->foglang['Home'];
			$this->subMenu[$this->node]['equip-loan'] = _('Equipment Loan');
			$this->subMenu[$this->node]['host-list'] = _('Host List');
			$this->subMenu[$this->node]['imaging-log'] = _('Imaging Log');
			$this->subMenu[$this->node]['inventory'] = _('Inventory');
			$this->subMenu[$this->node]['pend-mac'] = _('Pending MACs');
			$this->subMenu[$this->node]['snapin-log'] = _('Snapin Log');
			$this->subMenu[$this->node]['user-track'] = _('User Login Hist');
			$this->subMenu[$this->node]['vir-hist'] = _('Virus History');
			// Report link for the files contained within the reports directory.
			$reportlink = $_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=file&f=';
			$dh = opendir($this->FOGCore->getSetting('FOG_REPORT_DIR'));
			if ($dh != null)
			{
				while (!(($f=readdir($dh)) === false))
				{
					if (is_file($this->FOGCore->getSetting('FOG_REPORT_DIR').$f) && substr($f,strlen($f) - strlen('.php')) === '.php')
						$this->subMenu[$this->node][$reportlink.base64_encode($f)] = substr($f,0,strlen($f) -4);
				}
			}
			$this->subMenu[$this->node]['upload'] = _('Upload Reports');
		}
		// Service Sub/Sub menu items.
		if ($this->node == 'service')
		{
			// The service links redirects/tabs.
			$servicelink = $_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=edit';
			$this->subMenu[$this->node][$_SERVER['PHP_SELF'].'?node='.$this->node.'#home'] = $this->foglang['Home'];
			$this->subMenu[$this->node][$servicelink.'#autologout'] = $this->foglang['Auto'].' '.$this->foglang['Logout'];
			$this->subMenu[$this->node][$servicelink.'#clientupdater'] = $this->foglang['ClientUpdater'];
			$this->subMenu[$this->node][$servicelink.'#dircleanup'] = $this->foglang['DirectoryCleaner'];
			$this->subMenu[$this->node][$servicelink.'#displaymanager'] = sprintf($this->foglang['SelManager'],$this->foglang['Display']);
			$this->subMenu[$this->node][$servicelink.'#greenfog'] = $this->foglang['GreenFOG'];
			$this->subMenu[$this->node][$servicelink.'#hostregister'] = $this->foglang['HostRegistration'];
			$this->subMenu[$this->node][$servicelink.'#hostnamechanger'] = $this->foglang['HostnameChanger'];
			$this->subMenu[$this->node][$servicelink.'#printermanager'] = sprintf($this->foglang['SelManager'],$this->foglang['Printer']);
			$this->subMenu[$this->node][$servicelink.'#snapin'] = $this->foglang['SnapinClient'];
			$this->subMenu[$this->node][$servicelink.'#taskreboot'] = $this->foglang['TaskReboot'];
			$this->subMenu[$this->node][$servicelink.'#usercleanup'] = $this->foglang['UserCleanup'];
			$this->subMenu[$this->node][$servicelink.'#usertracker'] = $this->foglang['UserTracker'];
		}
		// Snapin Sub/Sub menu items.
		if ($this->node == 'snapin')
		{
			$this->subMenu[$this->node]['search'] = $this->foglang['NewSearch'];
			$this->subMenu[$this->node]['list'] = sprintf($this->foglang['ListAll'],$this->foglang['Snapins']);
			$this->subMenu[$this->node]['add'] = sprintf($this->foglang['CreateNew'],$this->foglang['Snapin']);
			if ($_GET['id'])
			{
				$this->subMenu[$this->node]['id'][$linkformat.'#snap-gen'] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$linkformat.'#snap-host'] = $this->foglang['Host'];
				$this->subMenu[$this->node]['id'][$delformat] = $this->foglang['Delete'];
			}
		}
		// Storage Sub/Sub menu items.
		if ($this->node == 'storage')
		{
			$this->subMenu[$this->node][$_SERVER['PHP_SELF'].'?node='.$this->node] = $this->foglang['AllSN'];
			$this->subMenu[$this->node][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=add-storage-node'] = $this->foglang['AddSN'];
			$this->subMenu[$this->node][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=storage-group'] = $this->foglang['AllSG'];
			$this->subMenu[$this->node][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=add-storage-group'] = $this->foglang['AddSG'];
			if ($_REQUEST['sub'] == 'edit')
			{
				$this->subMenu[$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub='.$_REQUEST['sub'].'&id='.$_REQUEST['id']] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=delete-storage-node&id='.$_REQUEST['id']] = $this->foglang['Delete'];
			}
			if ($_REQUEST['sub'] == 'edit-storage-group')
			{
				$this->subMenu[$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub='.$_REQUEST['sub'].'&id='.$_REQUEST['id']] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=delete-storage-group&id='.$_REQUEST['id']] = $this->foglang['Delete'];
			}
		}
		// Task Sub/Sub menu items.
		if ($this->node == 'tasks')
		{
			$this->subMenu[$this->node]['search'] = $this->foglang['NewSearch'];
			$this->subMenu[$this->node]['active'] = $this->foglang['ActiveTasks'];
			$this->subMenu[$this->node]['listhosts'] = sprintf($this->foglang['ListAll'],$this->foglang['Hosts']);
			$this->subMenu[$this->node]['listgroups'] = sprintf($this->foglang['ListAll'],$this->foglang['Groups']);
			$this->subMenu[$this->node]['active-multicast'] = $this->foglang['ActiveMCTasks'];
			$this->subMenu[$this->node]['active-snapins'] = $this->foglang['ActiveSnapins'];
			$this->subMenu[$this->node]['scheduled'] = $this->foglang['ScheduledTasks'];
		}
		// User Sub/Sub menu items.
		if ($this->node == 'users')
		{
			$this->subMenu[$this->node]['search'] = $this->foglang['NewSearch'];
			$this->subMenu[$this->node]['list'] = sprintf($this->foglang['ListAll'],$this->foglang['Users']);
			$this->subMenu[$this->node]['add'] = sprintf($this->foglang['CreateNew'],$this->foglang['User']);
			if ($_REQUEST['id'])
			{
				$this->subMenu[$this->node]['id'][$linkformat] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$delformat] = $this->foglang['Delete'];
			}
		}
		// Location Sub/Sub menu items.
		if ($this->node == 'location' || $this->node == 'locations')
		{
			$this->subMenu[$this->node]['search'] = $this->foglang['NewSearch'];
			$this->subMenu[$this->node]['list'] = sprintf($this->foglang['ListAll'],$this->foglang['Locations']);
			$this->subMenu[$this->node]['add'] = sprintf($this->foglang['CreateNew'],$this->foglang['Location']);
			if ($_REQUEST['id'])
			{
				$this->subMenu[$this->node]['id'][$linkformat] = $this->foglang['General'];
				$this->subMenu[$this->node]['id'][$delformat] = $this->foglang['Delete'];
			}
		}
		// Plugin Sub/Sub menu items.
		if ($this->node == 'plugin')
		{
			$this->subMenu[$this->node]['home'] = $this->foglang['Home'];
			$this->subMenu[$this->node]['installed'] = $this->foglang['InstalledPlugins'];
			$this->subMenu[$this->node]['activate'] = $this->foglang['ActivatePlugins'];
		}
		// ServerInfo Sub/Sub menu items.
		if ($this->node == 'hwinfo')
		{
			$this->subMenu[$this->node]['home&id='.$_REQUEST['id']] = $this->foglang['Home'];
		}
		$this->HookManager->processEvent('SUB_MENULINK_DATA',array('submenu' => &$this->subMenu));
	}
	public function buildMenu()
	{
		$this->buildMenuLinks();
		if ($this->FOGUser && $this->FOGUser->isValid() && $this->FOGUser->isLoggedIn())
			$this->buildMenuStruct();
	}
}
