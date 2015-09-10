<?php
class Location extends FOGController {
	// Table
	public $databaseTable = 'location';
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'lID',
		'name' => 'lName',
		'description' => 'lDesc',
		'createdBy' => 'lCreatedBy',
		'createdTime' => 'lCreatedTime',
		'storageGroupID' => 'lStorageGroupID',
		'storageNodeID' => 'lStorageNodeID',
		'tftp' => 'lTftpEnabled',
	);
    // Allow setting / getting of these additional fields
    public $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
	public $databaseFieldsRequired = array(
		'name',
		'storageGroupID',
	);
    // Overides
    private function loadHosts() {
        if (!$this->isLoaded(hosts) && $this->get(id)) {
            $HostIDs = $this->getClass(LocationAssociationManager)->find(array(locationID=>$this->get(id)),'','','','','','','hostID');
            $this->set(hosts,$HostIDs);
            $this->set(hostsnotinme,$this->getClass(HostManager)->find(array(id=>$HostIDs),'','','','','',true,'id'));
        }
        return $this;
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
        if (!$this->get(id)) parent::save();
        if ($this->isLoaded(hosts)) {
            // Remove old rows
            $this->getClass(LocationAssociationManager)->destroy(array(groupID=>$this->get(id)));
            // Create assoc
            foreach ($this->get(hosts) AS $i => &$Host) $this->getClass(LocationAssociation)->set(hostID,$Host)->set(locationID,$this->get(id))->save();
            unset($Host);
        }
        return parent::save();
    }
	public function destroy($field = 'id') {
		$this->getClass(LocationAssociationManager)->find(array(locationID=>$this->get(id)));
		return parent::destroy($field);
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
	public function getStorageGroup() {
		return $this->getClass(StorageGroup,$this->get(storageGroupID));
	}
	public function getStorageNode() {
		return $this->getClass(StorageNode,$this->get(storageNodeID));
	}
}
