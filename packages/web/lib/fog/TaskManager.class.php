<?php

// Blackout - 11:31 AM 2/10/2011
class TaskManager extends FOGManagerController
{
	// Table
	public $databaseTable = 'tasks';
	
	// Custom
	// Clean up
	public function hasActiveTaskCheckedIn($taskid)
	{
		$Task = new Task($taskid);
		return ((strtotime($Task->get('checkInTime')) - strtotime($Task->get('createdTime'))) > 2);
	}
}
