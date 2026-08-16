<?php
/**
 * UserGroup — a named group of users for role assignment.
 *
 * PHP version 5
 *
 * @category UserGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * UserGroup — a named group of users for role assignment.
 *
 * A user group holds member users (see UserGroupMember) and a set of roles
 * (see RoleUserGroupAssociation). A user's effective permissions are the
 * union of the roles assigned directly to the user and the roles assigned
 * to any group the user belongs to — resolved in one place, see
 * Authorization::getPermissions(). Groups are flat (no nesting).
 *
 * @category UserGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserGroup extends FOGController
{
    /**
     * The user group table.
     *
     * @var string
     */
    protected $databaseTable = 'userGroups';
    /**
     * The database fields and commonized names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ugID',
        'name' => 'ugName',
        'description' => 'ugDesc',
        'createdBy' => 'ugCreatedBy',
        'createdTime' => 'ugCreatedTime'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'users',
        'roles'
    ];
    /**
     * Add member users to this group.
     *
     * @param array $addArray The user ids to add.
     *
     * @return object
     */
    public function addUser($addArray)
    {
        return $this->addRemItem(
            'users',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove member users from this group.
     *
     * @param array $removeArray The user ids to remove.
     *
     * @return object
     */
    public function removeUser($removeArray)
    {
        return $this->addRemItem(
            'users',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Add roles to this group.
     *
     * @param array $addArray The role ids to add.
     *
     * @return object
     */
    public function addRole($addArray)
    {
        return $this->addRemItem(
            'roles',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove roles from this group.
     *
     * @param array $removeArray The role ids to remove.
     *
     * @return object
     */
    public function removeRole($removeArray)
    {
        return $this->addRemItem(
            'roles',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Stores/updates the group.
     *
     * @return object
     */
    public function save()
    {
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
        return $this
            ->assocSetter('UserGroupMember', 'user', true)
            ->assocSetter('RoleUserGroup', 'role')
            ->load();
    }
    /**
     * Load member users of this group.
     *
     * @return void
     */
    protected function loadUsers()
    {
        $find = ['usergroupID' => $this->get('id')];
        $userids = Route::getIds(
            'usergroupmember',
            $find,
            'userID'
        );
        $types = [];
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            ['types' => &$types]
        );
        $filtered = (count($types)
            ? (array)Route::getIds('user', ['type' => $types])
            : []);
        $associds = array_diff(
            $userids,
            $filtered
        );
        unset($filtered);
        $this->set('users', (array)$associds);
    }
    /**
     * Load roles assigned to this group.
     *
     * @return void
     */
    protected function loadRoles()
    {
        $find = ['usergroupID' => $this->get('id')];
        $roleids = Route::getIds(
            'roleusergroupassociation',
            $find,
            'roleID'
        );
        $this->set('roles', (array)$roleids);
    }
    /**
     * Destroy this group.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        // Funnel cleanup through the cascade authority (the usergroup case
        // in Route::deletemass removes the userGroupMembers and
        // roleUserGroupAssoc rows). Members lose only the group-sourced
        // roles; their direct role assignments are untouched.
        Route::deletemass('usergroup', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
}
