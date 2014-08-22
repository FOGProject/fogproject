<?php
class MulticastTask extends FOGBase
{
	// Updated to only care about tasks in its group
	public static function getAllMulticastTasks($root)
	{
		global $FOGCore;
		$arTasks = array();
		foreach($FOGCore->getClass('MulticastSessionsManager')->find(array('stateID' => array(0,1,2,3))) AS $MultiSess)
		{
			$count = $FOGCore->getClass('MulticastSessionsAssociationManager')->count(array('msID' => $MultiSess->get('id')));
			$Image = new Image($MultiSess->get('image'));
			$Tasks[] = new self(
				$MultiSess->get('id'), 
				$MultiSess->get('name'),
				$MultiSess->get('port'),
				$root.'/'.$MultiSess->get('logpath'),
				$FOGCore->getSetting('FOG_UDPCAST_INTERFACE'),
				($count > 0 ? $count : ($MultiSess->get('clients') > 0 ? $MultiSess->get('clients') : $FOGCore->getClass('HostManager')->count())),
				$MultiSess->get('isDD'),
				$Image->get('osID')
			);
		}
		return $Tasks;
	}

	private $intID, $strName, $intPort, $strImage, $strEth, $intClients;
	private $intImageType, $intOSID;
	private $procRef, $arPipes;
	private $deathTime;

	public function __construct($id, $name, $port, $image, $eth, $clients, $imagetype, $osid)
	{
		parent::__construct();
		$this->intID = $id;
		$this->strName = $name;
		$this->intPort = $port;
		$this->strImage = $image;
		$this->strEth = $eth;
		$this->intClients = $clients;
		$this->intImageType = $imagetype;
		$this->deathTime = null;
		$this->intOSID = $osid;
		$this->dubPercent = null;
	}

	public function getID() {return $this->intID;}
	public function getName() {return $this->strName;}
	public function getImagePath() {return $this->strImage;}
	public function getImageType() {return $this->intImageType;}
	public function getClientCount() {return $this->intClients;}
	public function getPortBase() {return $this->intPort;}
	public function getInterface() {return $this->strEth;}
	public function getOSID() {return $this->intOSID;}
	public function getUDPCastLogFile() {return MULTICASTLOGPATH.".udpcast.".$this->getID();}

	public function getCMD()
	{
		$interface = "";
		if ($this->getInterface() != null && strlen($this->getInterface()) > 0)
			$interface = sprintf(' --interface %s',$this->getInterface());
		$cmd = null;
		$wait = '';
		$waitTemp = $this->FOGCore->getSetting('FOG_UDPCAST_MAXWAIT');
		$count = '';
		$countTemp = $this->getClientCount();
		$count = sprintf(' --min-receivers %d',($countTemp > 0 ? $countTemp : $this->FOGCore->getClass('HostManager')->count()));
		if ($waitTemp)
			$wait = sprintf(' --max-wait %d',($waitTemp > 0 ? $waitTemp * 60 : 60));
		if (($this->getOSID() == 5 || $this->getOSID() == 6 || $this->getOSID() == 7) && $this->getImageType() == 1)
		{
			// Only Windows 7 and 8
			$strRec = null;
			$strSys = null;
			if (is_dir($this->getImagePath()))
			{
				$filelist = array();
				if ($handle = opendir($this->getImagePath()))
				{
					while (false !== ($file = readdir($handle)))
					{
						if ($file != '.' && $file != '..')
						{
							if ($file == 'rec.img.000')
								$strRec=rtrim($this->getImagePath(),'/').'/rec.img.*';
							if ($file == 'sys.img.000')
								$strSys=rtrim($this->getImagePath(),'/').'/sys.img.*';
						}
					}
					sort($filelist);
					closedir($handle);
				}
			}

			if ($strRec && $strSys)
			{
				// two parts
				$cmd = 'cat '.$strRec.'|'.UDPSENDERPATH.$count.' --portbase '.$this->getPortBase().$interface.$wait.' --full-duplex --ttl 32 --nokbd;';
				$cmd .= 'cat '.$strSys.'|'.UDPSENDERPATH.$count.' --portbase '.$this->getPortBase().$interface.$wait.' --full-duplex --ttl 32 --nokbd;';
			}
			else if (!$strRec && $strSys)
				$cmd = 'cat '.$strSys.'|'.UDPSENDERPATH.$count.' --portbase '.$this->getPortBase().' '.$interface.$wait.' --full-duplex --ttl 32 --nokbd;';
		}
		else if ($this->getImageType() == 1 || $this->getImageType() == 2)
		{
			if (is_dir($this->getImagePath()))
			{
				$filelist = array();
				if ($handle = opendir($this->getImagePath()))
				{
					while (false!==($file=readdir($handle)))
					{
						if ($file != '.' && $file != '..')
						{
							$ext = '';
							sscanf($file,'d1p%d.%s',$part,$ext);
							if ($ext == 'img')
								$filelist[] = $file;
						}
					}
					sort($filelist);
					closedir($handle);
				}
				foreach ($filelist AS $file)
				{
					$path = rtrim($this->getImagePath(),'/').'/'.$file;
					$cmd .= 'cat '.$path.'|'.UDPSENDERPATH.$count.' --portbase '.$this->getPortBase().$interface.$wait.' --full-duplex --ttl 32 --nokbd;';
				}
			}
			else
				$cmd = 'cat '.rtrim($this->getImagePath(),'/').'|'.UDPSENDERPATH.$count.' --portbase '.$this->getPortBase().$interface.$wait.' --full-duplex --ttl 32 --nokbd;';
		}
		else if ($this->getImageType() == 3)
		{
			$device = 1;
			$part = 0;
			if (is_dir($this->getImagePath()))
			{
				$filelist = array();
				if($handle = opendir($this->getImagePath()))
				{
					while (false !== ($file = readdir($handle)))
					{
						if ($file != '.' && $file != '..')
						{
							$ext = '';
							sscanf($file,'d%dp%d.%s',$device,$part,$ext);
							if ($ext == 'img')
								$filelist[] = $file;
						}
					}
					sort($filelist);
					closedir($handle);
				}
				$cmd = '';
				foreach ($filelist AS $file)
				{
					$path = rtrim($this->getImagePath(),'/').'/'.$file;
					$cmd .= 'cat '.$path.'|'.UDPSENDERPATH.$count.' --portbase '.$this->getPortBase().$interface.$wait.' --full-duplex --ttl 32 --nokbd;';
				}
			}
		}
		else if ($this->getImageType() == 4)
		{
			$device = 1;
			$part = 0;
			if (is_dir($this->getImagePath()))
			{
				$filelist = array();
				if($handle = opendir($this->getImagePath()))
				{
					while (false !== ($file = readdir($handle)))
					{
						if ($file != '.' && $file != '..')
								$filelist[] = $file;
					}
					sort($filelist);
					closedir($handle);
				}
				$cmd = '';
				foreach ($filelist AS $file)
				{
					$path = rtrim($this->getImagePath(),'/').'/'.$file;
					$cmd .= 'cat '.$path.'|'.UDPSENDERPATH.$count.' --portbase '.$this->getPortBase().$interface.$wait.' --full-duplex --ttl 32 --nokbd;';
				}
			}
		}
		return $cmd;
	}

