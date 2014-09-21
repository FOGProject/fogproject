<?php
/** Class Name: Capone
	This just generates the Capone information.
	Only valid if the plugin is installed.
	Although it can be "accessed" it won't be
	of any use until the plugin is installed and set.
*/
class Capone extends FOGController
{
	// Table
	public $databaseTable = 'capone';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'cID',
		'imageID'	=> 'cImageID',
		'osID'		=> 'cOSID',
		'key'		=> 'cKey',
	);
}
