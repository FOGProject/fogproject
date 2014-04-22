<?php 
class SubMenu
{
	private $node, $id, $name, $object, $title, $FOGSubMenu;
	function __construct($currentUser)
	{
		$this->node = $_GET['node'];
		$this->currentUser = $currentUser;
		$this->foglang = $GLOBALS['foglang'];
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
			$GA = $GLOBALS['FOGCore']->getClass('GroupAssociationManager')->find(array('hostID' => $this->object->get('id')));
			if ($GA[0])
				$this->title[$this->foglang['PrimaryGroup']] = $GLOBALS['FOGCore']->getClass('Group',$GA[0]->get('groupID'))->get('name');
		}
		else if ($this->node == 'images' && $_GET['id'])
		{
			$this->id = 'id';
			$this->name = sprintf($this->foglang['SelMenu'],$this->foglang['Image']);
			$this->object = new Image($_GET['id']);
			$this->title = array($this->foglang['Images'] => $this->object->get('name'),
								_('Last Uploaded') => stripslashes($this->object->get('deployed')),
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
								 		$GLOBALS['FOGCore']->getClass('StorageGroup',$this->object->get('storageGroupID'))->get('name')
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
		foreach($this->foglang['SubMenu'][$this->node][$this->id] AS $link => $menu)
		{
			if ((string)$menu != 'Array')
				$this->FOGSubMenu->addItems($this->node,array((string)$menu => (string)$link,),$this->id,$this->name);
		}
	}
	private function nodeOnly()
	{
		foreach($this->foglang['SubMenu'][$this->node] AS $link => $menu)
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

	public function buildMenu()
	{
		if ($this->currentUser != null && $this->currentUser->isLoggedIn())
			$this->buildMenuStruct();
	}
}
