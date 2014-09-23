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
	);
	
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'name',
	);

	// Overrides
	private function loadHosts()
	{
		if (!$this->isLoaded('hosts'))
		{
			if ($this->get('id'))
			{
				$PrinterAssocs = $this->FOGCore->getClass('PrinterAssociationManager')->find(array('printerID' => $this->get('id')));
				foreach($PrinterAssocs AS $PrinterAssoc)
					$this->add('hosts', new Host($PrinterAssoc->get('hostID')));
			}
		}
		return $this;
	}

	public function get($key = '')
	{
		if ($this->key($key) == 'hosts')
			$this->loadHosts();
		return parent::get($key);
	}

	public function set($key, $value)
	{
		if ($this->key($key) == 'hosts')
		{
			foreach((array)$value AS $Host)
				$newValue[] = ($Host instanceof Host ? $Host : new Host($Host));
		}
		// Set
		return parent::set($key, $value);
	}

	public function add($key, $value)
	{
		if ($this->key($key) == 'hosts' && !($value instanceof Host))
		{
			$this->loadHosts();
			$value = new Host($value);
		}
		// Add
		return parent::add($key, $value);
	}

	public function remove($key, $object)
	{
		if ($this->key($key) == 'hosts')
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
			$this->FOGCore->getClass('PrinterAssociationManager')->destroy(array('printerID' => $this->get('id')));
			// Create new Assocs
			foreach((array)$this->get('hosts') AS $Host)
			{
				if (($Host instanceof Host) && $Host->isValid())
				{
					$NewPrinter = new PrinterAssociation(array(
						'printerID' => $this->get('id'),
						'hostID' => $Host->get('id'),
					));
					$NewPrinter->save();
				}
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
}
