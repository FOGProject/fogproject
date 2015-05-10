<?php
class Example extends FOGController {
	// Table
	public $databaseTable = 'example';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'eID',
		'name'		=> 'eName',
		'other'		=> 'eOther',
		'hostID'	=> 'eHostID',
	);
}
