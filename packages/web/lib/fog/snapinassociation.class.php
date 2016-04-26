<?php
class SnapinAssociation extends FOGController {
    protected $databaseTable = 'snapinAssoc';
    protected $databaseFields = array(
        'id' => 'saID',
        'hostID' => 'saHostID',
        'snapinID' => 'saSnapinID'
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'snapinID',
    );
    public function getHost() {
        return static::getClass('Host',$this->get('hostID'));
    }
    public function getSnapin() {
        return static::getClass('Snapin',$this->get('snapinID'));
    }
}
