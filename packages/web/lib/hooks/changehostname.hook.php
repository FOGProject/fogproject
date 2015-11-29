<?php
class ChangeHostname extends Hook {
    public $name = 'ChangeHostname';
    public $description = 'Appends "Chicken-" to all hostnames ';
    public $author = 'Blackout';
    public $active = false;
    public function HostData($arguments) {
        foreach ($arguments['data'] AS $i => &$data) $arguments['data'][$i]['host_name'] = sprintf('%s-%s','Chicken',$data['host_name']);
    }
}
$HookManager->register('HOST_DATA', array(new ChangeHostname(), 'HostData'));
