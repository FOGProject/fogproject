<?php

// Blackout - 11:16 AM 26/09/2011
class Printer extends FOGController
{
	// Table
	public $databaseTable = 'printers';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'pID',
		'name'		=> 'pAlias',
		'port'		=> 'pPort',
		'file'		=> 'pDefFile',
		'model'		=> 'pModel',
		'config'	=> 'pConfig',
		'ip'		=> 'pIP',
		'pAnon2'	=> 'pAnon2',
		'pAnon3'	=> 'pAnon3',
		'pAnon4'	=> 'pAnon4',
		'pAnon5'	=> 'pAnon5',
	);
	
	// Allow setting / getting of these additional fields
	public $additionalFields = array(
		'hosts',
		'hostsnotinme',
		'noprinter',
	);
	
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'name',
	);

	// Overrides
	private function loadHosts()
	{
		$PrinterAssocs = null;
		$HostIDs = null;
		if (!$this->isLoaded('hosts'))
		{
			if ($this->get('id'))
			{
				$PrinterAssocs = $this->getClass('PrinterAssociationManager')->find(array('printerID' => $this->get('id')));
				foreach($PrinterAssocs AS $PrinterAssoc)
					$this->add('hosts', $PrinterAssoc->getHost());
				if (count($this->get('hosts')))
				{
					foreach($this->get('hosts') AS $Host)
					{
						if ($Host->isValid())
							$HostIDs[] = $Host->get('id');
					}
					$Hosts = $this->getClass('HostManager')->find(array('id' => (array)$HostIDs),'AND',null,'ASC','=',false,true);
					foreach($Hosts AS $Host)
						$this->add('hostsnotinme',$Host);
				}
				unset($PrinterAssocs, $PrinterAssoc, $Hosts, $Host, $HostIDs);
				$PrinterAssocs = $this->getClass('PrinterAssociationManager')->find();
				foreach($PrinterAssocs AS $PrinterAssoc)
					$HostIDs[] = $PrinterAssoc->get('hostID');
				$HostIDs = array_unique((array)$HostIDs);
				$Hosts = $this->getClass('HostManager')->find(array('id' => (array)$HostIDs),'AND',null,'ASC','=',false,true);
				foreach($Hosts AS $Host)
					$this->add('noprinter',$Host);
				unset($PrinterAssocs, $PrinterAssoc, $Hosts, $Host, $HostIDs);
			}
		}
		return $this;
	}

	public function get($key = '')
	{
		if ($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme' || $this->key($key) == 'noprinter')
			$this->loadHosts();
		return parent::get($key);
	}

	public function add($key, $value)
	{
		if (($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme' || $this->key($key) == 'noprinter') && !($value instanceof Host))
		{
			$this->loadHosts();
			$value = new Host($value);
		}
		// Add
		return parent::add($key, $value);
	}

	public function remove($key, $object)
	{
		if ($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme' || $this->key($key) == 'noprinter')
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
			$this->getClass('PrinterAssociationManager')->destroy(array('printerID' => $this->get('id')));
			// Create new Assocs
			$i = 0;
			foreach((array)$this->get('hosts') AS $Host)
			{
				if (($Host instanceof Host) && $Host->isValid())
				{
					$NewPrinter = new PrinterAssociation(array(
						'printerID' => $this->get('id'),
						'hostID' => $Host->get('id'),
						'isDefault' => ($i === 0 ? '1' : '0'),
					));
					$NewPrinter->save();
				}
				$i++;
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
		foreach((array)$removeArray AS $remove)
			$this->remove('hosts', ($remove instanceof Host ? $remove : new Host((int)$remove)));
		// Return
		return $this;
	}

	public function updateDefault($hostid,$onoff)
	{
		foreach((array)$hostid AS $id)
		{
			$Host = new Host($id);
			if ($Host && $Host->isValid())
				$Host->updateDefault($this->get('id'),in_array($Host->get('id'),$onoff));
		}
		return $this;
	}

	public function destroy($field = 'id')
	{
		// Remove all Host associations
		$this->getClass('PrinterAssociationManager')->destroy(array('printerID' => $this->get('id')));
		// Return
		return parent::destroy($field);
	}
}
