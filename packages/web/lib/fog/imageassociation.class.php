<?php
class ImageAssociation extends FOGController
{
    protected $databaseTable = 'imageGroupAssoc';
    protected $databaseFields = array(
        'id' => 'igaID',
        'imageID' => 'igaImageID',
        'storagegroupID' => 'igaStorageGroupID',
        'primary' => 'igaPrimary',
    );
    protected $databaseFieldsRequired = array(
        'imageID',
        'storagegroupID',
    );
    public function getImage()
    {
        return self::getClass('Image', $this->get('imageID'));
    }
    public function getStorageGroup()
    {
        return self::getClass('StorageGroup', $this->get('storagegroupID'));
    }
    public function isPrimary()
    {
        if (!$this->isValid()) {
            return false;
        }
        return (bool)$this->get('primary') > 0;
    }
}
