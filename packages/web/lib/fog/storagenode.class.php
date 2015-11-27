<?php
class StorageNode extends FOGController {
    protected $databaseTable = 'nfsGroupMembers';
    protected $databaseFields = array(
        'id' => 'ngmID',
        'name' => 'ngmMemberName',
        'description' => 'ngmMemberDescription',
        'isMaster' => 'ngmIsMasterNode',
        'storageGroupID' => 'ngmGroupID',
        'isEnabled' => 'ngmIsEnabled',
        'isGraphEnabled' => 'ngmGraphEnabled',
        'path' => 'ngmRootPath',
        'ftppath' => 'ngmFTPPath',
        'bitrate' => 'ngmMaxBitrate',
        'snapinpath' => 'ngmSnapinPath',
        'ip' => 'ngmHostname',
        'maxClients' => 'ngmMaxClients',
        'user' => 'ngmUser',
        'pass' => 'ngmPass',
        'key' => 'ngmKey',
        'interface' => 'ngmInterface',
        'bandwidth' => 'ngmBandwidthLimit',
        'webroot' => 'ngmWebroot',
    );
    protected $databaseFieldsRequired = array(
        'storageGroupID',
        'ip',
        'path',
        'ftppath',
        'user',
        'pass',
    );
    public function get($key = '') {
        if (in_array($this->key($key),array('path','ftppath','snapinpath','webroot'))) return rtrim(parent::get($key), '/');
        return parent::get($key);
    }
    public function getStorageGroup() {
        return $this->getClass('StorageGroup',$this->get('storageGroupID'));
    }
    public function getNodeFailure($Host) {
        $CurrTime = $this->nice_date();
        foreach ((array)$this->getClass('NodeFailureManager')->find(array('storageNodeID'=>$this->get('id'),'hostID'=>$Host)) AS $i => &$NodeFailure) {
            if ($CurrTime < $this->nice_date($NodeFailure->get('failureTime'))) return $NodeFailure;
            unset($NodeFailure);
        }
    }
    public function getClientLoad() {
        if ($this->get('maxClients') > 0 ) return (($this->getUsedSlotCount() + $this->getQueuedSlotCount()) / $this->get('maxClients'));
        return 0;
    }
    public function getUsedSlotCount() {
        $UsedTasks = explode(',',$this->getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        asort($UsedTasks);
        if (($index = $this->binary_search(8,$UsedTasks)) > -1) {
            unset($UsedTasks[$index]);
            $UsedTasks = array_values(array_filter((array)$UsedTasks));
            $countTasks = count(array_unique($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->getSubObjectIDs('Task',array('stateID'=>3,'typeID'=>8))),'msID')));
        }
        return ($countTasks + $this->getClass('TaskManager')->count(array('stateID'=>3,'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
    public function getQueuedSlotCount() {
        $UsedTasks = explode(',',$this->getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        asort($UsedTasks);
        if (($index = $this->binary_search(8,$UsedTasks)) > -1) {
            unset($UsedTasks[$index]);
            $UsedTasks = array_values(array_filter((array)$UsedTasks));
            $countTasks = count(array_unique($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->getSubObjectIDs('Task',array('stateID'=>array(0,1,2),'typeID'=>8))),'msID')));
        }
        return ($countTasks + $this->getClass('TaskManager')->count(array('stateID'=>array(0,1,2),'typeID'=>$usedTasks,'NFSMemberID'=>$this->get('id'))));
    }
}
