<?php
/** Class Name: iPXE
	Stores the Success and later the failure of devices
	communicating with iPXE.
*/
class iPXE extends FOGController
{
	/**
	* @param $databaseTable the table within the database to
	* perform the lookup on.
	*/
	public $databaseTable = 'ipxeTable';
	
	/**
	* @param $databaseFields the associative array.  Makes
	* so we can use common names and associate with the relevant
	* database calls back to the system.
	*/
	public $databaseFields = array(
		'id'		=> 'ipxeID',
		'product'		=> 'ipxeProduct',
		'manufacturer' => 'ipxeManufacturer',
		'mac' => 'ipxeMAC',
		'success' => 'ipxeSuccess',
		'failure' => 'ipxeFailure',
		'file' => 'ipxeFilename',
	);
}
