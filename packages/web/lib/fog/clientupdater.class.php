<?php
class ClientUpdater extends FOGController {
    protected $databaseTable = 'clientUpdates';
    protected $databaseFields = array(
        'id' => 'cuID',
        'name' => 'cuName',
        'md5' => 'cuMD5',
        'type' => 'cuType',
        'file' => 'cuFile',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'file',
    );
}
