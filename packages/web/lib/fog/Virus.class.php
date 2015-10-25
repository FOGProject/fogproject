<?php
class Virus extends FOGController {
    protected $databaseTable = 'virus';
    protected $databaseFields = array(
        'id' => 'vID',
        'name' => 'vName',
        'hostMAC' => 'vHostMAC',
        'file' => 'vOrigFile',
        'date' => 'vDateTime',
        'mode' => 'vMode',
        'anon2' => 'vAnon2',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'hostMAC',
        'file',
        'date',
    );
}
