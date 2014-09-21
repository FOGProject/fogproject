<?php
class ImagingLog extends FOGController
{
	// Table
	public $databaseTable = 'imagingLog';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'ilID',
		'hostID'		=> 'ilHostID',
		'start'	=> 'ilStartTime',
		'finish'	=> 'ilFinishTime',
		'image'	=> 'ilImageName',
		'type' => 'ilType',
	);
}
