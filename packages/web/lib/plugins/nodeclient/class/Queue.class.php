<?php
class Queue extends FOGController {
	// Table
	public $databaseTable = 'queueAssoc';
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'qaID',
		'hostID' => 'qaHostID',
		'stateID' => 'qaStateID',
		'moduleID' => 'qaModuleID',
		'taskVals' => 'qaTaskInfo',
		'createdTime' => 'qaCreatedTime',
	);
	public function getHost() {
		return $this->getClass('Host',$this->get('hostID'));
	}
	public function getModule() {
		return $this->getClass('Host',$this->get('moduleID'));
	}
}
