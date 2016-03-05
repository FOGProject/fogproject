<?php
class Capone extends FOGController {
    protected $databaseTable = 'capone';
    protected $databaseFields = array(
        'id' => 'cID',
        'imageID' => 'cImageID',
        'osID' => 'cOSID',
        'key' => 'cKey',
    );
    public function getImage() {
        return $this->getClass('Image',$this->get('imageID'));
    }
    public function getOS() {
        return $this->getClass('OS',$this->get('osID'));
    }
    public function getStorageGroup() {
        return $this->getImage()->getStorageGroup();
    }
    public function getStorageNode() {
        return $this->getStorageGroup()->getOptimalStorageNode($this->get('imageID'));
    }
}
