<?php
class TaskLog extends FOGController {
    protected $databaseTable = 'taskLog';
    protected $databaseFields = array(
        'id' => 'id',
        'taskID' => 'taskID',
        'taskStateID' => 'taskStateID',
        'ip' => 'ip',
        'createdTime' => 'createTime',
        'createdBy' => 'createdBy'
    );
    public function __construct($data = '') {
        parent::__construct($data);
        return $this->set('ip', $_SERVER['REMOTE_ADDR']);
    }
    public function getTask() {
        return static::getClass('Task',$this->get('taskID'));
    }
    public function getTaskState() {
        return static::getClass('TaskState',$this->get('taskStateID'));
    }
    public function getHost() {
        return $this->getTask()->getHost();
    }
}
