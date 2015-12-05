<?php
class TaskState extends FOGController {
    protected $databaseTable = 'taskStates';
    protected $databaseFields = array(
        'id' => 'tsID',
        'name' => 'tsName',
        'description' => 'tsDescription',
        'order' => 'tsOrder',
        'icon' => 'tsIcon',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    public function getIcon() {
        return $this->get('icon');
    }
}
