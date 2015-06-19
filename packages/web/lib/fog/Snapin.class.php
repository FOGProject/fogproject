<?php
class Snapin extends FOGController {
    // Table
    public $databaseTable = 'snapins';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'sID',
        'name' => 'sName',
        'description' => 'sDesc',
        'file' => 'sFilePath',
        'args' => 'sArgs',
        'createdTime' => 'sCreateDate',
        'createdBy' => 'sCreator',
        'reboot' => 'sReboot',
        'runWith' => 'sRunWith',
        'runWithArgs' => 'sRunWithArgs',
        'protected' => 'snapinProtect',
        'anon3' => 'sAnon3'
    );
    // Allow setting / getting of these additional fields
    public $additionalFields = array(
        'hosts',
        'hostsnotinme',
        'storageGroups',
    );
    // Overides
    private function loadHosts() {
        if (!$this->isLoaded('hosts') && $this->get('id')) {
            $HostIDs = $this->getClass('SnapinAssociationManager')->find(array('snapinID' => $this->get('id')),'','','','','','','hostID');
            $this->set('hosts',$HostIDs);
            $this->set('hostsnotinme',$this->getClass('HostManager')->find(array('id' => $HostIDs),'','','','','',true,'id'));
        }
        return $this;
    }
    private function loadGroups() {
        if (!$this->isLoaded('storageGroups') && $this->get('id')) {
            $StorageGroupIDs = array_unique($this->getClass('SnapinGroupAssociationManager')->find(array('snapinID' => $this->get('id')),'','','','','','','storageGroupID'));
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
        if (in_array($this->key($key),array('hosts','hostsnotinme')) && !($value instanceof Host)) {
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
            // Remove old rows
            $this->getClass('SnapinAssociationManager')->destroy(array('snapinID' => $this->get('id')));
            // Create assoc
            foreach ((array)$this->get('hosts') AS $Host) {
                if(($Host instanceof Host) && $Host->isValid()) {
                    $this->getClass('SnapinAssociation')
                        ->set('hostID',$Host->get('id'))
                        ->set('snapinID',$this->get('id'))
                        ->save();
                }
            }
        }
        if ($this->isLoaded('storageGroups')) {
            // Remove old rows
            $this->getClass('SnapinGroupAssociationManager')->destroy(array('snapinID' => $this->get('id')));
            // Create Assoc
            foreach((array)$this->get('storageGroups') AS $Group) {
                if (($Group instanceof StorageGroup) && $Group->isValid()) {
                    $this->getClass('SnapinGroupAssociation')
                        ->set('snapinID', $this->get('id'))
                        ->set('storageGroupID', $Group->get('id'))
                        ->save();
                }
            }
        }
        return $this;
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS $item) $this->add('hosts', $item);
        // Return
        return $this;
    }
    public function load($field = 'id') {
        parent::load($field);
        foreach(get_class_methods($this) AS $method) {
            if (strlen($method) > 5 && strpos($method,'load')) $this->$method();
        }
    }
    public function addGroup($addArray) {
        // Add
        foreach((array)$addArray AS $item)
            $this->add('storageGroups',$item);
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        // Iterate array (or other as array)
        foreach ((array)$removeArray AS $remove) $this->remove('hosts', ($remove instanceof Host ? $remove : $this->getClass('Host',(int)$remove)));
        // Return
        return $this;
    }
    public function removeGroup($removeArray) {
        // Iterate array (or other as array)
        foreach((array)$removeArray AS $remove) $this->remove('storageGroups', ($remove instanceof StorageGroup ? $remove : $this->getClass('StorageGroup',(int)$remove)));
        // Return
        return $this;
    }
    public function getStorageGroup() {
        $StorageGroup = current($this->get('storageGroups'));
        if (!$StorageGroup || ($StorageGroup instanceof StorageGroup && !$StorageGroup->isValid())) {
            foreach ($this->getClass('StorageGroupManager')->find() AS $SG) {
                if ($SG->isValid()) {
                    $this->add('storageGroups',$SG);
                    break;
                }
            }
            $StorageGroup = current($this->get('storageGroups'));
        }
        return $StorageGroup;
    }
    public function destroy($field = 'id') {
        // Remove all associations
        $this->getClass('SnapinAssociationManager')->destroy(array('snapinID' => $this->get('id')));
        foreach($this->getClass('SnapinTaskManager')->find(array('snapinID' => $this->get('id'))) AS $SnapJob) {
            $this->getClass('SnapinJobManager')->destroy(array('jobID' => $SnapJob->get('jobID')));
            $SnapJob->destroy();
        }
        $this->getClass('SnapinGroupAssociationManager')->destroy(array('snapinID' => $this->get('id')));
        // Return
        return parent::destroy($field);
    }
    /** deleteFile()
        This function just deletes the file(s) via FTP.
        Only used if the user checks the Add File? checkbox.
     */
    public function deleteFile() {
        $SG = $this->getStorageGroup();
        if ($SG && $SG->isValid()) {
            $SN = $this->getStorageGroup()->getMasterStorageNode();
            $SNME = ($SN && $SN->get('isEnabled') == '1' ? true : false);
            if (!$SNME) throw new Exception($this->foglang['NoMasterNode']);
            $ftphost = $this->FOGCore->resolveHostname($SN->get('ip'));
            $ftpuser = $SN->get('user');
            $ftppass = $SN->get('pass');
            $ftproot = rtrim($SN->get('snapinpath'),'/').'/'.$this->get('file');
            $this->FOGFTP
                ->set('host',$ftphost)
                ->set('username',$ftpuser)
                ->set('password',$ftppass)
                ->connect();
            if(!$this->FOGFTP->delete($ftproot)) throw new Exception($this->foglang['FailedDelete']);
        }
    }
}
