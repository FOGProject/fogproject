<?php
class MulticastSessions extends FOGController
{
	// Table
	public $databaseTable = 'multicastSessions';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'				=> 'msID',
		'name'				=> 'msName',
		'port'				=> 'msBasePort',
		'logpath'			=> 'msLogPath',
		'image'				=> 'msImage',
		'clients'			=> 'msClients',
		'interface'			=> 'msInterface',
		'starttime'			=> 'msStartDateTime',
		'percent'			=> 'msPercent',
		'stateID'			=> 'msState',
		'completetime'		=> 'msCompleteDateTime',
		'isDD'				=> 'msIsDD',
		'NFSGroupID'		=> 'msNFSGroupID',
		'anon3'				=> 'msAnon3',
		'anon4'				=> 'msAnon4',
		'anon5'				=> 'msAnon5',
	);
}
