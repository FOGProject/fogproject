<?php
/** \class GroupAssociation
	This just get's associated hosts and or groups.
*/
class GroupAssociation extends FOGController
{
	// Table
	public $databaseTable = 'groupMembers';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'gmID',
		'hostID'	=> 'gmHostID',
		'groupID'	=> 'gmGroupID'
	);
}
