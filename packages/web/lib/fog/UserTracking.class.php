<?php

class UserTracking extends FOGController
{
	// Table
	public $databaseTable = 'userTracking';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'utID',
		'hostID'	=> 'utHostID',
		'username'	=> 'utUserName',
		'action'	=> 'utAction',
		'datetime'	=> 'utDateTime',
		'description' => 'utDesc',
		'date'		=> 'utDate',
		'anon3'		=> 'utAnon3',
	);
}
