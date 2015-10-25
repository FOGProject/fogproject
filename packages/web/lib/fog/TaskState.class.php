<?php
class TaskState extends FOGController {
    protected $databaseTable = 'taskStates';
    protected $databaseFields = array(
        'id' => 'tsID',
        'name' => 'tsName',
        'description' => 'tsDescription',
        'order' => 'tsOrder'
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    public function getIcon() {
        $icon = array(
            1=>'bookmark-o',
            2=>'pause',
            3=>'spinner fa-pulse fa-fw',
            4=>'check-circle',
            5=>'ban',
        );
        $this->HookManager->event[] = 'ICON_STATE';
        $this->HookManager->processEvent('ICON_STATE',array('icon'=>&$icon));
        return $icon[$this->get('id')];
    }
}
