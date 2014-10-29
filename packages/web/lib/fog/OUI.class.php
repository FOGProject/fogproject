<?php
/** Class Name: OUI
	Defines variables for MAC Manufacturer from ieee.
*/
class OUI extends FOGController
{
	// Table
	public $databaseTable = 'oui';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'ouiID',
		'prefix'		=> 'ouiMACPrefix',
		'name' => 'ouiMan',
	);
}
