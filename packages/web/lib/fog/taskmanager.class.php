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
        $MulticastSessionAssocIDs = array_filter(self::getSubObjectIDs('MulticastSessionsAssociation',$findWhere));
        $MulticastSessionIDs = array_filter(self::getSubObjectIDs('MulticastSessionsAssociation',$findWhere,'msID'));
        $MulticastSessionIDs = array_filter(self::getSubObjectIDs('MulticastSessions',array('stateID'=>$notComplete,'id'=>$MulticastSessionIDs)));
        if (count($MulticastSessionAssocIDs) > 0) self::getSubObjectIDs('MulticastSessionsAssociationManager')->destroy(array('id'=>$MulticastSessionsAssocIDs));
        $StillLeft = self::getClass('MulticastSessionsAssociationManager')->count(array('msID'=>$MulticastSessionIDs));
        if (count($SnapinTaskIDs) > 0) self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        if (count($SnapinJobIDs) > 0) self::getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        if ($StillLeft < 1 && count($MulticastSessionIDs) > 0) self::getClass('MulticastSessionsManager')->cancel($MulticastSessionIDs);
    }
}
