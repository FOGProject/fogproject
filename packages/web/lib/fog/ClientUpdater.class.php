<?php
/** Class Name: ClientUpdater
	Used from FOG Service on the client machine.
	This class just gives access to the database
	variables.
*/
class ClientUpdater extends FOGController
{
	/**
	* @param $databaseTable the database table field to compare.
	*/
	public $databaseTable = 'clientUpdates';
	/**
	* @param $databaseFields the array for nice name to table association
	* for calling back the data requested.
	*/
	public $databaseFields = array(
		'id'		=> 'cuID',
		'name'		=> 'cuName',
		'md5'		=> 'cuMD5',
		'type'		=> 'cuType',
		'file'		=> 'cuFile',
	);
}
