<?php
/**
 * Subnet Group plugin
 *
 * PHP version 5
 *
 * @category SubnetGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Subnet Group plugin
 *
 * @category SubnetGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubnetGroup extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'subnetgroup';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sgID',
        'name' => 'sgName',
        'groupID' => 'sgGroupID',
        'subnets' => 'sgSubnets'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'groupID',
        'subnets'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'group'
    ];
    /**
     * Database -> Class field relationships.
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Group' => [
            'id',
            'groupID',
            'group'
        ]
    ];
}
