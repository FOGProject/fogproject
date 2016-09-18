<?php
/**
 * Adds the ldap type to the reports/exports items
 *
 * PHP version 5
 *
 * @category AddLDAPType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the ldap type to the reports/exports items
 *
 * @category AddLDAPType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
