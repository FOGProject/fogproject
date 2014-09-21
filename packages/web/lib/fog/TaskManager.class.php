<?php

// Blackout - 11:31 AM 2/10/2011
class TaskManager extends FOGManagerController
{
	// Table
	public $databaseTable = 'tasks';
	
	// Search query
	public $searchQuery = 'SELECT tasks.* FROM tasks
		LEFT OUTER JOIN hosts ON (taskHostID=hostID)
		LEFT OUTER JOIN hostMAC ON (taskHostID=hmHostID)
		LEFT OUTER JOIN taskTypes ON (taskTypeID=ttID)
		LEFT OUTER JOIN taskStates ON (taskStateID=tsID)
		LEFT OUTER JOIN images ON (hostImage=imageID)
		WHERE
			hostID LIKE "%${keyword}%" OR
			hostName LIKE "%${keyword}%" OR
			hostDesc LIKE "%${keyword}%" OR
			hostMAC LIKE "%${keyword}%" OR
			hmMAC LIKE "%${keyword}%" OR
			tsName LIKE "%${keyword}%" OR
			ttName LIKE "%${keyword}%" OR
			imageName LIKE "%${keyword}%" OR
			taskCreateBy LIKE "%${keyword}%"
		GROUP BY
			taskID DESC
		ORDER BY hostName';

	// Custom
	// Clean up
	function hasActiveTaskCheckedIn($taskid)
	{
		$Task = new Task($taskid);
		return ((strtotime($Task->get('checkInTime')) - strtotime($Task->get('createdTime'))) > 2);
	}
}
