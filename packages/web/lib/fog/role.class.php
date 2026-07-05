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
 * users (see RoleUserAssociation). Users with no role at all are treated
 * as implicit administrators — see Authorization::getPermissions().
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
        if (!$this->isLoaded('permissions')) {
            return $this;
        }
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
        // rows). Members of a deleted role fall back to implicit-admin
        // access, consistent with the no-role upgrade stance.
        Route::deletemass('role', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
}
