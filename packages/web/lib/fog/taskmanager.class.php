<?php
class TaskManager extends FOGManagerController {
    public function cancel($taskids) {
        $cancelled = $this->getCancelledState();
        $notComplete = array_merge((array)$this->getQueuedStates(),(array)$this->getProgressState());
        $findWhere = array('id'=>(array)$taskids,'stateID'=>$notComplete);
        $hostIDs = self::getSubObjectIDs('Task',$findWhere,'hostID');
        $this->update($findWhere,'',array('stateID'=>$cancelled));
        $findWhere = array('hostID'=>$hostIDs,'stateID'=>$notComplete);
        $SnapinJobIDs = array_filter(self::getSubObjectIDs('SnapinJob',$findWhere));
        $findWhere = array('stateID'=>$notComplete,'jobID'=>$SnapinJobIDs);
        $SnapinTaskIDs = array_filter(self::getSubObjectIDs('SnapinTask',$findWhere));
        $findWhere = array('taskID'=>$taskids);
        $MulticastSessionIDs = array_filter(self::getSubObjectIDs('MulticastSessionsAssociation',$findWhere,'msID'));
        $MulticastSessionIDs = array_filter(self::getSubObjectIDs('MulticastSessions',array('stateID'=>$notComplete,'id'=>$MulticastSessionIDs)));
        if (count($SnapinTaskIDs)) self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        if (count($SnapinJobIDs)) self::getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        if (count($MulticastSessionIDs)) self::getClass('MulticastSessionsManager')->cancel($MulticastSessionIDs);
    }
}
