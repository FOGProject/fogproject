<?php
class TaskManager extends FOGManagerController {
    public function cancel($taskids) {
        $findWhere = array('taskID'=>(array)$taskids);
        $SnapinJobIDs = self::getSubObjectIDs('SnapinTask',$findWhere,'jobID');
        $SnapinTaskIDs = self::getSubObjectIDs('SnapinTask',$findWhere);
        $MulticastSessionIDs = self::getSubObjectIDs('MulticastSessionsAssociation',$findWhere,'msID');
        $this->array_change_key($findWhere,'taskID','id');
        if (count($SnapinTaskIDs)) self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        if (count($SnapinJobIDs)) self::getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        if (count($MulticastSessionIDs)) self::getClass('MulticastSessionsManager')->cancel($MulticastSessionIDs);
        $this->update($findWhere,'',array('stateID'=>$this->getCancelledState()));
    }
}
