<?php
/**
 * Role association between user -> role links.
 *
 * PHP version 7.4+
 *
 * @category RoleUserAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Role association between user -> role links.
 *
 * Shares the roleUserAssoc table with the retired accesscontrol plugin so
 * plugin-era assignments carry over natively.
 *
 * @category RoleUserAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RoleUserAssociation extends FOGController
{
    /**
     * Role user association table.
     *
     * @var string
     */
    protected $databaseTable = 'roleUserAssoc';
    /**
     * Role user association fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ruaID',
        'name' => 'ruaName',
        'roleID' => 'ruaRoleID',
        'userID' => 'ruaUserID'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'roleID',
        'userID'
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
    /**
     * Gets the user object
     *
     * @return object
     */
    public function getUser()
    {
        return new User($this->get('userID'));
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\RoleUserAssociation', 'RoleUserAssociation');
