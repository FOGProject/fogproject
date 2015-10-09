<?php
class StorageNode extends FOGController {
    // Table
    public $databaseTable = 'nfsGroupMembers';
    // Name -> Database field name
    public $databaseFields = array(
        'id'		=> 'ngmID',
        'name'		=> 'ngmMemberName',
        'description'	=> 'ngmMemberDescription',
        'isMaster'	=> 'ngmIsMasterNode',
        'storageGroupID'=> 'ngmGroupID',
        'isEnabled'	=> 'ngmIsEnabled',
        'isGraphEnabled'=> 'ngmGraphEnabled',
        'path'		=> 'ngmRootPath',
        'ftppath' => 'ngmFTPPath',
        'bitrate' => 'ngmMaxBitrate',
        'snapinpath'		=> 'ngmSnapinPath',
        'ip'		=> 'ngmHostname',
        'maxClients'	=> 'ngmMaxClients',
        'user'		=> 'ngmUser',
        'pass'		=> 'ngmPass',
        'key'		=> 'ngmKey',
        'interface'	=> 'ngmInterface',
        'bandwidth' => 'ngmBandwidthLimit',
        'webroot' => 'ngmWebroot',
    );
    // Required database fields
    public $databaseFieldsRequired = array(
        'storageGroupID',
        'ip',
        'path',
        'ftppath',
        'user',
        'pass',
    );
    // Overrides
    public function get($key = '') {
        // Path: Always remove trailing slash on NFS path
        if (in_array($this->key($key),array('path','ftppath','snapinpath','webroot'))) return rtrim(parent::get($key), '/');
        // FOGController get()
        return parent::get($key);
    }
    public function getStorageGroup() {
        return $this->getClass(StorageGroup,$this->get(storageGroupID));
    }
    public function getNodeFailure($Host) {
        $CurrTime = $this->nice_date();
        $NodeFailures = $this->getClass(NodeFailureManager)->find(array(
            storageNodeID=>$this->get(id),
            hostID=>$this->DB->sanitize($Host instanceof Host ? $Host->get(id) : $Host),
        ));
        foreach($NodeFailures AS $i => &$NodeFailure) {
            $FailUntil = $this->nice_date($NodeFailure->get(failureTime));
            if ($CurrTime < $FailUntil) return $NodeFailure;
        }
        unset($NodeFailure);
    }
    public function getClientLoad() {
        $max = $this->get(maxClients);
        if ( $max > 0 ) {
            return (($this->getUsedSlotCount() + $this->getQueuedSlotCount()) / $max);
        }
        return 0;
    }
    public function getUsedSlotCount() {
        $UsedTasks = explode(',',$this->FOGCore->getSetting(FOG_USED_TASKS));
        $countTasks = 0;
        if (in_array(8,(array)$UsedTasks)) {
            foreach($UsedTasks AS $ind => &$val) if ($val = 8) unset($UsedTasks[$ind]);
            unset($val);
            $MCTasks = $this->getClass(TaskManager)->find(array(stateID=>3,typeID=>8));
            foreach ($MCTasks AS $i => &$MulticastTask) {
                $Multicast = $this->getClass(MulticastSessionsAssociationManager)->find(array(taskID=>$MulticastTask->get(id)));
                $Multicast = @array_shift($Multicast);
                if ($Multicast->isValid()) $MulticastJobID = $this->getClass(MulticastSessionsManager)->find(array(id=>$Multicast->get(msID)),'','','','','','','id');
            }
            unset($MulticastTask);
            $MulticastJobID = array_unique((array)$MulticastJobID);
            $countTasks = count($MulticastJobID);
            $UsedTasks = array_values((array)$UsedTasks);
        }
        $countTasks += $this->getClass(TaskManager)->count(array(
            stateID=>3,
            typeID=>$UsedTasks,
            NFSMemberID=>$this->get(id),
        ));
        return $countTasks;
    }
    public function getQueuedSlotCount() {
        return $this->getClass(TaskManager)->count(array(
            stateID=>array(1,2),
            typeID=>array(1,2,8,15,16,17),
            NFSMemberID=>$this->get(id),
        ));
    }
}
