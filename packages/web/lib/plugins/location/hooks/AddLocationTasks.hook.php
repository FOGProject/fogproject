<?php
class AddLocationTasks extends Hook {
	public function __construct() {
		parent::__construct();
		$this->name = 'AddLocationTasks';
		$this->description = 'Add Location to Active Tasks';
		$this->author = 'Rowlett';
		$this->active = true;
		$this->node = 'location';
	}
	public function TasksActiveTableHeader($arguments) {
		if (in_array($this->node,$_SESSION[PluginsInstalled]) && $_REQUEST[node] == 'task') $arguments[headerData][3] = _('Location');
	}
	public function TasksActiveData($arguments) {
        if (in_array($this->node,$_SESSION[PluginsInstalled]) && $_REQUEST[node] == 'task') {
				foreach((array)$arguments[data] AS $i => &$data) {
                    $LocAssocs = $this->getClass(LocationAssociationManager)->find(array('hostID' => $data[host_id]),'','','','','','','locationID');
                    $locID = array_shift($LocAssocs);
                    $arguments[data][$i][details_taskname] = $this->getClass(Location,$locID)->get(name);
                }
                unset($data);
		}
	}
}
$AddLocationTasks = new AddLocationTasks();
// Register hooks
$HookManager->register('HOST_DATA', array($AddLocationTasks, 'TasksActiveTableHeader'));
$HookManager->register('HOST_DATA', array($AddLocationTasks, 'TasksActiveData'));
