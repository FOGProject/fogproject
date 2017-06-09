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
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler"/>'
            . '<label for="toggler"></label>',
            _('Mobile Only?'),
            _('API?'),
            _('Username'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<input type="checkbox" name="user[]" value='
            . '"${id}" class="toggle-action" id="user-${id}"/>'
            . '<label for="user-${id}"></label>',
            '${mobileYes}',
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
                'class' => 'l filter-false',
                'width' => 16
            ),
            array(
                'class' => 'l',
                'width' => 22
            ),
            array(
                'class' => 'l',
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
        self::$returnData = function (&$User) use (&$types) {
            if (count($types) > 0
                && in_array($User->get('type'), $types)
            ) {
                return;
            }
            $this->data[] = array(
                'id' => $User->get('id'),
                'mobileYes' => $User->get('type') == 1 ? _('Yes') : _('No'),
                'apiYes' => $User->get('api') ? _('Yes') : _('No'),
                'name' => $User->get('name'),
                'friendly' => (
                    $User->get('display') ?
                    $User->get('display') :
                    _('No friendly name defined')
                )
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
        $this->title = _('New User');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $this->data = array();
        $fields = array(
            '<label class="label-control" for="name">'
            . _('User Name')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . '</span>'
            . '<input type="text" class="'
            . 'form-control username-input" name='
            . '"name" value="'
            . $_POST['name']
            . '" autocomplete="off" id="name" required/>'
            . '</div>',
            '<label class="label-control" for="display">'
            . _('Friendly Name')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . '</span>'
            . '<input type="text" class="'
            . 'form-control friendlyname-input" name="'
            . 'display" value="'
            . $_POST['display']
            . '" autocomplete="off"/>'
            . '</div>',
            '<label class="label-control" for="password">'
            . _('User Password')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input1" name="password" value='
            . '"" autocomplete='
            . '"off" id="password" required/>'
            . '</div>',
            '<label class="label-control" for="password2">'
            . _('User Password (confirm)')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input2" name="password_confirm" value='
            . '"" autocomplete="off" required/>'
            . '</div>',
            '<label class="label-control" for="apion">'
            . _('User API Enabled')
            . '</label>' => '<div class="checkbox">'
            . '<input type="checkbox" class="'
            . 'api-enabled" name="apienabled" id="'
            . 'apion"'
            . (
                isset($_POST['apienabled']) ?
                ' checked' :
                ''
            )
            . '/>'
            . '</div>',
            '<label class="label-control" for="add">'
            . _('Create user?')
            . '</label> ' => '<button class="btn btn-default" name="'
            . 'add" id="add" type="submit">'
            . _('Create')
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
                'USER_ADD',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="form-group">';
        echo '<input type="text" name="fakeusernameremembered" class="fakes"/>';
        echo '<input type="password" name="fakepasswordremembered" class="fakes"/>';
        echo '</div>';
        echo '<h2>';
        echo _('Create new user');
        echo '</h2>';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render();
        echo '</form>';
    }
    /**
     * Actually create the new user.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager
            ->processEvent('USER_ADD_POST');
        try {
            $name = strtolower(
                filter_input(INPUT_POST, 'name')
            );
            $password = filter_input(INPUT_POST, 'password');
            $friendly = trim(
                filter_input(INPUT_POST, 'display')
            );
            $test = preg_match(
                '/(?=^.{3,40}$)^[\w][\w0-9]*[._-]?[\w0-9]*[.]?[\w0-9]+$/i',
                $name
            );
            $apien = isset($_POST['apienabled']);
            if (!$test) {
                throw new Exception(
                    sprintf(
                        '%s.<br/><small>%s.<br/>%s.<br/>%s.</br>%s.</small>',
                        _('Username does not meet rules'),
                        _('Must start with a word character'),
                        _('Must be at least 3 characters'),
                        _('Must be shorter than 41 characters'),
                        _('No contiguous special characters')
                    )
                );
            }
            if (self::getClass('UserManager')->exists($name)) {
                throw new Exception(_('Username already exists'));
            }
            $User = self::getClass('User')
                ->set('name', $name)
                ->set('password', $password)
                ->set('display', $friendly)
                ->set('api', $apien)
                ->set('type', 0)
                ->set('token', self::createSecToken());
            if (!$User->save()) {
                throw new Exception(_('Failed to create user'));
            }
            $hook = 'USER_ADD_SUCCESS';
            $msg = sprintf(
                '%s<br/>%s',
                _('User created'),
                _('You may now create another')
            );
        } catch (Exception $e) {
            $hook = 'USER_ADD_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('User' => &$User)
            );
        unset($User);
        self::setMessage($msg);
        self::redirect($this->formAction);
    }
    /**
     * User general div element.
     *
     * @return void
     */
    public function userGeneral()
    {
        $fields = array(
            '<label class="label-control" for="name">'
            . _('User Name')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . '</span>'
            . '<input type="text" class="'
            . 'form-control username-input" name='
            . '"name" value="'
            . $this->obj->get('name')
            . '" autocomplete="off" id="name" required/>'
            . '</div>',
            '<label class="label-control" for="display">'
            . _('Friendly Name')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . '</span>'
            . '<input type="text" class="'
            . 'form-control friendlyname-input" name="'
            . 'display" value="'
            . $this->obj->get('display')
            . '" autocomplete="off"/>'
            . '</div>',
            '<label class="label-control" for="updategen">'
            . _('Update General?')
            . '</label> ' => '<button class="btn btn-default" name="'
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
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $this->data = array();
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
        echo '<div id="user-general" class="tab-pane fade in active">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=user-general">';
        $this->render();
        echo '</form>';
        echo '</div>';
    }
    /**
     * Change password div element.
     *
     * @return void
     */
    public function userChangePW()
    {
        $fields = array(
            '<label class="label-control" for="password">'
            . _('User Password')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input1" name="password" value='
            . '"" autocomplete='
            . '"off" id="password" required/>'
            . '</div>',
            '<label class="label-control" for="password2">'
            . _('User Password (confirm)')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input2" name="password_confirm" value='
            . '"" autocomplete="off" required/>'
            . '</div>',
            '<label class="label-control" for="updatepw">'
            . _('Update Password?')
            . '</label> ' => '<button class="btn btn-default" name="'
            . 'update" id="updatepw" type="submit">'
            . _('Update')
            . '</button>'
        );
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $this->data = array();
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
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=user-changepw">';
        $this->render();
        echo '</form>';
        echo '</div>';
    }
    /**
     * API div element.
     *
     * @return void
     */
    public function userAPI()
    {
        $fields = array(
            '<label class="label-control" for="apion">'
            . _('User API Enabled')
            . '</label>' => '<div class="checkbox">'
            . '<input type="checkbox" class="'
            . 'api-enabled" name="apienabled" id="'
            . 'apion"'
            . (
                $this->obj->get('api') ?
                ' checked' :
                ''
            )
            . '/>'
            . '</div>',
            '<label class="label-control" for="token">'
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
            . '<button class="btn btn-default resettoken" type="button">'
            . _('Reset Token')
            . '</button>'
            . '</div>'
            . '</div>',
            '<label class="label-control" for="updateapi">'
            . _('Update API?')
            . '</label> ' => '<button class="btn btn-default" name="'
            . 'update" id="updateapi" type="submit">'
            . _('Update')
            . '</button>'
        );
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $this->data = array();
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
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=user-api">';
        $this->render();
        echo '</form>';
        echo '</div>';
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
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        echo '<div class="form-group">';
        echo '<input type="text" name="fakeusernameremembered" class="fakes"/>';
        echo '<input type="password" name="fakepasswordremembered" class="fakes"/>';
        echo '</div>';
        echo '<div class="tab-content">';
        $this->userGeneral();
        $this->userChangePW();
        $this->userAPI();
        echo '</div>';
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
        try {
            $name = strtolower(trim($_REQUEST['name']));
            $friendly = trim($_REQUEST['display']);
            global $tab;
            switch ($tab) {
            case 'user-general':
                $test = preg_match(
                    '/(?=^.{3,40}$)^[\w][\w0-9]*[._-]?[\w0-9]*[.]?[\w0-9]+$/i',
                    $name
                );
                if (!$test) {
                    throw new Exception(
                        sprintf(
                            '%s.<br/><small>%s.<br/>%s.<br/>%s.</br>%s.</small>',
                            _('Username does not meet rules'),
                            _('Must start with a word character'),
                            _('Must be at least 3 characters'),
                            _('Must be shorter than 41 characters'),
                            _('No contiguous special characters')
                        )
                    );
                }
                $exists = $this->obj
                    ->getManager()
                    ->exists(
                        $name,
                        $this->obj->get('id')
                    );
                if ($name != trim($this->obj->get('name'))
                    && $exists
                ) {
                    throw new Exception(_('Username already exists'));
                }
                $this->obj
                    ->set('name', $name)
                    ->set('display', $friendly)
                    ->set('type', isset($_REQUEST['isGuest']));
                break;
            case 'user-changepw':
                $this->obj
                    ->set('password', $_REQUEST['password']);
                break;
            case 'user-api':
                $this->obj
                    ->set('api', isset($_REQUEST['apienabled']))
                    ->set('token', base64_decode($_REQUEST['apitoken']));
            }
            if (!$this->obj->save()) {
                throw new Exception(_('User update failed'));
            }
            $hook = 'USER_UPDATE_SUCCESS';
            $msg = _('User updated');
        } catch (Exception $e) {
            $hook = 'USER_UPDATE_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('User' => &$this->obj)
            );
        self::setMessage($msg);
        self::redirect($this->formAction);
    }
}
