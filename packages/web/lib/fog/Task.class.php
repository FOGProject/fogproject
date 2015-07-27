<?php
class Task extends FOGController {
    // Table
    public $databaseTable = 'tasks';
    // Name -> Database field name
    public $databaseFields = array(
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
    // Required database fields
    public $databaseFieldsRequired = array(
        'id',
        'typeID',
        'hostID',
    );
    public function getInFrontOfHostCount() {
        $Tasks = $this->getClass(TaskManager)->find(array(
            stateID=>array(1,2),
            typeID=>array(1,15,17),
            NFSGroupID=>$this->get(NFSGroupID),
        ));
        $count = 0;
        $curTime = $this->nice_date();
        foreach($Tasks AS $i => &$Task) {
            if ($this->get(id)>$Task->get(id)) {
                $tasktime = $this->nice_date($Task->get(checkInTime));
                if (($curTime->getTimestamp()-$tasktime->getTimestamp())<$this->FOGCore->getSetting(FOG_CHECKIN_TIMEOUT)) $count++;
            }
        }
        unset($Task);
        return $count;
    }
    public function cancel() {
        // Set State to User cancelled
        $SnapinJob = $this->getHost()->getActiveSnapinJob();
        if ($SnapinJob instanceof SnapinJob && $SnapinJob->isValid()) {
            $this->getClass(SnapinTaskManager)->destroy(array(jobID=>$SnapinJob->get(id)));
            $SnapinJob->destroy();
        }
        if ($this->getTaskType()->isMulticast()) {
            $MSAs = $this->getClass(MulticastSessionsAssociationManager)->find(array(taskID=>$this->get(id)));
            foreach ($MSAs AS $i => &$MSA) {
                if ($MSA->isValid()) {
                    $MS = $MSA->getMulticastSession();
                    $clients = $MS->get(clients);
                    $MS->set(clients,--$clients);
                    if ($MS->get(clients)<=0) {
                        $MS->set(completetime,$this->formatTime('now','Y-m-d H:i:s'))
                            ->set(stateID,5);
                    }
                    $MS->save();
                }
            }
            unset($MSA);
        }
        $this->set(stateID,5)->save();
    }
    // Overrides
    public function set($key, $value) {
        // Check in time: Convert Unix time to MySQL datetime
        if ($this->key($key) == 'checkInTime' && is_numeric($value) && strlen($value) == 10) $value = $this->nice_date($value)->format('Y-m-d H:i:s');
        // Return
        return parent::set($key, $value);
    }
    public function destroy($field = 'id') {
        $SnapinJob = $this->getHost()->getActiveSnapinJob();
        $SnapinTasks = $this->getClass(SnapinTaskManager)->find(array(jobID=>$SnapinJob->get(id),stateID=>array(0,1)));
        // cancel's all the snapin tasks for that host.
        if ($SnapinTasks) {
            foreach($SnapinTasks AS $i => &$ST) {
                foreach($ST AS $i => &$SnapinTask) $SnapinTask->set(stateID,-1)->save();
                unset($SnapinTask);
            }
            unset($ST);
        }
        // FOGController destroy
        return parent::destroy($field);
    }
    public function setHost($Host) {
        if ($Host instanceof Host) $this->set(hostID,$Host->get(id));
        else $this->set(hostID,$Host);
        return $this;
    }
    public function hasTransferData() {
        return $this->getPercent() && strlen(trim($this->getPercent())) > 0 &&
            $this->getTransferRate() && strlen(trim($this->getTransferRate())) > 0 &&
            $this->getTimeElapsed() && strlen(trim($this->getTimeElapsed())) > 0 &&
            $this->getTimeRemaining() && strlen(trim($this->getTimeRemaining())) > 0 &&
            $this->getDataCopied() && strlen(trim($this->getDataCopied())) > 0 &&
            $this->getTaskPercentText() && strlen(trim($this->getTaskPercentText())) > 0 &&
            $this->getTaskDataTotal() && strlen(trim($this->getTaskDataTotal())) > 0;
    }
    // Custom Functions
    public function getHost() {
        return $this->getClass(Host,$this->get(hostID));
    }
    public function getStorageGroup() {
        return $this->getClass(StorageGroup,$this->get(NFSGroupID));
    }
    public function getStorageNode() {
        return $this->getClass(StorageNode,$this->get(NFSMemberID));
    }
    public function getImage() {
        return $this->getClass(Image,$this->get(imageID));
    }
    public function getTaskType() {
        return $this->getClass(TaskType,$this->get(typeID));
    }
    public function getTaskTypeText() {
        return $this->getTaskType()->get(name);
    }
    public function getTaskState() {
        return $this->getClass(TaskState,$this->get(stateID));
    }
    public function getTaskStateText() {
        return $this->getTaskState()->get(name);
    }
}
