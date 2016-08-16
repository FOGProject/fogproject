<?php
class AddWOLBroadcastType extends Hook
{
    public $name = 'AddWOLBroadcastType';
    public $description = 'Add Report Management Type';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'wolbroadcast';
}
$AddWOLBroadcastType = new AddWOLBroadcastType();
$HookManager->register('REPORT_TYPES', array($AddWOLBroadcastType, 'reportTypes'));
