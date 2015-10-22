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
        $this->getClass('GroupAssociationManager')->destroy(array('groupID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save() {
        parent::save();
        if ($this->isLoaded('hosts')) {
            $DBHostIDs = $this->getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                $this->getClass('GroupAssociationManager')->destroy(array('groupID'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                unset($RemoveHostIDs);
            }
            $Hosts = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
            foreach ((array)$Hosts AS $i => &$Host) {
                $this->getClass('GroupAssociation')
                    ->set('hostID',$Host)
                    ->set('groupID',$this->get('id'))
                    ->save();
            }
            unset($Host);
        }
        return $this;
    }
    public function getHostCount() {
        return $this->getClass('GroupAssociationManager')->count(array('groupID'=>$this->get('id')));
    }
    public function addPrinter($printerAdd, $printerDel, $level = 0) {
        $this->getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('printerLevel'=>$level));
        foreach ((array)$this->get('hosts') AS $i => &$HostID) {
            $Host = $this->getClass('Host',$HostID);
            if ($printerAdd) $Host->addPrinter($printerAdd);
            if ($printerDel) $Host->removePrinter($printerDel);
            $Host->save();
        }
        unset($HostID);
        return $this;
    }
    public function addSnapin($addArray) {
        foreach ((array)$this->get('hosts') AS $i => &$HostID) $this->getClass('Host',$HostID)->addSnapin($addArray)->save();
        unset($Host);
        return $this;
    }
    public function removeSnapin($removeArray) {
        foreach ((array)$this->get('hosts') AS $i => &$HostID) $this->getClass('Host',$HostID)->removeSnapin($removeArray)->save();
        unset($Host);
        return $this;
    }
    public function addModule($addArray) {
        foreach ((array)$this->get('hosts') AS $i => &$HostID) $this->getClass('Host',$HostID)->addModule($addArray)->save();
        unset($Host);
        return $this;
    }
    public function removeModule($removeArray) {
        foreach ((array)$this->get('hosts') AS $i => &$HostID) $this->getClass('Host',$HostID)->removeModule($removeArray)->save();
        unset($Host);
        return $this;
    }
    public function addHost($addArray) {
        $Hosts = array_unique(array_diff((array)$addArray,(array)$this->get('hosts')));
        if (count($Hosts)) {
            $Hosts = array_merge((array)$this->get('hosts'),(array)$Hosts);
            $this->set('hosts',$Hosts);
        }
        return $this;
    }
    public function removeHost($removeArray) {
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
    public function addImage($imageID) {
        if (!$imageID) throw new Exception(_('Select an image'));
        if (!$this->getClass('Image',$imageID)->isValid()) throw new Exception(_('Select a valid image'));
        if ($this->getClass('TaskManager')->count(array('hostID'=>$this->get('hosts'),'stateID'=>array(0,1,2,3)))) throw new Exception(_('There is a host in a tasking'));
        $this->getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('imageID'=>$imageID));
        return $this;
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false) {
        if ($this->getClass('TaskManager')->count(array('hostID'=>$this->get('hosts'),'stateID'=>array(0,1,2,3)))) throw new Exception(_('One or more hosts are currently in a tasking'));
        $success = array();
        foreach ((array)$this->get('hosts') AS $i => &$HostID) $success[] = $this->getClass('Host',$HostID)->createImagePackage($taskTypeID,$taskName,$shutdown, $debug,$deploySnapins,$isGroupTask,$_SESSION['FOG_USERNAME'],$passreset,$sessionjoin);
        unset($HostID);
        return $success;
    }
    public function setAD($useAD, $domain, $ou, $user, $pass, $legacy) {
        $this->getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('useAD'=>$useAD,'ADDomain'=>trim($domain),'ADOU'=>trim($ou),'ADUser'=>trim($user),'ADPass'=>$pass,'ADPassLegacy'=>$legacy));
        return $this;
    }
    public function doMembersHaveUniformImages() {
        $images = array_unique($this->getSubObjectIDs('Host',array('id'=>$this->get('hosts')),'imageID'));
        return (count($images) == 1);
    }
    public function updateDefault($printerid) {
        foreach ((array)$this->get('hosts') AS $i => &$HostID) $this->getClass('Host',$HostID)->updateDefault($printerid,true);
        unset($Host);
        return $this;
    }
    protected function loadHosts() {
        if ($this->get('id')) $this->set('hosts',$this->getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('hosts'));
            $this->set('hostsnotinme',$this->getSubObjectIDs('Host',$find,'',true));
            unset($find);
        }
    }
}
