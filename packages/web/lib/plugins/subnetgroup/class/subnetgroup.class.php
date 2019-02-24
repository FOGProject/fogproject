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
     * Load Group
     *
     * @return object
     */
    protected function loadGroup()
    {
        $find = ['id' => $this->get('groupID')];
        Route::ids(
            'group',
            $find,
            'id'
        );
        $groups = json_decode(
            Route::getData(),
            true
        );
        $this->set('group', array_shift($groups));
    }
}
