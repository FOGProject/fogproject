<?php

class SnapinJob extends FOGController
{
	// Table
	public $databaseTable = 'snapinJobs';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'sjID',
		'hostID'	=> 'sjHostID',
		'stateID'	=> 'sjStateID',
		'createTime' => 'sjCreateTime',
	);
}
