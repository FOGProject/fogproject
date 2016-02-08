<?php
class Slack extends FOGController {
    protected $databaseTable = 'slack';
    protected $databaseFields = array(
        'id'     => 'sID',
        'token'  => 'sToken',
        'name' => 'sUsername',
    );
    protected $databaseFieldsRequired = array(
        'token',
        'name',
    );
}
