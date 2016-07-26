<?php
class LocationAssociation extends FOGController {
    protected $databaseTable = 'locationAssoc';
    protected $databaseFields = array(
        'id' => 'laID',
        'locationID' => 'laLocationID',
        'hostID' => 'laHostID',
    );
    protected $databaseFieldsRequired = array(
        'locationID',
        'hostID',
    );
    public function getLocation() {
        return self::getClass('Location',$this->get('locationID'));
    }
    public function getHost() {
        return self::getClass('Host',$this->get('hostID'));
    }
    public function getStorageGroup() {
        return $this->getLocation()->getStorageGroup();
    }
    public function getStorageNode() {
        return $this->getLocation()->getStorageNode();
    }
    public function isTFTP() {
        $Location = $this->getLocation();
        if (!$Location->isValid()) return;
        return (bool)$Location->get('tftp');
    }
}
