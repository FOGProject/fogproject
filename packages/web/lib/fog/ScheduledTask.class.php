<?php
/** Class Name ScheduledTask
	Extends the FOGController class.
	Sets up the Scheduled tasks in the database.
*/
class ScheduledTask extends FOGController
{
	// Table
	public $databaseTable = 'scheduledTasks';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'stID',
		'name'		=> 'stName',
		'description'	=> 'stDesc',
		'type'		=> 'stType',
		'taskType'	=> 'stTaskTypeID',
		'minute'	=> 'stMinute',
		'hour'		=> 'stHour',
		'dayOfMonth'	=> 'stDOM',
		'month'		=> 'stMonth',
		'dayOfWeek'	=> 'stDOW',
		'isGroupTask'	=> 'stIsGroup',
		'hostID'	=> 'stGroupHostID',
		'shutdown'	=> 'stShutDown',
		'other1'	=> 'stOther1',
		'other2'	=> 'stOther2',
		'other3'	=> 'stOther3',
		'other4'	=> 'stOther4',
		'other5'	=> 'stOther5',
		'scheduleTime'	=> 'stDateTime',
		'isActive'	=> 'stActive'
	);
	// Allow setting / getting of these additional fields
	public $additionalFields = array(
	);
	// Database field to Class relationships
	public $databaseFieldClassRelationships = array(
	);
	// Custom Functions
	public function getHost()
	{
		return new Host($this->get('hostID'));
	}
	public function getGroup()
	{
		return new Group($this->get('hostID'));
	}
	public function getImage()
	{
		return $this->getHost()->getImage();
	}
	public function getShutdownAfterTask()
	{
		return $this->get('shutdown');
	}
	public function setShutdownAfterTask($value)
	{
		return $this->set('shutdown', $value);
	}
	public function setOther1($value)
	{
		return $this->set('other1', $value);
	}
	public function setOther2($value)
	{
		return $this->set('other2', $value);
	}
	public function setOther3($value)
	{
		return $this->set('other3', $value);
	}
	public function setOther4($value)
	{
		return $this->set('other4', $value);
	}
	public function setOther5($value)
	{
		return $this->set('other5', $value);
	}
	public function getTimer()
	{
		if($this->get('type') == 'C')
			$minute = trim($this->get('minute'));
		else
			$minute = trim($this->get('scheduleTime'));
		$hour = trim($this->get('hour'));
		$dom = trim($this->get('dayOfMonth'));
		$month = trim($this->get('month'));
		$dow = trim($this->get('dayOfWeek'));
		return new Timer($minute,$hour,$dom,$month,$dow);
	}
	public function getTaskType()
	{
		$Task = new TaskType($this->get('taskType'));
		return $Task;
	}
	public function isGroupBased()
	{
		return ($this->get('isGroupTask') == '1' ? true : false);
	}
}
