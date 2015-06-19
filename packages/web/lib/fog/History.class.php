<?php
class History extends FOGController {
    // Table
    public $databaseTable = 'history';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'hID',
        'info' => 'hText',
        'createdBy' => 'hUser',
        'createdTime' => 'hTime',
        'ip' => 'hIP',
    );
}
