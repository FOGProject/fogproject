<?php
class TaskManager extends FOGManagerController {
    public function cancel($taskids) {
        $findWhere = array('id'=>(array)$taskids);
        $cancelled = $this->getCancelledState();
        $this->update($findWhere,'',array('stateID'=>$cancelled));
        $this->array_change_key($findWhere,'id','taskID');
        $SnapinJobIDs = array_filter(self::getSubObjectIDs('SnapinTask',$findWhere,'jobID'));
        $SnapinTaskIDs = array_filter(self::getSubObjectIDs('SnapinTask',$findWhere));
        $MulticastSessionIDs = array_filter(self::getSubObjectIDs('MulticastSessionsAssociation',$findWhere,'msID'));
        if (count($SnapinTaskIDs)) self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        if (count($SnapinJobIDs)) self::getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        if (count($MulticastSessionIDs)) self::getClass('MulticastSessionsManager')->cancel($MulticastSessionIDs);
    }
}
