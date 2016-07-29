<?php
class UserCleanup extends FOGController {
    protected $databaseTable = 'userCleanup';
    protected $databaseFields = array(
        'id'		=> 'ucID',
        'name'		=> 'ucName',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
}
