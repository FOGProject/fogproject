<?php
class ModuleAssociation extends FOGController {
	// Table
	public $databaseTable = 'moduleStatusByHost';
	// Name -> Database field name
	public $databaseFields = array(
			'id' => 'msID',
			'hostID' => 'msHostID',
			'moduleID' => 'msModuleID',
			'state' => 'msState',
			);
	public function getModule() {return $this->getClass('Module',$this->get('moduleID'));}
	public function getHost() {return $this->getClass('Host',$this->get('hostID'));}
}
