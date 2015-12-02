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
        'isEnabled' => 'sEnabled',
        'toReplicate' => 'sReplicate',
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
    public function destroy($field = 'id') {
        $this->getClass('SnapinJobManager')->destroy(array('id'=>$this->getSubObjectIDs('SnapinTask',array('snapinID'=>$this->get('id')),'jobID')));
        $this->getClass('SnapinTaskManager')->destroy(array('snapinID'=>$this->get('id')));
        $this->getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$this->get('id')));
        $this->getClass('SnapinAssociationManager')->destroy(array('snapinID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save($mainObject = true) {
        if ($mainObject) parent::save();
        switch ($this->get('id')) {
        case 0:
        case null:
        case false:
        case '0':
        case '':
            $this->destroy();
            throw new Exception(_('Snapin ID was not set, or unable to be created'));
            break;
        case ($this->isLoaded('hosts')):
            $DBHostIDs = $this->getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                $this->getClass('SnapinAssociationManager')->destroy(array('snapinID'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                $DBHostIDs = $this->getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            $Hosts = $this->getClass('HostManager')->find(array('id'=>array_diff((array)$this->get('hosts'),(array)$DBHostIDs)));
            foreach ((array)$Hosts AS $i => &$Host) {
                if (!$Host->isValid()) {
                    $Host->destroy();
                    continue;
                }
                $this->getClass('SnapinAssociation')
                    ->set('hostID',$Host->get('id'))
                    ->set('snapinID',$this->get('id'))
                    ->save();
                unset($Host);
            }
            unset($Hosts,$DBHostIDs);
        case ($this->isLoaded('storageGroups')):
            $DBGroupIDs = $this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID');
            $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('storageGroups'));
            if (count($RemoveGroupIDs)) {
                $this->getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$this->get('id'),'storageGroupID'=>$RemoveGroupIDs));
                $DBGroupIDs = $this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID');
                unset($RemoveGroupIDs);
            }
            $Groups = $this->getClass('StorageGroupManager')->find(array('id'=>array_diff((array)$this->get('storageGroups'),(array)$DBGroupIDs)));
            foreach ((array)$Groups AS $i => &$Group) {
                if (!$Group->isValid()) {
                    $Group->destroy();
                    continue;
                }
                $this->getClass('SnapinGroupAssociation')
                    ->set('snapinID',$this->get('id'))
                    ->set('storageGroupID',$Group->get('id'))
                    ->save();
                unset($Group);
            }
            unset($Groups,$DBGroupIDs);
        }
        return $this;
    }
    public function deleteFile() {
        if ($this->get('protected')) throw new Exception($this->foglang['ProtectedSnapin']);
        foreach ((array)$this->getClass('StorageNodeManager')->find(array('storageGroupID'=>$this->get('storageGroups'),'isEnabled'=>1)) AS $i => &$StorageNode) {
            if (!$StorageNode->isValid()) continue;
            $this->FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!$this->FOGFTP->connect()) {
                $this->FOGFTP->close();
                continue;
            }
            $snapinfiles = $this->FOGFTP->nlist($StorageNode->get('snapinpath'));
            $snapinfile = preg_grep(sprintf('#%s#',$this->get('file')),$snapinfiles);
            if (!count($snapinfile)) continue;
            $delete = sprintf('/%s/%s',trim($StorageNode->get('snapinpath'),'/'),$this->get('file'));
            $this->FOGFTP
                ->delete($delete)
                ->close();
            unset($StorageNode);
        }
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
    public function getPrimaryGroup($groupID) {
        if (!$this->getClass('SnapinGroupAssociationManager')->count(array('snapinID'=>$this->get('id'),'primary'=>1)) && $groupID == @min($this->getSubObjectIDs('StorageGroup','','id'))) {
            $this->setPrimaryGroup($groupID);
            return true;
        }
        return (bool)$this->getClass('SnapinGroupAssociation',@min($this->getSubObjectIDs('SnapinGroupAssociation',array('storageGroupID'=>$groupID,'snapinID'=>$this->get('id')),'id')))->getPrimary();
    }
    public function setPrimaryGroup($groupID) {
        $this->getClass('SnapinGroupAssociationManager')->update(array('snapinID'=>$this->get('id'),'storageGroupID'=>array_diff((array)$this->get('storageGroups'),(array)$groupID)),'',array('primary'=>0));
        $this->getClass('SnapinGroupAssociationManager')->update(array('snapinID'=>$this->get('id'),'storageGroupID'=>$groupID),'',array('primary'=>1));
    }
    protected function loadHosts() {
        if ($this->get('id')) $this->set('hosts',$this->getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('hosts'));
            $this->set('hostsnotinme',$this->getSubObjectIDs('Host',$find,'',true));
            unset($find);
        }
    }
    protected function loadStorageGroups() {
        if ($this->get('id')) {
            $this->set('storageGroups',$this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID'));
            if (!count($this->get('storageGroups'))) $this->set('storageGroups',(array)@min($this->getSubObjectIDs('StorageGroup','','id')));
        }
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
