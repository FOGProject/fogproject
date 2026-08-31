<?php
/**
 * User group management page — native role-based access control.
 *
 * PHP version 7.4+
 *
 * @category UserGroupManagement
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

        return self::fastmerge($fields, self::siteAddField($labelClass));
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
                    (string)filter_input(INPUT_POST, 'usergroup')
                );
                $description = trim(
                    (string)filter_input(INPUT_POST, 'description')
                );
                $exists = self::getClass('UserGroupManager')
                    ->exists($usergroup);
                if ($exists) {
                    throw new \Exception(
                        _('A user group already exists with this name!')
                    );
                }
                $UserGroup = self::getClass('UserGroup')
                    ->set('name', $usergroup)
                    ->set('description', $description);
                if (!$UserGroup->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Add user group failed!'));
                }
                $this->siteAddPost('usergroup', $UserGroup);
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
            (string)filter_input(INPUT_POST, 'usergroup')
        );
        $description = trim(
            (string)filter_input(INPUT_POST, 'description')
        );

        $exists = self::getClass('UserGroupManager')
            ->exists($usergroup);
        if ($usergroup != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(
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
            throw new \Exception(
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
        $this->notes = [
            _('User Group') => $this->obj->get('name'),
            _('Members') => (string)count((array)$this->obj->get('users')),
            _('Roles') => (string)count((array)$this->obj->get('roles'))
        ];
        // Info-card notes that mirror a General-tab control, so the card
        // tracks the form instead of going stale until the next page
        // load. Keys must match $notes exactly; notes left out here (the
        // association counts, and anything no control on this page can
        // change) keep their server-rendered value.
        $this->noteSources = [
            _('User Group') => '#usergroup'
        ];
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
        // Site
        $tabData[] = [
            'name' => _('Site'),
            'id' => 'usergroup-site',
            'generator' => function () {
                $this->usergroupSite();
            }
        ];

        // Site grants -- the opposite direction from the tab above, hence
        // the longer label. Hidden without site.view for the same reason
        // the POST takes site.edit: this is a second door onto somebody
        // else's association.
        if (Authorization::can('site.view')) {
            $tabData[] = [
                'name' => _('Site Grants'),
                'id' => 'usergroup-sitegrant',
                'generator' => function () {
                    $this->usergroupSiteGrants();
                }
            ];
        }

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
                    case 'usergroup-site':
                        $this->usergroupSitePost();
                        break;
                    case 'usergroup-sitegrant':
                        $this->usergroupSiteGrantPost();
                        break;
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
                    throw new \Exception(_('User group update failed!'));
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

    /**
     * Presents the site tab.
     *
     * @return void
     */
    public function usergroupSite()
    {
        $this->renderSiteTab('usergroup', $this->obj);
    }
    /**
     * Updates the site.
     *
     * @return void
     */
    public function usergroupSitePost()
    {
        $this->siteTabPost('usergroup', $this->obj);
    }

    /**
     * Presents the sites this user group grants to its members.
     *
     * Deliberately NOT the "Site" tab above, which sits two entries away
     * and is the easiest thing on this page to confuse it with. That one
     * says which site this user group BELONGS TO -- it is an object a
     * site-scoped admin can see and edit. This one says which sites its
     * MEMBERS GET. Same page, same word, opposite direction, which is why
     * the labels and the box titles both spell it out rather than relying
     * on the reader to hold the distinction.
     *
     * The other end of the Site page's "Granted To -> User Groups" tab.
     * Both write `siteUserGroupGrants`.
     *
     * @return void
     */
    public function usergroupSiteGrants()
    {
        $this->renderAssocTab(
            'usergroup-sitegrant',
            _("Sites Granted To This User Group's Members"),
            _('Site Name'),
            'site'
        );
    }
    /**
     * Updates the sites this user group grants to its members.
     *
     * Written through the Site, and gated on site.edit rather than the
     * usergroup.edit right that reached this POST -- granting a site to a
     * user group widens what every member can see, including the person
     * making the change if they are one. See RoleManagement::roleSitePost().
     *
     * @return void
     */
    public function usergroupSiteGrantPost()
    {
        if (!Authorization::can('site.edit')) {
            throw new \Exception(
                _('You do not have permission to change site grants.')
            );
        }
        $this->assocPostInverse(
            'Site',
            'addGrantUserGroup',
            'removeGrantUserGroup'
        );
        SiteScope::forgetCaches();
    }
    /**
     * Gets the list of sites this user group grants.
     *
     * @return void
     */
    public function getSitesList()
    {
        return $this->assocItemsList(
            'site',
            'siteusergroupgrant',
            'siteUserGroupGrants',
            '`sites`.`siteID`',
            '`siteUserGroupGrants`.`suggSiteID`',
            '`siteUserGroupGrants`.`suggGroupID`',
            [
                [
                    'db' => 'siteAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
