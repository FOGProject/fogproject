<?php
class ChangeHostname extends Hook {
	/** @var $name the name of the hook */
	public $name = 'ChangeHostname';
	/** @var $description the description of what the hook does */
	public $description = 'Appends "Chicken-" to all hostnames ';
	/** @var $author the author of the hook */
	public $author = 'Blackout';
	/** @var $active whether or not the hook is to be running */
	public $active = false;
	/** @function HostData the data to change
	  * @param $arguments the Hook Events to enact upon
	  * @return void
	  */
	public function HostData($arguments) {
		foreach ($arguments['data'] AS $i => $data)$arguments['data'][$i]['host_name'] = 'Chicken-' . $data['host_name'];
	}
}
$HookManager->register('HOST_DATA', array(new ChangeHostname(), 'HostData'));
