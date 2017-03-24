<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlRuleAssociation
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlRuleAssociation
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlRuleAssociation extends FOGController
{
    protected $databaseTable = 'roleRuleAssoc';
    protected $databaseFields = array(
        'id' => 'rraID',
        'name' => 'rraName',
        'roleID' => 'rraRoleID',
        'ruleID' => 'rraRuleID',
    );
    protected $databaseFieldsRequired = array(
        'roleID',
        'ruleID',
    );
}
