<?php
class Peer extends FOGController {
    protected $databaseTable = 'peer';
    protected $databaseFields = array(
        'id' => 'id',
        'hash' => 'hash',
        'agent' => 'user_agent',
        'ip' => 'ip_address',
        'key' => 'key',
        'port' => 'port',
    );
}
