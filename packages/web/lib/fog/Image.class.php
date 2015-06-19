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
    );
    // Overrides
    private function loadHosts() {
        if (!$this->isLoaded('hosts') && $this->get('id')) {
            $this->set('hosts',array_unique($this->getClass('HostManager')->find(array('imageID' => $this->get('id')),'','','','','','','id')));
            $this->set('hostsnotinme',array_unique($this->getClass('HostManager')->find(array('imageID' => $this->get('id')),'','','','','',true,'id')));
        }
        return $this;
    }
    private function loadGroups() {
        if (!$this->isLoaded('storageGroups') && $this->get('id')) {
            $StorageGroupIDs = array_unique($this->getClass('ImageAssociationManager')->find(array('imageID' => $this->get('id')),'','','','','','','storageGroupID'));
            if (!count($StorageGroupIDs)) {
                foreach($this->getClass('StorageGroupManager')->find() AS $Group) {
                    if ($Group->isValid()) {
                        $StorageGroupIDs = $Group->get('id');
                        break;
                    }
                }
            }
            $this->set('storageGroups',$StorageGroupIDs);
        }
        return $this;
    }
    public function get($key = '') {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
        else if ($this->key($key) == 'storageGroups') $this->loadGroups();
        return parent::get($key);
    }
    public function set($key, $value) {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) {
            $this->loadHosts();
            foreach((array)$value AS $Host) $newValue[] = ($Host instanceof Host ? $Host : $this->getClass('Host',$Host));
            $value = (array)$newValue;
        } else if ($this->key($key) == 'storageGroups') {
            $this->loadGroups();
            foreach((array)$value AS $Group) $newValue[] = ($Group instanceof StorageGroup ? $Group : $this->getClass('StorageGroup',$Group));
            $value = (array)$newValue;
        }
        // Set
        return parent::set($key, $value);
    }
    public function add($key, $value) {
        if ($this->key($key) == 'hosts' && !($value instanceof Host)) {
            $this->loadHosts();
            $value = $this->getClass('Host',$value);
        } else if ($this->key($key) == 'storageGroups' && !($value instanceof StorageGroup)) {
            $this->loadGroups();
            $value = $this->getClass('StorageGroup',$value);
        }
        // Add
        return parent::add($key, $value);
    }
    public function remove($key, $object) {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
        else if ($this->key($key) == 'storageGroups') $this->loadGroups();
        // Remove
        return parent::remove($key, $object);
    }
    public function save() {
        parent::save();
        if ($this->isLoaded('hosts')) {
            // Unset all hosts
            foreach($this->getClass('HostManager')->find(array('imageID' => $this->get('id'))) AS $Host) {
                if(($Host instanceof Host) && $Host->isValid()) $Host->set('imageID', 0)->save();
            }
            // Reset the hosts necessary
            foreach ((array)$this->get('hosts') AS $Host) {
                if (($Host instanceof Host) && $Host->isValid()) $Host->set('imageID', $this->get('id'))->save();
            }
        }
        if ($this->isLoaded('storageGroups')) {
            // Remove old rows
            $this->getClass('ImageAssociationManager')->destroy(array('imageID' => $this->get('id')));
            // Create Assoc
            foreach((array)$this->get('storageGroups') AS $Group) {
                if (($Group instanceof StorageGroup) && $Group->isValid()) {
                    $this->getClass('ImageAssociation')
                        ->set('imageID',$this->get('id'))
                        ->set('storageGroupID',$Group->get('id'))
                        ->save();
                }
            }
        }
        return $this;
    }
    public function load($field = 'id') {
        parent::load($field);
        foreach(get_class_methods($this) AS $method) {
            if (strlen($method) > 5 && strpos($method,'load')) $this->$method();
        }
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS $item) $this->add('hosts', $item);
        // Return
        return $this;
    }
    public function addGroup($addArray) {
        // Add
        foreach((array)$addArray AS $item) $this->add('storageGroups',$item);
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        // Iterate array (or other as array)
        foreach((array)$removeArray AS $remove) $this->remove('hosts', ($remove instanceof Host ? $remove : $this->getClass('Host',(int)$remove)));
        // Return
        return $this;
    }
    public function removeGroup($removeArray) {
        // Iterate array (or other as array)
        foreach((array)$removeArray AS $remove) {
            if (count($this->get('storageGroups')) > 1) $this->remove('storageGroups', ($remove instanceof StorageGroup ? $remove : $this->getClass('StorageGrup',(int)$remove)));
        }
        // Return
        return $this;
    }
    public function getStorageGroup() {
        $StorageGroup = current((array)$this->get('storageGroups'));
        if (!$StorageGroup || ($StorageGroup instanceof StorageGroup && !$StorageGroup->isValid())) {
            foreach ($this->getClass('StorageGroupManager')->find() AS $SG) {
                if ($SG->isValid()) {
                    $this->add('storageGroups',$SG);
                    break;
                }
            }
            $StorageGroup = $SG;
        }
        return $StorageGroup;
    }
    public function getOS() {return $this->getClass('OS',$this->get('osID'));}
        public function getImageType() {return $this->getClass('ImageType',$this->get('imageTypeID'));}
    public function getImagePartitionType() {
        if ($this->get('imagePartitionTypeID')) $IPT = $this->getClass('ImagePartitionType',$this->get('imagePartitionTypeID'));
        else $IPT = $this->getClass('ImagePartitionType',1);
        return $IPT;
    }
    public function deleteFile() {
        if ($this->get('protected')) throw new Exception($this->foglang['ProtectedImage']);
        $SN = $this->getStorageGroup()->getMasterStorageNode();
        $SNME = ($SN && $SN->get('isEnabled') == '1' ? true : false);
        if (!$SNME)	throw new Exception($this->foglang['NoMasterNode']);
        $ftphost = $this->FOGCore->resolveHostname($SN->get('ip'));
        $ftpuser = $SN->get('user');
        $ftppass = $SN->get('pass');
        $ftproot = rtrim($SN->get('path'),'/').'/'.$this->get('path');
        $this->FOGFTP
            ->set('host',$ftphost)
            ->set('username',$ftpuser)
            ->set('password',$ftppass)
            ->connect();
        if(!$this->FOGFTP->delete($ftproot)) throw new Exception($this->foglang['FailedDeleteImage']);
    }
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
