<?php
class TaskManager extends FOGManagerController {
    public function cancel($taskids) {
        $findWhere = array('taskID'=>(array)$taskids);
        $SnapinJobIDs = static::getSubObjectIDs('SnapinTask',$findWhere,'jobID');
        $SnapinTaskIDs = static::getSubObjectIDs('SnapinTask',$findWhere);
        $MulticastSessionIDs = static::getSubObjectIDs('MulticastSessionsAssociation',$findWhere,'msID');
        $this->array_change_key($findWhere,'taskID','id');
        if (count($SnapinTaskIDs)) static::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        if (count($SnapinJobIDs)) static::getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        if (count($MulticastSessionIDs)) static::getClass('MulticastSessionsManager')->cancel($MulticastSessionIDs);
        $this->update($findWhere,'',array('stateID'=>$this->getCancelledState()));
    }
}
