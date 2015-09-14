<?php
class MACAddressAssociation extends FOGController {
    // Table
    public $databaseTable = 'hostMAC';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'hmID',
        'hostID' => 'hmHostID',
        'mac' => 'hmMAC',
        'description' => 'hmDesc',
        'pending' => 'hmPending',
        'primary' => 'hmPrimary',
        'clientIgnore' => 'hmIgnoreClient',
        'imageIgnore' => 'hmIgnoreImaging',
    );
    // Custom
    public function getHost() {$this->getClass(Host,$this->get(hostID));}
}
