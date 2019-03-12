<?php
/**
 * Subnetgroup Class handler.
 *
 * PHP version 5
 *
 * @category Subnetgroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Subnetgroup Class handler.
 *
 * @category Subnetgroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Subnetgroup extends FOGController
{
    /**
     * The subnetgroup table
     *
     * @var string
     */
    protected $databaseTable = 'subnetgroup';
    /**
     * The subnetgroup fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'sgID',
        'name' => 'sgName',
        'groupID' => 'sgGroupID',
        'subnets' => 'sgSubnets',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'groupID',
        'subnets',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'group',
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Group' => array(
            'id',
            'groupID',
            'group'
        ),
    );
}
