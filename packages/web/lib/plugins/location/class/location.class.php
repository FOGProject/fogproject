<?php
class Location extends FOGController {
    protected $databaseTable = 'location';
    protected $databaseFields = array(
        'id' => 'lID',
        'name' => 'lName',
        'description' => 'lDesc',
        'createdBy' => 'lCreatedBy',
        'createdTime' => 'lCreatedTime',
        'storageGroupID' => 'lStorageGroupID',
        'storageNodeID' => 'lStorageNodeID',
        'tftp' => 'lTftpEnabled',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'storageGroupID',
    );
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    public function destroy($field = 'id') {
        self::getClass('LocationAssociationManager')->destroy(array('locationID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save() {
        parent::save();
        switch (true) {
        case ($this->isLoaded('hostss')):
            $DBHostIDs = self::getSubObjectIDs('LocationAssociation',array('locationID'=>$this->get('id')),'hostID');
            $ValidHostIDs = self::getSubObjectIDs('Host');
            $notValid = array_diff((array)$DBHostIDs,(array)$ValidHostIDs);
            if (count($notValid)) self::getClass('LocationAssociationManager')->destroy(array('hostID'=>$notValid));
            unset($ValidHostIDs,$DBHostIDs);
            $DBHostIDs = self::getSubObjectIDs('LocationAssociation',array('locationID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                self::getClass('LocationAssociationManager')->destroy(array('locationID'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                $DBHostIDs = self::getSubObjectIDs('LocationAssociation',array('locationID'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            $insert_fields = array('locationID','hostID');
            $insert_values = array();
            $DBHostIDs = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
            array_walk($DBHostIDs,function(&$hostID,$index) use (&$insert_values) {
                $insert_values[] = array($this->get('id'),$hostID);
            });
            if (count($insert_values) > 0) self::getClass('LocationAssociationManager')->insert_batch($insert_fields,$insert_values);
            unset($DBHostIDs,$RemoveHostIDs);
        }
        return $this;
    }
    public function addHost($addArray) {
        $this->set('hosts',array_unique(array_merge((array)$this->get('hosts'),(array)$addArray)));
        return $this;
    }
    public function removeHost($removeArray) {
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
    public function getStorageGroup() {
        return self::getClass('StorageGroup',$this->get('storageGroupID'));
    }
    public function getStorageNode() {
        return self::getClass('StorageNode',$this->get('storageNodeID'));
    }
    protected function loadHosts() {
        $this->set('hosts',self::getSubObjectIDs('LocationAssociation',array('locationID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme',self::getSubObjectIDs('Host',$find,'id',true));
        unset($find);
    }
}
