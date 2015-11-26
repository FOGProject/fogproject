<?php
class TaskMobile extends FOGPage {
    public $node = 'task';
    public function __construct($name = '') {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        $this->headerData = array(
            _('Force'),
            _('Task Name'),
            _('Host'),
            _('Type'),
            _('State'),
            _('Kill'),
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array('class'=>'filter-false'),
        );
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
        $this->active();
    }
    public function search() {
        $this->redirect(sprintf('?node=%s&sub=active',$this->node));
    }
    public function search_post() {
        $this->redirect(sprintf('?node=%s&sub=active',$this->node));
    }
    public function force() {
        $this->obj->set('isForced',1)->save();
        $this->redirect(sprintf('?node=%s',$this->node));
    }
    public function killtask() {
        $this->obj->cancel();
        $this->redirect(sprintf('?node=%s',$this->node));
    }
    public function active() {
        foreach ((array)$this->getClass('TaskManager')->find(array('stateID'=>array(-1,0,1,2,3))) AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            $Host = $Task->getHost();
            if (!$Host->isValid()) continue;
            $name = sprintf('%s %s',$Task->isForced() ? '*' : '',$Host->get('name'));
            unset($Host);
            $this->data[] = array(
                'task_id'=>$Task->get('id'),
                'task_name'=>$Task->get('name'),
                'host_name'=>$name,
                'task_type'=> $Task->getTaskTypeText(),
                'task_state'=> $Task->getTaskStateText(),
                'task_force'=>(!$Task->isForced() ? '<a href="?node=${node}&sub=force&id=${task_id}"><i class="fa fa-step-forward fa-2x task"></i></a>' : '<i class="fa fa-play fa-2x task"></i>'),
            );
            unset($Task);
        }
        $this->render();
    }
}
