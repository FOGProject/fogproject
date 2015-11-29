<?php
class RemoveIPAddressColumn extends Hook {
    public $name = 'RemoveIPAddressColumn';
    public $description = 'Removes the "IP Address" column from Host Lists';
    public $author = 'Blackout';
    public $active = false;
    public function HostTableHeader($arguments) {
        unset($arguments['headerData'][4]);
    }
    public function HostData($arguments) {
        unset($arguments['templates'][4]);
    }
}
$RemoveIPAddressColumn = new RemoveIPAddressColumn();
$HookManager->register('HOST_HEADER_DATA',array($RemoveIPAddressColumn,'HostTableHeader'));
$HookManager->register('HOST_DATA',array($RemoveIPAddressColumn,'HostData'));
