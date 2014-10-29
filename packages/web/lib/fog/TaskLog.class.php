<?php

// Blackout - 6:00 PM 5/05/2012
class TaskLog extends FOGController
{
	// Table
	public $databaseTable = 'taskLog';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'id',
		'taskID'	=> 'taskID',
		'taskStateID'	=> 'taskStateID',
		'ip'		=> 'ip',
		'createdTime'	=> 'createTime',
		'createdBy'	=> 'createdBy'
	);
	
	// Overrides
	public function __construct($data = '')
	{
		// FOGController constructor
		parent::__construct($data);
		
		// Set IP Address -> Return
		return $this->set('ip', $_SERVER['REMOTE_ADDR']);
	}
	
	
	// Custom
	public function getTask()
	{
		return new Task( $this->get('taskID') );
	}
	
	public function getTaskState()
	{
		return new TaskState( $this->get('taskStateID') );
	}
	
	public function getHost()
	{
		return $this->getTask()->getHost();
	}
}
