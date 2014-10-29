<?php
/** Class Name: History
	This just generates the Capone information.
	Only valid if the plugin is installed.
	Although it can be "accessed" it won't be
	of any use until the plugin is installed and set.
*/
class History extends FOGController
{
	// Table
	public $databaseTable = 'history';
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'hID',
		'info' => 'hText',
		'createdBy' => 'hUser',
		'createdTime' => 'hTime',
		'ip' => 'hIP',
	);
}
