<?php

// Blackout - 6:04 PM 28/09/2011
class Snapin extends FOGController
{
	// Table
	public $databaseTable = 'snapins';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'sID',
		'name'		=> 'sName',
		'description'	=> 'sDesc',
		'file'		=> 'sFilePath',
		'args'		=> 'sArgs',
		'createdTime'	=> 'sCreateDate',
		'createdBy'	=> 'sCreator',
		'reboot'	=> 'sReboot',
		'runWith'	=> 'sRunWith',
		'runWithArgs'	=> 'sRunWithArgs',
		'anon3'		=> 'sAnon3'
	);
}