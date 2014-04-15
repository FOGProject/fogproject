<?php
/** Class Name: ClientUpdater
	Used from FOG Service on the client machine.
	This class just gives access to the database
	variables.
*/
class ClientUpdater extends FOGController
{
	// Table
	public $databaseTable = 'clientUpdates';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'cuID',
		'name'		=> 'cuName',
		'md5'		=> 'cuMD5',
		'type'		=> 'cuType',
		'file'		=> 'cuFile',
	);
}
