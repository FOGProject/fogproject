<?php
class MACAddressAssociation extends FOGController {
    protected $databaseTable = 'hostMAC';
    protected $databaseFields = array(
        'id' => 'hmID',
        'hostID' => 'hmHostID',
        'mac' => 'hmMAC',
        'description' => 'hmDesc',
        'pending' => 'hmPending',
        'primary' => 'hmPrimary',
        'clientIgnore' => 'hmIgnoreClient',
        'imageIgnore' => 'hmIgnoreImaging',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'mac',
    );
    public function getHost() {
        return self::getClass(Host,$this->get(hostID));
    }
}
