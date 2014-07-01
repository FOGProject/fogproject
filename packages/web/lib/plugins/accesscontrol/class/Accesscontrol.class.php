<?php
/** Class Name: Example
	This just serves as an example class file.

	This is only for the Example Plugin created, but
	could serve to help the user create their own class files.
*/
class Accesscontrol extends FOGController
{
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
