<?php
/**
 * Subnetgroups Class handler.
 *
 * PHP version 5
 *
 * @category Subnetgroups
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Subnetgroups Class handler.
 *
 * @category Subnetgroups
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Subnetgroups extends FOGController
{
    /**
     * The subnetgroups table
     *
     * @var string
     */
    protected $databaseTable = 'subnetgroups';
    /**
     * The subnetgroups fields and common names
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
    /**
     * Load the group object
     *
     * @return object
     */
    protected function loadGroup()
    {
        $group = self::getSubObjectIDs(
            'Group',
            array('id' => $this->get('groupID')),
            'data'
        );
        if (isset($group[0])) {
            $this->set('group', $group[0]);
        }
    }
}
