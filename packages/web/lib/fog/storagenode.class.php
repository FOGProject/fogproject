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
        'sslpath' => 'ngmSSLPath',
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
        if (in_array($this->key($key),array('path','ftppath','snapinpath','sslpath','webroot'))) return rtrim(parent::get($key), '/');
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
        if ($this->get('maxClients') > 0) {
            $this->set('maxClients',$this->get('maxClients') - $this->getUsedSlotCount());
            return (($this->getUsedSlotCount() + $this->getQueuedSlotCount()) / ($this->get('maxClients') + $this->getUsedSlotCount()));
        }
        return 0;
    }
    public function getUsedSlotCount() {
        $UsedTasks = explode(',',$this->getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        if ($index = array_search(8,$UsedTasks)) {
            unset($UsedTasks[$index]);
            $UsedTasks = array_values(array_filter((array)$UsedTasks));
            $countTasks = count(array_unique($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->getSubObjectIDs('Task',array('stateID'=>$this->getProgressState(),'typeID'=>8))),'msID')));
        }
        return ($countTasks + $this->getClass('TaskManager')->count(array('stateID'=>$this->getProgressState(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
    public function getQueuedSlotCount() {
        $UsedTasks = explode(',',$this->getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        if ($index = array_search(8,$UsedTasks)) {
            unset($UsedTasks[$index]);
            $UsedTasks = array_values(array_filter((array)$UsedTasks));
            $countTasks = count(array_unique($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->getSubObjectIDs('Task',array('stateID'=>$this->getQueuedStates(),'typeID'=>8))),'msID')));
        }
        return ($countTasks + $this->getClass('TaskManager')->count(array('stateID'=>$this->getQueuedStates(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
}
