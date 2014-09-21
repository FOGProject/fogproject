<?php

// Blackout - 11:33 AM 8/01/2012
class TaskState extends FOGController
{
	// Table
	public $databaseTable = 'taskStates';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'tsID',
		'name'		=> 'tsName',
		'description'	=> 'tsDescription',
		'order'		=> 'tsOrder'
	);
}