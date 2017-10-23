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
        if ($id) {
            $linkstr = "$this->linkformat#user-%s";
            $this->subMenu = array(
                sprintf(
                    $linkstr,
                    'general'
                ) => self::$foglang['General'],
                sprintf(
                    $linkstr,
                    'changepw'
                ) => _('Change password'),
                sprintf(
                    $linkstr,
                    'api'
                ) => _('API Settings'),
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('Friendly Name') => (
                    $this->obj->get('display') ?
                    $this->obj->get('display') :
                    _('No friendly name defined')
                ),
                self::$foglang['User'] => $this->obj->get('name'),
            );
        }
        self::$HookManager
            ->processEvent(
                'SUB_MENULINK_DATA',
                array(
                    'menu' => &$this->menu,
                    'submenu' => &$this->subMenu,
                    'id' => &$this->id,
                    'notes' => &$this->notes,
                    'object'=> &$this->obj,
                    'linkformat' => &$this->linkformat,
                    'delformat' => &$this->delformat
                )
            );
        $this->headerData = array(
            '<label for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler"/>'
            . '</label>',
            _('API?'),
            _('Username'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<label for="user-${id}">'
            . '<input type="checkbox" name="user[]" value='
            . '"${id}" class="toggle-action" id="user-${id}"/>'
            . '</label>',
            '${apiYes}',
            sprintf(
                '<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>',
                $this->node,
                $this->id,
                _('Edit User')
            ),
            '${friendly}'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false form-group',
                'width' => 16
            ),
            array(
                'width' => 22
            ),
            array(
                'width' => 22
            ),
            array(),
            array()
        );
        $types = array();
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            array('types' => &$types)
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $User the object to use
         *
         * @return void
         */
        self::$returnData = function (&$User) use (&$types) {
            if (count($types) > 0
                && in_array($User->type, $types)
            ) {
                return;
            }
            $this->data[] = array(
                'id' => $User->id,
                'apiYes' => $User->api ? _('Yes') : _('No'),
                'name' => $User->name,
                'friendly' => $User->display
            );
            unset($User);
        };
    }
    /**
     * Page to enable creating a new user.
     *
     * @return void
     */
    public function add()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('New User');
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->data = array();
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $display = filter_input(
            INPUT_POST,
            'display'
        );
        $fields = array(
            '<label for="name">'
            . _('User Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control username-input" name='
            . '"name" value="'
            . $name
            . '" autocomplete="off" id="name" required/>'
            . '</div>',
            '<label for="display">'
            . _('Friendly Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control friendlyname-input" name="'
            . 'display" value="'
            . $display
            . '" autocomplete="off" id="display"/>'
            . '</div>',
            '<label for="password">'
            . _('User Password')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input1" name="password" value='
            . '"" autocomplete='
            . '"off" id="password" required/>'
            . '</div>',
            '<label for="password2">'
            . _('User Password (confirm)')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input2" name="password_confirm" value='
            . '"" autocomplete="off" required/>'
            . '</div>',
            '<label for="apion">'
            . _('User API Enabled')
            . '</label>' => '<input type="checkbox" class="'
            . 'api-enabled" name="apienabled" id="'
            . 'apion"'
            . (
                isset($_POST['apienabled']) ?
                ' checked' :
                ''
            )
            . '/>',
            '<label for="add">'
            . _('Create user?')
            . '</label> ' => '<button class="btn btn-info btn-block" name="'
            . 'add" id="add" type="submit">'
            . _('Create')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'USER_ADD',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        unset($fields);
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo '<input type="text" name="fakeusernameremembered" class="fakes"/>';
        echo '<input type="password" name="fakepasswordremembered" class="fakes"/>';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually create the new user.
     *
     * @return void
     */
    public function addPost()
    {
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
                throw new Exception(
                    _('Add user failed!')
                );
            }
            $hook = 'USER_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('User added!'),
                    'title' => _('User Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'USER_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('User Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('User' => &$User)
            );
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
        $this->title = _('User General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $fields = array(
            '<label for="name">'
            . _('User Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control username-input" name='
            . '"name" value="'
            . $this->obj->get('name')
            . '" autocomplete="off" id="name" required/>'
            . '</div>',
            '<label for="display">'
            . _('Friendly Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control friendlyname-input" name="'
            . 'display" value="'
            . $this->obj->get('display')
            . '" autocomplete="off" id="display"/>'
            . '</div>',
            '<label for="updategen">'
            . _('Update General?')
            . '</label> ' => '<button class="btn btn-info btn-block" name="'
            . 'update" id="updategen" type="submit">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'USER_FIELDS',
                array(
                    'fields' => &$fields,
                    'User' => &$this->obj
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'USER_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="user-general">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=user-general">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
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
        $this->title = _('User Change Password');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $fields = array(
            '<label for="password">'
            . _('User Password')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input1" name="password" value='
            . '"" autocomplete='
            . '"off" id="password" required/>'
            . '</div>',
            '<label for="passwordConfirm">'
            . _('User Password (confirm)')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input2" name="password_confirm" value='
            . '"" autocomplete="off" id="passwordConfirm" required/>'
            . '</div>',
            '<label for="updatepw">'
            . _('Update Password?')
            . '</label> ' => '<button class="btn btn-info btn-block" name="'
            . 'update" id="updatepw" type="submit">'
            . _('Update')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'USER_PW_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div id="user-changepw" class="tab-pane fade">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=user-changepw">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
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
        $this->title = _('User API Settings');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $fields = array(
            '<label for="apion">'
            . _('User API Enabled')
            . '</label>' => '<input type="checkbox" class="'
            . 'api-enabled" name="apienabled" id="'
            . 'apion"'
            . (
                $this->obj->get('api') ?
                ' checked' :
                ''
            )
            . '/>',
            '<label for="token">'
            . _('User API Token')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control token" name="'
            . 'apitoken" id="token" readonly value="'
            . base64_encode(
                $this->obj->get('token')
            )
            . '"/>'
            . '<div class="input-group-btn">'
            . '<button class="btn btn-warning resettoken" type="button">'
            . _('Reset Token')
            . '</button>'
            . '</div>'
            . '</div>',
            '<label for="updateapi">'
            . _('Update API?')
            . '</label> ' => '<button class="btn btn-info btn-block" name="'
            . 'update" id="updateapi" type="submit">'
            . _('Update')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'USER_API_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div id="user-api" class="tab-pane fade">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=user-api">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
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
        if (!$this->obj->get('token')) {
            $this->obj
                ->set('token', self::createSecToken())
                ->save();
        }
        echo '<div class="col-xs-9 tab-content">';
        echo '<input type="text" name="fakeusernameremembered" class="fakes"/>';
        echo '<input type="password" name="fakepasswordremembered" class="fakes"/>';
        $this->userGeneral();
        $this->userChangePW();
        $this->userAPI();
        self::$HookManager->processEvent(
            'USER_EDIT_EXTRA',
            array(
                'User' => &$this->obj,
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
                'formAction' => &$this->formAction,
                'render' => &$this
            )
        );
        echo '</div>';
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
        self::$HookManager
            ->processEvent(
                'USER_EDIT_POST',
                array('User' => &$this->obj)
            );
        global $tab;
        try {
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
                throw new Exception(_('User update failed!'));
            }
            $hook = 'USER_UPDATE_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('User updated!'),
                    'title' => _('User Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'USER_UPDATE_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('User Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('User' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
