<?php
class TaskManager extends FOGManagerController {
    public function cancel($taskids) {
        $findWhere = array('taskID'=>(array)$taskids);
        $SnapinJobIDs = $this->getSubObjectIDs('SnapinTask',$findWhere,'jobID');
        $SnapinTaskIDs = $this->getSubObjectIDs('SnapinTask',$findWhere,'taskID');
        $MulticastSessionIDs = $this->getSubObjectIDs('MulticastSessionsAssociation',$findWhere,'msID');
        $this->array_change_key($findWhere,'taskID','id');
        if (count($SnapinTaskIDs)) $this->getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        if (count($SnapinJobIDs)) $this->getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        if (count($MulticastSessionIDs)) $this->getClass('MulticastSessionsManager')->cancel($MulticastSessionIDs);
        $this->update($findWhere,'',array('stateID'=>$this->getCancelledState()));
    }
}
