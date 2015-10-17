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
        'storageGroupsnotinme',
        'path',
    );
    public function get($key = '') {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        return parent::get($key);
    }
    public function set($key,$value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        return parent::set($key,$value);
    }
    public function add($key,$value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        return parent::add($key,$value);
    }
    public function remove($key,$value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        return parent::remove($key,$value);
    }
    public function destroy($field = 'id') {
        $this->getClass('SnapinJobManager')->destroy(array('id'=>$this->getSubObjectIDs('SnapinTask',array('snapinID'=>$this->get('id')),'jobID')));
        $this->getClass('SnapinTaskManager')->destroy(array('snapinID'=>$this->get('id')));
        $this->getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$this->get('id')));
        $this->getClass('SnapinAssociation')->destroy(array('snapinID'=>$this->get('id')));
        return parent::destroy($field);
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
                unset($Host,$Hosts,$DBHostIDs);
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
                unset($Group,$Groups,$DBGroupIDs);
        }
        return $this;
    }
    public function deleteFile() {
        if ($this->get('protected')) throw new Exception($this->foglang['ProtectedSnapin']);
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
        $Hosts = array_unique(array_merge((array)$addArray,(array)$this->get('hosts')));
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
    public function addGroup($addArray) {
        $Groups = array_unique(array_merge((array)$addArray,(array)$this->get('storageGroups')));
        if (count($Groups)) {
            $Hosts = array_merge((array)$this->get('storageGroups'),(array)$Groups);
            $this->set('storageGroups',$Groups);
        }
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
    protected function loadStorageGroups() {
        if ($this->get('id')) $this->set('storageGroups',$this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID'));
        if (!count($this->get('storageGroups'))) $this->set('storageGroups',(array)@min($this->getSubObjectIDs('StorageGroup','','id')));
    }
    protected function loadStorageGroupsnotinme() {
        if ($this->get('id')) $this->set('storageGroups',$this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID'));
        if ($this->get('id')) {
            $find = array('id'=>$this->get('storageGroups'));
            $this->set('storageGroupsnotinme',$this->getSubObjectIDs('StorageGroup',$find,'',true));
            unset($find);
        }
    }
    protected function loadPath() {
        if ($this->get('id')) $this->set('path',$this->get('file'));
        return $this;
    }
}
