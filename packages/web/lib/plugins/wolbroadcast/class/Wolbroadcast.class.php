<?php
/** Class Name: Wolbroadcast
	Connects the wolbroadcast table
*/
class Wolbroadcast extends FOGController
{
	// Table
	public $databaseTable = 'wolbroadcast';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'wbID',
		'name'		=> 'wbName',
		'description' => 'wbDesc',
		'broadcast'		=> 'wbBroadcast',
	);
}
