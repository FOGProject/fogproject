<?php
class OUI extends FOGController {
    protected $databaseTable = 'oui';
    protected $databaseFields = array(
        'id' => 'ouiID',
        'prefix' => 'ouiMACPrefix',
        'name' => 'ouiMan',
    );
    protected $databaseFieldsRequired = array(
        'prefix',
        'name',
    );
}
