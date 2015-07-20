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
    // Overides
    private function loadHosts() {
        if (!$this->isLoaded(hosts) && $this->get(id)) {
            $HostIDs = $this->getClass(GroupAssociationManager)->find(array(groupID=>$this->get(id)),'','','','','','','hostID');
            $this->set(hosts,$HostIDs);
            $this->set(hostsnotinme,$this->getClass(HostManager)->find(array(id=>$HostIDs),'','','','','',true,'id'));
        }
        return $this;
    }
    public function getHostCount() {
        return $this->getClass(GroupAssociationManager)->count(array(groupID=>$this->get(id)));
    }
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
    public function load($field = 'id') {
        parent::load($field);
        $methods = get_class_methods($this);
        foreach($methods AS $i => &$method) {
            if (strlen($method) > 5 && strpos($method,'load'))
                $this->$method();
        }
        unset($method);
    }
    public function save() {
        parent::save();
        if ($this->isLoaded(hosts)) {
            // Remove old rows
            $this->getClass(GroupAssociationManager)->destroy(array(groupID=>$this->get(id)));
            // Create assoc
            foreach ($this->get(hosts) AS $i => &$Host) $this->getClass(GroupAssociation)->set(hostID,$Host)->set(groupID,$this->get(id))->save();
            unset($Host);
        }
        return $this;
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
    public function setAD($useAD, $domain, $ou, $user, $pass) {
        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts)));
        foreach($Hosts AS $i => &$Host) $Host->setAD($useAD,$domain,$ou,$user,$pass);
        unset($Host);
        return $this;
    }
    public function addPrinter($printAdd,$printDel,$level = 0) {
        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts)));
        foreach($Hosts AS $i => &$Host) $Host->set(printerLevel,$level)->addPrinter($printAdd)->removePrinter($printDel)->save();
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
        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts)));
        foreach($Hosts AS $i => &$Host) {
            if ($Host->get(task)->isValid()) throw new Exception(_('There is a host in tasking'));
            $Host->set(imageID,$imageID)->save();
        }
        unset($Host);
        return $this;
    }
    public function destroy($field = 'id') {
        // Remove All Host Associations
        $this->getClass(GroupAssociationManager)->destroy(array(groupID=>$this->get(id)));
        // Return
        return parent::destroy($field);
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false) {

        $Hosts = $this->getClass(HostManager)->find(array(id=>$this->get(hosts)));
        foreach ($Hosts AS $i => &$Host) if (!$Host->get(pending)) $success[] = $Host->createImagePackage($taskTypeID,$taskName,$shutdown,$debug,$deploySnapins,$isGroupTask,$_SESSION[FOG_USERNAME],$passreset,$sessionjoin);
        return $success;
    }
}
