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
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'name',
	);
	public function load($field = 'id')
	{
		parent::load($field);
		foreach(get_class_methods($this) AS $method)
		{
			if (strlen($method) > 5 && strpos($method,'load'))
				$this->$method();
		}
	}
	// Overrides
	private function loadHosts()
	{
		if (!$this->isLoaded('hosts') && $this->get('id'))
		{
			$HostIDs = $this->getClass('PrinterAssociationManager')->find(array('printerID' => $this->get('id')),'','','','','','','hostID');
			$this->set('hosts',$this->getClass('HostManager')->find(array('id' => $HostIDs)));
			$this->set('hostsnotinme',$this->getClass('HostManager')->find(array('id' => $HostIDs),'','','','','',true));
		}
		return $this;
	}
	public function get($key = '')
	{
		if ($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme')
			$this->loadHosts();
		return parent::get($key);
	}
	public function set($key, $value)
	{
		if ($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme')
		{
			foreach((array)$value AS $Host)
				$newValue[] = ($Host instanceof Host ? $Host : new Host($Host));
			$value = (array)$newValue;
		}
		// Set
		return parent::set($key,$value);
	}
	public function add($key, $value)
	{
		if (($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme') && !($value instanceof Host))
		{
			$this->loadHosts();
			$value = new Host($value);
		}
		// Add
		return parent::add($key, $value);
	}
	public function remove($key, $object)
	{
		if ($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme')
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
	public function isValid() {
		$ret = false;
		if ($this->get('config') == 'Network') $ret = ($this->get('name') ? true : false);
		else if ($this->get('config') == 'iPrint') $ret = ($this->get('name') && $this->get('port') ? true : false);
		else if ($this->get('config') == 'Local') $ret = ($this->get('name') && $this->get('port') && $this->get('file') && $this->get('model') ? true : false);
		return $ret;
	}
}
