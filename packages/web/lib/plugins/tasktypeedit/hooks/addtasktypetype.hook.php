<?php
class AddTaskTypeType extends Hook
{
    public $name = 'AddTaskTypeType';
    public $description = 'Add Report Management Type';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'tasktypeedit';
}
$AddTaskTypeType = new AddTaskTypeType();
$HookManager->register('REPORT_TYPES', array($AddTaskTypeType, 'reportTypes'));
