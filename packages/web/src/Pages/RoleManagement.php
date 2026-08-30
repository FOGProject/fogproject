<?php
/**
 * Role management page — native role-based access control.
 *
 * PHP version 7.4+
 *
 * @category RoleManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Auth\Authorization;
use FOG\Auth\SiteScope;
use FOG\Base\FOGPage;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

/**
 * Role management page — native role-based access control.
 *
 * @category RoleManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RoleManagement extends FOGPage
{
    /**
     * The node of this page.
     *
     * @var string
     */
    public $node = 'role';
    /**
     * Constructor
     *
     * @param string $name The name for the page.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Role Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Role Name'),
            _('Role Description')
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Display labels for the permission matrix rows.
     *
     * @return array registry node => label
     */
    private static function _permNodeLabels()
    {
        return [
            'host' => _('Hosts'),
            'group' => _('Groups'),
            'image' => _('Images'),
            'snapin' => _('Snapins'),
            'printer' => _('Printers'),
            'module' => _('Modules'),
            'user' => _('Users'),
            'role' => _('Roles'),
            'storagenode' => _('Storage Nodes'),
            'storagegroup' => _('Storage Groups'),
            'ipxe' => _('iPXE Menu'),
            'task' => _('Tasks'),
            'service' => _('Client Settings'),
            'settings' => _('FOG Settings'),
            'report' => _('Reports'),
            'plugin' => _('Plugins'),
            // Adding system.export puts a new row and a new one-checkbox
            // column in this matrix. Labeled so neither reads as the
            // ucfirst() fallback -- `install` and `manage` already rely on
            // it, but a grant this wide should not be introduced that way.
            'system' => _('System')
        ];
    }
    /**
     * Display labels for the permission matrix columns.
     *
     * @return array action => label
     */
    private static function _permActionLabels()
    {
        return [
            'view' => _('View'),
            'create' => _('Create'),
            'edit' => _('Edit'),
            'delete' => _('Delete'),
            'task' => _('Task'),
            'export' => _('Export')
        ];
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $role = filter_input(INPUT_POST, 'role');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 col-form-label';

        return [
            self::makeLabel(
                $labelClass,
                'role',
                _('Role Name')
            ) => self::makeInput(
                'form-control rolename-input',
                'role',
                _('Role Name'),
                'text',
                'role',
                $role,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Role Description')
            ) => self::makeTextarea(
                'form-control roledescription-input',
                'description',
                _('Role Description'),
                'description',
                $description
            )
        ];
    }
    /**
     * Create new role.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'role',
            _('Create New Role'),
            'ROLE_ADD_FIELDS',
            'Role'
        );
    }
    /**
     * Create new role (modal form).
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'role',
            'ROLE_ADD_FIELDS',
            'Role'
        );
    }
    /**
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Role',
            'ROLE_ADD',
            _('Role added!'),
            _('Role Create Success'),
            _('Role Create Fail'),
            function (&$serverFault) {
                $role = trim(
                    filter_input(INPUT_POST, 'role')
                );
                $description = trim(
                    filter_input(INPUT_POST, 'description')
                );
                $exists = self::getClass('RoleManager')
                    ->exists($role);
                if ($exists) {
                    throw new \Exception(
                        _('A role already exists with this name!')
                    );
                }
                $Role = self::getClass('Role')
                    ->set('name', $role)
                    ->set('description', $description);
                if (!$Role->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Add role failed!'));
                }
                return $Role;
            }
        );
    }
    /**
     * Displays the role general tab.
     *
     * @return void
     */
    public function roleGeneral()
    {
        $role = (
            filter_input(INPUT_POST, 'role') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'role',
                _('Role Name')
            ) => self::makeInput(
                'form-control rolename-input',
                'role',
                _('Role Name'),
                'text',
                'role',
                $role,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Role Description')
            ) => self::makeTextarea(
                'form-control roledescription-input',
                'description',
                _('Role Description'),
                'description',
                $description
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
        );

        self::$HookManager->processEvent(
            'ROLE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Role' => &$this->obj
            ]
        );

        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderGeneralForm('role', $rendered, $buttons);
    }
    /**
     * Updates the role general element.
     *
     * @return void
     */
    public function roleGeneralPost()
    {
        self::checkAuthAndCSRF();
        $role = trim(
            filter_input(INPUT_POST, 'role')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        $exists = self::getClass('RoleManager')
            ->exists($role);
        if ($role != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(
                _('A role with this name already exists!')
            );
        }
        $this->obj
            ->set('name', $role)
            ->set('description', $description);
    }
    /**
     * Displays the role permissions tab: an Administrator (full access)
     * master toggle plus a node x action checkbox matrix built from the
     * permission registry (so plugin-registered nodes appear too).
     *
     * @return void
     */
    public function rolePermission()
    {
        $registry = Authorization::registry();
        $perms = (array)$this->obj->get('permissions');
        $hasStar = in_array('*', $perms, true);

        // Column set: union of registry actions in first-seen order.
        $actions = [];
        foreach ($registry as $nodeActions) {
            foreach ((array)$nodeActions as $action) {
                if (!in_array($action, $actions, true)) {
                    $actions[] = $action;
                }
            }
        }
        $nodeLabels = self::_permNodeLabels();
        $actionLabels = self::_permActionLabels();

        echo self::makeFormTag(
            '',
            'role-permission-form',
            self::makeTabUpdateURL(
                'role-permission',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo '<div class="form-check mb-3">';
        echo self::makeInput(
            'form-check-input',
            'allperm',
            '',
            'checkbox',
            'role-perm-all',
            '1',
            false,
            false,
            -1,
            -1,
            ($hasStar ? 'checked' : '')
        );
        echo self::makeLabel(
            'form-check-label',
            'role-perm-all',
            _('Administrator (full access)')
        );
        echo '</div>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-striped align-middle" '
            . 'id="role-perm-table">';
        echo '<thead><tr>';
        echo '<th>' . _('Area') . '</th>';
        foreach ($actions as $action) {
            $label = $actionLabels[$action] ?? ucfirst($action);
            echo '<th class="text-center">'
                . \Initiator::e($label)
                . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($registry as $rnode => $nodeActions) {
            $label = $nodeLabels[$rnode] ?? ucfirst($rnode);
            echo '<tr>';
            echo '<td>' . \Initiator::e($label) . '</td>';
            foreach ($actions as $action) {
                echo '<td class="text-center">';
                if (in_array($action, (array)$nodeActions, true)) {
                    $perm = "{$rnode}.{$action}";
                    $checked = $hasStar
                        || in_array($perm, $perms, true)
                        || in_array("{$rnode}.*", $perms, true);
                    echo self::makeInput(
                        'form-check-input role-perm-box',
                        'permission[]',
                        '',
                        'checkbox',
                        'perm-' . $rnode . '-' . $action,
                        $perm,
                        false,
                        false,
                        -1,
                        -1,
                        ($checked ? 'checked' : '')
                    );
                }
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo self::makeButton(
            'permission-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the role permissions.
     *
     * @return void
     */
    public function rolePermissionPost()
    {
        self::checkAuthAndCSRF();
        $registry = Authorization::registry();
        if (isset($_POST['allperm'])) {
            $perms = ['*'];
        } else {
            $posted = filter_input_array(
                INPUT_POST,
                ['permission' => ['flags' => FILTER_REQUIRE_ARRAY]]
            );
            $valid = [];
            foreach ((array)($posted['permission'] ?? []) as $perm) {
                $perm = trim((string)$perm);
                $rnode = strstr($perm, '.', true);
                if (false === $rnode || !isset($registry[$rnode])) {
                    continue;
                }
                $action = substr((string)strrchr($perm, '.'), 1);
                if (!in_array($action, (array)$registry[$rnode], true)) {
                    continue;
                }
                $valid["{$rnode}.{$action}"] = true;
            }
            // Compress a full row to '<node>.*' so the stored form matches
            // the seeded defaults and stays stable across round trips.
            $perms = [];
            foreach ($registry as $rnode => $actions) {
                $granted = [];
                foreach ((array)$actions as $action) {
                    if (isset($valid["{$rnode}.{$action}"])) {
                        $granted[] = "{$rnode}.{$action}";
                    }
                }
                if (!count($granted)) {
                    continue;
                }
                if (count($granted) === count((array)$actions)) {
                    $perms[] = "{$rnode}.*";
                } else {
                    $perms = array_merge($perms, $granted);
                }
            }
        }
        $adminRemains = Authorization::adminExistsGiven(
            [
                'rolePermissions' => [
                    (int)$this->obj->get('id') => $perms
                ]
            ]
        );
        if (!$adminRemains) {
            throw new \Exception(
                _('This change would leave no user with administrator access.')
            );
        }
        $this->obj->set('permissions', $perms);
    }
    /**
     * Present the users tab.
     *
     * @return void
     */
    public function roleUsers()
    {
        $this->renderAssocTab(
            'role-user',
            _('Role User Associations'),
            _('User Name'),
            'user'
        );
    }
    /**
     * Update users.
     *
     * @return void
     */
    public function roleUserPost()
    {
        $this->assocPost('addUser', 'removeUser');
        // assocPost only mutates the in-memory list; the save happens in
        // editPost after this returns, so throwing here aborts the change.
        $adminRemains = Authorization::adminExistsGiven(
            [
                'roleUsers' => [
                    (int)$this->obj->get('id') => (array)$this->obj->get('users')
                ]
            ]
        );
        if (!$adminRemains) {
            throw new \Exception(
                _('This change would leave no user with administrator access.')
            );
        }
    }
    /**
     * Present the user groups tab.
     *
     * @return void
     */
    public function roleUserGroups()
    {
        $this->renderAssocTab(
            'role-usergroup',
            _('Role User Group Associations'),
            _('User Group Name'),
            'usergroup'
        );
    }
    /**
     * Update user groups.
     *
     * @return void
     */
    public function roleUserGroupPost()
    {
        $this->assocPost('addUserGroup', 'removeUserGroup');
        // Same guard, and keyed the same way, as
        // UserGroupManagement::usergroupRolePost(). Without it this page
        // would be an unguarded route to exactly the lockout the user group
        // page refuses: detaching the last user group that carries
        // administrator access.
        //
        // adminExistsGiven() is keyed by group, not by role, and each entry
        // REPLACES that group's whole role list -- so the in-memory user
        // group list has to be inverted into "which roles does each group
        // hold after this change", not merely which groups were touched.
        $roleID = (int)$this->obj->get('id');
        $attached = array_map('intval', (array)$this->obj->get('usergroups'));
        $current = array_map(
            'intval',
            (array)Route::getIds(
                'roleusergroupassociation',
                ['roleID' => $roleID],
                'usergroupID'
            )
        );
        $groupRoles = [];
        // Attached groups gain this role. Reading the group's stored roles
        // and adding it is required: on a newly attached group the stored
        // list does not contain this role yet, and passing it unchanged
        // would understate the access the change actually grants.
        foreach ($attached as $groupID) {
            $roles = array_map(
                'intval',
                (array)self::getClass('UserGroup', $groupID)->get('roles')
            );
            $roles[] = $roleID;
            $groupRoles[$groupID] = array_values(array_unique($roles));
        }
        // Detached groups keep their other roles but lose this one. Without
        // this branch, removing the last group carrying administrator
        // access would look like no change at all.
        foreach (array_diff($current, $attached) as $groupID) {
            $roles = array_map(
                'intval',
                (array)self::getClass('UserGroup', $groupID)->get('roles')
            );
            $groupRoles[$groupID] = array_values(
                array_diff($roles, [$roleID])
            );
        }
        // assocPost only mutates the in-memory list; the save happens in
        // editPost after this returns, so throwing here aborts the change.
        $adminRemains = Authorization::adminExistsGiven(
            ['groupRoles' => $groupRoles]
        );
        if (!$adminRemains) {
            throw new \Exception(
                _('This change would leave no user with administrator access.')
            );
        }
    }
    /**
     * The edit element.
     *
     * @return void
     */
    public function edit()
    {
        // What this role grants and who holds it -- the two questions the
        // page exists to answer, and both are behind association tabs.
        $this->notes = [
            _('Role') => $this->obj->get('name'),
            _('Permissions') => (string)count((array)$this->obj->get('permissions')),
            _('Users') => (string)count((array)$this->obj->get('users')),
            _('User Groups') => (string)count((array)$this->obj->get('usergroups'))
        ];
        // Info-card notes that mirror a General-tab control, so the card
        // tracks the form instead of going stale until the next page
        // load. Keys must match $notes exactly; notes left out here (the
        // association counts, and anything no control on this page can
        // change) keep their server-rendered value.
        $this->noteSources = [
            _('Role') => '#role'
        ];
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'role-general',
            'generator' => function () {
                $this->roleGeneral();
            }
        ];

        // Permissions
        $tabData[] = [
            'name' => _('Permissions'),
            'id' => 'role-permission',
            'generator' => function () {
                $this->rolePermission();
            }
        ];

        // User Association
        $tabData[] = [
            'name' => _('User Association'),
            'id' => 'role-user',
            'generator' => function () {
                $this->roleUsers();
            }
        ];

        // User Group Association
        $tabData[] = [
            'name' => _('User Group Association'),
            'id' => 'role-usergroup',
            'generator' => function () {
                $this->roleUserGroups();
            }
        ];

        // Sites granted. Hidden without site.view for the same reason the
        // POST takes site.edit: this is a second door onto somebody else's
        // association, and role.edit is not the right that opens it. An
        // install using no sites has nothing to show here either.
        if (Authorization::can('site.view')) {
            $tabData[] = [
                'name' => _('Site Grants'),
                'id' => 'role-site',
                'generator' => function () {
                    $this->roleSites();
                }
            ];
        }
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Present the sites this role grants.
     *
     * The other end of the Site page's "Granted To -> Roles" tab. Both
     * write `siteRoleGrants`, so there is no second source of truth to
     * drift -- the same reasoning every other association tab in FOG is
     * editable from both ends for.
     *
     * @return void
     */
    public function roleSites()
    {
        $this->renderAssocTab(
            'role-site',
            _('Sites This Role Grants'),
            _('Site Name'),
            'site'
        );
    }
    /**
     * Update the sites this role grants.
     *
     * Two things are different from every other tab on this page.
     *
     * It writes through the Site, not through $this->obj: assocSetter()
     * derives the column it diffs on from the owning class name, so the
     * grant table is driven from a Site whichever end is being looked at.
     *
     * And it takes `site.edit` rather than riding in on the role.edit
     * right that reached this POST. Granting a site to a role is not a
     * change to the role -- it widens what everyone holding the role can
     * see, INCLUDING the person making the change if they hold it. Without
     * this gate, role.edit alone would be a route to widening your own
     * scope, which the Site page itself does not hand out. Same reasoning
     * as the LDAP plugin's group tabs, which gate on ldapgroup.edit.
     *
     * @return void
     */
    public function roleSitePost()
    {
        if (!Authorization::can('site.edit')) {
            throw new \Exception(
                _('You do not have permission to change site grants.')
            );
        }
        $this->assocPostInverse('Site', 'addGrantRole', 'removeGrantRole');
        // Who can see what just changed, and both answers are cached.
        SiteScope::forgetCaches();
    }
    /**
     * Gets the list of sites this role grants.
     *
     * @return void
     */
    public function getSitesList()
    {
        return $this->assocItemsList(
            'site',
            'siterolegrant',
            'siteRoleGrants',
            '`sites`.`siteID`',
            '`siteRoleGrants`.`srgSiteID`',
            '`siteRoleGrants`.`srgRoleID`',
            [
                [
                    'db' => 'siteAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Update the edit elements.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'Role',
            'ROLE_EDIT',
            _('Role updated!'),
            _('Role Update Success'),
            _('Role Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'role-general':
                        $this->roleGeneralPost();
                        break;
                    case 'role-permission':
                        $this->rolePermissionPost();
                        break;
                    case 'role-user':
                        $this->roleUserPost();
                        break;
                    case 'role-usergroup':
                        $this->roleUserGroupPost();
                        break;
                    case 'role-site':
                        $this->roleSitePost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Role update failed!'));
                }
                Authorization::resetCache();
            }
        );
    }
    /**
     * Delete this role, refusing when that would leave no user with
     * administrator access.
     *
     * @return void
     */
    public function delete()
    {
        self::checkauth();
        $this->_guardRoleRemoval([(int)$this->obj->get('id')]);
        parent::delete();
    }
    /**
     * Delete the selected roles, refusing when that would leave no user
     * with administrator access.
     *
     * @return void
     */
    public function deletemulti()
    {
        self::checkauth();
        $remitems = filter_input_array(
            INPUT_POST,
            ['remitems' => ['flags' => FILTER_REQUIRE_ARRAY]]
        );
        $this->_guardRoleRemoval(
            array_map('intval', (array)($remitems['remitems'] ?? []))
        );
        parent::deletemulti();
    }
    /**
     * Refuse role removal that would leave zero effective administrators.
     * Members of a deleted role fall back to implicit-admin access, so
     * deletion is only blocked when no implicit or '*'-role admin remains.
     *
     * @param array $roleIDs the role ids about to be removed
     *
     * @return void
     */
    private function _guardRoleRemoval($roleIDs)
    {
        $adminRemains = Authorization::adminExistsGiven(
            ['removeRoles' => (array)$roleIDs]
        );
        if ($adminRemains) {
            return;
        }
        header('Content-type: application/json');
        self::jsonSend(
            HTTPResponseCodes::HTTP_BAD_REQUEST,
            json_encode(
                [
                    'error' => _(
                        'Removing this role would leave no user with administrator access.'
                    ),
                    'title' => _('Delete Fail')
                ]
            )
        );
    }
    /**
     * Gets the user list for the users association tab.
     *
     * @return void
     */
    public function getUsersList()
    {
        return $this->assocItemsList(
            'user',
            'roleuserassociation',
            'roleUserAssoc',
            '`users`.`uID`',
            '`roleUserAssoc`.`ruaUserID`',
            '`roleUserAssoc`.`ruaRoleID`',
            [
                [
                    'db' => 'roleAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the user group list for the user groups association tab.
     *
     * @return void
     */
    public function getUserGroupsList()
    {
        return $this->assocItemsList(
            'usergroup',
            'roleusergroupassociation',
            'roleUserGroupAssoc',
            '`userGroups`.`ugID`',
            '`roleUserGroupAssoc`.`rugGroupID`',
            '`roleUserGroupAssoc`.`rugRoleID`',
            [
                [
                    'db' => 'roleAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
