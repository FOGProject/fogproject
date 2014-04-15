<?php
class ModuleStatusByHost extends FOGController
{
	// Table
	public $databaseTable = 'moduleStatusByHost';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'msID',
		'hostID' => 'msHostID',
		'moduleID' => 'msModuleID',
		'state' => 'msState',
	);

	public function getModuleName()
	{
		$Module = new Module($this->get('moduleID'));
		return $Module->get('name');
	}
}
