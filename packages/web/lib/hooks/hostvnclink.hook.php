<?php
class HostVNCLink extends Hook {
    public $name = 'HostVNCLink';
    public $description = 'Adds a "VNC" link to the Host Lists';
    public $author = 'Blackout';
    public $active = false;
    public $port = 5800;
    public function HostData($arguments) {
        $arguments['templates'][9] = sprintf('<a href="vnc://%s:%d" target="_blank" title="%s: ${host_name}">VNC</a>', '${host_name}', $this->port,_('Open VNC connection to'));
        $arguments['attributes'][9] = array('class' => 'c');
    }
    public function HostTableHeader($arguments) {
        $arguments['headerData'][9] = 'VNC';
    }
}
$HostVNCLink = new HostVNCLink();
$HookManager->register('HOST_DATA',array($HostVNCLink,'HostData'));
$HookManager->register('HOST_HEADER_DATA',array($HostVNCLink,'HostTableHeader'));
