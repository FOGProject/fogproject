<?php
class AddHostModel extends Hook {
	/** @var $name the name of the hook */
	public $name = 'AddHostModel';
	/** @var $description the description of what the hook does */
	public $description = 'Adds host model to the host lists';
	/** @var $author the author of the hook */
	public $author = 'Rowlett/TomElliott';
	/** @var $active whether or not the hook is to be running */
	public $active = false;
	/** @function HostData the data to change
	  * @param $arguments the Hook Events to enact upon
	  * @return void
	  */
	public function HostData($arguments) {
		if ($_REQUEST['node'] == 'host') {
			foreach((array)$arguments['data'] AS $i => $data) {
				$Host = current($this->getClass('HostManager')->find(array('name' => $data['host_name'])));
				if ($Host && $Host->isValid()) $Inventory = $Host->get('inventory');
				$arguments['templates'][5] = '${model}';
				$arguments['data'][$i]['model'] = $Inventory && $Inventory->isValid() ? $Inventory->get('sysproduct') : '';
				$arguments['attributes'][5] = array('width' => 20,'class' => 'c');
			}
		}
	}
	/** @function HostTableHeader the header data to change
	  * @param $arguments the Hook Events to enact upon
	  * @return void
	  */
	public function HostTableHeader($arguments) {
		if ($_REQUEST['node'] == 'host') $arguments['headerData'][5] = 'Model';
	}
}
$AddHostModel = new AddHostModel();
$HookManager->register('HOST_DATA', array($AddHostModel, 'HostData'));
$HookManager->register('HOST_HEADER_DATA', array($AddHostModel, 'HostTableHeader'));
