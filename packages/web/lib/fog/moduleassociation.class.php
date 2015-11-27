<?php
class ModuleAssociation extends FOGController {
    protected $databaseTable = 'moduleStatusByHost';
    protected $databaseFields = array(
        'id' => 'msID',
        'hostID' => 'msHostID',
        'moduleID' => 'msModuleID',
        'state' => 'msState',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'moduleID',
    );
    public function getModule() {
        return $this->getClass('Module',$this->get('moduleID'));
    }
    public function getHost() {
        return $this->getClass('Host',$this->get('hostID'));
    }
}
