<?php
class NodeJS extends FOGController
{
	// Table
	public $databaseTable = 'nodeJSconfig';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'nodeID',
		'port' => 'port',
		'aeskey' => 'aesTmp',
	);
}
