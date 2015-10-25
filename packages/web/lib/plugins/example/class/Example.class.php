<?php
class Example extends FOGController {
    protected $databaseTable = 'example';
    protected $databaseFields = array(
        'id' => 'eID',
        'name' => 'eName',
        'other' => 'eOther',
        'hostID' => 'eHostID',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'hostID',
    );
}
