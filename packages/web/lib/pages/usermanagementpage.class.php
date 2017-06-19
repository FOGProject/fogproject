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
            '<label class="control-label" for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler"/>'
            . '</label>',
            _('API?'),
            _('Username'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<label class="control-label" for="user-${id}">'
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
                'class' => 'l filter-false form-group',
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
        $this->title = _('New User');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-3'),
            array('class' => 'col-xs-9 form-group'),
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
            '<label class="control-label" for="name">'
            . _('User Name')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . _('Required')
            . '</span>'
            . '<input type="text" class="'
            . 'form-control username-input" name='
            . '"name" value="'
            . $name
            . '" autocomplete="off" id="name" required/>'
            . '</div>',
            '<label class="control-label" for="display">'
            . _('Friendly Name')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . _('Optional')
            . '</span>'
            . '<input type="text" class="'
            . 'form-control friendlyname-input" name="'
            . 'display" value="'
            . $display
            . '" autocomplete="off" id="display"/>'
            . '</div>',
            '<label class="control-label" for="password">'
            . _('User Password')
            . '</label>' => '<div class="input-group has-error">'
            . '<input type="password" class="'
            . 'form-control password-input1" name="password" value='
            . '"" autocomplete='
            . '"off" id="password" required/>'
            . '</div>',
            '<label class="control-label" for="password2">'
            . _('User Password (confirm)')
            . '</label>' => '<div class="input-group has-error">'
            . '<input type="password" class="'
            . 'form-control password-input2" name="password_confirm" value='
            . '"" autocomplete="off" required/>'
            . '</div>',
            '<label class="control-label" for="apion">'
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
            '<label class="control-label" for="add">'
            . _('Create user?')
            . '</label> ' => '<button class="btn btn-default btn-block" name="'
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
            '<label class="control-label" for="name">'
            . _('User Name')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . _('Required')
            . '</span>'
            . '<input type="text" class="'
            . 'form-control username-input" name='
            . '"name" value="'
            . $this->obj->get('name')
            . '" autocomplete="off" id="name" required/>'
            . '</div>',
            '<label class="control-label" for="display">'
            . _('Friendly Name')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . _('Optional')
            . '</span>'
            . '<input type="text" class="'
            . 'form-control friendlyname-input" name="'
            . 'display" value="'
            . $this->obj->get('display')
            . '" autocomplete="off" id="display"/>'
            . '</div>',
            '<label class="control-label" for="updategen">'
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
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
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
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('User general');
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
    }
    /**
     * Change password div element.
     *
     * @return void
     */
    public function userChangePW()
    {
        $fields = array(
            '<label class="control-label" for="password">'
            . _('User Password')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input1" name="password" value='
            . '"" autocomplete='
            . '"off" id="password" required/>'
            . '</div>',
            '<label class="control-label" for="passwordConfirm">'
            . _('User Password (confirm)')
            . '</label>' => '<div class="input-group">'
            . '<input type="password" class="'
            . 'form-control password-input2" name="password_confirm" value='
            . '"" autocomplete="off" id="passwordConfirm" required/>'
            . '</div>',
            '<label class="control-label" for="updatepw">'
            . _('Update Password?')
            . '</label> ' => '<button class="btn btn-info btn-block" name="'
            . 'update" id="updatepw" type="submit">'
            . _('Update')
            . '</button>'
        );
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
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
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('User change password');
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
    }
    /**
     * API div element.
     *
     * @return void
     */
    public function userAPI()
    {
        $fields = array(
            '<label class="control-label" for="apion">'
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
            '<label class="control-label" for="token">'
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
            '<label class="control-label" for="updateapi">'
            . _('Update API?')
            . '</label> ' => '<button class="btn btn-info btn-block" name="'
            . 'update" id="updateapi" type="submit">'
            . _('Update')
            . '</button>'
        );
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
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
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading">';
        echo '<h4 class="title">';
        echo _('User API');
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
        echo '<div class="col-xs-offset-3 tab-content">';
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
            $name = strtolower(
                trim(
                    filter_input(INPUT_POST, 'name')
                )
            );
            $password = filter_input(INPUT_POST, 'password');
            $friendly = trim(
                filter_input(INPUT_POST, 'display')
            );
            $apien = isset($_POST['apienabled']);
            $apitoken = base64_decode(
                filter_input(INPUT_POST, 'apitoken')
            );
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
                    ->set('display', $friendly);
                break;
            case 'user-changepw':
                $this->obj
                    ->set('password', $password);
                break;
            case 'user-api':
                $this->obj
                    ->set('api', $apien)
                    ->set('token', $apitoken);
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
