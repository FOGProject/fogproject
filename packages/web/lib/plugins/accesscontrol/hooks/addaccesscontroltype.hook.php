<?php
class AddAccesscontrolType extends Hook {
    public $name = 'AddAccesscontrolType';
    public $description = 'Add Report Management Type';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'accesscontrol';
}
$AddAccesscontrolType = new AddAccesscontrolType();
$HookManager->register('REPORT_TYPES',array($AddAccesscontrolType,'reportTypes'));
