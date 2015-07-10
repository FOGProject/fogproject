<?php
class Pushbullet extends FOGController {
    public $databaseTable = 'pushbullet';
    public $databaseFields = array(
        'id'     => 'pID',
        'token'  => 'pToken',
        'name'   => 'pName',
        'email'  => 'pEmail',
    );
}
