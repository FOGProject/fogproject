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
        if (!$this->isLoaded('hosts') && $this->get('id')) {
            $HostIDs = $this->getClass(GroupAssociationManager)->find(array('groupID' => $this->get('id')),'','','','','','','hostID');
            $this->set(hosts,$HostIDs);
            $this->set(hostsnotinme,$this->getClass('HostManager')->find(array('id' => $HostIDs),'','','','','',true,'id'));
        }
        return $this;
    }
    public function getHostCount() {return $this->getClass(GroupAssociationManager)->count(array('groupID' => $this->get('id')));}
        public function get($key = '') {
            if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
            return parent::get($key);
        }
    public function load($field = 'id') {
        parent::load($field);
        foreach(get_class_methods($this) AS &$method) {
            if (strlen($method) > 5 && strpos($method,'load'))
                $this->$method();
        }
        unset($method);
    }
    public function add($key,$value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        return parent::add($key,$value);
    }
    public function save() {
        parent::save();
        if ($this->isLoaded(hosts)) {
            // Remove old rows
            $this->getClass(GroupAssociationManager)->destroy(array('groupID' => $this->get('id')));
            // Create assoc
            foreach ($this->get(hosts) AS &$Host) $this->getClass(GroupAssociation)->set(hostID,$Host)->set(groupID,$this->get(id))->save();
            unset($Host);
        }
        return $this;
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS &$item) $this->add(hosts,$item);
        unset($item);
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        // Iterate array (or other as array)
        foreach ((array)$removeArray AS &$remove) $this->remove(hosts,$remove);
        unset($remove);
        // Return
        return $this;
    }
    public function addSnapin($snapArray) {
        foreach($this->getClass(HostManager)->find(array('id' => $this->get(hosts))) AS &$Host) $Host->addSnapin($snapArray)->save();
        unset($Host);
        return $this;
    }
    public function removeSnapin($snapArray) {
        foreach($this->getClass(HostManager)->find(array('id' => $this->get(hosts))) AS &$Host) $Host->removeSnapin($snapArray)->save();
        unset($Host);
        return $this;
    }
    public function setAD($useAD, $domain, $ou, $user, $pass) {
        foreach($this->getClass(HostManager)->find(array('id' => $this->get(hosts))) AS &$Host) $Host->setAD($useAD,$domain,$ou,$user,$pass);
        unset($Host);
        return $this;
    }
    public function addPrinter($printAdd,$printDel,$level = 0) {
        foreach($this->getClass(HostManager)->find(array('id' => $this->get(hosts))) AS &$Host) $Host->set(printerLevel,$level)->addPrinter($printAdd)->removePrinter($printDel)->save();
        unset($Host);
        return $this;
    }
    // Custom Variables
    public function doMembersHaveUniformImages() {
        $images = array_unique($this->getClass(HostManager)->find(array('id' => $this->get(hosts)),'','','','','','','imageID'));
        return (count($images) == 1);
    }
    public function updateDefault($printerid,$onoff) {
        foreach($this->getClass(HostManager)->find(array('id' => $this->get(hosts))) AS &$Host) {
            foreach ($printerid AS &$printer) {
                if ($printer == $onoff) $Host->updateDefault($printer,1);
                else $Host->updateDefault($printer,0);
            }
            unset($printer);
        }
        unset($Host);
        return $this;
    }
    public function addImage($imageID) {
        if (!$imageID) throw new Exception(_('Select an image'));
        foreach($this->getClass(HostManager)->find(array('id' => $this->get(hosts))) AS &$Host) {
            if ($Host->get(task)->isValid()) throw new Exception(_('There is a host in tasking'));
            $Host->set(imageID,$imageID)->save();
        }
        unset($Host);
        return $this;
    }
    public function destroy($field = 'id') {
        // Remove All Host Associations
        $this->getClass(GroupAssociationManager)->destroy(array('groupID' => $this->get(id)));
        // Return
        return parent::destroy($field);
    }
}
