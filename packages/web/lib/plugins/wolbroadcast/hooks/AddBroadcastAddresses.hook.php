<?php
class AddBroadcastAddresses extends Hook
{
	var $name = 'AddBroadcastAddresses';
	var $description = 'Add the broadcast addresses to use WOL with';
	var $author = 'Tom Elliott';
	var $active = true;
	var $node = 'wolbroadcast';
	public function AddBCaddr($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			$arguments['broadcast'] = array_merge($arguments['broadcast'],$this->getClass('WolbroadcastManager')->find('','','','','','','','broadcast'));
	}
}
$AddBroadcastAddresses = new AddBroadcastAddresses();
// Register hooks
$HookManager->register('BROADCAST_ADDR',array($AddBroadcastAddresses, 'AddBCaddr'));
