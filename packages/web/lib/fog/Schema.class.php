<?php
/** Class Name: Schema
	This just generates the Schema information.
*/
class Schema extends FOGController
{
	// Table
	public $databaseTable = 'schemaVersion';
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'vID',
		'version' => 'vValue',
	);
}
