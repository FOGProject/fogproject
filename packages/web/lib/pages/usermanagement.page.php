<?php
/**
 * User management page.
 *
 * PHP version 5
 *
 * @category UserManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * User management page.
 *
 * @category UserManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserManagement extends FOGPage
{
    /**
     * The node this works off of.
     *
     * @var string
     */
    public $node = 'user';
    /**
     * Initializes the user class.
     *
     * @param string $name The name to load this as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'User Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Username'),
            _('Friendly Name'),
            _('API?')
        ];
        $this->attributes = [
            [],
            [],
            ['width' => 22]
        ];
        $types = [];
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            ['types' => &$types]
        );
        if ($this->obj instanceof User
            && $this->obj->isValid()
            && !$this->obj->get('token')
        ) {
            $this->obj
                ->set('token', self::createSecToken())
                ->save();
        }
        return $this;
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $user = filter_input(INPUT_POST, 'user');
        $display = filter_input(INPUT_POST, 'display');

        $labelClass = 'col-sm-3 col-form-label';

        return [
            self::makeLabel(
                $labelClass,
                'user',
                _('User Name')
            ) => self::makeInput(
                'form-control username-input',
                'user',
                _('User Name'),
                'text',
                'user',
                $user,
                true,
                false,
                3,
                50,
                'beRegexTo="'
                . '(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[A-Za-z\d][\w\s\-\.]*[A-Za-z\d]$"'
                . ' requirements="'
                . _('Username must begin with 2 numbers or letters.')
                . ' '
                . _('Username must end with a number or letter.')
                . ' '
                . _('You may use _, ., -, or a space between.')
                . ' '
                . _('It must be between 3 and 50 characters.')
                . '"'
            ),
            self::makeLabel(
                $labelClass,
                'display',
                _('Friendly Name')
            ) => self::makeInput(
                'form-control userdisplay-input',
                'display',
                _('Friendly Name'),
                'text',
                'display',
                $display,
                false,
                false
            ),
            self::makeLabel(
                $labelClass,
                'password',
                _('User Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control password1-input',
                'password',
                _('User Password'),
                'password',
                'password',
                '',
                true,
                false,
                (int)self::getSetting('FOG_USER_MINPASSLENGTH'),
                -1,
                'beRegexTo="'
                . self::getSetting('FOG_USER_VALIDPASSCHARS')
                . '" requirements="'
                . _(self::getSetting('FOG_USER_VALIDPASSHELPMSG'))
                . '"'
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'password_name',
                _('User Password')
                . '<br/>('
                . _('confirm')
                . ')'
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control password2-input',
                'password_name',
                _('User Password'),
                'password',
                'password_name',
                '',
                true,
                false,
                -1,
                -1,
                'beEqualTo="password"'
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'apienabled',
                _('User API Enable')
            ) => self::makeInput(
                'apienabled-input',
                'apienabled',
                '',
                'checkbox',
                'apienabled',
                '',
                false,
                false,
                -1,
                -1,
                (isset($_POST['apienabled']) ? 'checked' : '')
            )
        ];
    }
    /**
     * Page to enable creating a new user.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'user',
            _('Create New User'),
            'USER_ADD_FIELDS',
            'User'
        );
    }
    /**
     * Page to enable creating a new user.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'user',
            'USER_ADD_FIELDS',
            'User'
        );
    }
    /**
     * Actually create the new user.
     *
     * @return void
     */
    public function addPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('USER_ADD_POST');
        $userPat = "/(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[A-Za-z\d][\w\s\-\.]*[A-Za-z\d]$/";
        $userErr =  _('Username must begin with 2 numbers or letters.')
            . ' '
            . _('Username must end with a number or letter.')
            . ' '
            . _('You may use _, ., -, or a space between.')
            . ' '
            . _('It must be between 3 and 50 characters.');
        $user = strtolower(
            trim(
                filter_input(INPUT_POST, 'user')
            )
        );
        $password = trim(
            filter_input(INPUT_POST, 'password')
        );
        $friendly = trim(
            filter_input(INPUT_POST, 'display')
        );
        $apien = (int)isset($_POST['apienabled']);
        $token = self::createSecToken();

        $serverFault = false;
        try {
            if (!preg_match($userPat, $user)) {
                throw new Exception($userErr);
            }
            $exists = self::getClass('UserManager')
                ->exists($user);
            if ($exists) {
                throw new Exception(
                    _('A username already exists with this name!')
                );
            }
            $User = self::getClass('User')
                ->set('name', $user)
                ->set('password', $password)
                ->set('display', $friendly)
                ->set('api', $apien)
                ->set('type', 0)
                ->set('token', $token);
            if (!$User->save()) {
                $serverFault = true;
                throw new Exception(_('Add user failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'USER_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('User added!'),
                    'title' => _('User Create Success'),
                    'id' => $User->get('id')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'USER_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('User Create Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'User' => &$User,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * User general div element.
     *
     * @return void
     */
    public function userGeneral()
    {
        $user = (
            filter_input(INPUT_POST, 'user') ?:
            $this->obj->get('name')
        );

        $display = (
            filter_input(INPUT_POST, 'display') ?:
            $this->obj->get('display')
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'user',
                _('User Name')
            ) => self::makeInput(
                'form-control username-input',
                'user',
                _('User Name'),
                'text',
                'user',
                $user,
                true,
                false,
                3,
                50,
                'beRegexTo="'
                . '(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[A-Za-z\d][\w\s\-\.]*[A-Za-z\d]$"'
                . ' requirements="'
                . _('Username must begin with 2 numbers or letters.')
                . ' '
                . _('Username must end with a number or letter.')
                . ' '
                . _('You may use _, ., -, or a space between.')
                . ' '
                . _('It must be between 3 and 50 characters.')
                . '"'
            ),
            self::makeLabel(
                $labelClass,
                'display',
                _('Friendly Name')
            ) => self::makeInput(
                'form-control userdisplay-input',
                'display',
                _('Friendly Name'),
                'text',
                'display',
                $display,
                false,
                false
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
            'USER_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'User' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderGeneralForm('user', $rendered, $buttons);
    }
    /**
     * User General Post
     *
     * @return void
     */
    public function userGeneralPost()
    {
        self::checkAuthAndCSRF();
        $userPat = "/(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[A-Za-z\d][\w\s\-\.]*[A-Za-z\d]$/";
        $userErr =  _('Username must begin with 2 numbers or letters.')
            . ' '
            . _('Username must end with a number or letter.')
            . ' '
            . _('You may use _, ., -, or a space between.')
            . ' '
            . _('It must be between 3 and 50 characters.');
        $user = strtolower(
            trim(
                filter_input(INPUT_POST, 'user')
            )
        );
        $display = trim(
            filter_input(INPUT_POST, 'display')
        );
        if (!preg_match($userPat, $user)) {
            throw new Exception($userErr);
        }
        $exists = self::getClass('UserManager')
            ->exists($user);
        if ($user != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A user already exists with this name')
            );
        }
        $this->obj
            ->set('name', $user)
            ->set('display', $display);
    }
    /**
     * Change password div element.
     *
     * @return void
     */
    public function userChangePW()
    {
        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'password',
                _('User Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control password1-input',
                'password',
                _('User Password'),
                'password',
                'password',
                '',
                true,
                false,
                (int)self::getSetting('FOG_USER_MINPASSLENGTH'),
                -1,
                'beRegexTo="'
                . self::getSetting('FOG_USER_VALIDPASSCHARS')
                . '" requirements="'
                . _(self::getSetting('FOG_USER_VALIDPASSHELPMSG'))
                . '"'
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'password_name',
                _('User Password')
                . '<br/>('
                . _('confirm')
                . ')'
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control password2-input',
                'password_name',
                _('User Password'),
                'password',
                'password_name',
                '',
                true,
                false,
                -1,
                -1,
                'beEqualTo="password"'
            )
            . '</div>'
        ];

        $buttons = self::makeButton(
            'changepw-send',
            _('Update'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'USER_CHANGEPW_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'User' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'user-changepw-form',
            self::makeTabUpdateURL(
                'user-changepw',
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
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * User change password post.
     *
     * @return void
     */
    public function userChangePWPost()
    {
        self::checkAuthAndCSRF();
        $password = trim(
            filter_input(INPUT_POST, 'password')
        );
        $this->obj
            ->set('password', $password);
    }
    /**
     * API div element.
     *
     * @return void
     */
    public function userAPI()
    {
        $apienabled = (
            isset($_POST['apienabled']) ?
            ' checked' :
            (
                $this->obj->get('api') ?
                ' checked' :
                ''
            )
        );
        $token = base64_encode(
            $this->obj->get('token')
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'apienabled',
                _('User API Enable')
            ) => self::makeInput(
                'apienabled-input',
                'apienabled',
                '',
                'checkbox',
                'apienabled',
                '',
                false,
                false,
                -1,
                -1,
                $apienabled
            ),
            self::makeLabel(
                $labelClass,
                'apitoken',
                _('User API Token')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control token',
                'apitoken',
                _('User API Token'),
                'text',
                'apitoken',
                $token,
                false,
                false,
                -1,
                -1,
                '',
                true,
                false
            )
            . self::makeButton(
                'resettoken',
                _('Reset Token'),
                'btn btn-warning resettoken'
            )
            . '</div>'
        ];

        $buttons = self::makeButton(
            'api-send',
            _('Update'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'USER_API_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'User' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'user-api-form',
            self::makeTabUpdateURL(
                'user-api',
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
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * User Change API Post
     *
     * @return void
     */
    public function userAPIPost()
    {
        self::checkAuthAndCSRF();
        $apien = (int)isset($_POST['apienabled']);
        $apitoken = base64_decode(
            filter_input(INPUT_POST, 'apitoken')
        );
        $this->obj
            ->set('api', $apien)
            ->set('token', $apitoken);
    }
    /**
     * Present the roles tab.
     *
     * @return void
     */
    public function userRole()
    {
        $this->renderAssocTab(
            'user-role',
            _('Role Associations'),
            _('Role Name'),
            'role'
        );
    }
    /**
     * Update the user's role associations.
     *
     * @return void
     */
    public function userRolePost()
    {
        $this->assocPost('addRole', 'removeRole');
        // assocPost only mutates the in-memory list; the save happens in
        // editPost after this returns, so throwing here aborts the change.
        $adminRemains = Authorization::adminExistsGiven(
            [
                'userRoles' => [
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
     * Present the user groups tab.
     *
     * @return void
     */
    public function userGroup()
    {
        $this->renderAssocTab(
            'user-group',
            _('User Group Associations'),
            _('Group Name'),
            'usergroup'
        );
    }
    /**
     * Update the user's group associations.
     *
     * @return void
     */
    public function userGroupPost()
    {
        $this->assocPost('addGroup', 'removeGroup');
        // assocPost only mutates the in-memory list; the save happens in
        // editPost after this returns, so throwing here aborts the change.
        $adminRemains = Authorization::adminExistsGiven(
            [
                'userGroups' => [
                    (int)$this->obj->get('id') => (array)$this->obj->get('usergroups')
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
     * Enable user to edit a user.
     *
     * @return void
     */
    public function edit()
    {
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'user-general',
            'generator' => function () {
                $this->userGeneral();
            }
        ];

        // Password Changing
        $tabData[] = [
            'name' => _('Password'),
            'id' => 'user-changepw',
            'generator' => function () {
                $this->userChangePW();
            }
        ];

        // API Updating
        $tabData[] = [
            'name' => _('API'),
            'id' => 'user-api',
            'generator' => function () {
                $this->userAPI();
            }
        ];

        // Roles
        $tabData[] = [
            'name' => _('Roles'),
            'id' => 'user-role',
            'generator' => function () {
                $this->userRole();
            }
        ];

        // User Groups
        $tabData[] = [
            'name' => _('Groups'),
            'id' => 'user-group',
            'generator' => function () {
                $this->userGroup();
            }
        ];
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Actually save the edits.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'User',
            'USER_EDIT',
            _('User updated!'),
            _('User Update Success'),
            _('User Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'user-general':
                        $this->userGeneralPost();
                        break;
                    case 'user-changepw':
                        $this->userChangePWPost();
                        break;
                    case 'user-api':
                        $this->userAPIPost();
                        break;
                    case 'user-role':
                        $this->userRolePost();
                        break;
                    case 'user-group':
                        $this->userGroupPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('User update failed!'));
                }
                if ('user-role' === $tab || 'user-group' === $tab) {
                    Authorization::resetCache();
                }
            }
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
            'roleuserassociation',
            'roleUserAssoc',
            '`roles`.`rID`',
            '`roleUserAssoc`.`ruaRoleID`',
            '`roleUserAssoc`.`ruaUserID`',
            [
                [
                    'db' => 'userAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the user group list for the groups association tab.
     *
     * @return void
     */
    public function getGroupsList()
    {
        return $this->assocItemsList(
            'usergroup',
            'usergroupmember',
            'userGroupMembers',
            '`userGroups`.`ugID`',
            '`userGroupMembers`.`ugmGroupID`',
            '`userGroupMembers`.`ugmUserID`',
            [
                [
                    'db' => 'userAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
