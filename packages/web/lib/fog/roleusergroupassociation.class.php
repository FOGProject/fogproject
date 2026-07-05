<?php
/**
 * Role association between user group -> role links.
 *
 * PHP version 5
 *
 * @category RoleUserGroupAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Role association between user group -> role links.
 *
 * Assigns a role to a whole user group; every member inherits the role's
 * permissions (see Authorization::getPermissions()). The friendly
 * `usergroupID` key matches assocSetter's derivation from the UserGroup
 * class name.
 *
 * @category RoleUserGroupAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RoleUserGroupAssociation extends FOGController
{
    /**
     * Role user group association table.
     *
     * @var string
     */
    protected $databaseTable = 'roleUserGroupAssoc';
    /**
     * Role user group association fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'rugID',
        'name' => 'rugName',
        'usergroupID' => 'rugGroupID',
        'roleID' => 'rugRoleID'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'usergroupID',
        'roleID'
    ];
    /**
     * Gets the user group object
     *
     * @return object
     */
    public function getUserGroup()
    {
        return new UserGroup($this->get('usergroupID'));
    }
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
