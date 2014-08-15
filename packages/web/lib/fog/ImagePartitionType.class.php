<?php

// Blackout - 1:50 PM 1/12/2011
class ImagePartitionType extends FOGController
{
	// Table
	public $databaseTable = 'imagePartitionTypes';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'imagePartitionTypeID',
		'name'		=> 'imagePartitionTypeName',
		'type'		=> 'imagePartitionTypeValue'
	);
}