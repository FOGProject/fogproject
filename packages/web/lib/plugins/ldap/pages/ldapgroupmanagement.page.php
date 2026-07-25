<?php
/**
 * The LDAP group management page
 *
 * PHP version 5
 *
 * @category LDAPGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The LDAP group management page
 *
 * A directory group is edited here rather than on the LDAP server page
 * because what it grants is an ordinary association: the standard
 * association tab enumerates roles (or user groups) and checks the ones
 * this group grants. renderAssocTab() needs an enumerable entity and an
 * owner id, and on the server page the owner would be the server -- which
 * is a bystander to a group/role relationship.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/882
 *
 * @category LDAPGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPGroupManagement extends FOGPage
{
    /**
     * The node this page works on.
     *
     * @var string
     */
    public $node = 'ldapgroup';
    /**
     * Initialize object.
     *
     * @param string $name the name to construct with
     */
    public function __construct($name = '')
    {
        $this->name = _('LDAP Group Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Directory Group'),
            _('LDAP Server')
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * The LDAP servers available to scope a group to, id => name.
     *
     * @return array
     */
    private static function _serverChoices()
    {
        $ids = (array)Route::getIds('ldap', [], 'id');
        $names = (array)Route::getIds('ldap', [], 'name');
        if (count($ids) !== count($names)) {
            return [];
        }
        return array_combine($ids, $names);
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $ldapgroup = filter_input(INPUT_POST, 'ldapgroup');
        $serverID = filter_input(INPUT_POST, 'serverID');

        $labelClass = 'col-sm-3 col-form-label';

        return [
            self::makeLabel(
                $labelClass,
                'ldapgroup',
                _('Directory Group Name')
            ) => self::makeInput(
                'form-control ldapgroupname-input',
                'ldapgroup',
                _('Domain Admins'),
                'text',
                'ldapgroup',
                $ldapgroup,
                true
            ),
            self::makeLabel(
                $labelClass,
                'serverID',
                _('LDAP Server')
            ) => self::selectForm(
                'serverID',
                self::_serverChoices(),
                $serverID,
                true
            )
        ];
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'ldapgroup',
            _('Create New LDAP Group'),
            'LDAPGROUP_ADD_FIELDS',
            'LDAPGroup'
        );
    }
    /**
     * Creates new item from a modal.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'ldapgroup',
            'LDAPGROUP_ADD_FIELDS',
            'LDAPGroup'
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
            'LDAPGroup',
            'LDAPGROUP_ADD',
            _('LDAP Group added!'),
            _('LDAP Group Create Success'),
            _('LDAP Group Create Fail'),
            function (&$serverFault) {
                $name = trim(
                    filter_input(INPUT_POST, 'ldapgroup')
                );
                $serverID = (int)filter_input(INPUT_POST, 'serverID');
                if ('' === $name) {
                    throw new Exception(_('A group name is required!'));
                }
                /**
                 * Validated against the servers that exist rather than
                 * trusted, so a posted id cannot scope a group to a server
                 * that was deleted between render and submit.
                 */
                if (!array_key_exists($serverID, self::_serverChoices())) {
                    throw new Exception(_('Please select an LDAP server!'));
                }
                if (self::_groupExists($serverID, $name)) {
                    throw new Exception(
                        _('That group is already defined for this server!')
                    );
                }
                $LDAPGroup = self::getClass('LDAPGroup')
                    ->set('name', $name)
                    ->set('serverID', $serverID);
                if (!$LDAPGroup->save()) {
                    $serverFault = true;
                    throw new Exception(_('Add LDAP group failed!'));
                }
                return $LDAPGroup;
            }
        );
    }
    /**
     * Whether a group of this name is already defined for a server.
     *
     * Raw bound SQL rather than a manager exists()/count(): _buildSql()
     * turns '*' and '+' in a scalar filter value into a SQL LIKE wildcard,
     * and both are legal in an LDAP group name ('+' separates the parts of
     * a multi-valued RDN), so "Techs+Chicago" would report a clash that is
     * not one. The unique index is the real guard; this only exists to turn
     * its error into a readable message.
     *
     * @param int    $serverID the LDAP server id
     * @param string $name     the directory group name
     * @param int    $ignoreID a group id to exclude (the one being renamed)
     *
     * @return bool
     */
    private static function _groupExists($serverID, $name, $ignoreID = 0)
    {
        $rows = self::$DB
            ->query(
                'SELECT `lgID` FROM `LDAPGroups` '
                . 'WHERE `lgServerID` = :server AND `lgName` = :name '
                . 'AND `lgID` <> :ignore',
                [],
                [
                    'server' => (int)$serverID,
                    'name' => $name,
                    'ignore' => (int)$ignoreID
                ]
            )
            ->fetch('', 'fetch_all')
            ->get();
        return is_array($rows) && !empty($rows);
    }
    /**
     * The general tab.
     *
     * @return void
     */
    public function ldapGroupGeneral()
    {
        $name = (
            filter_input(INPUT_POST, 'ldapgroup') ?:
            $this->obj->get('name')
        );
        $serverID = (
            filter_input(INPUT_POST, 'serverID') ?:
            $this->obj->get('serverID')
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ldapgroup',
                _('Directory Group Name')
            ) => self::makeInput(
                'form-control ldapgroupname-input',
                'ldapgroup',
                _('Domain Admins'),
                'text',
                'ldapgroup',
                $name,
                true
            ),
            self::makeLabel(
                $labelClass,
                'serverID',
                _('LDAP Server')
            ) => self::selectForm(
                'serverID',
                self::_serverChoices(),
                $serverID,
                true
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
            'LDAPGROUP_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'LDAPGroup' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'ldapgroup-general-form',
            self::makeTabUpdateURL(
                'ldapgroup-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo $this->deleteModal();
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the general tab.
     *
     * @throws Exception
     *
     * @return void
     */
    public function ldapGroupGeneralPost()
    {
        self::checkAuthAndCSRF();
        $name = trim(
            filter_input(INPUT_POST, 'ldapgroup')
        );
        $serverID = (int)filter_input(INPUT_POST, 'serverID');
        if ('' === $name) {
            throw new Exception(_('A group name is required!'));
        }
        if (!array_key_exists($serverID, self::_serverChoices())) {
            throw new Exception(_('Please select an LDAP server!'));
        }
        if (self::_groupExists($serverID, $name, (int)$this->obj->get('id'))) {
            throw new Exception(
                _('That group is already defined for this server!')
            );
        }
        $this->obj
            ->set('name', $name)
            ->set('serverID', $serverID);
    }
    /**
     * The roles this group grants.
     *
     * @return void
     */
    public function ldapGroupRoles()
    {
        $this->renderAssocTab(
            'ldapgroup-role',
            _('LDAP Group Role Associations'),
            _('Role Name'),
            'role',
            'btn btn-primary float-end',
            _(
                'Members of this directory group receive these roles on '
                . 'sign in. Roles are recomputed from the directory each '
                . 'time, so removing someone from the group downgrades '
                . 'them at their next login.'
            )
        );
    }
    /**
     * Updates the roles this group grants.
     *
     * @return void
     */
    public function ldapGroupRolePost()
    {
        $this->assocPost('addRole', 'removeRole');
    }
    /**
     * The user groups this group grants.
     *
     * @return void
     */
    public function ldapGroupUserGroups()
    {
        $this->renderAssocTab(
            'ldapgroup-usergroup',
            _('LDAP Group User Group Associations'),
            _('User Group Name'),
            'usergroup',
            'btn btn-primary float-end',
            _(
                'Preferred over mapping straight to a role: the user group '
                . 'holds the roles, so policy stays in one place and the '
                . 'directory only decides who is in which bucket.'
            )
        );
    }
    /**
     * Updates the user groups this group grants.
     *
     * @return void
     */
    public function ldapGroupUserGroupPost()
    {
        $this->assocPost('addUserGroup', 'removeUserGroup');
    }
    /**
     * The edit element.
     *
     * @return void
     */
    public function edit()
    {
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'ldapgroup-general',
            'generator' => function () {
                $this->ldapGroupGeneral();
            }
        ];

        // Role Association
        $tabData[] = [
            'name' => _('Role Association'),
            'id' => 'ldapgroup-role',
            'generator' => function () {
                $this->ldapGroupRoles();
            }
        ];

        // User Group Association
        $tabData[] = [
            'name' => _('User Group Association'),
            'id' => 'ldapgroup-usergroup',
            'generator' => function () {
                $this->ldapGroupUserGroups();
            }
        ];
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Update the edit elements.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'LDAPGroup',
            'LDAPGROUP_EDIT',
            _('LDAP Group updated!'),
            _('LDAP Group Update Success'),
            _('LDAP Group Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'ldapgroup-general':
                        $this->ldapGroupGeneralPost();
                        break;
                    case 'ldapgroup-role':
                        $this->ldapGroupRolePost();
                        break;
                    case 'ldapgroup-usergroup':
                        $this->ldapGroupUserGroupPost();
                        break;
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('LDAP Group update failed!'));
                }
                Authorization::resetCache();
            }
        );
    }
    /**
     * Gets the role list for the role association tab.
     *
     * @return void
     */
    public function getRolesList()
    {
        return $this->assocItemsList(
            'role',
            'ldapgrouproleassociation',
            'ldapGroupRoleAssoc',
            '`roles`.`rID`',
            '`ldapGroupRoleAssoc`.`lgraRoleID`',
            '`ldapGroupRoleAssoc`.`lgraGroupID`',
            [
                [
                    'db' => 'ldapGroupAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the user group list for the user group association tab.
     *
     * @return void
     */
    public function getUserGroupsList()
    {
        return $this->assocItemsList(
            'usergroup',
            'ldapgroupusergroupassociation',
            'ldapGroupUserGroupAssoc',
            '`userGroups`.`ugID`',
            '`ldapGroupUserGroupAssoc`.`lgugUserGroupID`',
            '`ldapGroupUserGroupAssoc`.`lgugGroupID`',
            [
                [
                    'db' => 'ldapGroupAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
