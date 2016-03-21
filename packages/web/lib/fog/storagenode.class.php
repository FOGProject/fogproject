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
        'snapinfiles',
        'logfiles',
    );
    public function get($key = '') {
        if (in_array($this->key($key),array('path','ftppath','snapinpath','sslpath','webroot'))) return rtrim(parent::get($key), '/');
        return parent::get($key);
    }
    public function getStorageGroup() {
        return self::getClass('StorageGroup',$this->get('storageGroupID'));
    }
    public function getNodeFailure($Host) {
        $CurrTime = $this->nice_date();
        foreach ((array)self::getClass('NodeFailureManager')->find(array('storageNodeID'=>$this->get('id'),'hostID'=>$Host)) AS $i => &$NodeFailure) {
            if ($CurrTime < $this->nice_date($NodeFailure->get('failureTime'))) return $NodeFailure;
            unset($NodeFailure);
        }
    }
    public function loadLogfiles() {
        $URL = array_map(function(&$path) {
            return sprintf('http://%s/fog/status/getlogs.php?path=%s',$this->get('ip'),urlencode($path));
        },array('/var/log/httpd/','/var/log/apache2/','/var/log/fog'));
        $paths = self::$FOGURLRequests->process($URL);
        $tmppath = array();
        array_walk($paths,function(&$response,&$index) use (&$tmppath) {
            $tmppath = array_filter(array_merge((array)$tmppath,json_decode($response,true)));
        },(array)$paths);
        $paths = array_unique($tmppath);
        unset($tmppath);
        natcasesort($paths);
        $this->set('logfiles',array_values($paths));
    }
    public function loadSnapinfiles() {
        $URL = sprintf('http://%s/fog/status/getsnapins.php?path=%s',$this->get('ip'),urlencode($this->get('snapinpath')));
        $paths = self::$FOGURLRequests->process($URL);
        $paths = @array_shift($paths);
        $paths = json_decode($paths);
        $pathstring = sprintf('/%s/',trim($this->get('snapinpath'),'/'));
        $paths = array_values(array_unique(array_filter((array)preg_replace('#dev|postdownloadscripts|ssl#','',preg_replace("#$pathstring#",'',$paths)))));
        $this->set('snapinfiles',$paths);
    }
    public function loadImages() {
        $URL = sprintf('http://%s/fog/status/getimages.php?path=%s',$this->get('ip'),urlencode($this->get('path')));
        $paths = self::$FOGURLRequests->process($URL);
        $paths = @array_shift($paths);
        $paths = json_decode($paths);
        $pathstring = sprintf('/%s/',trim($this->get('path'),'/'));
        if (count($paths) < 1) {
            self::$FOGFTP
                ->set('host',$this->get('ip'))
                ->set('username',$this->get('user'))
                ->set('password',$this->get('pass'));
            $pathstring = sprintf('/%s/',trim($this->get('ftppath'),'/'));
            if (!self::$FOGFTP->connect()) return;
            $paths = self::$FOGFTP->nlist($pathstring);
            self::$FOGFTP->close();
        }
        $paths = array_values(array_unique(array_filter((array)preg_replace('#dev|postdownloadscripts|ssl#','',preg_replace("#$pathstring#",'',$paths)))));
        $this->set('images',$this->getSubObjectIDs('Image',array('path'=>$paths)));
    }
    public function getClientLoad() {
        if ((int)$this->get('maxClients') <= 0) return (double)((int)$this->getStorageGroup()->getUsedSlotCount() + (int)$this->getStorageGroup()->getQueuedSlotCount()) / (int)$this->getTotalSupportedClients();
        return (double)((int)$this->getUsedSlotCount() + (int)$this->getQueuedSlotCount()) / (int)$this->get('maxClients');
    }
    public function getUsedSlotCount() {
        $UsedTasks = explode(',',$this->getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        if (($index = array_search(8,$UsedTasks)) === false) return ($countTasks + self::getClass('TaskManager')->count(array('stateID'=>$this->getProgressState(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
        unset($UsedTasks[$index]);
        $UsedTasks = array_values(array_filter((array)$UsedTasks));
        $countTasks = count(array_unique($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->getSubObjectIDs('Task',array('stateID'=>$this->getProgressState(),'typeID'=>8))),'msID')));
        return ($countTasks + self::getClass('TaskManager')->count(array('stateID'=>$this->getProgressState(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
    public function getQueuedSlotCount() {
        $UsedTasks = explode(',',$this->getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        if (($index = array_search(8,$UsedTasks)) === false) return ($countTasks + self::getClass('TaskManager')->count(array('stateID'=>$this->getQueuedStates(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
        unset($UsedTasks[$index]);
        $UsedTasks = array_values(array_filter((array)$UsedTasks));
        $countTasks = count(array_unique($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->getSubObjectIDs('Task',array('stateID'=>$this->getQueuedStates(),'typeID'=>8))),'msID')));
        return ($countTasks + self::getClass('TaskManager')->count(array('stateID'=>$this->getQueuedStates(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
}
