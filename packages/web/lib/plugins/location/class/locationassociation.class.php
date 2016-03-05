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
        return $this->getClass('Location',$this->get('locationID'));
    }
    public function getHost() {
        return $this->getClass('Host',$this->get('hostID'));
    }
    public function getStorageGroup() {
        $Location = $this->getLocation();
        if (!$Location->isValid()) return;
        return $this->getClass('StorageGroup',$Location->get('storageGroupID'));
    }
    public function getStorageNode() {
        $Location = $this->getLocation();
        if (!$Location->isValid()) return;
        if ($Location->get('storageNodeID')) return $this->getClass('StorageNode',$Location->get('storageNodeID'));
        return $this->getStorageGroup()->getOptimalStorageNode($this->getHost()->get('imageID'));
    }
    public function isTFTP() {
        $Location = $this->getLocation();
        if (!$Location->isValid()) return;
        return (bool)$Location->get('tftp');
    }
}
