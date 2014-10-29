<?php
class MulticastSessionsAssociation extends FOGController
{
	// Table
	public $databaseTable = 'multicastSessionsAssoc';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'				=> 'msaID',
		'msID'				=> 'msID',
		'taskID'			=> 'tID',
	);
}
