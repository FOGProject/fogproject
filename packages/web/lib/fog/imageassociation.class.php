<?php
class ImageAssociation extends FOGController {
    protected $databaseTable = 'imageGroupAssoc';
    protected $databaseFields = array(
        'id' => 'igaID',
        'imageID' => 'igaImageID',
        'storageGroupID' => 'igaStorageGroupID',
        'primary' => 'igaPrimary',
    );
    protected $databaseFieldsRequired = array(
        'imageID',
        'storageGroupID',
    );
    public function getImage() {
        return self::getClass('Image',$this->get('imageID'));
    }
    public function getStorageGroup() {
        return self::getClass('StorageGroup',$this->get('storageGroupID'));
    }
    public function getPrimary() {
        return (bool)$this->get('primary');
    }
}
