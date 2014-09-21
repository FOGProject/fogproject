<?php
class ModuleAssociation extends FOGController
{
	// Table
	public $databaseTable = 'moduleStatusByHost';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'msID',
		'hostID' => 'msHostID',
		'moduleID' => 'msModuleID',
		'state' => 'msState',
	);
}
