<?php
class AddBroadcastAddresses extends Hook {
	public function __construct() {
		parent::__construct();
		$this->name = 'AddBroadcastAddresses';
		$this->description = 'Add the broadcast addresses to use WOL with';
		$this->author = 'Tom Elliott';
		$this->active = true;
		$this->node = 'wolbroadcast';
	}
	public function AddBCaddr($arguments) {
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			$arguments['broadcast'] = array_merge($arguments['broadcast'],$this->getClass('WolbroadcastManager')->find('','','','','','','','broadcast'));
	}
}
$AddBroadcastAddresses = new AddBroadcastAddresses();
// Register hooks
$HookManager->register('BROADCAST_ADDR',array($AddBroadcastAddresses, 'AddBCaddr'));
