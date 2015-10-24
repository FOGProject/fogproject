<?php
class Image extends FOGController {
    protected $databaseTable = 'images';
    protected $databaseFields = array(
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
    protected $databaseFieldsRequired = array(
        'name',
        'path',
        'imageTypeID',
        'osID',
    );
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
        'storageGroups',
        'storageGroupsnotinme',
    );
    public function destroy($field = 'id') {
        $this->getClass('HostManager')->update(array('imageID'=>$this->get('id')),'',array('imageID'=>0));
        $this->getClass('ImageAssociationManager')->destroy(array('imageID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save() {
        parent::save();
        switch (true) {
        case ($this->isLoaded('hosts')):
            $DBHostIDs = $this->getSubObjectIDs('Host',array('imageID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                $this->getClass('HostManager')->update(array('imageID'=>$this->get('id')),'',array('imageID'=>0));
                $DBHostIDs = $this->getSubObjectIDs('Host',array('imageID'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            $Hosts = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
            $this->getClass('HostManager')->update(array('id'=>$Hosts),'',array('imageID'=>$this->get('id')));
            unset($Hosts,$DBHostIDs);
        case ($this->isLoaded('storageGroups')):
            $DBGroupIDs = $this->getSubObjectIDs('ImageAssociation',array('imageID'=>$this->get('id')),'storageGroupID');
            $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('storageGroups'));
            if (count($RemoveGroupIDs)) {
                $this->getClass('ImageAssociationManager')->destroy(array('imageID'=>$this->get('id'),'storageGroupID'=>$RemoveGroupIDs));
                $DBGroupIDs = $this->getSubObjectIDs('ImageAssociation',array('imageID'=>$this->get('id')),'storageGroupID');
                unset($RemoveGroupIDs);
            }
            $Groups = array_diff((array)$this->get('storageGroups'),(array)$DBGroupIDs);
            foreach ((array)$Groups AS $i => &$Group) {
                $this->getClass('ImageAssociation')
                    ->set('imageID',$this->get('id'))
                    ->set('storageGroupID',$Group)
                    ->save();
            }
            unset($Group,$Groups,$DBGroupIDs);
        }
        return $this;
    }
    public function deleteFile() {
        if ($this->get('protected')) throw new Exception($this->foglang['ProtectedImage']);
        if (!$this->getStorageGroup()->getMasterStorageNode()->get('isEnabled')) throw new Exception($this->foglang['NoMasterNode']);
        $delete = rtrim($this->getStorageGroup()->getMasterStorageNode()->get('ftppath'),'/').DIRECTORY_SEPARATOR.$this->get('path');
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
        $Hosts = array_unique(array_diff((array)$addArray,(array)$this->get('hosts')));
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
    public function getOS() {
        return $this->getClass('OS',$this->get('osID'));
    }
    public function getImageType() {
        return $this->getClass('ImageType',$this->get('imageTypeID'));
    }
    public function getImagePartitionType() {
        if ($this->get('imagePartitionTypeID')) $IPT = $this->getClass('ImagePartitionType',$this->get('imagePartitionTypeID'));
        else $IPT = $this->getClass('ImagePartitionType',1);
        return $IPT;
    }
    public function getPrimaryGroup($groupID) {
        if (!$this->getClass('ImageAssociationManager')->count(array('imageID'=>$this->get('id'),'primary'=>1)) && $groupID == @min($this->getSubObjectIDs('StorageGroup','','id'))) {
            $this->setPrimaryGroup($groupID);
            return true;
        }
        return (bool)$this->getClass('ImageAssociation',@min($this->getSubObjectIDs('ImageAssociation',array('storageGroupID'=>$groupID,'imageID'=>$this->get('id')),'id')))->getPrimary();
    }
    public function setPrimaryGroup($groupID) {
        $this->getClass('ImageAssociationManager')->update(array('imageID'=>$this->get('id'),'storageGroupID'=>array_diff((array)$this->get('storageGroups'),(array)$groupID)),'',array('primary'=>0));
        $this->getClass('ImageAssociationManager')->update(array('imageID'=>$this->get('id'),'storageGroupID'=>$groupID),'',array('primary'=>1));
    }
    protected function loadHosts() {
        if ($this->get('id')) $this->set('hosts',$this->getSubObjectIDs('Host',array('imageID'=>$this->get('id')),'id'));
    }
    protected function loadHostsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('hosts'));
            $this->set('hostsnotinme',$this->getSubObjectIDs('Host',$find,'',true));
            unset($find);
        }
    }
    protected function loadStorageGroups() {
        if ($this->get('id')) $this->set('storageGroups',$this->getSubObjectIDs('ImageAssociation',array('imageID'=>$this->get('id')),'storageGroupID'));
        if (!count($this->get('storageGroups'))) $this->set('storageGroups',(array)@min($this->getSubObjectIDs('StorageGroup','','id')));
    }
    protected function loadStorageGroupsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('storageGroups'));
            $this->set('storageGroupsnotinme',$this->getSubObjectIDs('StorageGroup',$find,'',true));
            unset($find);
        }
    }
}
