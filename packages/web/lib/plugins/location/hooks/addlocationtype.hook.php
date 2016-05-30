<?php
class AddLocationType extends Hook {
    public $name = 'AddLocationType';
    public $description = 'Add Report Management Type';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'location';
}
$AddLocationType = new AddLocationType();
$HookManager->register('REPORT_TYPES',array($AddLocationType,'reportTypes'));
