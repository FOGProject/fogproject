<?php
/**
 * Role — native role-based access control.
 *
 * PHP version 5
 *
 * @category Role
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Role — native role-based access control.
 *
 * A role is a named set of permissions (see RolePermission) assigned to
 * users (see RoleUserAssociation) or to whole user groups (see
 * RoleUserGroupAssociation). Access is deny-by-default: a user with no
 * role can do nothing — see Authorization::getPermissions().
 *
 * The roles/roleUserAssoc tables are shared with (and adopted from) the
 * retired accesscontrol plugin, so plugin-era roles and user assignments
 * carry over natively on upgrade.
 *
 * @category Role
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Role extends FOGController
{
    /**
     * The role table.
     *
     * @var string
     */
    protected $databaseTable = 'roles';
    /**
     * The database fields and commonized names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'rID',
        'name' => 'rName',
        'description' => 'rDesc',
        'createdBy' => 'rCreatedBy',
        'createdTime' => 'rCreatedTime'
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
        'usergroups',
        'permissions'
    ];
    /**
     * Add users to this role.
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
     * Remove users from this role.
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
     * Add user groups to this role.
     *
     * The role/user group link was previously editable only from the user
     * group side, which made roleUserGroupAssoc the one asymmetric
     * association in the product -- a role could hold user groups that its
     * own edit page could neither show nor change.
     *
     * @param array $addArray The user group ids to add.
     *
     * @return object
     */
    public function addUserGroup($addArray)
    {
        return $this->addRemItem(
            'usergroups',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove user groups from this role.
     *
     * @param array $removeArray The user group ids to remove.
     *
     * @return object
     */
    public function removeUserGroup($removeArray)
    {
        return $this->addRemItem(
            'usergroups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Add permissions to this role.
     *
     * @param array $addArray The permission names to add.
     *
     * @return object
     */
    public function addPermission($addArray)
    {
        return $this->addRemItem(
            'permissions',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove permissions from this role.
     *
     * @param array $removeArray The permission names to remove.
     *
     * @return object
     */
    public function removePermission($removeArray)
    {
        return $this->addRemItem(
            'permissions',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Stores/updates the role.
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('RoleUser', 'user')
            ->assocSetter('RoleUserGroup', 'usergroup')
            ->_syncPermissions()
            ->load();
    }
    /**
     * Load users assigned to this role.
     *
     * @return void
     */
    protected function loadUsers()
    {
        $find = ['roleID' => $this->get('id')];
        $userids = Route::getIds(
            'roleuserassociation',
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
     * Load user groups assigned to this role.
     *
     * The mirror of UserGroup::loadRoles(), reading the same join table
     * from the other side.
     *
     * @return void
     */
    protected function loadUsergroups()
    {
        $this->set(
            'usergroups',
            (array)Route::getIds(
                'roleusergroupassociation',
                ['roleID' => $this->get('id')],
                'usergroupID'
            )
        );
    }
    /**
     * Load this role's permission names.
     *
     * @return void
     */
    protected function loadPermissions()
    {
        $find = ['roleID' => $this->get('id')];
        $permissions = Route::getIds(
            'rolepermission',
            $find,
            'name'
        );
        $this->set('permissions', (array)$permissions);
    }
    /**
     * Sync the rolePermissions rows to the in-memory permission list.
     *
     * Permission rows carry payload strings ('host.edit', 'image.*', '*'),
     * not entity ids, so assocSetter (which diffs on id columns) cannot be
     * used here; the rows are diffed and written directly instead.
     *
     * @return object
     */
    private function _syncPermissions()
    {
        // Deliberately unconditional: FOGBase::isLoaded() marks a key loaded
        // on every call, even when it answers false and nothing follows up
        // by loading it. Gating this on isLoaded('permissions') would let a
        // save that never touches permissions (e.g. a plain rename) poison
        // that flag, so any later get('permissions') on this same object
        // (e.g. the API response after save()) would skip the real DB load
        // and see an empty list instead of the role's actual permissions.
        // get() already lazy-loads correctly when not yet loaded; when
        // nothing changed, $permissions and $cur end up identical below and
        // this is a no-op past the two SELECTs.
        $permissions = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'trim',
                        (array)$this->get('permissions')
                    )
                )
            )
        );
        $cur = Route::getIds(
            'rolepermission',
            ['roleID' => $this->get('id')],
            'name'
        );
        $rem = array_diff((array)$cur, $permissions);
        if (count($rem ?: [])) {
            Route::deletemass(
                'rolepermission',
                [
                    'name' => array_values($rem),
                    'roleID' => $this->get('id')
                ]
            );
        }
        $add = array_diff($permissions, (array)$cur);
        if (count($add ?: [])) {
            $insert_values = [];
            foreach ($add as $permission) {
                // Rows are written with insertBatch rather than through
                // RolePermission::save(), so the guard there does not see
                // them -- this is the path PUT /fog/role/<id> takes. Only
                // newly added strings are checked: a role may legitimately
                // still hold a permission whose plugin has since been
                // uninstalled, and re-saving it should not fail.
                Authorization::assertCanGrant($permission);
                $insert_values[] = [
                    $this->get('id'),
                    $permission
                ];
            }
            self::getClass('RolePermissionManager')->insertBatch(
                ['roleID', 'name'],
                $insert_values
            );
        }
        return $this;
    }
    /**
     * Destroy this role.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        // Funnel cleanup through the cascade authority (the role case in
        // Route::deletemass removes the rolePermissions and roleUserAssoc
        // rows). Members lose whatever this role granted them; the
        // last-administrator guard in Route::deletemass is what keeps that
        // from locking the install out.
        Route::deletemass('role', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
}
