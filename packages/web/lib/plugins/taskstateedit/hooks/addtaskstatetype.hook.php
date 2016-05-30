<?php
class AddTaskStateType extends Hook {
    public $name = 'AddTaskStateType';
    public $description = 'Add Report Management Type';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'taskstateedit';
}
$AddTaskStateType = new AddTaskStateType();
$HookManager->register('REPORT_TYPES',array($AddTaskStateType,'reportTypes'));
