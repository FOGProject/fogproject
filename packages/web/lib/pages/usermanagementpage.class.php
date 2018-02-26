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
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $display = filter_input(
            INPUT_POST,
            'display'
        );
        $fields = [
            '<label class="col-sm-2 control-label" for="name">'
            . _('User Name')
            . '</label>' => '<input type="text" class="'
            . 'form-control username-input" name='
            . '"name" value="'
            . $name
            . '" autocomplete="off" id="name" minlength="3" maxlength="40" required/>',
            '<label class="col-sm-2 control-label" for="display">'
            . _('Friendly Name')
            . '</label>' => '<input type="text" class="'
            . 'form-control friendlyname-input" name="'
            . 'display" value="'
            . $display
            . '" autocomplete="off" id="display"/>',
            '<label class="col-sm-2 control-label" for="password">'
            . _('User Password')
            . '</label>' => '<div class="input-group"><input type="password" class="'
            . 'form-control password-input1" name="password" value='
            . '"" autocomplete='
            . '"off" id="password" required/></div>',
            '<label class="col-sm-2 control-label" for="password2">'
            . _('User Password (confirm)')
            . '</label>' => '<div class="input-group"><input type="password" class="'
            . 'form-control password-input2" name="password_confirm" beEqualTo="password" value='
            . '"" autocomplete="off" id="password2" required/></div>',
            '<label class="col-sm-2 control-label" for="apion">'
            . _('User API Enabled')
            . '</label>' => '<input type="checkbox" class="'
            . 'api-enabled" name="apienabled" id="'
            . 'apion"'
            . (
                isset($_POST['apienabled']) ?
                ' checked' :
                ''
            )
            . '/>'
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
        echo '<form id="user-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- User General -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h3 class="box-title">';
        echo _('Create New User');
        echo '</h3>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<!-- User General -->';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="send">' . _('Create') . '</button>';
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
        $name = strtolower(
            trim(
                filter_input(INPUT_POST, 'name')
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
            if (!$name) {
                throw new Exception(
                    _('A user name is required!')
                );
            }
            $test = preg_match(
                '/(?=^.{3,40}$)^[\w][\w0-9]*[._-]?[\w0-9]*[.]?[\w0-9]+$/i',
                $name
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
            if (self::getClass('UserManager')->exists($name)) {
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
                ->set('name', $name)
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );

        $name = filter_input(INPUT_POST, 'name') ?: $this->obj->get('name');
        $display = filter_input(INPUT_POST, 'display') ?: $this->obj->get('display');

        $fields = [
            '<label for="name" class="col-sm-2 control-label">'
            . _('User Name')
            . '</label>' => '<input id="name" class="form-control" placeholder="'
            . _('User Name')
            . '" type="text" value="'
            . $name
            . '" name="name" minlength="3" maxlength="40" required/>',
            '<label for="display" class="col-sm-2 control-label">'
            . _('Friendly Name')
            . '</label>' => '<input id="display" class="form-control" placeholder="'
            . _('Friendly Name')
            . '" name="display" value="'
            . $display
            . '"/>'
        ];
        self::$HookManager->processEvent(
            'USER_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'User' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<form id="user-general-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('user-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="general-send">'
            . _('Update')
            . '</button>';
        echo '<button class="btn btn-danger pull-right" id="general-delete">'
            . _('Delete')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Change password div element.
     *
     * @return void
     */
    public function userChangePW()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $fields = [
            '<label for="password" class="col-sm-2 control-label">'
            . _('User Password')
            . '</label>' => '<div class="input-group"><input id="password" class="form-control" placeholder="'
            . _('User Password')
            . '" type="password" value="" name="password" required/></div>',
            '<label for="passwordConfirm" class="col-sm-2 control-label">'
            . _('User Password (confirm)')
            . '</label>' => '<div class="input-group"><input id="passwordConfirm" class="form-control" placeholder="'
            . _('User Password (confirm)')
            . '" type="password" value="" name="password_confirm" beEqualTo="password" required/></div>'
        ];
        self::$HookManager->processEvent(
            'USER_CHANGEPW_FIELDS',
            [
                'fields' => &$fields,
                'User' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<form id="user-changepw-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('user-changepw', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="changepw-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * API div element.
     *
     * @return void
     */
    public function userAPI()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );

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

        $fields = [
            '<label for="apion" class="col-sm-2 control-label">'
            . _('User API Enabled')
            . '</label>' => '<input id="apion" type="checkbox" name="apienabled"'
            . $apienabled
            . '/>',
            '<label for="token" class="col-sm-2 control-label">'
            . _('User API Token')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control token" name="'
            . 'apitoken" id="token" readonly value="'
            . $token
            . '"/><div class="input-group-btn">'
            . '<button class="btn btn-warning resettoken" type="button">'
            . _('Reset Token')
            . '</button>'
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
        echo '<div class="box box-solid">';
        echo '<form id="user-api-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('user-api', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="api-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
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
            [
                'tabData' => &$tabData
            ]
        );

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * User General Post
     *
     * @return void
     */
    public function userGeneralPost()
    {
        $name = strtolower(
            trim(
                filter_input(INPUT_POST, 'name')
            )
        );
        $display = trim(
            filter_input(INPUT_POST, 'display')
        );
        if ($this->obj->get('name') != $name
            && self::getClass('UserManager')->exists(
                $name,
                $this->obj->get('id')
            )
        ) {
            throw new Exception(
                _('A user already exists with this name')
            );
        }
        $this->obj
            ->set('name', $name)
            ->set('display', $display);
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
