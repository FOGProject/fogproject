<?php
/**
 * User group management page — native role-based access control.
 *
 * PHP version 5
 *
 * @category UserGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * User group management page — native role-based access control.
 *
 * @category UserGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserGroupManagement extends FOGPage
{
    /**
     * The node of this page.
     *
     * @var string
     */
    public $node = 'usergroup';
    /**
     * Constructor
     *
     * @param string $name The name for the page.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('User Group Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Group Name'),
            _('Group Description')
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $usergroup = filter_input(INPUT_POST, 'usergroup');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 col-form-label';

        return [
            self::makeLabel(
                $labelClass,
                'usergroup',
                _('Group Name')
            ) => self::makeInput(
                'form-control usergroupname-input',
                'usergroup',
                _('Group Name'),
                'text',
                'usergroup',
                $usergroup,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Group Description')
            ) => self::makeTextarea(
                'form-control usergroupdescription-input',
                'description',
                _('Group Description'),
                'description',
                $description
            )
        ];
    }
    /**
     * Create new user group.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'usergroup',
            _('Create New User Group'),
            'USERGROUP_ADD_FIELDS',
            'UserGroup'
        );
    }
    /**
     * Create new user group (modal form).
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'usergroup',
            'USERGROUP_ADD_FIELDS',
            'UserGroup'
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
            'UserGroup',
            'USERGROUP_ADD',
            _('User group added!'),
            _('User Group Create Success'),
            _('User Group Create Fail'),
            function (&$serverFault) {
                $usergroup = trim(
                    filter_input(INPUT_POST, 'usergroup')
                );
                $description = trim(
                    filter_input(INPUT_POST, 'description')
                );
                $exists = self::getClass('UserGroupManager')
                    ->exists($usergroup);
                if ($exists) {
                    throw new Exception(
                        _('A user group already exists with this name!')
                    );
                }
                $UserGroup = self::getClass('UserGroup')
                    ->set('name', $usergroup)
                    ->set('description', $description);
                if (!$UserGroup->save()) {
                    $serverFault = true;
                    throw new Exception(_('Add user group failed!'));
                }
                return $UserGroup;
            }
        );
    }
    /**
     * Displays the user group general tab.
     *
     * @return void
     */
    public function usergroupGeneral()
    {
        $usergroup = (
            filter_input(INPUT_POST, 'usergroup') ?:
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
                'usergroup',
                _('Group Name')
            ) => self::makeInput(
                'form-control usergroupname-input',
                'usergroup',
                _('Group Name'),
                'text',
                'usergroup',
                $usergroup,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Group Description')
            ) => self::makeTextarea(
                'form-control usergroupdescription-input',
                'description',
                _('Group Description'),
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
            'USERGROUP_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'UserGroup' => &$this->obj
            ]
        );

        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderGeneralForm('usergroup', $rendered, $buttons);
    }
    /**
     * Updates the user group general element.
     *
     * @return void
     */
    public function usergroupGeneralPost()
    {
        self::checkAuthAndCSRF();
        $usergroup = trim(
            filter_input(INPUT_POST, 'usergroup')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        $exists = self::getClass('UserGroupManager')
            ->exists($usergroup);
        if ($usergroup != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A user group with this name already exists!')
            );
        }
        $this->obj
            ->set('name', $usergroup)
            ->set('description', $description);
    }
    /**
     * Present the members (users) tab.
     *
     * @return void
     */
    public function usergroupMembers()
    {
        $this->renderAssocTab(
            'usergroup-member',
            _('User Group Member Associations'),
            _('User Name'),
            'user'
        );
    }
    /**
     * Update members.
     *
     * @return void
     */
    public function usergroupMemberPost()
    {
        $this->assocPost('addUser', 'removeUser');
        // assocPost only mutates the in-memory list; the save happens in
        // editPost after this returns, so throwing here aborts the change.
        $adminRemains = Authorization::adminExistsGiven(
            [
                'groupUsers' => [
                    (int)$this->obj->get('id') => (array)$this->obj->get('users')
                ]
            ]
        );
        if (!$adminRemains) {
            throw new Exception(
                _('This change would leave no user with administrator access.')
            );
        }
    }
    /**
     * Present the roles tab.
     *
     * @return void
     */
    public function usergroupRoles()
    {
        $this->renderAssocTab(
            'usergroup-role',
            _('User Group Role Associations'),
            _('Role Name'),
            'role'
        );
    }
    /**
     * Update roles.
     *
     * @return void
     */
    public function usergroupRolePost()
    {
        $this->assocPost('addRole', 'removeRole');
        // assocPost only mutates the in-memory list; the save happens in
        // editPost after this returns, so throwing here aborts the change.
        $adminRemains = Authorization::adminExistsGiven(
            [
                'groupRoles' => [
                    (int)$this->obj->get('id') => (array)$this->obj->get('roles')
                ]
            ]
        );
        if (!$adminRemains) {
            throw new Exception(
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
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'usergroup-general',
            'generator' => function () {
                $this->usergroupGeneral();
            }
        ];

        // Members
        $tabData[] = [
            'name' => _('Members'),
            'id' => 'usergroup-member',
            'generator' => function () {
                $this->usergroupMembers();
            }
        ];

        // Roles
        $tabData[] = [
            'name' => _('Role Association'),
            'id' => 'usergroup-role',
            'generator' => function () {
                $this->usergroupRoles();
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
            'UserGroup',
            'USERGROUP_EDIT',
            _('User group updated!'),
            _('User Group Update Success'),
            _('User Group Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'usergroup-general':
                        $this->usergroupGeneralPost();
                        break;
                    case 'usergroup-member':
                        $this->usergroupMemberPost();
                        break;
                    case 'usergroup-role':
                        $this->usergroupRolePost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('User group update failed!'));
                }
                Authorization::resetCache();
            }
        );
    }
    /**
     * Delete this user group, refusing when that would leave no user with
     * administrator access.
     *
     * @return void
     */
    public function delete()
    {
        self::checkauth();
        $this->_guardGroupRemoval([(int)$this->obj->get('id')]);
        parent::delete();
    }
    /**
     * Delete the selected user groups, refusing when that would leave no
     * user with administrator access.
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
        $this->_guardGroupRemoval(
            array_map('intval', (array)($remitems['remitems'] ?? []))
        );
        parent::deletemulti();
    }
    /**
     * Refuse user group removal that would leave zero effective
     * administrators. Members of a deleted group lose only the roles that
     * group conferred, so deletion is only blocked when no implicit or
     * '*'-role admin would remain.
     *
     * @param array $groupIDs the user group ids about to be removed
     *
     * @return void
     */
    private function _guardGroupRemoval($groupIDs)
    {
        $adminRemains = Authorization::adminExistsGiven(
            ['removeGroups' => (array)$groupIDs]
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
                        'Removing this group would leave no user with administrator access.'
                    ),
                    'title' => _('Delete Fail')
                ]
            )
        );
    }
    /**
     * Gets the user list for the members association tab.
     *
     * @return void
     */
    public function getUsersList()
    {
        return $this->assocItemsList(
            'user',
            'usergroupmember',
            'userGroupMembers',
            '`users`.`uID`',
            '`userGroupMembers`.`ugmUserID`',
            '`userGroupMembers`.`ugmGroupID`',
            [
                [
                    'db' => 'usergroupAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the role list for the roles association tab.
     *
     * @return void
     */
    public function getRolesList()
    {
        return $this->assocItemsList(
            'role',
            'roleusergroupassociation',
            'roleUserGroupAssoc',
            '`roles`.`rID`',
            '`roleUserGroupAssoc`.`rugRoleID`',
            '`roleUserGroupAssoc`.`rugGroupID`',
            [
                [
                    'db' => 'usergroupAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
