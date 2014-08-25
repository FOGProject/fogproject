<?php
/**
* Class Torrent 
*/
class Torrent extends FOGController
{
	// Table
	public $databaseTable = 'torrent';

	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'id',
		'hash' => 'hash',
	);
}
