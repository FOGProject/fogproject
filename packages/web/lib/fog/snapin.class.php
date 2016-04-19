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
        'shutdown' => 'sShutdown',
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
        self::getClass('SnapinJobManager')->destroy(array('id'=>self::getSubObjectIDs('SnapinTask',array('snapinID'=>$this->get('id')),'jobID')));
        self::getClass('SnapinTaskManager')->destroy(array('snapinID'=>$this->get('id')));
        self::getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$this->get('id')));
        self::getClass('SnapinAssociationManager')->destroy(array('snapinID'=>$this->get('id')));
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
            $DBHostIDs = self::getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID');
            $ValidHostIDs = self::getSubObjectIDs('Host');
            $notValid = array_diff((array)$DBHostIDs,(array)$ValidHostIDs);
            if (count($notValid)) self::getClass('SnapinAssociationManager')->destroy(array('hostID'=>$notValid));
            unset($ValidHostIDs,$notValid);
            $DBHostIDs = self::getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                self::getClass('SnapinAssociationManager')->destroy(array('snapinID'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                $DBHostIDs = self::getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            array_map(function(&$Host) {
                if (!$Host->isValid()) return;
                self::getClass('SnapinAssociation')
                    ->set('hostID',$Host->get('id'))
                    ->set('snapinID',$this->get('id'))
                    ->save();
                unset($Host);
            },(array)self::getClass('HostManager')->find(array('id'=>array_diff((array)$this->get('hosts'),(array)$DBHostIDs))));
            unset($DBHostIDs);
        case ($this->isLoaded('storageGroups')):
            $DBGroupIDs = self::getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID');
            $ValidHostIDs = self::getSubObjectIDs('StorageGroup');
            $notValid = array_diff((array)$DBGroupIDs,(array)$ValidHostIDs);
            if (count($notValid)) self::getClass('SnapinGroupAssociationManager')->destroy(array('storageGroupID'=>$notValid));
            unset($ValidHostIDs,$notValid);
            $DBGroupIDs = self::getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID');
            $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('storageGroups'));
            if (count($RemoveGroupIDs)) {
                self::getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$this->get('id'),'storageGroupID'=>$RemoveGroupIDs));
                $DBGroupIDs = self::getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID');
                unset($RemoveGroupIDs);
            }
            array_map(function(&$Group) {
                if (!$Group->isValid()) return;
                self::getClass('SnapinGroupAssociation')
                    ->set('snapinID',$this->get('id'))
                    ->set('storageGroupID',$Group->get('id'))
                    ->save();
                unset($Group);
            },(array)self::getClass('StorageGroupManager')->find(array('id'=>array_diff((array)$this->get('storageGroups'),(array)$DBGroupIDs))));
            unset($DBGroupIDs);
        }
        return $this;
    }
    public function deleteFile() {
        if ($this->get('protected')) throw new Exception(self::$foglang['ProtectedSnapin']);
        array_map(function(&$StorageNode) {
            if (!$StorageNode->isValid()) return;
            self::$FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!self::$FOGFTP->connect()) return;
            $snapinfiles = self::$FOGFTP->nlist($StorageNode->get('snapinpath'));
            $snapinfile = preg_grep(sprintf('#%s#',$this->get('file')),$snapinfiles);
            if (!count($snapinfile)) return;
            $delete = sprintf('/%s/%s',trim($StorageNode->get('snapinpath'),'/'),$this->get('file'));
            self::$FOGFTP
                ->delete($delete)
                ->close();
            unset($StorageNode);
        },(array)self::getClass('StorageNodeManager')->find(array('storageGroupID'=>$this->get('storageGroups'),'isEnabled'=>1)));
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
        if (!count($this->get('storageGroups'))) $this->set('storageGroups',(array)@min(self::getSubObjectIDs('StorageGroup')));
        $Group = array_map(function(&$id) {
            if ($this->getPrimaryGroup($id)) return self::getClass('StorageGroup',$id);
        },(array)$this->get('storageGroups'));
        $Group = array_shift($Group);
        if ($Group instanceof StorageGroup && $Group->isValid()) return $Group;
        return self::getClass('StorageGroup',@min($this->get('storageGroups')));
    }
    public function getPrimaryGroup($groupID) {
        if (!self::getClass('SnapinGroupAssociationManager')->count(array('snapinID'=>$this->get('id'),'primary'=>1)) && $groupID == @min(self::getSubObjectIDs('StorageGroup'))) {
            $this->setPrimaryGroup($groupID);
            return true;
        }
        return (bool)self::getClass('SnapinGroupAssociation',@min(self::getSubObjectIDs('SnapinGroupAssociation',array('storageGroupID'=>$groupID,'snapinID'=>$this->get('id')),'id')))->getPrimary();
    }
    public function setPrimaryGroup($groupID) {
        self::getClass('SnapinGroupAssociationManager')->update(array('snapinID'=>$this->get('id'),'storageGroupID'=>array_diff((array)$this->get('storageGroups'),(array)$groupID)),'',array('primary'=>0));
        self::getClass('SnapinGroupAssociationManager')->update(array('snapinID'=>$this->get('id'),'storageGroupID'=>$groupID),'',array('primary'=>1));
    }
    protected function loadHosts() {
        if ($this->get('id')) $this->set('hosts',self::getSubObjectIDs('SnapinAssociation',array('snapinID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('hosts'));
            $this->set('hostsnotinme',self::getSubObjectIDs('Host',$find,'',true));
            unset($find);
        }
    }
    protected function loadStorageGroups() {
        if ($this->get('id')) {
            $this->set('storageGroups',self::getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID'));
            if (!count($this->get('storageGroups'))) $this->set('storageGroups',(array)@min(self::getSubObjectIDs('StorageGroup','','id')));
        }
    }
    protected function loadStorageGroupsnotinme() {
        if ($this->get('id')) $this->set('storageGroups',self::getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$this->get('id')),'storageGroupID'));
        if ($this->get('id')) {
            $find = array('id'=>$this->get('storageGroups'));
            $this->set('storageGroupsnotinme',self::getSubObjectIDs('StorageGroup',$find,'',true));
            unset($find);
        }
    }
    protected function loadPath() {
        if ($this->get('id')) $this->set('path',$this->get('file'));
        return $this;
    }
}
