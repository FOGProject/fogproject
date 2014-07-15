<?php

class Virus extends FOGController
{
	// Table
	public $databaseTable = 'virus';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'vID',
		'name'		=> 'vName',
		'hostMAC'	=> 'vHostMAC',
		'file'		=> 'vOrigFile',
		'date'		=> 'vDateTime',
		'mode'		=> 'vMode',
		'anon2'		=> 'vAnon2',
	);
}
