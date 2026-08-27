<?php
/**
 * Membership association between user group -> user links.
 *
 * PHP version 7.4+
 *
 * @category UserGroupMember
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * Membership association between user group -> user links.
 *
 * The friendly `usergroupID` key matches assocSetter's derivation from the
 * UserGroup class name, so UserGroup->save() and User->save() both drive
 * membership through this association.
 *
 * @category UserGroupMember
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserGroupMember extends FOGController
{
    /**
     * User group member table.
     *
     * @var string
     */
    protected $databaseTable = 'userGroupMembers';
    /**
     * User group member fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ugmID',
        'name' => 'ugmName',
        'usergroupID' => 'ugmGroupID',
        'userID' => 'ugmUserID'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'usergroupID',
        'userID'
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
     * Gets the user object
     *
     * @return object
     */
    public function getUser()
    {
        return new User($this->get('userID'));
    }
}
