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
        return static::getClass('StorageGroup',$this->get('storageGroupID'));
    }
    public function getNodeFailure($Host) {
        $NodeFailure = array_map(function(&$Failed) {
            $CurrTime = static::nice_date();
            if ($CurrTime < static::nice_date($Failed->get('failureTime'))) return $Failed;
            unset($Failed);
        },(array)static::getClass('NodeFailureManager')->find(array('storageNodeID'=>$this->get('id'),'hostID'=>$Host)));
        $NodeFailure = @array_shift($NodeFailure);
        if ($NodeFailure instanceof StorageNode && $NodeFailure->isValid()) return $NodeFailure;
    }
    public function loadLogfiles() {
        $URL = array_map(function(&$path) {
            return sprintf('http://%s/fog/status/getfiles.php?path=%s',$this->get('ip'),urlencode($path));
        },array('/var/log/nginx/','/var/log/httpd/','/var/log/apache2/','/var/log/fog','/var/log/php7.0-fpm/','/var/log/php-fpm/','/var/log/php5-fpm/','/var/log/php5.6-fpm/'));
        $paths = static::$FOGURLRequests->process($URL);
        $tmppath = array();
        array_walk($paths,function(&$response,&$index) use (&$tmppath) {
            $tmppath = array_filter((array)array_merge((array)$tmppath,(array)json_decode($response,true)));
        },(array)$paths);
        $paths = array_unique((array)$tmppath);
        unset($tmppath);
        @natcasesort($paths);
        $this->set('logfiles',array_values((array)$paths));
    }
    public function loadSnapinfiles() {
        $URL = sprintf('http://%s/fog/status/getfiles.php?path=%s',$this->get('ip'),urlencode($this->get('snapinpath')));
        $paths = static::$FOGURLRequests->process($URL);
        $paths = @array_shift($paths);
        $paths = json_decode($paths);
        $pathstring = sprintf('/%s/',trim($this->get('snapinpath'),'/'));
        if (count($paths) < 1) {
            static::$FOGFTP
                ->set('host',$this->get('ip'))
                ->set('username',$this->get('user'))
                ->set('password',$this->get('pass'));
            if (!static::$FOGFTP->connect()) return;
            $paths = static::$FOGFTP->nlist($pathstring);
            static::$FOGFTP->close();
        }
        $paths = array_values(array_unique(array_filter((array)preg_replace('#dev|postdownloadscripts|ssl#','',preg_replace("#$pathstring#",'',$paths)))));
        $this->set('snapinfiles',$paths);
    }
    public function loadImages() {
        $URL = sprintf('http://%s/fog/status/getfiles.php?path=%s',$this->get('ip'),urlencode($this->get('path')));
        $paths = static::$FOGURLRequests->process($URL);
        $paths = @array_shift($paths);
        $paths = json_decode($paths);
        $pathstring = sprintf('/%s/',trim($this->get('path'),'/'));
        if (count($paths) < 1) {
            static::$FOGFTP
                ->set('host',$this->get('ip'))
                ->set('username',$this->get('user'))
                ->set('password',$this->get('pass'));
            if (!static::$FOGFTP->connect()) return;
            $pathstring = sprintf('/%s/',trim($this->get('ftppath'),'/'));
            $paths = static::$FOGFTP->nlist($pathstring);
            static::$FOGFTP->close();
        }
        $paths = array_values(array_unique(array_filter((array)preg_replace('#dev|postdownloadscripts|ssl#','',preg_replace("#$pathstring#",'',$paths)))));
        $this->set('images',static::getSubObjectIDs('Image',array('path'=>$paths)));
    }
    public function getClientLoad() {
        if ((int)$this->get('maxClients') <= 0) return (double)((int)$this->getStorageGroup()->getUsedSlotCount() + (int)$this->getStorageGroup()->getQueuedSlotCount()) / (int)$this->getTotalSupportedClients();
        return (double)((int)$this->getUsedSlotCount() + (int)$this->getQueuedSlotCount()) / (int)$this->get('maxClients');
    }
    public function getUsedSlotCount() {
        $UsedTasks = explode(',',static::getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        if (($index = array_search(8,$UsedTasks)) === false) return ($countTasks + static::getClass('TaskManager')->count(array('stateID'=>$this->getProgressState(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
        unset($UsedTasks[$index]);
        $UsedTasks = array_values(array_filter((array)$UsedTasks));
        $countTasks = count(array_unique(static::getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>static::getSubObjectIDs('Task',array('stateID'=>$this->getProgressState(),'typeID'=>8))),'msID')));
        return ($countTasks + static::getClass('TaskManager')->count(array('stateID'=>$this->getProgressState(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
    public function getQueuedSlotCount() {
        $UsedTasks = explode(',',static::getSetting('FOG_USED_TASKS'));
        $countTasks = 0;
        if (($index = array_search(8,$UsedTasks)) === false) return ($countTasks + static::getClass('TaskManager')->count(array('stateID'=>$this->getQueuedStates(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
        unset($UsedTasks[$index]);
        $UsedTasks = array_values(array_filter((array)$UsedTasks));
        $countTasks = count(array_unique(static::getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>static::getSubObjectIDs('Task',array('stateID'=>$this->getQueuedStates(),'typeID'=>8))),'msID')));
        return ($countTasks + static::getClass('TaskManager')->count(array('stateID'=>$this->getQueuedStates(),'typeID'=>$UsedTasks,'NFSMemberID'=>$this->get('id'))));
    }
}
