<?php

class KeySequence extends FOGController
{
	// Table
	public $databaseTable = 'keySequence';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'ksID',
		'name'		=> 'ksValue',
		'ascii'		=> 'ksAscii',
	);
}
