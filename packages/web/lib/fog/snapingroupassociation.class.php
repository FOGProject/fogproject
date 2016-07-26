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
        return self::getClass('Snapin',$this->get('snapinID'));
    }
    public function getStorageGroup() {
        return self::getClass('StorageGroup',$this->get('storageGroupID'));
    }
    public function isPrimary() {
        if (!$this->isValid()) return;
        return (bool)$this->get('primary');
    }
}
