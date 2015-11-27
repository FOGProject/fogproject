<?php
class Wolbroadcast extends FOGController {
    protected $databaseTable = 'wolbroadcast';
    protected $databaseFields = array(
        'id' => 'wbID',
        'name' => 'wbName',
        'description' => 'wbDesc',
        'broadcast' => 'wbBroadcast',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'broadcast',
    );
}
