<?php
class MulticastSessionsAssociation extends FOGController {
    // Table
    public $databaseTable = 'multicastSessionsAssoc';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'msaID',
        'msID' => 'msID',
        'taskID' => 'tID',
    );
    public function getMulticastSession() {return $this->getClass('MulticastSessions',$this->get('msID'));}
    public function getTask() {return $this->getClass('Task',$this->get(taskID));}
}
