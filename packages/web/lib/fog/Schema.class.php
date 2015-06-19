<?php
class Schema extends FOGController {
    // Table
    public $databaseTable = 'schemaVersion';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'vID',
        'version' => 'vValue',
    );
}
