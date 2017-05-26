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
            '<input type="text" name="fakeusernameremembered"/>' =>
            '<input type="password" name="fakepasswordremembered"/>',
            _('User Name') => sprintf(
                '<input type="text" class="username-input" name='
                . '"name" value="%s" autocomplete="off"/>',
                $_REQUEST['name']
            ),
            _('Friendly Name') => sprintf(
                '<input type="text" class="friendlyname-input" name='
                . '"display" value="%s" autocomplete="off"/>',
                $_REQUEST['name']
            ),
            _('User Password') => '<input type="password" class='
            . '"password-input1" name="password" value="" autocomplete='
            . '"off" id="password"/>',
            _('User Password (confirm)') => '<input type="password" class='
            . '"password-input2" name="password_confirm" value='
            . '"" autocomplete="off"/>',
            _('Allow API') => '<input type="checkbox" name="apienabled" '
            . 'autocomplete="off" id="apion" checked/>'
            . '<label for="apion"></label>',
            sprintf(
                '%s&nbsp;'
                . '<i class="icon icon-help hand fa fa-question" title="%s"></i>',
                _('Mobile/Quick Image Access Only?'),
                sprintf(
                    '%s - %s, %s %s.',
                    _('Warning'),
                    _('if you tick this box'),
                    _('this user will not be able to log into'),
                    _('this FOG Management console in the future')
                )
            ) =>  '<input type="checkbox" name="isGuest" autocomplete="off" id="'
            . 'isguest"/>'
            . '<label for="isguest"></label>',
            '&nbsp;' => sprintf(
                '<input name="add" type="submit" value="%s"/>',
                _('Create User')
            )
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
        printf(
            '<h2>%s</h2><form method="post" action="%s">',
            _('Add new user account'),
            $this->formAction
        );
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
            $name = strtolower(trim($_REQUEST['name']));
            $friendly = trim($_REQUEST['display']);
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
            if (self::getClass('UserManager')->exists($name)) {
                throw new Exception(_('Username already exists'));
            }
            $User = self::getClass('User')
                ->set('name', $name)
                ->set('display', $friendly)
                ->set('type', isset($_REQUEST['isGuest']))
                ->set('api', isset($_REQUEST['apienabled']))
                ->set('token', self::createSecToken())
                ->set('password', $_REQUEST['password']);
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
        echo '<div id="tab-container"><div id="user-general">';
        $fields = array(
            '<input type="text" name="fakeusernameremembered"/>' => 
            '<input type="password" name="fakepasswordremembered"/>',
            _('User Name') => sprintf(
                '<input type="text" class="username-input" name='
                . '"name" value="%s" autocomplete="off"/>',
                $this->obj->get('name')
            ),
            _('Friendly Name') => sprintf(
                '<input type="text" class="friendlyname-input" name='
                . '"display" value="%s" autocomplete="off"/>',
                $this->obj->get('display')
            ),
            sprintf(
                '%s&nbsp;'
                . '<i class="icon icon-help hand fa fa-question" title="%s"></i>',
                _('Mobile/Quick Image Access Only?'),
                sprintf(
                    '%s - %s, %s %s.',
                    _('Warning'),
                    _('if you tick this box'),
                    _('this user will not be able to log into'),
                    _('this FOG Management console in the future')
                )
            ) => sprintf(
                '<input type="checkbox" name="isGuest" autocomplete="off" id="'
                . 'isguest"%s/>&nbsp;<label for="isguest"></label>',
                (
                    $this->type == 1 ?
                    ' checked' :
                    ''
                )
            ),
            '&nbsp;' => sprintf(
                '<input name="update" type="submit" value="%s"/>',
                _('Update')
            )
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
        printf(
            '<form method="post" action="%s&tab=user-general">',
            $this->formAction
        );
        $this->render();
        echo '</form></div>';
        echo '<div id="user-changepw">';
        $fields = array(
            _('User Password') => '<input type="password" class='
            . '"password-input1" name="password" value="" autocomplete='
            . '"off" id="password"/>',
            _('User Password (confirm)') => '<input type="password" class='
            . '"password-input2" name="password_confirm" value='
            . '"" autocomplete="off"/>',
            '&nbsp;' => sprintf(
                '<input name="update" type="submit" value="%s"/>',
                _('Update')
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
                'USER_PW_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<form method="post" action="%s&tab=user-changepw">',
            $this->formAction
        );
        $this->render();
        echo '</form></div>';
        echo '<div id="user-api">';
        $fields = array(
            _('User API Enabled') => sprintf(
                '<input type="checkbox" class="api-enabled" name="apienabled" id="'
                . 'apion"%s/><label for="apion"></label>',
                $this->obj->get('api') ? ' checked' : ''
            ),
            _('User API Token') => sprintf(
                '<input type="password" class="token" name="apitoken" readonly '
                . 'value="%s"/><br/><input type="button" class="resettoken" '
                . ' value="%s"/>',
                base64_encode(
                    $this->obj->get('token')
                ),
                _('Reset Token')
            ),
            '&nbsp;' => sprintf(
                '<input name="update" type="submit" value="%s"/>',
                _('Update')
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
                'USER_API_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<form method="post" action="%s&tab=user-api">',
            $this->formAction
        );
        $this->render();
        echo '</form></div></div>';
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
