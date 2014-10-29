<?php
class AddHostSerial extends Hook
{
	// Class variables
	var $name = 'AddHostSerial';
	var $description = 'Adds host serial to the host lists';
	var $author = 'Junkhacker with edits from Tom Elliott';
	var $active = false;
	function HostData($arguments)
	{
		if ($_REQUEST['node'] == 'host')
		{
			foreach((array)$arguments['data'] AS $i => $data)
			{
				$Host = current($this->getClass('HostManager')->find(array('name' => $data['host_name'])));
				if ($Host && $Host->isValid())
					$Inventory = $Host->get('inventory');
				// Add column template into 'templates' array
				$arguments['templates'][7] = '${serial}';
				// Set the field.
				$arguments['data'][$i]['serial'] = $Inventory && $Inventory->isValid() ? $Inventory->get('sysserial') : '';
				// Add these HTML attributes to that column
				$arguments['attributes'][7] = array('width' => 20,'class' => 'c');
			}
		}
	}
	function HostTableHeader($arguments)
	{
		if ($_REQUEST['node'] == 'host')
		{
			// Updates Header column with the content 'Serial'
			$arguments['headerData'][7] = 'Serial';
		}
	}
}
$AddHostSerial = new AddHostSerial();
// Register hooks with HookManager on desired events
$HookManager->register('HOST_DATA', array($AddHostSerial, 'HostData'));
$HookManager->register('HOST_HEADER_DATA', array($AddHostSerial, 'HostTableHeader'));
