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
    protected $additionalFields = array(
        'images',
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
    public function loadImages() {
        $URL = sprintf('http://%s/fog/status/getimages.php?path=%s',$this->get('ip'),urlencode($this->get('path')));
        $paths = $this->FOGURLRequests->process($URL);
        $paths = @array_shift($paths);
        $paths = json_decode($paths);
        $pathstring = sprintf('/%s/',trim($this->get('path'),'/'));
        $paths = array_values(array_unique(array_filter(preg_replace('#dev|postdownloadscripts#','',preg_replace("#$pathstring#",'',$paths)))));
        $this->set('images',$this->getSubObjectIDs('Image',array('path'=>$paths)));
    }
    public function getClientLoad() {
        if ((int)$this->get('maxClients') <= 0) return (double)((int)$this->getStorageGroup()->getUsedSlotCount() + (int)$this->getStorageGroup()->getQueuedSlotCount()) / (int)$this->getTotalSupportedClients();
        return (double)((int)$this->getUsedSlotCount() + (int)$this->getQueuedSlotCount()) / (int)$this->get('maxClients');
    }
    public function getUsedSlotCount() {
        $UsedTasks = explode(',',$this->getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        if (($index = array_search(8,$UsedTasks)) === false) return ($countTasks + $this->getClass('TaskManager')->count(array('stateID'=>$this->getProgressState(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
        unset($UsedTasks[$index]);
        $UsedTasks = array_values(array_filter((array)$UsedTasks));
        $countTasks = count(array_unique($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->getSubObjectIDs('Task',array('stateID'=>$this->getProgressState(),'typeID'=>8))),'msID')));
        return ($countTasks + $this->getClass('TaskManager')->count(array('stateID'=>$this->getProgressState(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
    public function getQueuedSlotCount() {
        $UsedTasks = explode(',',$this->getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        if (($index = array_search(8,$UsedTasks)) === false) return ($countTasks + $this->getClass('TaskManager')->count(array('stateID'=>$this->getQueuedStates(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
        unset($UsedTasks[$index]);
        $UsedTasks = array_values(array_filter((array)$UsedTasks));
        $countTasks = count(array_unique($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->getSubObjectIDs('Task',array('stateID'=>$this->getQueuedStates(),'typeID'=>8))),'msID')));
        return ($countTasks + $this->getClass('TaskManager')->count(array('stateID'=>$this->getQueuedStates(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
}
