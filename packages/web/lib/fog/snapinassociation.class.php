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
        return self::getClass('Host',$this->get('hostID'));
    }
    public function getSnapin() {
        return self::getClass('Snapin',$this->get('snapinID'));
    }
}
