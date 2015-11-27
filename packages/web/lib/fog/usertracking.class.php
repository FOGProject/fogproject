<?php
class UserTracking extends FOGController {
    protected $databaseTable = 'userTracking';
    protected $databaseFields = array(
        'id' => 'utID',
        'hostID' => 'utHostID',
        'username' => 'utUserName',
        'action' => 'utAction',
        'datetime' => 'utDateTime',
        'description' => 'utDesc',
        'date' => 'utDate',
        'anon3' => 'utAnon3',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'username',
    );
}
