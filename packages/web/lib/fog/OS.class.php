<?php
class OS extends FOGController {
    protected $databaseTable = 'os';
    protected $databaseFields = array(
        'id' => 'osID',
        'name' => 'osName',
        'description' => 'osDescription'
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
}
