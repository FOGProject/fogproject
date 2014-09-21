<?php
/** Class Name: DirCleaner
	Sets the variables for the GUI
	It's mainly used as a service script
	But keeps method of access in line with
	the rest of the system.
*/
class DirCleaner extends FOGController
{
	/**
	* @param $databaseTable the table within the database to
	* perform the lookup on.
	*/
	public $databaseTable = 'dirCleaner';
	
	/**
	* @param $databaseFields the associative array.  Makes
	* so we can use common names and associate with the relevant
	* database calls back to the system.
	*/
	public $databaseFields = array(
		'id'		=> 'dcID',
		'path'		=> 'dcPath',
	);
}
