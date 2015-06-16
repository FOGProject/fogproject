<?php
class MulticastSessionsAssociation extends FOGController
{
	// Table
	public $databaseTable = 'multicastSessionsAssoc';

	// Name -> Database field name
	public $databaseFields = array(
			'id'				=> 'msaID',
			'msID'				=> 'msID',
			'taskID'			=> 'tID',
			);
	public function getMulticastSession()
	{
		return new MulticastSessions($this->get('msID'));
	}
	public function getTask()
	{
		return new Task($this->taskID);
	}
}
