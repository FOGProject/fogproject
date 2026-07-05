<?php
/**
 * Permission granted to a role.
 *
 * PHP version 5
 *
 * @category RolePermission
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Permission granted to a role.
 *
 * The name field holds the permission string: '<node>.<action>' (e.g.
 * 'host.edit'), a node wildcard ('host.*'), or the global wildcard '*'.
 * See Authorization for the registry of valid nodes/actions and the
 * matching rules.
 *
 * @category RolePermission
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RolePermission extends FOGController
{
    /**
     * Role permission table.
     *
     * @var string
     */
    protected $databaseTable = 'rolePermissions';
    /**
     * Role permission fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'rpID',
        'roleID' => 'rpRoleID',
        'name' => 'rpName'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'roleID',
        'name'
    ];
    /**
     * Gets the role object
     *
     * @return object
     */
    public function getRole()
    {
        return new Role($this->get('roleID'));
    }
}
