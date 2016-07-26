<?php
class Task extends TaskType {
    protected $databaseTable = 'tasks';
    protected $databaseFields = array(
        'id' => 'taskID',
        'name' => 'taskName',
        'checkInTime' => 'taskCheckIn',
        'hostID' => 'taskHostID',
        'stateID' => 'taskStateID',
        'createdTime' => 'taskCreateTime',
        'createdBy' => 'taskCreateBy',
        'isForced' => 'taskForce',
        'scheduledStartTime' => 'taskScheduledStartTime',
        'typeID' => 'taskTypeID',
        'pct' => 'taskPCT',
        'bpm' => 'taskBPM',
        'timeElapsed' => 'taskTimeElapsed',
        'timeRemaining' => 'taskTimeRemaining',
        'dataCopied' => 'taskDataCopied',
        'percent' => 'taskPercentText',
        'dataTotal' => 'taskDataTotal',
        'NFSGroupID' => 'taskNFSGroupID',
        'NFSMemberID' => 'taskNFSMemberID',
        'NFSFailures' => 'taskNFSFailures',
        'NFSLastMemberID' => 'taskLastMemberID',
        'shutdown' => 'taskShutdown',
        'passreset' => 'taskPassreset',
        'isDebug' => 'taskIsDebug',
        'imageID' => 'taskImageID',
        'wol' => 'taskWOL',
    );
    protected $databaseFieldsRequired = array(
        'id',
        'typeID',
        'hostID',
    );
    public function getInFrontOfHostCount() {
        $count = 0;
        $curTime = self::nice_date();
        $MyCheckinTime = self::nice_date($this->get('checkInTime'));
        $myLastCheckin = $curTime->getTimestamp() - $MyCheckinTime->getTimestamp();
        if ($myLastCheckin >= self::getSetting('FOG_CHECKIN_TIMEOUT')) $this->set('checkInTime',$curTime->format('Y-m-d H:i:s'))->save();
        array_map(function(&$Task) use (&$count,$curTime,$MyCheckinTime) {
            if (!$Task->isValid()) return;
            $TaskCheckinTime = self::nice_date($Task->get('checkInTime'));
            $timeOfLastCheckin = $curTime->getTimestamp() - $TaskCheckinTime->getTimestamp();
            if ($timeOfLastCheckin >= self::getSetting('FOG_CHECKIN_TIMEOUT')) $Task->set('checkInTime',$curTime->format('Y-m-d H:i:s'))->save();
            if ($MyCheckinTime > $TaskCheckinTime) $count++;
            unset($Task);
        },(array)self::getClass('TaskManager')->find(array('stateID'=>array_merge((array)$this->getQueuedStates()),'typeID'=>array(1,15,17),'NFSGroupID'=>$this->get('NFSGroupID'),'NFSMemberID'=>$this->get('NFSMemberID'))));
        return $count;
    }
    public function cancel() {
        $SnapinJob = $this->getHost()->get('snapinjob');
        if ($SnapinJob instanceof SnapinJob && $SnapinJob->isValid()) {
            self::getClass('SnapinTaskManager')->update(array('jobID'=>$SnapinJob->get('id')),'',array('complete'=>self::nice_date()->format('Y-m-d H:i:s'),'stateID'=>$this->getCancelledState()));
            $SnapinJob->set('stateID',$this->getCancelledState())->save();
        }
        if ($this->isMulticast()) self::getClass('MulticastSessionsManager')->update(array('id'=>self::getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->get('id')),'jobID')),'',array('clients'=>0,'completetime'=>$this->formatTime('now','Y-m-d H:i:s'),'stateID'=>$this->getCancelledState()));
        $this->set('stateID',$this->getCancelledState())->save();
        return $this;
    }
    public function set($key, $value) {
        if ($this->key($key) == 'checkInTime' && is_numeric($value) && strlen($value) == 10) $value = self::nice_date($value)->format('Y-m-d H:i:s');
        return parent::set($key, $value);
    }
    public function destroy($field = 'id') {
        $this->cancel();
        return parent::destroy($field);
    }
    public function getHost() {
        return self::getClass('Host',$this->get('hostID'));
    }
    public function getStorageGroup() {
        return self::getClass('StorageGroup',$this->get('NFSGroupID'));
    }
    public function getStorageNode() {
        return self::getClass('StorageNode',$this->get('NFSMemberID'));
    }
    public function getImage() {
        return self::getClass('Image',$this->get('imageID'));
    }
    public function getTaskType() {
        return self::getClass('TaskType',$this->get('typeID'));
    }
    public function getTaskTypeText() {
        return $this->getTaskType()->get('name');
    }
    public function getTaskState() {
        return self::getClass('TaskState',$this->get('stateID'));
    }
    public function getTaskStateText() {
        return $this->getTaskState()->get('name');
    }
    public function isForced() {
        return (bool)($this->get('isForced') > 0);
    }
    public function isDebug() {
        return (bool)(parent::isDebug() || $this->get('isDebug'));
    }
}
