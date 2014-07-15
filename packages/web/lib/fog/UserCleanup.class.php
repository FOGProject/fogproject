<?php

class UserCleanup extends FOGController
{
	// Table
	public $databaseTable = 'userCleanup';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'ucID',
		'name'		=> 'ucName',
	);
}
