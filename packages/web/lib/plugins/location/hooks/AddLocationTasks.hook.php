<?php
class AddLocationTasks extends Hook
{
	var $name = 'AddLocationTasks';
	var $description = 'Add Location to Active Tasks';
	var $author = 'Rowlett';
	var $active = true;
    var $node = 'location';	
	public function TasksActiveTableHeader($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
		{
			if ($_REQUEST['node'] == 'tasks' && ($_REQUEST['sub'] == 'active' || !$_REQUEST['sub']))
				$arguments['headerData'][3] = 'Location';
		}
	}

	public function TasksActiveData($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
		{
			if ($_REQUEST['node'] == 'tasks' && ($_REQUEST['sub'] == 'active' || !$_REQUEST['sub']))
			{
				foreach((array)$arguments['data'] AS $i => $data)
				{
					$Host = current($this->getClass('HostManager')->find(array('id' => $arguments['data'][$i]['host_id'])));
					if ($Host && $Host->isValid())
					$LA = current($this->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id'))));
					$Location = ($LA ? new Location($LA->get('locationID')) : '');
					// Set the field.
					$arguments['data'][$i]['details_taskname'] = $Location && $Location->isValid() ? $Location->get('name') : '';
				}
			}
		}
	}
}
$AddLocationTasks = new AddLocationTasks();
// Register hooks
$HookManager->register('HOST_DATA', array($AddLocationTasks, 'TasksActiveTableHeader'));
$HookManager->register('HOST_DATA', array($AddLocationTasks, 'TasksActiveData'));
