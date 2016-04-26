<?php
class SnapinGroupAssociation extends FOGController {
    protected $databaseTable = 'snapinGroupAssoc';
    protected $databaseFields = array(
        'id' => 'sgaID',
        'snapinID' => 'sgaSnapinID',
        'storageGroupID' => 'sgaStorageGroupID',
        'primary' => 'sgaPrimary',
    );
    protected $databaseFieldsRequired = array(
        'snapinID',
        'storageGroupID',
    );
    public function getSnapin() {
        return static::getClass('Snapin',$this->get('snapinID'));
    }
    public function getStorageGroup() {
        return static::getClass('StorageGroup',$this->get('storageGroupID'));
    }
    public function getPrimary() {
        return (bool)$this->get('primary');
    }
}
