<?php
class TaskLog extends FOGController {
	/** @var $databaseTable the table to work with */
	public $databaseTable = 'taskLog';
	/** @var $databaseFields the fields within the table */
	public $databaseFields = array(
			'id'		=> 'id',
			'taskID'	=> 'taskID',
			'taskStateID'	=> 'taskStateID',
			'ip'		=> 'ip',
			'createdTime'	=> 'createTime',
			'createdBy'	=> 'createdBy'
			);
	/** @function __construct() the class constructor
	 * @param $data the data to pass to the parent constructor
	 * @return sets the ip field to that of the remote server
	 */
	public function __construct($data = '') {
		parent::__construct($data);
		return $this->set('ip', $_SERVER['REMOTE_ADDR']);
	}
	/** @function getTask() return the task
	 * @return the task
	 */
	public function getTask() {return $this->getClass('Task',$this->get('taskID'));}
	/** @function getTaskState() return the task state
	 * @return the task state
	 */
	public function getTaskState() {return $this->getClass('TaskState',$this->get('taskStateID'));}
	/** @function getHost() return the host
	 * @return the task host
	 */
	public function getHost() {return $this->getTask()->getHost();}
}
