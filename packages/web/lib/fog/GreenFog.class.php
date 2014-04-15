<?php
/** \class GreenFog
	Gets the database information for the Green FOG database stuff.
	Green FOG is what can be used to perform shutdown and restart
	tasks.
*/
class GreenFog extends FOGController
{
	public $databaseTable = 'greenFog';
	public $databaseFields = array(
		'id'	=> 'gfID',
		'hostID' => 'gfHostID',
		'hour'	=> 'gfHour',
		'min'	=> 'gfMin',
		'action' => 'gfAction',
		'days'	=> 'gfDays',
	);
}
