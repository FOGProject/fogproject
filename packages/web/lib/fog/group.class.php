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
    public function save($mainObject = true) {
        if ($mainObject) parent::save();
        switch ($this->get('id')) {
        case 0:
        case null:
        case false:
        case '0':
        case '':
            $this->destroy();
            throw new Exception(_('Group ID was not set, or unable to be created'));
            break;
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
            $hostids = $this->get('hosts');
            array_map(function(&$Host) {
                if (!$Host->isValid()) return;
                self::getClass('GroupAssociation')
                    ->set('hostID',$Host->get('id'))
                    ->set('groupID',$this->get('id'))
                    ->save();
                unset($Host);
            },(array)self::getClass('HostManager')->find(array('id'=>array_diff((array)$hostids,(array)$DBHostIDs))));
            unset($DBHostIDs,$RemoveHostIDs);
            break;
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
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('printerLevel'=>$level));
        array_map(function(&$Host) use ($printerAdd,$printerDel,$level) {
            if (!$Host->isValid()) return;
            if ($printerAdd) $Host->addPrinter($printerAdd);
            if ($printerDel) $Host->removePrinter($printerDel);
            $Host->save();
            unset($Host);
        },(array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))));
        return $this;
    }
    public function addSnapin($addArray) {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        array_map(function(&$Host) use ($addArray) {
            if (!$Host->isValid()) return;
            $Host->addSnapin($addArray)->save();
            unset($Host);
        },(array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))));
        return $this;
    }
    public function removeSnapin($removeArray) {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        array_map(function(&$Host) use ($removeArray) {
            if (!$Host->isValid()) return;
            $Host->removeSnapin($removeArray)->save();
            unset($Host);
        },(array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))));
        return $this;
    }
    public function addModule($addArray) {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        array_map(function(&$Host) use ($addArray) {
            if (!$Host->isValid()) return;
            $Host->addModule($addArray)->save();
            unset($Host);
        },(array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))));
        return $this;
    }
    public function removeModule($removeArray) {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        array_map(function(&$Host) use ($removeArray) {
            if (!$Host->isValid()) return;
            $Host->removeModule($removeArray)->save();
            unset($Host);
        },(array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))));
        return $this;
    }
    public function addHost($addArray) {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        $this->set('hosts',array_unique(array_merge((array)$this->get('hosts'),(array)$addArray)));
        return $this;
    }
    public function removeHost($removeArray) {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
    public function addImage($imageID) {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        if (!$imageID) throw new Exception(_('Select an image'));
        if (!self::getClass('Image',$imageID)->isValid()) throw new Exception(_('Select a valid image'));
        if (self::getClass('TaskManager')->count(array('hostID'=>$this->get('hosts'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())))) throw new Exception(_('There is a host in a tasking'));
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('imageID'=>$imageID));
        return $this;
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false,$wol = false) {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
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
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        $pass = trim($this->encryptpw($pass));
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('useAD'=>$useAD,'ADDomain'=>trim($domain),'ADOU'=>trim($ou),'ADUser'=>trim($user),'ADPass'=>$pass,'ADPassLegacy'=>$legacy,'enforce'=>$enforce));
        return $this;
    }
    public function doMembersHaveUniformImages() {
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        $imageID = self::getSubObjectIDs('Host',array('id'=>$this->get('hosts')),'imageID','','','','','array_count_values');
        $imageID = count($imageID) == 1 ? array_shift($imageID) : 0;
        return $imageID == $this->getHostCount();
    }
    public function updateDefault($printerid) {
        if (!$this->get('id')) return;
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        array_map(function(&$Host) use ($printerid) {
            if (!$Host->isValid()) return;
            $Host->updateDefault($printerid,true);
            unset($Host);
        },(array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))));
        return $this;
    }
    protected function loadHosts() {
        if (!$this->get('id')) return;
        $this->set('hosts',self::getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        if (!$this->get('id')) return;
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme',self::getSubObjectIDs('Host',$find,'',true));
        unset($find);
    }
}
