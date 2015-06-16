<?php
class Capone extends FOGController {
	// Table
	public $databaseTable = 'capone';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'cID',
		'imageID'	=> 'cImageID',
		'osID'		=> 'cOSID',
		'key'		=> 'cKey',
	);
}
