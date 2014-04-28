<?php

class SnapinTask extends FOGController
{
	// Table
	public $databaseTable = 'snapinTasks';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'stID',
		'jobID'		=> 'stJobID',
		'stateID'		=> 'stState',
		'checkin'	=> 'stCheckinDate',
		'complete'	=> 'stCompleteDate',
		'snapinID'	=> 'stSnapinID',
		'return'	=> 'stReturnCode',
		'details'	=> 'stReturnDetails',
	);

	public $additionalFields = array(
		'hostID',
		'created',
	);
}
