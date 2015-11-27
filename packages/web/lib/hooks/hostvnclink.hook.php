<?php
class HostVNCLink extends Hook {
    public $name = 'HostVNCLink';
    public $description = 'Adds a "VNC" link to the Host Lists';
    public $author = 'Blackout';
    public $active = false;
    public $port = 5800;
    public function HostData($arguments) {
        $arguments['templates'][8] = sprintf('<a href="http://%s:%d" target="_blank">VNC</a>', '${host_name}', $this->port);
        $arguments['attributes'][8] = array('class' => 'c');
    }
    public function HostTableHeader($arguments) {
        $arguments['headerData'][8] = 'VNC';
    }
}
// Register hooks with HookManager on desired events
$HookManager->register('HOST_DATA', array(new HostVNCLink(), 'HostData'));
$HookManager->register('HOST_HEADER_DATA', array(new HostVNCLink(), 'HostTableHeader'));
