<?php
class TaskMobile extends FOGPage {
	public $node = 'tasks';
	public function __construct($name = '') {
		$this->name = 'Task Management';
		// Call parent constructor
		parent::__construct($this->name);
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
			'<a href="?node=${node}&sub=killtask&id=${task_id}"><i class="fa fa-minus-circle fa-2x task"></i></a>',
		);
	}
	public function index() {
		foreach((array)$this->getClass('TaskManager')->find(array('stateID' => array(1,2,3))) AS $Task) {
			$Host = new Host($Task->get('hostID'));
			$this->data[] = array(
				'task_force' => (!$Task->get('isForced') ? '<a href="?node=${node}&sub=force&id=${task_id}"><i class="fa fa-step-forward fa-2x task"></i></a>' : '<i class="fa fa-play fa-2x task"></i>'),
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
	public function search() {
		unset($this->headerData[0],$this->headerData[5],$this->attributes[0],$this->attributes[5],$this->templates[0],$this->templates[5]);
		parent::search();
	}
	public function search_post() {
		unset($this->headerData[0],$this->headerData[5],$this->attributes[0],$this->attributes[5],$this->templates[0],$this->templates[5]);
		foreach((array)$this->getClass('TaskManager')->search() AS $Task) {
			$Host = new Host($Task->get('hostID'));
			$this->data[] = array(
				'task_id' => $Task->get('id'),
				'task_name' => $Task->get('name'),
				'host_name' => ($Task->get('isForced') ? '* '.$Host->get('name') : $Host->get('name')),
				'task_type' => $Task->getTaskTypeText(),
				'task_state' => $Task->getTaskStateText(),
			);
		}
		$this->render();
	}
	public function force() {
		$Task = new Task($_REQUEST['id']);
		$Task->set('isForced',true)->save();
		$this->FOGCore->redirect('?node='.$this->node);
	}
	public function killtask() {
		$Task = new Task($_REQUEST['id']);
		$Task->destroy();
		$this->FOGCore->redirect('?node='.$this->node);
	}
}
