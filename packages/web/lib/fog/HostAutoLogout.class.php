<?php
class HostAutoLogout extends FOGController
{
	// Database Table
	public $databaseTable = 'hostAutoLogOut';

	// Fields
	public $databaseFields = array(
		'id' => 'haloID',
		'hostID' => 'haloHostID',
		'time' => 'haloTime',
	);
}
