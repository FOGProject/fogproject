<?php
class MulticastSessionsAssociation extends FOGController {
    protected $databaseTable = 'multicastSessionsAssoc';
    protected $databaseFields = array(
        'id' => 'msaID',
        'msID' => 'msID',
        'taskID' => 'tID',
    );
    protected $databaseFieldsRequired = array(
        'msID',
        'taskID',
    );
    public function getMulticastSession() {
        return $this->getClass('MulticastSessions',$this->get('msID'));
    }
    public function getTask() {
        return $this->getClass('Task',$this->get('taskID'));
    }
}
