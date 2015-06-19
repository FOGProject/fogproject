<?php
class SnapinAssociation extends FOGController {
    // Table
    public $databaseTable = 'snapinAssoc';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'saID',
        'hostID' => 'saHostID',
        'snapinID' => 'saSnapinID'
    );
    // Custom
    public function getHost() {return $this->getClass('Host',$this->get('hostID'));}
    public function getSnapin() {return $this->getClass('Snapin',$this->get('snapinID'));}
}
