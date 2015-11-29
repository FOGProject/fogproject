<?php
class ChangeTableHeader extends Hook {
    public $name = 'ChangeTableHeader';
    public $description = 'Remove & add table header columns';
    public $author = 'Blackout';
    public $active = false;
    public function HostTableHeader($arguments) {
        $arguments['headerData'][3] = 'Chicken Sandwiches';
    }
}
$HookManager->register('HOST_HEADER_DATA',array(new ChangeTableHeader(),'HostTableHeader'));
