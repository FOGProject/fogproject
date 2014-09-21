<?php

class LocationAssociation extends FOGController
{
	// Table
	public $databaseTable = 'locationAssoc';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'laID',
		'locationID'		=> 'laLocationID',
		'hostID' => 'laHostID',
	);
}
