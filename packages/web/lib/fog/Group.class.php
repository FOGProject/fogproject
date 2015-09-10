<?php
class Group extends FOGController {
    // Table
    public $databaseTable = 'groups';
    // Name -> Database field name
    public $databaseFields = array(
        'id'		=> 'groupID',
        'name'		=> 'groupName',
        'description'	=> 'groupDesc',
        'createdBy'	=> 'groupCreateBy',
        'createdTime'	=> 'groupDateTime',
        'building'	=> 'groupBuilding',
        'kernel'	=> 'groupKernel',
        'kernelArgs'	=> 'groupKernelArgs',
        'kernelDevice'	=> 'groupPrimaryDisk',
    );
    // Allow setting / getting of these additional fields
    public $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    // Required database fields
    public $databaseFieldsRequired = array(
        'id',
        'name',
    );
    public $databaseFieldClassRelationships = array();
    // Load the items
    public function load($field = 'id') {
        parent::load($field);
        $methods = get_class_methods($this);
        foreach($methods AS $i => &$method) {
            if (strlen($method) > 5 && strpos($method,'load')) $this->$method();
        }
        unset($method);
    }
    private function loadHosts() {
        if (!$this->isLoaded(hosts) && $this->get(id)) {
            $HostIDs = $this->getClass(GroupAssociationManager)->find(array(groupID=>$this->get(id)),'','','','','','','hostID');
            $PendHostsIDs = $this->getClass(HostManager)->find(array(pending=>1),'','','','','','','id');
            $HostsIDs = array_diff((array)$HostIDs,(array)$PendHostsIDs);
            $this->set(hosts,$HostsIDs);
            $this->set(hostsnotinme,$this->getClass(HostManager)->find(array(id=>$HostIDs),'','','','','',true,'id'));
        }
        return $this;
    }
    // Overrides
    public function get($key = '') {
        if (in_array($this->key($key),array(hosts,hostsnotinme))) $this->loadHosts();
        return parent::get($key);
    }
    public function set($key,$value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        return parent::set($key,$value);
    }
    public function add($key,$value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        return parent::add($key,$value);
    }
    public function remove($key,$value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        return parent::remove($key,$value);
    }
    public function save() {
        if (!$this->get(id)) parent::save();
        if ($this->isLoaded(hosts)) {
            // Remove old rows
            $this->getClass(GroupAssociationManager)->destroy(array(groupID=>$this->get(id)));
            // Create assoc
            foreach ($this->get(hosts) AS $i => &$Host) $this->getClass(GroupAssociation)->set(hostID,$Host)->set(groupID,$this->get(id))->save();
            unset($Host);
        }
        return parent::save();
    }
    // Custom Functions
    public function getHostCount() {
        return $this->getClass(GroupAssociationManager)->count(array(groupID=>$this->get(id)));
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS $i => &$item) $this->add(hosts,$item);
        unset($item);
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        // Iterate array (or other as array)
        foreach ((array)$removeArray AS $i => &$remove) $this->remove(hosts,$remove);
        unset($remove);
        // Return
        return $this;
    }
    public function addSnapin($snapArray) {
        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts)));
        foreach($Hosts AS $i => &$Host) $Host->addSnapin($snapArray)->save();
        unset($Host);
        return $this;
    }
    public function removeSnapin($snapArray) {
        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts)));
        foreach($Hosts AS $i => &$Host) $Host->removeSnapin($snapArray)->save();
        unset($Host);
        return $this;
    }
    public function setAD($useAD,$domain,$ou,$user,$pass,$legacy) {
        $this->getClass(HostManager)->update(array(id=>$this->get(hosts)),'',array(useAD=>$useAD,ADDomain=>trim($domain),ADOU=>trim($ou),ADUser=>trim($user),ADPass=>$pass,ADPassLegacy=>$legacy));
        return $this;
    }
    public function addPrinter($printAdd,$printDel,$level = 0) {
        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts)));
        foreach($Hosts AS $i => &$Host) {
            $Host->set(printerLevel,$level);
            if ($printAdd) $Host->addPrinter($printAdd);
            if ($printDel) $Host->removePrinter($printDel);
            $Host->save();
        }
        unset($Host);
        return $this;
    }
    // Custom Variables
    public function doMembersHaveUniformImages() {
        $images = array_unique($this->getClass(HostManager)->find(array(id=>$this->get(hosts)),'','','','','','','imageID'));
        return (count($images) == 1);
    }
    public function updateDefault($printerid) {
        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts)));
        foreach($Hosts AS $i => &$Host) $Host->updateDefault($printerid,true);
        unset($Host);
        return $this;
    }
    public function addImage($imageID) {
        if (!$imageID) throw new Exception(_('Select an image'));
        if (!$this->getClass(Image,$imageID)->isValid()) throw new Exception(_('Select a valid image'));
        if ($this->getClass(TaskManager)->count(array(hostID=>$this->get(hosts),stateID=>array(0,1,2,3)))) throw new Exception(_('There is a host in a tasking'));
        $this->getClass(HostManager)->update(array(id=>$this->get(hosts)),'',array(imageID=>$imageID));
        return $this;
    }
    public function destroy($field = 'id') {
        // Remove All Host Associations
        $this->getClass(GroupAssociationManager)->destroy(array(groupID=>$this->get(id)));
        // Return
        return parent::destroy($field);
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false) {
        if ($this->getClass(TaskManager)->count(array(hostID=>$this->get(hosts),stateID=>array(0,1,2,3)))) throw new Exception(_('One or more hosts are currently in a tasking'));
        $success = array();
        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts),pending=>array('',false,null,0)));
        foreach ($Hosts AS $i => &$Host) $success[] = $Host->createImagePackage($taskTypeID,$taskName,$shutdown,$debug,$deploySnapins,$isGroupTask,$_SESSION[FOG_USERNAME],$passreset,$sessionjoin);
        unset($Host);
        return $success;
    }
}
