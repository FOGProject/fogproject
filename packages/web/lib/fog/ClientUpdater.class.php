<?php
class ClientUpdater extends FOGController {
	/** @var $databaseTable the database table field to compare.
	  */
	public $databaseTable = 'clientUpdates';
	/** @var $databaseFields the array for nice name to table association
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
