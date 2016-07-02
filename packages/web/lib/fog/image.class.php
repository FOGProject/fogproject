<?php
class Image extends FOGController {
    protected $databaseTable = 'images';
    protected $databaseFields = array(
        'id'=>'imageID',
        'name'=>'imageName',
        'description'=>'imageDesc',
        'path'=>'imagePath',
        'createdTime'=>'imageDateTime',
        'createdBy'=>'imageCreateBy',
        'building'=>'imageBuilding',
        'size'=>'imageSize',
        'imageTypeID'=>'imageTypeID',
        'imagePartitionTypeID'=>'imagePartitionTypeID',
        'osID'=>'imageOSID',
        'size'=>'imageSize',
        'deployed'=>'imageLastDeploy',
        'format'=>'imageFormat',
        'magnet'=>'imageMagnetUri',
        'protected'=>'imageProtect',
        'compress'=>'imageCompress',
        'isEnabled'=>'imageEnabled',
        'toReplicate'=>'imageReplicate',
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
        self::getClass('HostManager')->update(array('imageID'=>$this-get('id')),'',array('imageID'=>'0'));
        self::getClass('ImageAssociationManager')->destroy(array('imageID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save() {
        parent::save();
        switch (true) {
        case ($this->isLoaded('hosts')):
            $DBHostIDs = self::getSubObjectIDs('Host',array('imageID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                self::getClass('HostManager')->update(array('imageID'=>$this->get('id'),'id'=>$RemoveHostIDs),'',array('imageID'=>'0'));
                $DBHostIDs = self::getSubObjectIDs('Host',array('imageID'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            $DBHostIDs = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
            self::getClass('HostManager')->update(array('id'=>$DBHostIDs),'',array('imageID'=>$this->get('id')));
            unset($DBHostIDs,$RemoveHostIDs);
        case ($this->isLoaded('storageGroups')):
            $DBGroupIDs = self::getSubObjectIDs('ImageAssociation',array('imageID'=>$this->get('id')),'storageGroupID');
            $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('storageGroups'));
            if (count($RemoveGroupIDs)) {
                self::getClass('ImageAssociationManager')->destroy(array('imageID'=>$this->get('id'),'storageGroupID'=>$RemoveGroupIDs));
                $DBGroupIDs = self::getSubObjectIDs('ImageAssociation',array('imageID'=>$this->get('id')),'storageGroupID');
                unset($RemoveGroupIDs);
            }
            $primaryGroupIDs = self::getSubObjectIDs('ImageAssociation',array('imageID'=>$this->get('id'),'primary'=>'1'));
            $insert_fields = array('imageID','storageGroupID','primary');
            $insert_values = array();
            $DBGroupIDs = array_diff((array)$this->get('storageGroups'),(array)$DBGroupIDs);
            array_walk($DBGroupIDs,function(&$groupID,$index) use (&$insert_values,$primaryGroupIDs) {
                $insert_values[] = array($this->get('id'),$groupID,in_array($groupID,$primaryGroupIDs) ? '1' : '0');
            });
            if (count($insert_values) > 0) self::getClass('ImageAssociationManager')->insert_batch($insert_fields,$insert_values);
        }
        return $this;
    }
    public function deleteFile() {
        if ($this->get('protected')) throw new Exception(self::$foglang['ProtectedImage']);
        array_map(function(&$StorageNode) {
            if (!$StorageNode->isValid()) return;
            $delete = sprintf('/%s/%s',trim($StorageNode->get('ftppath'),'/'),$this->get('path'));
            self::$FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!self::$FOGFTP->connect()) return;
            self::$FOGFTP
                ->delete($delete)
                ->close();
            unset($StorageNode);
        },(array)self::getClass('StorageNodeManager')->find(array('storageGroupID'=>$this->get('storageGroups'),'isEnabled'=>1)));
    }
    protected function loadHosts() {
        $this->set('hosts',self::getSubObjectIDs('Host',array('imageID'=>$this->get('id'))));
    }
    public function addHost($addArray) {
        return $this->addRemItem('hosts',(array)$addArray,'merge');
    }
    public function removeHost($removeArray) {
        return $this->addRemItem('hosts',(array)$removeArray,'diff');
    }
    protected function loadHostsnotinme() {
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme',self::getSubObjectIDs('Host',$find,'',true));
    }
    protected function loadStorageGroups() {
        $this->set('storageGroups',(array)self::getSubObjectIDs('ImageAssociation',array('imageID'=>$this->get('id')),'storageGroupID'));
    }
    public function addGroup($addArray) {
        return $this->addRemItem('storageGroups',(array)$addArray,'merge');
    }
    public function removeGroup($removeArray) {
        return $this->addRemItem('storageGroups',(array)$removeArray,'diff');
    }
    protected function loadStorageGroupsnotinme() {
        $find = array('id'=>$this->get('storageGroups'));
        $this->set('storageGroupsnotinme',self::getSubObjectIDs('StorageGroup',$find,'',true));
    }
    public function getStorageGroup() {
        if (!count($this->get('storageGroups'))) $this->set('storageGroups',(array)@min(self::getSubObjectIDs('StorageGroup')));
        $primaryGroup = array_filter(array_map(function(&$val) {
            if ($this->getPrimaryGroup($val)) return $val;
            return false;
        },(array)$this->get('storageGroups')));
        if (count($primaryGroup) < 1) $primaryGroup = (array)@min((array)$this->get('storageGroups'));
        return self::getClass('StorageGroup',array_shift($primaryGroup));
    }
    public function getOS() {
        return self::getClass('OS',$this->get('osID'));
    }
    public function getImageType() {
        return self::getClass('ImageType',$this->get('imageTypeID'));
    }
    public function getImagePartitionType() {
        return self::getClass('ImagePartitionType',($this->get('imagePartitionTypeID') > 0 ? $this->get('imagePartitionTypeID') : 1));
    }
    public function getPartitionType() {
        return $this->getImagePartitionType()->get('type');
    }
    public function getPrimaryGroup($groupID) {
        $primaryCount = self::getClass('ImageAssociationManager')->count(array('imageID'=>$this->get('id'),'primary'=>'1'));
        if ($primaryCount < 1) $primaryCount = self::getClass('ImageAssociationManager')->count(array('imageID'=>$this->get('id')));
        if ($primaryCount < 1) $this->setPrimaryGroup(@min(self::getSubObjectIDs('StorageGroup')));
        $assocID = @min(self::getSubObjectIDs('ImageAssociation',array('storageGroupID'=>$groupID,'imageID'=>$this->get('id'))));
        return self::getClass('ImageAssociation',$assocID)->isPrimary();
    }
    public function setPrimaryGroup($groupID) {
        self::getClass('ImageAssociationManager')->update(array('imageID'=>$this->get('id'),'storageGroupID'=>array_diff((array)$this->get('storageGroups'),(array)$groupID)),'',array('primary'=>'0'));
        self::getClass('ImageAssociationManager')->update(array('imageID'=>$this->get('id'),'storageGroupID'=>$groupID),'',array('primary'=>'1'));
    }
}
