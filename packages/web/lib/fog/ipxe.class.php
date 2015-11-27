<?php
class iPXE extends FOGController {
    protected $databaseTable = 'ipxeTable';
    protected $databaseFields = array(
        'id' => 'ipxeID',
        'product' => 'ipxeProduct',
        'manufacturer' => 'ipxeManufacturer',
        'mac' => 'ipxeMAC',
        'success' => 'ipxeSuccess',
        'failure' => 'ipxeFailure',
        'file' => 'ipxeFilename',
        'version' => 'ipxeVersion',
    );
}
