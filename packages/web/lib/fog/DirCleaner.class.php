<?php
/** Class Name: DirCleaner
	Sets the variables for the GUI
	It's mainly used as a service script
	But keeps method of access in line with
	the rest of the system.
*/
class DirCleaner extends FOGController
{
	// Table
	public $databaseTable = 'dirCleaner';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'dcID',
		'path'		=> 'dcPath',
	);
}
