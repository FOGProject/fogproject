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
		'default',
	);
	
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'name',
	);
}
