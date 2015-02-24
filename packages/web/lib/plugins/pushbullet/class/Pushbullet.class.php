<?php

class Pushbullet extends FOGController
{
	// Table
	public $databaseTable = 'pushbullet';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'     => 'pID',
		'token'  => 'pToken',
		'name'   => 'pName',
		'email'  => 'pEmail',
	);
	public function destroy($field = 'id')
	{
		return parent::destroy($field);
	}
}
