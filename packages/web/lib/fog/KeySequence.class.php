<?php
class KeySequence extends FOGController {
    protected $databaseTable = 'keySequence';
    protected $databaseFields = array(
        'id' => 'ksID',
        'name' => 'ksValue',
        'ascii' => 'ksAscii',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'ascii',
    );
}
