<?php
class AddBroadcastAddresses extends Hook {
    public $name = 'AddBroadcastAddresses';
    public $description = 'Add the broadcast addresses to use WOL with';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'wolbroadcast';
    public function AddBCaddr($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $arguments['broadcast'] = array_merge((array)$arguments['broadcast'],(array)$this->getSubObjectIDs('Wolbroadcast','','broadcast'));
    }
}
$AddBroadcastAddresses = new AddBroadcastAddresses();
$HookManager->register('BROADCAST_ADDR',array($AddBroadcastAddresses,'AddBCaddr'));
