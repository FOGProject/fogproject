<?php
class AddLocationTasks extends Hook {
    public $name = 'AddLocationTasks';
    public $description = 'Add Location to Active Tasks';
    public $author = 'Rowlett';
    public $active = true;
    public $node = 'location';
    public function TasksActiveTableHeader($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'task') return;
        $arguments['headerData'][4] = _('Location');
    }
    public function TasksActiveData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'task') return;
        $arguments['templates'][4] = '${location}';
        $arguments['attributes'][4] = array('class'=>'r');
        foreach ((array)$arguments['data'] AS $i => &$data) {
            $locationID = $this->getSubObjectIDs('LocationAssociation',array('hostID'=>$data['host_id']),'locationID');
            $locID = array_shift($locationID);
            $arguments['data'][$i]['location'] = $this->getClass('Location',$locID)->get('name');
            unset($data);
        }
    }
}
$AddLocationTasks = new AddLocationTasks();
$HookManager->register('HOST_DATA',array($AddLocationTasks,'TasksActiveTableHeader'));
$HookManager->register('HOST_DATA',array($AddLocationTasks,'TasksActiveData'));
