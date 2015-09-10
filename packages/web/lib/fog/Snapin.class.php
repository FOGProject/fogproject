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
        'path',
    );
    private function loadPath() {
        $this->set(path,$this->get('file'));
    }
    // Overides
    private function loadHosts() {
        if (!$this->isLoaded(hosts) && $this->get(id)) {
            $HostIDs = $this->getClass(SnapinAssociationManager)->find(array('snapinID' => $this->get(id)),'','','','','','','hostID');
            $this->set(hosts,$HostIDs);
            $this->set(hostsnotinme,$this->getClass(HostManager)->find(array('id' => $HostIDs),'','','','','',true,'id'));
        }
        return $this;
    }
    private function loadGroups() {
        if (!$this->isLoaded(storageGroups) && $this->get(id)) {
            $StorageGroupIDs = array_unique($this->getClass(SnapinGroupAssociationManager)->find(array('snapinID' => $this->get(id)),'','','','','','','storageGroupID'));
            if (!count($StorageGroupIDs)) {
                $Groups = $this->getClass(StorageGroupManager)->find();
                foreach($Groups AS $i => &$Group) {
                    if ($Group->isValid()) {
                        $StorageGroupIDs = $Group->get(id);
                        break;
                    }
                }
                unset($Group);
            }
            $this->set(storageGroups,$StorageGroupIDs);
        }
        return $this;
    }
    public function get($key = '') {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
        else if ($this->key($key) == 'storageGroups') $this->loadGroups();
        return parent::get($key);
    }
    public function set($key, $value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        else if ($this->key($key) == 'storageGroups') $this->loadGroups();
        // Set
        return parent::set($key, $value);
    }
    public function add($key, $value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        else if ($this->key($key) == 'storageGroups') $this->loadGroups();
        // Add
        return parent::add($key, $value);
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
            // Remove old rows
            $this->getClass(SnapinAssociationManager)->destroy(array('snapinID' => $this->get(id)));
            // Create assoc
            foreach ($this->get(hosts) AS $i => &$Host) {
                $this->getClass(SnapinAssociation)
                    ->set(hostID,$Host)
                    ->set(snapinID,$this->get(id))
                    ->save();
            }
            unset($Host);
        }
        if ($this->isLoaded(storageGroups)) {
            // Remove old rows
            $this->getClass(SnapinGroupAssociationManager)->destroy(array('snapinID' => $this->get('id')));
            $Groups = $this->get(storageGroups);
            // Create Assoc
            foreach($Groups AS $i => &$Group) {
                $this->getClass(SnapinGroupAssociation)
                    ->set(snapinID,$this->get(id))
                    ->set(storageGroupID,$Group)
                    ->save();
            }
            unset($Group);
        }
        return parent::save();
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS $i => &$item) $this->add(hosts,$item);
        unset($item);
        // Return
        return $this;
    }
    public function load($field = 'id') {
        parent::load($field);
        $methods = get_class_methods($this);
        foreach ($methods AS $i => &$method) {
            if (strlen($method) > 5 && strpos($method,'load')) $this->$method();
        }
        unset($method);
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
        foreach ((array)$removeArray AS $i => &$remove) $this->remove(hosts,$remove);
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
    public function destroy($field = 'id') {
        // Remove all associations
        $this->getClass(SnapinAssociationManager)->destroy(array(snapinID=>$this->get(id)));
        $ST = $this->getClass(SnapinTaskManager)->find(array(snapinID=>$this->get(id)));
        foreach($ST AS $i => &$SnapJob) {
            $this->getClass(SnapinJobManager)->destroy(array(jobID=>$SnapJob->get(jobID)));
            $SnapJob->destroy();
        }
        unset($SnapJob);
        $this->getClass(SnapinGroupAssociationManager)->destroy(array(snapinID=>$this->get(id)));
        // Return
        return parent::destroy($field);
    }
    /** deleteFile()
        This function just deletes the file(s) via FTP.
        Only used if the user checks the Add File? checkbox.
     */
    public function deleteFile() {
        $SN = $this->getStorageGroup()->getMasterStorageNode();
        $SNME = ($SN && $SN->get(isEnabled) == 1 ? true : false);
        if (!$SNME) throw new Exception($this->foglang[NoMasterNode]);
        $ftphost = $SN->get(ip);
        $ftpuser = $SN->get(user);
        $ftppass = $SN->get(pass);
        $ftproot = rtrim($SN->get(snapinpath),'/').'/'.$this->get('file');
        $this->FOGFTP
            ->set(host,$ftphost)
            ->set(username,$ftpuser)
            ->set(password,$ftppass)
            ->connect();
        if (!$this->FOGFTP->delete($ftproot)) throw new Exception($this->foglang[FailedDelete]);
    }
}
