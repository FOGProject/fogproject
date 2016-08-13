<?php
class Group extends FOGController {
    protected $databaseTable = 'groups';
    protected $databaseFields = array(
        'id' => 'groupID',
        'name' => 'groupName',
        'description' => 'groupDesc',
        'createdBy' => 'groupCreateBy',
        'createdTime' => 'groupDateTime',
        'building' => 'groupBuilding',
        'kernel' => 'groupKernel',
        'kernelArgs' => 'groupKernelArgs',
        'kernelDevice' => 'groupPrimaryDisk',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    public function destroy($field = 'id') {
        self::getClass('GroupAssociationManager')->destroy(array('groupID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save() {
        parent::save();
        switch (true) {
        case ($this->isLoaded('hosts')):
            $DBHostIDs = self::getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID');
            $ValidHostIDs = self::getSubObjectIDs('Host');
            $notValid = array_diff((array)$DBHostIDs,(array)$ValidHostIDs);
            if (count($notValid)) self::getClass('GroupAssociationManager')->destroy(array('hostID'=>$notValid));
            unset($ValidHostIDs,$DBHostIDs);
            $DBHostIDs = self::getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                self::getClass('GroupAssociationManager')->destroy(array('groupID'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                $DBHostIDs = self::getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            $insert_fields = array('hostID','groupID');
            $insert_values = array();
            $Hosts = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
            array_walk($Hosts,function(&$hostID,$index) use (&$insert_values) {
                $insert_values[] = array($hostID,$this->get('id'));
            });
            if (count($insert_values) > 0) self::getClass('GroupAssociationManager')->insert_batch($insert_fields,$insert_values);
            unset($DBHostIDs,$RemoveHostIDs,$Hosts);
        }
        return $this;
    }
    public function getHostCount() {
        $GroupHostIDs = self::getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID');
        $ValidHostIDs = self::getSubObjectIDs('Host','','id');
        $notValid = array_diff((array)$GroupHostIDs,(array)$ValidHostIDs);
        if (count($notValid)) self::getClass('GroupAssociationManager')->destroy(array('hostID'=>$notValid));
        return self::getClass('GroupAssociationManager')->count(array('groupID'=>$this->get('id')));
    }
    public function addPrinter($printerAdd, $printerDel, $level = 0) {
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('printerLevel'=>$level));
        if (count($printerDel) > 0) self::getClass('PrinterAssociationManager')->destroy(array('hostID'=>$this->get('hosts'),'printerID'=>$printerDel));
        if (count($printerAdd) > 0) {
            $insert_fields = array('hostID','printerID');
            $insert_values = array();
            array_walk($this->get('hosts'),function(&$hostID,$index) use (&$insert_values,$printerAdd) {
                foreach((array)$printerAdd AS &$printerID) {
                    $insert_values[] = array($hostID,$printerID);
                }
            });
            if (count($insert_values) > 0) self::getClass('PrinterAssociationManager')->insert_batch($insert_fields,$insert_values);
        }
        return $this;
    }
    public function addSnapin($addArray) {
        $insert_fields = array('hostID','snapinID');
        $insert_values = array();
        array_walk($this->get('hosts'),function(&$hostID,$index) use (&$insert_values,$addArray) {
            foreach ($addArray AS $snapinID) {
                $insert_values[] = array($hostID,$snapinID);
            }
        });
        if (count($insert_values) > 0) self::getClass('SnapinAssociationManager')->insert_batch($insert_fields,$insert_values);
        return $this;
    }
    public function removeSnapin($removeArray) {
        self::getClass('SnapinAssociationManager')->destroy(array('hostID'=>$this->get('hosts'),'snapinID'=>$removeArray));
        return $this;
    }
    public function addModule($addArray) {
        $insert_fields = array('hostID','moduleID','state');
        $insert_values = array();
        array_walk($this->get('hosts'),function(&$hostID,$index) use (&$insert_values,$addArray) {
            foreach ($addArray AS &$moduleID) {
                $insert_values[] = array($hostID,$moduleID,'1');
            }
        });
        if (count($insert_values) > 0) self::getClass('ModuleAssociationManager')->insert_batch($insert_fields,$insert_values);
        return $this;
    }
    public function removeModule($removeArray) {
        self::getClass('ModuleAssociationManager')->destroy(array('hostID'=>$this->get('hosts'),'moduleID'=>$removeArray));
        return $this;
    }
    public function setDisp($x,$y,$r) {
        self::getClass('HostScreenSettingsManager')->destroy(array('hostID'=>$this->get('hosts')));
        $items = array_map(function(&$hostID) use ($x,$y,$r) {
            return array($hostID,$x,$y,$r);
        },(array)$this->get('hosts'));
        self::getClass('HostScreenSettingsManager')->insert_batch(array('hostID','width','height','refresh'),$items);
        return $this;
    }
    public function setAlo($time) {
        self::getClass('HostAutoLogoutManager')->destroy(array('hostID'=>$this->get('hosts')));
        $items = array_map(function(&$hostID) use ($time) {
            return array($hostID,$time);
        },(array)$this->get('hosts'));
        self::getClass('HostAutoLogoutManager')->insert_batch(array('hostID','time'),$items);
        return $this;
    }
    public function addHost($addArray) {
        return $this->addRemItem('hosts',(array)$addArray,'merge');
    }
    public function removeHost($removeArray) {
        return $this->addRemItem('hosts',(array)$removeArray,'diff');
    }
    public function addImage($imageID) {
        if ($imageID > 0 && !self::getClass('Image',$imageID)->isValid()) throw new Exception(_('Select a valid image'));
        if (self::getClass('TaskManager')->count(array('hostID'=>$this->get('hosts'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())))) throw new Exception(_('There is a host in a tasking'));
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('imageID'=>$imageID));
        return $this;
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false,$wol = false) {
        $hostCount = count($this->get('hosts'));
        if ($hostCount < 1) throw new Exception(_('No hosts to task'));
        if (self::getClass('TaskManager')->count(array('hostID'=>$hostids,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())))) throw new Exception(_('There is a host in a tasking'));
        $TaskType = self::getClass('TaskType',$taskTypeID);
        if (!$TaskType->isValid()) throw new Exception(self::$foglang['TaskTypeNotValid']);
        $imagingTypes = in_array($taskTypeID,array(1,2,8,15,16,17,24));
        $now = $this->nice_date();
        if ($TaskType->isMulticast()) {
            $Image = self::getClass('Image',@min(self::getSubObjectIDs('Host',array('id'=>$this->get('hosts')),'imageID')));
            if (!$Image->isValid()) throw new Exception(self::$foglang['ImageNotValid']);
            if (!$Image->get('isEnabled')) throw new Exception(_('Image is not enabled'));
            $StorageGroup = $Image->getStorageGroup();
            if (!$StorageGroup->isValid()) throw new Exception(self::$foglang['ImageGroupNotValid']);
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode->isValid()) throw new Exception(_('Unable to find master Storage Node'));
            $port = self::getSetting('FOG_MULTICAST_PORT_OVERRIDE') ? self::getSetting('FOG_MULTICAST_PORT_OVERRIDE') : self::getSetting('FOG_UDPCAST_STARTINGPORT');
            $MulticastSession = self::getClass('MulticastSessions')
                ->set('name',$taskName)
                ->set('port',$port)
                ->set('logpath',$Image->get('path'))
                ->set('image',$Image->get('id'))
                ->set('interface',$StorageNode->get('interface'))
                ->set('stateID',0)
                ->set('starttime',$now->format('Y-m-d H:i:s'))
                ->set('percent',0)
                ->set('isDD',$Image->get('imageTypeID'))
                ->set('NFSGroupID',$StorageGroup->get('id'));
            if ($MulticastSession->save()) {
                self::getClass('MulticastSessionsAssociationManager')->destroy(array('hostID'=>$this->get('hosts')));
                $randomnumber = mt_rand(24576,32766) * 2;
                while ($randomnumber == $MulticastSession->get('port')) $randomnumber = mt_rand(24576,32766) * 2;
                $this->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
            }
            $hostIDs = $this->get('hosts');
            $batchFields = array('name','createdBy','hostID','isForced','stateID','typeID','NFSGroupID','NFSMemberID','wol','imageID','shutdown','isDebug','passreset');
            $batchTask = array();
            for ($i = 0;$i < $hostCount; $i++) {
                $batchTask[] = array($taskName,$username,$hostIDs[$i],0,$this->getQueuedState(),$TaskType->get('id'),$StorageGroup->get('id'),$StorageNode->get('id'),$wol,$Image->get('id'),$shutdown,$debug,$passreset);
            }
            if (count($batchTask) > 0) {
                list($first_id,$affected_rows) = self::getClass('TaskManager')->insert_batch($batchFields,$batchTask);
                $ids = range($first_id,($first_id + $affected_rows - 1));
                $multicastsessionassocs = array();
                array_walk($batchTask,function(&$val,&$index) use (&$ids,$MulticastSession,&$multicastsessionassocs) {
                    $multicastsessionassocs[] = array($MulticastSession->get('id'),$ids[$index]);
                });
                if (count($multicastsessionassocs) > 0) self::getClass('MulticastSessionsAssociationManager')->insert_batch(array('msID','taskID'),$multicastsessionassocs);
            }
            unset($hostCount,$hostIDs,$batchTask,$first_id,$affected_rows,$ids,$multicastsessionassocs);
            $this->createSnapinTasking(-1,$now);
        } else if ($TaskType->isDeploy()) {
            $hostIDs = $this->get('hosts');
            $imageIDs = self::getSubObjectIDs('Host',array('id'=>$hostIDs),'imageID',false,'AND','name',false,'');
            $batchFields = array('name','createdBy','hostID','isForced','stateID','typeID','NFSGroupID','NFSMemberID','wol','imageID','shutdown','isDebug','passreset');
            $batchTask = array();
            for ($i = 0;$i < $hostCount;$i++) {
                $Image = self::getClass('Image',$imageIDs[$i]);
                if (!$Image->isValid()) continue;
                $StorageGroup = $Image->getStorageGroup();
                if (!$StorageGroup->isValid()) continue;
                $StorageNode = $StorageGroup->getOptimalStorageNode($Image->get('id'));
                if (!$StorageNode->isValid()) continue;
                $batchTask[] = array($taskName,$username,$hostIDs[$i],0,$this->getQueuedState(),$TaskType->get('id'),$StorageGroup->get('id'),$StorageNode->get('id'),$wol,$Image->get('id'),$shutdown,$debug,$passreset);
            }
            if (count($batchTask) > 0) self::getClass('TaskManager')->insert_batch($batchFields,$batchTask);
            unset($hostCount,$hostIDs,$batchTask,$first_id,$affected_rows,$ids,$multicastsessionassocs);
            $this->createSnapinTasking($deploySnapins,$now);
        } else if ($TaskType->isSnapinTasking()) {
            $hostIDs = $this->createSnapinTasking($deploySnapins,$now);
            $hostCount = count($hostIDs);
            $batchFields = array('name','createdBy','hostID','stateID','typeID','wol');
            $batchTask = array();
            for ($i = 0; $i < $hostCount; $i++) {
                $batchTask[] = array($taskName,$username,$hostIDs[$i],$this->getQueuedState(),$TaskType->get('id'),$wol);
            }
            if (count($batchTask) > 0) self::getClass('TaskManager')->insert_batch($batchFields,$batchTask);
        }
        if ($wol) {
            $url = 'http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s';
            $hostMACs = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('hosts')),'mac');
            $nodeURLs = array_map(function(&$Node) use ($url,$hostMACs) {
                $curroot = trim(trim($Node->get('webroot'),'/'));
                $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
                return sprintf($url,$Node->get('ip'),$webroot,implode('|',$hostMACs));
            },(array)self::getClass('StorageNodeManager')->find(array('isEnabled'=>1)));
            $curroot = trim(trim(self::getSetting('FOG_WEB_ROOT')));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            $nodeURLs[] = sprintf($url,self::getSetting('FOG_WEB_HOST'),$webroot,implode('|',$hostMACs));
            self::$FOGURLRequests->process($nodeURLs);
        }
        return array('All hosts successfully tasked');
    }
    private function createSnapinTasking($snapin = -1,$now) {
        if ($snapin === false) return;
        $hostIDs = array_values(self::getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('hosts')),'hostID'));
        $hostCount = count($hostIDs);
        $snapinJobs = array();
        for ($i = 0;$i < $hostCount;$i++) {
            $hostID = $hostIDs[$i];
            $snapins[$hostID] = $snapin === -1 ? self::getSubObjectIDs('SnapinAssociation',array('hostID'=>$hostID),'snapinID') : array($snapin);
            if (count($snapins[$hostID]) < 1) continue;
            $snapinJobs[] = array($hostID,$this->getQueuedState(),$now->format('Y-m-d H:i:s'));
        }
        if (count($snapinJobs) > 0) {
            list($first_id,$affected_rows) = self::getClass('SnapinJobManager')->insert_batch(array('hostID','stateID','createdTime'),$snapinJobs);
            $ids = range($first_id,($first_id + $affected_rows - 1));
            for ($i = 0;$i < $hostCount;$i++) {
                $hostID = $hostIDs[$i];
                $jobID = $ids[$i];
                $snapinCount = count($snapins[$hostID]);
                for ($j = 0;$j < $snapinCount;$j++) {
                    $snapinTasks[] = array($jobID,$this->getQueuedState(),$snapins[$hostID][$j]);
                }
            }
            if (count($snapinTasks) > 0) self::getClass('SnapinTaskManager')->insert_batch(array('jobID','stateID','snapinID'),$snapinTasks);
        }
        return $hostIDs;
    }
    public function setAD($useAD, $domain, $ou, $user, $pass, $legacy, $enforce) {
        $pass = trim($this->encryptpw($pass));
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('useAD'=>$useAD,'ADDomain'=>trim($domain),'ADOU'=>trim($ou),'ADUser'=>trim($user),'ADPass'=>$pass,'ADPassLegacy'=>$legacy,'enforce'=>$enforce));
        return $this;
    }
    public function doMembersHaveUniformImages() {
        $imageID = self::getSubObjectIDs('Host',array('id'=>$this->get('hosts')),'imageID','','','','','array_count_values');
        $imageID = count($imageID) == 1 ? array_shift($imageID) : 0;
        return $imageID == $this->getHostCount();
    }
    public function updateDefault($printerid) {
        $AllGroupHostsPrinters = self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('hosts')));
        self::getClass('PrinterAssociationManager')->update(array('id'=>$AllGroupHostsPrinters),'',array('isDefault'=>'0'));
        self::getClass('PrinterAssociationManager')->update(array('printerID'=>$printerid,'hostID'=>$this->get('hosts')),'',array('isDefault'=>'1'));
        return $this;
    }
    protected function loadHosts() {
        $this->set('hosts',self::getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme',self::getSubObjectIDs('Host',$find,'',true));
        unset($find);
    }
}
