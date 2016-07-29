<?php
class Pushbullet extends FOGController {
    protected $databaseTable = 'pushbullet';
    protected $databaseFields = array(
        'id'     => 'pID',
        'token'  => 'pToken',
        'name'   => 'pName',
        'email'  => 'pEmail',
    );
    protected $databaseFieldsRequired = array(
        'token',
        'name',
        'email',
    );
}
