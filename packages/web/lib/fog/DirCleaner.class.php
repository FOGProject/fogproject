<?php
class DirCleaner extends FOGController {
    protected $databaseTable = 'dirCleaner';
    protected $databaseFields = array(
        'id' => 'dcID',
        'path' => 'dcPath',
    );
    protected $databaseFieldsRequired = array(
        'path',
    );
}
