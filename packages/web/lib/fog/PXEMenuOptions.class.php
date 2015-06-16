<?php
/**
* Class PXEMenuOptions
*/
class PXEMenuOptions extends FOGController
{
	// Table
	public $databaseTable = 'pxeMenu';

	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'pxeID',
		'name' => 'pxeName',
		'description' => 'pxeDesc',
		'params' => 'pxeParams',
		'default' => 'pxeDefault',
		'regMenu' => 'pxeRegOnly',
		'args' => 'pxeArgs',
	);
}
