<?php
/** Class Name: TaskMobile
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	Gives a minimal task page for viewing from mobile
	devices.

	Useful for:
	Managing tasks from mobile devices.
*/
class TaskMobile extends FOGPage
{
	var $name = 'Task Management';
	var $node = 'taskss';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);

	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header Data
		$this->headerData = array(
			_('Force'),
			_('Task Name'),
			_('Host'),
			_('Type'),
			_('State'),
			_('Kill'),
		);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${task_force}',
			'${task_name}',
			'${host_name}',
			'${task_type}',
			'${task_state}',
			'<a href="?node=${node}&sub=killtask&id=${task_id}"><img src="images/kill.png" border="0" class="task" /></a>',
		);
	}

	public function index()
	{
		foreach((array)$this->FOGCore->getClass('TaskManager')->find(array('stateID' => array(1,2,3))) AS $Task)
		{
			$Host = new Host($Task->get('hostID'));
			$this->data[] = array(
				'task_force' => (!$Task->get('isForced') ? '<a href="?node=${node}&sub=force&id=${task_id}"><img src="images/force.png" border="0" class="task" /></a>' : ''),
				'node' => $_REQUEST['node'],
				'task_id' => $Task->get('id'),
				'task_name' => $Task->get('name'),
				'host_name' => ($Task->get('isForced') ? '* '.$Host->get('name') : $Host->get('name')),
				'task_type' => $Task->getTaskTypeText(),
				'task_state' => $Task->getTaskStateText(),
			);
		}
		$this->render();
	}

	public function force()
	{
		$Task = new Task($_REQUEST['id']);
		$Task->set('isForced',true)->save();
		$this->FOGCore->redirect('?node='.$this->node);
	}

	public function killtask()
	{
		$Task = new Task($_REQUEST['id']);
		$Task->destroy();
		$this->FOGCore->redirect('?node='.$this->node);
	}
}
// Register page with FOGPageManager
$FOGPageManager->register(new TaskMobile());
