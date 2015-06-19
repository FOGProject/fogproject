<?php
class TaskState extends FOGController {
    // Table
    public $databaseTable = 'taskStates';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'tsID',
        'name' => 'tsName',
        'description' => 'tsDescription',
        'order' => 'tsOrder'
    );
}
