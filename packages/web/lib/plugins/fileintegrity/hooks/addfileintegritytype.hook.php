<?php
class AddFileIntegrityType extends Hook
{
    public $name = 'AddFileIntegrityType';
    public $description = 'Add Report Management Type';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'fileintegrity';
}
$AddFileIntegrityType = new AddFileIntegrityType();
$HookManager->register('REPORT_TYPES', array($AddFileIntegrityType, 'reportTypes'));
