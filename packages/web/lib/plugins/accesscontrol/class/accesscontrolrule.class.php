<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlRule
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlRule
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlRule extends FOGController
{
    /**
     * The example table.
     *
     * @var string
     */
    protected $databaseTable = 'rules';
    /**
     * The database fields and commonized items.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'ruleID',
        'name' => 'ruleName',
        'type' => 'ruleType',
        'value' => 'ruleValue',
        'parent' => 'ruleParent',
        'createdBy' => 'ruleCreatedBy',
        'createdTime' => 'ruleCreatedTime',
        'node' => 'ruleNode'
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'type',
        'value',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'parent',
    );
}
