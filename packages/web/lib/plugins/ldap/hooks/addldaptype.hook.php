<?php
class AddLDAPType extends Hook
{
    public $name = 'AddLDAPType';
    public $description = 'Add Report Management Type';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'ldap';
}
$AddLDAPType = new AddLDAPType();
$HookManager->register('REPORT_TYPES', array($AddLDAPType, 'reportTypes'));
