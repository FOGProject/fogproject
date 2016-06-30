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
        case ($this->get('hosts')):
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
        self::getClass('PrinterAssociationManager')->destroy(array('hostID'=>$this->get('hosts'),'printerID'=>$printerDel));
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('printerLevel'=>$level));
        $insert_fields = array('hostID','printerID');
        $insert_values = array();
        array_walk($this->get('hosts'),function(&$hostID,$index) use (&$insert_values,$printerAdd) {
            foreach($printerAdd AS &$printerID) {
                $insert_values[] = array($hostID,$printerID);
            }
        });
        if (count($insert_values) > 0) self::getClass('PrinterAssociationManager')->insert_batch($insert_fields,$insert_values);
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
        $this->set('hosts',array_unique(array_merge((array)$this->get('hosts'),(array)$addArray)));
        return $this;
    }
    public function removeHost($removeArray) {
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
    public function addImage($imageID) {
        if ($imageID > 0 && !self::getClass('Image',$imageID)->isValid()) throw new Exception(_('Select a valid image'));
        if (self::getClass('TaskManager')->count(array('hostID'=>$this->get('hosts'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())))) throw new Exception(_('There is a host in a tasking'));
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('imageID'=>$imageID));
        return $this;
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false,$wol = false) {
        if (self::getClass('TaskManager')->count(array('hostID'=>$hostids,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())))) throw new Exception(_('There is a host in a tasking'));
        $success = array();
        array_map(function(&$Host) use ($taskTypeID,$taskName,$shutdown,$debug,$deploySnapins,$isGroupTask,$username,$passreset,$sessionjoin,$wol,&$success) {
            if (!$Host->isValid()) return;
            $success[] = $Host->createImagePackage($taskTypeID,$taskName,$shutdown, $debug,$deploySnapins,$isGroupTask,$_SESSION['FOG_USERNAME'],$passreset,$sessionjoin,$wol);
            unset($Host);
        },(array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))));
        return $success;
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
