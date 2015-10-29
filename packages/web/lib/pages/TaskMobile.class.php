<?php
class TaskMobile extends FOGPage {
    public $node = 'task';
    public function __construct($name = '') {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        if (is_numeric($_REQUEST['id']) && intval($_REQUEST['id'])) $this->obj = $this->getClass('Task',$_REQUEST['id']);
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
        $Tasks = $this->getClass('TaskManager')->find(array('stateID'=>array(1,2,3)));
        foreach ((array)$Tasks AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            $Host = $Task->getHost();
            if (!$Host->isValid()) {
                unset($Task,$Host);
                continue;
            }
            $name = ($Task->get('isForced') ? '* ' : '').$Host->get('name');
            unset($Host);
            $this->data[] = array(
                'task_force'=>(!$Task->get('isForced') ? '<a href="?node=${node}&sub=force&id=${task_id}"><i class="fa fa-step-forward fa-2x task"></i></a>' : '<i class="fa fa-play fa-2x task"></i>'),
                'node'=>$_REQUEST['node'],
                'task_id'=>$Task->get('id'),
                'task_name'=>$Task->get('name'),
                'host_name'=>$name,
                'task_type'=>$Task->getTaskTypeText(),
                'task_state'=>$Task->getTaskStateText(),
            );
            unset($Task);
        }
        unset($id,$ids,$name);
        $this->render();
    }
    public function search() {
        unset($this->headerData[0],$this->headerData[5],$this->attributes[0],$this->attributes[5],$this->templates[0],$this->templates[5]);
        $this->getClass('TaskManager')->search();
    }
    public function search_post() {
        unset($this->headerData[0],$this->headerData[5],$this->attributes[0],$this->attributes[5],$this->templates[0],$this->templates[5]);
        $Tasks = $this->getClass('TaskManager')->search('',true);
        foreach((array)$Tasks AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            if (in_array($Task->get('stateID'),array(0,1,2,3))) {
                $Host = $Task->getHost();
                if (!$Host->isValid()) {
                    unset($Host,$Task);
                    continue;
                }
                $name = ($Task->get('isForced') ? '* ' : '').$Host->get('name');
                unset($Host);
                $this->data[] = array(
                    'task_id'=>$Task->get('id'),
                    'task_name'=>$Task->get('name'),
                    'host_name'=>$name,
                    'task_type'=>$Task->getTaskTypeText(),
                    'task_state'=>$Task->getTaskStateText(),
                );
            }
            unset($Task);
        }
        unset($id,$ids,$name);
        $this->render();
    }
    public function force() {
        $this->obj->set('isForced',1)->save();
        $this->redirect('?node='.$this->node);
    }
    public function killtask() {
        $this->obj->cancel();
        $this->redirect('?node='.$this->node);
    }
    public function active() {
        $Tasks = $this->getClasss('TaskManager')->find(array('stateID'=>array(1,2,3)));
        foreach ((array)$Tasks AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            $Host = $Task->getHost();
            if (!$Host->isValid()) {
                unset($Task,$Host);
                continue;
            }
            $name = ($Task->get('isForced') ? '* ' : '').$Host->get('name');
            unset($Host);
            $this->data[] = array(
                'task_id'=>$Task->get('id'),
                'task_name'=>$Task->get('name'),
                'host_name'=>$name,
                'task_type'=> $Task->getTaskTypeText(),
                'task_state'=> $Task->getTaskStateText(),
                'task_force'=>(!$Task->get(isForced) ? '<a href="?node=${node}&sub=force&id=${task_id}"><i class="fa fa-step-forward fa-2x task"></i></a>' : '<i class="fa fa-play fa-2x task"></i>'),
            );
            unset($Task,$Host);
        }
        unset($id,$ids,$name);
        $this->render();
    }
}
