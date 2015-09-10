<?php
class Image extends FOGController {
    // Table
    public $databaseTable = 'images';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'imageID',
        'name' => 'imageName',
        'description' => 'imageDesc',
        'path' => 'imagePath',
        'createdTime' => 'imageDateTime',
        'createdBy' => 'imageCreateBy',
        'building' => 'imageBuilding',
        'size' => 'imageSize',
        'imageTypeID' => 'imageTypeID',
        'imagePartitionTypeID' => 'imagePartitionTypeID',
        'osID' => 'imageOSID',
        'size' => 'imageSize',
        'deployed' => 'imageLastDeploy',
        'format' => 'imageFormat',
        'magnet' => 'imageMagnetUri',
        'protected' => 'imageProtect',
        'compress' => 'imageCompress',
    );
    // Additional Fields
    public $additionalFields = array(
        'hosts',
        'hostsnotinme',
        'storageGroups',
        'storageGroupsnotinme',
    );
    public function isValid() {
        return $this->get(id) && $this->get(name);
    }
    // Overrides
    private function loadHosts() {
        if (!$this->isLoaded(hosts) && $this->get(id)) {
            $this->set(hosts,array_unique($this->getClass(HostManager)->find(array(imageID=>$this->get(id)),'','','','','','','id')));
            $this->set(hostsnotinme,array_unique($this->getClass(HostManager)->find(array(imageID=>$this->get(id)),'','','','','',true,'id')));
        }
        return $this;
    }
    private function loadGroups() {
        if (!$this->isLoaded(storageGroups) && $this->get(id)) {
            $StorageGroupIDs = array_unique($this->getClass(ImageAssociationManager)->find(array(imageID=>$this->get(id)),'','','','','','','storageGroupID'));
            $this->set(storageGroups,$StorageGroupIDs);
            $this->set(storageGroupsnotinme,array_unique($this->getClass(StorageGroupManager)->find(array(id=>$StorageGroupIDs),'','','','','',true,'id')));
        }
        return $this;
    }
    public function get($key = '') {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
        else if (in_array($this->key($key),array('storageGroups','storageGroupsnotinme'))) $this->loadGroups();
        return parent::get($key);
    }
    public function set($key, $value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        else if ($this->key($key) == 'storageGroups')$this->loadGroups();
        // Set
        return parent::set($key, $value);
    }
    public function remove($key, $object) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        else if ($this->key($key) == 'storageGroups') $this->loadGroups();
        // Remove
        return parent::remove($key, $object);
    }
    public function save() {
        if (!$this->get(id)) parent::save();
        if ($this->isLoaded(hosts)) {
            // Unset Hosts with image
            $this->getClass(HostManager)->update(array(imageID=>$this->get(id)),'',array(imageID=>0));
            // Set these hosts
            $this->getClass(HostManager)->update(array(id=>$this->get(hosts)),'',array(imageID=>$this->get(id)));
        }
        if ($this->isLoaded(storageGroups)) {
            // Remove old rows
            $this->getClass(ImageAssociationManager)->destroy(array(imageID=>$this->get(id)));
            // Create Assoc
            foreach($this->get(storageGroups) AS $i => &$Group) {
                $this->getClass(ImageAssociation)
                    ->set(imageID,$this->get(id))
                    ->set(storageGroupID,$Group)
                    ->save();
            }
            unset($Group);
        }
        return parent::save();
    }
    public function load($field = 'id') {
        parent::load($field);
        $methods = get_class_methods($this);
        foreach($methods AS $i => &$method) {
            if (strlen($method) > 5 && strpos($method,'load')) $this->$method();
        }
        unset($method);
    }
    public function add($key,$value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        else if ($this->key($key) == 'storageGroups') $this->loadGroups();
        return parent::add($key,$value);
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS $i => &$item) $this->add(hosts, $item);
        unset($item);
        // Return
        return $this;
    }
    public function addGroup($addArray) {
        // Add
        foreach((array)$addArray AS $i => &$item) $this->add(storageGroups,$item);
        unset($item);
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        // Iterate array (or other as array)
        foreach((array)$removeArray AS $i => &$remove) $this->remove(hosts, $remove);
        unset($remove);
        // Return
        return $this;
    }
    public function removeGroup($removeArray) {
        // Iterate array (or other as array)
        foreach((array)$removeArray AS $i => &$remove) $this->remove(storageGroups,$remove);
        unset($remove);
        // Return
        return $this;
    }
    public function getStorageGroup() {
        $StorageGroup = $this->getClass(StorageGroup,current((array)$this->get(storageGroups)));
        if (!$StorageGroup->isValid()) {
            $this->add(storageGroups,@min($this->getClass(StorageGroupManager)->find('','','','','','','','id')));
            $StorageGroup = $this->getClass(StorageGroup,current((array)$this->get(storageGroups)));
        }
        return $StorageGroup;
    }
    public function getOS() {
        return $this->getClass(OS,$this->get(osID));
    }
    public function getImageType() {
        return $this->getClass(ImageType,$this->get(imageTypeID));
    }
    public function getImagePartitionType() {
        if ($this->get(imagePartitionTypeID)) $IPT = $this->getClass(ImagePartitionType,$this->get(imagePartitionTypeID));
        else $IPT = $this->getClass(ImagePartitionType,1);
        return $IPT;
    }
    public function deleteFile() {
        if ($this->get('protected')) throw new Exception($this->foglang[ProtectedImage]);
        $SN = $this->getStorageGroup()->getMasterStorageNode();
        $SNME = ($SN->get(isEnabled) == 1);
        if (!$SNME)	throw new Exception($this->foglang[NoMasterNode]);
        $ftphost = $SN->get(ip);
        $ftpuser = $SN->get(user);
        $ftppass = $SN->get(pass);
        $ftproot = rtrim($SN->get(ftppath),'/').'/'.$this->get(path);
        $this->FOGFTP
            ->set(host,$ftphost)
            ->set(username,$ftpuser)
            ->set(password,$ftppass)
            ->connect();
        if(!$this->FOGFTP->delete($ftproot)) throw new Exception($this->foglang[FailedDeleteImage]);
    }
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
