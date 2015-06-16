<?php

class HostScreenSettings extends FOGController
{
	// Table
	public $databaseTable = 'hostScreenSettings';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'hssID',
		'hostID'	=> 'hssHostID',
		'width'		=> 'hssWidth',
		'height'	=> 'hssHeight',
		'refresh'	=> 'hssRefresh',
		'orientation' => 'hssOrientation',
		'other1'	=> 'hssOther1',
		'other2'	=> 'hssOther2',
	);
}
