<?php
class PendingMAC extends FOGController
{
	// Table
	public $databaseTable = 'pendingMACS';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'				=> 'pmID',
		'pending'			=> 'pmAddress',
		'hostID'			=> 'pmHostID',
	);
}
