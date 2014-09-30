<?php

// Blackout - 3:52 PM 11/05/2012
class MACAddressAssociation extends FOGController
{
	// Table
	public $databaseTable = 'hostMAC';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'hmID',
		'hostID'	=> 'hmHostID',
		'mac'		=> 'hmMAC',
		'description'	=> 'hmDesc',
	//	'pending' => 'hmPending',
	//	'primary' => 'hmPrimary',
	//	'clientIgnore' => 'hmClientIgnore',
	//	'imageIgnore' => 'hmImageIgnore',
	);
	
	// Overrides
	public function set($key, $value)
	{
		if ($this->key($key) == 'mac' && !($value instanceof MACAddress))
			$value = new MACAddress($value);
		return parent::set($key, $value);
	}
	
	// Custom
	public function getHost()
	{
		return new Host( $this->get('hostID') );
	}
	
	public function getMACAddress()
	{
		return $this->get('mac');
	}
}
