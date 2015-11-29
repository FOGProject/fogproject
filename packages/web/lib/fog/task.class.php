<?php
class Task extends FOGController {
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
    );
    protected $databaseFieldsRequired = array(
        'id',
        'typeID',
        'hostID',
    );
    public function getInFrontOfHostCount() {
        $Tasks = $this->getClass('TaskManager')->find(array(
            'stateID'=>array(1,2),
            'typeID'=>array(1,15,17),
            'NFSGroupID'=>$this->get('NFSGroupID'),
        ));
        $count = 0;
        $curTime = $this->nice_date();
        foreach($Tasks AS $i => &$Task) {
            if ($this->get('id') > $Task->get('id')) {
                $tasktime = $this->nice_date($Task->get('checkInTime'));
                if (($curTime->getTimestamp()-$tasktime->getTimestamp()) < $this->getSetting('FOG_CHECKIN_TIMEOUT')) $count++;
            }
        }
        unset($Task);
        return $count;
    }
    public function cancel() {
        $SnapinJob = $this->getHost()->getActiveSnapinJob();
        if ($SnapinJob instanceof SnapinJob && $SnapinJob->isValid()) {
            $this->getClass('SnapinTaskManager')->update(array('jobID'=>$SnapinJob->get('id')),'',array('complete'=>$this->nice_date()->format('Y-m-d H:i:s'),'stateID'=>5));
            $SnapinJob->set('stateID',5)->save();
        }
        if ($this->getTaskType()->isMulticast()) $this->getClass('MulticastSessionsManager')->update(array('id'=>$this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->get('id')),'jobID')),'',array('clients'=>0,'completetime'=>$this->formatTime('now','Y-m-d H:i:s'),'stateID'=>5));
        $this->set('stateID',5)->save();
        return $this;
    }
    public function set($key, $value) {
        if ($this->key($key) == 'checkInTime' && is_numeric($value) && strlen($value) == 10) $value = $this->nice_date($value)->format('Y-m-d H:i:s');
        return parent::set($key, $value);
    }
    public function destroy($field = 'id') {
        $this->cancel();
        return parent::destroy($field);
    }
    public function getHost() {
        return $this->getClass('Host',$this->get('hostID'));
    }
    public function getStorageGroup() {
        return $this->getClass('StorageGroup',$this->get('NFSGroupID'));
    }
    public function getStorageNode() {
        return $this->getClass('StorageNode',$this->get('NFSMemberID'));
    }
    public function getImage() {
        return $this->getClass('Image',$this->get('imageID'));
    }
    public function getTaskType() {
        return $this->getClass('TaskType',$this->get('typeID'));
    }
    public function getTaskTypeText() {
        return $this->getTaskType()->get('name');
    }
    public function getTaskState() {
        return $this->getClass('TaskState',$this->get('stateID'));
    }
    public function getTaskStateText() {
        return $this->getTaskState()->get('name');
    }
    public function isForced() {
        return (bool)($this->get('isForced') > 0);
    }
    public function isUpload() {
        return (bool)$this->getTaskType()->isUpload();
    }
    public function isMulticast() {
        return (bool)$this->getTaskType()->isMulticast();
    }
}
