<?php
class AddHostModel extends Hook
{
	// Class variables
	var $name = 'AddHostModel';
	var $description = 'Adds host model to the host lists';
	var $author = 'Rowlett/TomElliott';
	var $active = false;
	public function HostData($arguments)
	{
		if ($_REQUEST['node'] == 'host')
		{
			foreach((array)$arguments['data'] AS $i => $data)
			{
				$Host = current($this->FOGCore->getClass('HostManager')->find(array('name' => $data['host_name'])));
				if ($Host && $Host->isValid())
					$Inventory = $Host->get('inventory');
				// Add column template into 'templates' array
				$arguments['templates'][5] = '${model}';
				// Set the field.
				$arguments['data'][$i]['model'] = $Inventory && $Inventory->isValid() ? $Inventory->get('sysproduct') : '';
				// Add these HTML attributes to that column
				$arguments['attributes'][5] = array('width' => 20,'class' => 'c');
			}
		}
	}
	public function HostTableHeader($arguments)
	{
		if ($_REQUEST['node'] == 'host')
		{
			// Updates Header column with the content 'Model'
			$arguments['headerData'][5] = 'Model';
		}
	}
}
$AddHostModel = new AddHostModel();
// Register hooks with HookManager on desired events
$HookManager->register('HOST_DATA', array($AddHostModel, 'HostData'));
$HookManager->register('HOST_HEADER_DATA', array($AddHostModel, 'HostTableHeader'));
