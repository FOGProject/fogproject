<?php
/**
* Class Peer
*/
class Peer extends FOGController
{
	// Table
	public $databaseTable = 'peer';

	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'id',
		'hash' => 'hash',
		'agent' => 'user_agent',
		'ip' => 'ip_address',
		'key' => 'key',
		'port' => 'port',
	);
}
