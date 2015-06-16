<?php
class Accesscontrol extends FOGController {
	// Table
	public $databaseTable = 'accessControls';
	// Name -> Database field name
	public $databaseFields = array(
			'id'		=> 'acID',
			'name'		=> 'acName',
			'description' => 'acDesc',
			'other'		=> 'acOther',
			'userID'	=> 'acUserID',
			'groupID'	=> 'acGroupID',
			);
}
