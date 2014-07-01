<?php
class Module extends FOGController
{
	// Table
	public $databaseTable = 'modules';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'id',
		'name'		=> 'name',
		'shortName'	=> 'short_name',
		'description'	=> 'description'
	);
	
	// Overrides
	public function isValid()
	{
		return ($this->get('id') && $this->get('name') && $this->get('shortName'));
	}
}
