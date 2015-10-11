<?php
class Snapin extends FOGController {
    protected $databaseTable = 'snapins';
    protected $databaseFields = array(
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
        'anon3' => 'sAnon3',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'file',
    );
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
        'storageGroups',
        'path',
    );
    public function get($key = '') {
        switch ($this->key($key)) {
            case 'hosts':
            case 'hostsnotinme':
                $this->loadHosts();
                break;
            case 'storageGroups':
                $this->loadGroups();
                break;
        }
        return parent::get($key);
    }
    public function set($key,$value) {
        switch ($this->key($key)) {
            case 'hosts':
            case 'hostsnotinme':
                $this->loadHosts();
                break;
            case 'storageGroups':
                $this->loadGroups();
                break;
            case 'path':
                $value = $this->get('file');
                break;
        }
        return parent::set($key,$value);
    }
    public function add($key,$value) {
        switch ($this->key($key)) {
            case 'hosts':
            case 'hostsnotinme':
                $this->loadHosts();
                break;
            case 'storageGroups':
                $this->loadGroups();
                break;
        }
        return parent::add($key,$value);
    }
    public function remove($key,$value) {
        switch ($this->key($key)) {
            case 'hosts':
            case 'hostsnotinme':
                $this->loadHosts();
                break;
            case 'storageGroups':
                $this->loadGroups();
                break;
        }
        return parent::remove($key,$value);
    }
    public function save() {
        parent::save();
        switch (true) {
            case ($this->isLoaded('hosts')):
                $DBHostIDs = $this->getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID');
                $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
                if (count($RemoveHostIDs)) {
                    $this->getClass('SnapinAssociationManager')->destroy(array('snapinID'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                    $DBHostIDs = $this->getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID');
                    unset($RemoveHostIDs);
                }
                $Hosts = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
                foreach ((array)$Hosts AS $i => &$Host) {
                    $this->getClass('SnapinAssociation')
                        ->set('hostID',$Host)
                        ->set('snapinID',$this->get('id'))
                        ->save();
                }
                unset($Host);
                break;
            case ($this->isLoaded('storageGroups')):
                $DBGroupIDs = $this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID');
                $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('storageGroups'));
                if (count($RemoveGroupIDs)) {
                    $this->getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$this->get('id'),'storageGroupID'=>$RemoveGroupIDs));
                    $DBGroupIDs = $this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID');
                    unset($RemoveGroupIDs);
                }
                $Groups = array_diff((array)$this->get('storageGroups'),(array)$DBGroupIDs);
                foreach ((array)$Groups AS $i => &$Group) {
                    $this->getClass('SnapinGroupAssociation')
                        ->set('snapinID',$this->get('id'))
                        ->set('storageGroupID',$Group)
                        ->save();
                }
                unset($Group);
                break;
        }
        return $this;
    }
    public function destroy($field = 'id') {
        $this->getClass('SnapinJobManager')->destroy(array('id'=>$this->getSubObjectIDs('SnapinTask',array('snapinID'=>$this->get('id')),'jobID')));
        $this->getClass('SnapinTaskManager')->destroy(array('snapinID'=>$this->get('id')));
        $this->getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$this->get('id')));
        $this->getClass('SnapinAssociation')->destroy(array('snapinID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function deleteFile() {
        if (!$this->getStorageGroup()->getMasterStorageNode()->get('isEnabled')) throw new Exception($this->foglang['NoMasterNode']);
        $delete = rtrim($this->getStorageGroup()->getMasterStorageNode()->get('snapinpath'),'/').DIRECTORY_SEPARATOR.$this->get('file');
        $this->FOGFTP
            ->set('host',$this->getStorageGroup()->getMasterStorageNode()->get('ip'))
            ->set('username',$this->getStorageGroup()->getMasterStorageNode()->get('username'))
            ->set('password',$this->getStorageGroup()->getMasterStorageNode()->get('password'));
        if (!$this->FOGFTP->connect()) throw new Exception(_('Failed to connect to node'));
        if (!$this->FOGFTP->delete($delete)) {
            $this->FOGFTP->close();
            throw new Exception($this->foglang['FailedDelete']);
        }
        $this->FOGFTP->close();
    }
    public function addHost($addArray) {
        $this->set('hosts',array_unique(array_merge((array)$this->get('hosts'),(array)$addArray)));
        return $this;
    }
    public function removeHost($removeArray) {
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
    public function addGroup($addArray) {
        $this->set('storageGroups',array_unique(array_merge((array)$this->get('storageGroups'),(array)$addArray)));
        return $this;
    }
    public function removeGroup($removeArray) {
        $this->set('storageGroups',array_unique(array_diff((array)$this->get('storageGroups'),(array)$removeArray)));
        return $this;
    }
    public function getStorageGroup() {
        if (!count($this->get('storageGroups'))) $this->set('storageGroups',(array)@min($this->getSubObjectIDs('StorageGroup','','id')));
        return $this->getClass('StorageGroup',@min($this->get('storageGroups')));
    }
    private function loadHosts() {
        if (!$this->isLoaded('hosts') && $this->get('id')) {
            $this->set('hosts',array_unique($this->getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID')));
            $this->set('hostsnotinme',$this->getSubObjectIDs('Host',array('id'=>$this->get('hosts')),'id',true));
        }
        return $this;
    }
    private function loadGroups() {
        if (!$this->isLoaded('storageGroups') && $this->get('id')) {
            $this->set('storageGroups',array_unique($this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID')));
            if (!count($this->get('storageGroups'))) $this->set('storageGroups',(array)@min($this->getSubObjectIDs('StorageGroup','','id')));
        }
        return $this;
    }
}
