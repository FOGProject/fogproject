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
        return self::getClass('MulticastSessions',$this->get('msID'));
    }
    public function getTask() {
        return self::getClass('Task',$this->get('taskID'));
    }
}
