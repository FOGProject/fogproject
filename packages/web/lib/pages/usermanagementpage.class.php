<?php
/**
 * User management page.
 *
 * PHP version 5
 *
 * @category UserManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * User management page.
 *
 * @category UserManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserManagementPage extends FOGPage
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
        $this->name = _('User Management');
        parent::__construct($this->name);
        global $id;
        $this->headerData = [
            _('Username'),
            _('Friendly Name'),
            _('API?')
        ];
        $this->templates = [
            '',
            '',
            ''
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
    }
    /**
     * Page to enable creating a new user.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New User');
        $user = filter_input(
            INPUT_POST,
            'user'
        );
        $display = filter_input(
            INPUT_POST,
            'display'
        );
        $labelClass = 'col-sm-2 control-label';
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
                40
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
                false
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
        self::$HookManager
            ->processEvent(
                'USER_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'User' => self::getClass('User')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="user-create">';
        echo self::makeFormTag(
            'form-horizontal',
            'user-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box-body">';
        echo '<!-- User General -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New User');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<!-- User General -->';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * Actually create the new user.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('USER_ADD_POST');
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
            if (!$user) {
                throw new Exception(
                    _('A user name is required!')
                );
            }
            $test = preg_match(
                '/(?=^.{3,40}$)^[\w][\w0-9]*[._-]?[\w0-9]*[.]?[\w0-9]+$/i',
                $user
            );
            if (!$test) {
                throw new Exception(
                    sprintf(
                        '%s.<br/>%s.<br/>%s.<br/>%s.<br/>%s.',
                        _('Username does not meet requirements'),
                        _('Username must start with a word character'),
                        _('Username must be at least 3 characters'),
                        _('Username must be less than 41 characters'),
                        _('Username cannot contain contiguous special characters')
                    )
                );
            }
            if (self::getClass('UserManager')->exists($user)) {
                throw new Exception(
                    _('A username already exists with this name!')
                );
            }
            if (!$password) {
                throw new Exception(
                    _('A password is required!')
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
                throw new Exception(
                    _('Add user failed!')
                );
            }
            $code = 201;
            $hook = 'USER_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('User added!'),
                    'title' => _('User Create Success'),
                    'id' => $User->get('id')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'USER_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('User Create Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'User' => &$User,
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        http_response_code($code);
        unset($User);
        echo $msg;
        exit;
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

        $labelClass = 'col-sm-2 control-label';

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
                40
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

        self::$HookManager->processEvent(
            'USER_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'User' => &$this->obj
            ]
        );

        $rendered = self::formFields($fields);
        unset($fields);

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-right'
        );

        echo '<div class="box box-solid">';
        echo self::makeFormTag(
            'form-horizontal',
            'user-general-form',
            self::makeTabUpdateURL(
                'user-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * User General Post
     *
     * @return void
     */
    public function userGeneralPost()
    {
        $user = strtolower(
            trim(
                filter_input(INPUT_POST, 'user')
            )
        );
        $display = trim(
            filter_input(INPUT_POST, 'display')
        );
        if ($this->obj->get('name') != $user
            && self::getClass('UserManager')->exists(
                $user,
                $this->obj->get('id')
            )
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
        $labelClass = 'col-sm-2 control-label';

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
                false
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

        self::$HookManager->processEvent(
            'USER_CHANGEPW_FIELDS',
            [
                'fields' => &$fields,
                'User' => &$this->obj
            ]
        );

        $rendered = self::formFields($fields);
        unset($fields);

        $buttons = self::makeButton(
            'changepw-send',
            _('Update'),
            'btn btn-primary'
        );

        echo '<div class="box box-solid">';
        echo self::makeFormTag(
            'form-horizontal',
            'user-changepw-form',
            self::makeTabUpdateURL(
                'user-changepw',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * User change password post.
     *
     * @return void
     */
    public function userChangePWPost()
    {
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

        $labelClass = 'col-sm-2 control-label';

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
            . '<div class="input-group-btn">'
            . self::makeButton(
                'resettoken',
                _('Reset Token'),
                'btn btn-warning resettoken'
            )
            . '</div>'
            . '</div>'
        ];

        self::$HookManager->processEvent(
            'USER_API_FIELDS',
            [
                'fields' => &$fields,
                'User' => &$this->obj
            ]
        );

        $rendered = self::formFields($fields);
        unset($fields);

        $buttons = self::makeButton(
            'api-send',
            _('Update'),
            'btn btn-primary'
        );

        echo '<div class="box box-solid">';
        echo self::makeFormTag(
            'form-horizontal',
            'user-api-form',
            self::makeTabUpdateURL(
                'user-api',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * User Change API Post
     *
     * @return void
     */
    public function userAPIPost()
    {
        $apien = (int)isset($_POST['apienabled']);
        $apitoken = base64_decode(
            filter_input(INPUT_POST, 'apitoken')
        );
        $this->obj
            ->set('api', $apien)
            ->set('token', $apitoken);
    }
    /**
     * Enable user to edit a user.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );

        if (!$this->obj->get('token')) {
            $this->obj
                ->set('token', self::createSecToken())
                ->save();
        }

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'user-general',
            'generator' => function() {
                $this->userGeneral();
            }
        ];

        // Password Changing
        $tabData[] = [
            'name' => _('Password'),
            'id' => 'user-changepw',
            'generator' => function() {
                $this->userChangePW();
            }
        ];

        // API Updating
        $tabData[] = [
            'name' => _('API'),
            'id' => 'user-api',
            'generator' => function() {
                $this->userAPI();
            }
        ];

        self::$HookManager->processEvent(
            'USER_TAB_DATA',
            ['tabData' => &$tabData]
        );

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually save the edits.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager
            ->processEvent(
                'USER_EDIT_POST',
                ['User' => &$this->obj]
            );
        $serverFault = false;
        try {
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
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('User update failed!'));
            }
            $code = 201;
            $hook = 'USER_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('User updated!'),
                    'title' => _('User Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'USER_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('User Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'User' => &$this->obj,
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        http_response_code($code);
        echo $msg;
        exit;
    }
}