	public function startTask()
	{
		@unlink($this->getUDPCastLogFile());
		$descriptor = array(0 => array('pipe','r'), 1 => array('file',$this->getUDPCastLogFile(),'w'), 2 => array('file',$this->getUDPCastLogFile(),'w'));
		$this->procRef = @proc_open('exec '.$this->getCMD(),$descriptor,$pipes);
		$this->arPipes = $pipes;
		$MultiSess = new MulticastSessions($this->intID);
		$MultiSess->set('stateID','1')->save();
		return $this->isRunning();
	}

	public function flagAsDead()
	{
		if($this->deathTime == null)
			$this->deathTime = time();
	}

	public function killTask()
	{
		foreach($this->arPipes AS $closeme)
			@fclose($closeme);
		if ($this->isRunning())
		{
			$pid = $this->getPID();
			if ($pid)
				@posix_kill($pid, SIGTERM);
			@proc_terminate($this->procRef, SIGTERM);
		}
    	@proc_close($this->procRef);
		$this->procRef=null;
		@unlink($this->getUDPCastLogFile());
		foreach($this->FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $this->intID)) AS $MultiSessAssoc)
		{
			$Task = new Task($MultiSessAssoc->get('taskID'));
			$Task->set('stateID','5')->save();
		}
		$MultiSess = new MulticastSessions($this->intID);
		$MultiSess->set('name',null)->set('name','')->set('stateID','5')->save();
		return true;
	}

	public function updateStats()
	{
		foreach($this->FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('msid' => $this->intID)) AS $MultiSessAssoc)
		{
			$Task = new Task($MultiSessAssoc->get('taskID'));
			if ($Task && $Task->isValid())
				$TaskPercent[] = $Task->get('percent');
		}
		$TaskPercent = array_unique((array)$TaskPercent);
		$MultiSess = new MulticastSessions($this->intID);
		$MultiSess->set('percent',max((array)$TaskPercent))->save();
	}

	public function isRunning()
	{
		if ($this->procRef)
		{
			$ar = proc_get_status($this->procRef);
			return $ar['running'];
		}
		return false;
	}

	public function getPID()
	{
		if ($this->procRef)
		{
			$ar = proc_get_status($this->procRef);
			return $ar['pid'];
		}
		return -1;
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
