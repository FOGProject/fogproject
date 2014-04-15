<?php

// Blackout - 1:50 PM 1/12/2011
class ImageType extends FOGController
{
	// Table
	public $databaseTable = 'imageTypes';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'imageTypeID',
		'name'		=> 'imageTypeName',
		'type'		=> 'imageTypeValue'
	);
}