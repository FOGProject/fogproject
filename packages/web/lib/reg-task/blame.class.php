<?php
class Blame extends TaskingElement {
    public function __construct() {
        parent::__construct();
        array_map(function(&$StorageNode) {
            if ($this->Task->get('NFSMemberID') < 1 || in_array($this->Task->get('NFSMemberID'),static::getAllBlamedNodes())) {
                $this->Task->set('stateID',$this->getQueuedState());
                return;
            }
            $Failed = static::getClass('NodeFailure')
                ->set('storageNodeID',$this->Task->get('NFSMemberID'))
                ->set('taskID',$this->Task->get('id'))
                ->set('hostID',$this->Host->get('id'))
                ->set('groupID',$this->Task->get('NFSGroupID'))
                ->set('failureTime',$this->nice_date('+5 minutes')->format('Y-m-d H:i:s'));
            if ($Failed->save()) $this->Task->set('stateID',$this->getQueuedState());
        },(array)$this->StorageNodes);
        if ($this->Task->save()) echo '##';
    }
}
