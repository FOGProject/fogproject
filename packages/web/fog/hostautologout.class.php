<?php
class HostAutoLogout extends FOGController {
    protected $databaseTable = 'hostAutoLogOut';
    protected $databaseFields = array(
        'id' => 'haloID',
        'hostID' => 'haloHostID',
        'time' => 'haloTime',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'time',
    );
}
