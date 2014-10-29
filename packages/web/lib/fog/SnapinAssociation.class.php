<?php

// Blackout - 3:55 PM 4/05/2012
class SnapinAssociation extends FOGController
{
	// Table
	public $databaseTable = 'snapinAssoc';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'saID',
		'hostID'	=> 'saHostID',
		'snapinID'	=> 'saSnapinID'
	);
	
	// Custom
	public function getHost()
	{
		return new Host( $this->get('hostID') );
	}
	
	public function getSnapin()
	{
		return new Snapin( $this->get('snapinID') );
	}
}