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
            $this->subMenu = array(
                $this->linkformat => self::$foglang['General'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
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
            . '"toggle-checkboxAction" />',
            _('Username'),
            _('Edit')
        );
        $this->templates = array(
            '<input type="checkbox" name="user[]" value='
            . '"${id}" class="toggle-action" />',
            sprintf(
                '<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>',
                $this->node,
                $this->id,
                _('Edit User')
            ),
            sprintf(
                '<a href="?node=%s&sub=edit&%s=${id}" title="%s">'
                . '<i class="icon fa fa-pencil"></i></a>',
                $this->node,
                $this->id,
                _('Edit User')
            )
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array(),
            array(
                'class' => 'c filter-false',
                'width' => 55
            )
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
                'name' => $User->get('name'),
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
            '<input style="display:none" type='
            . '"text" name="fakeusernameremembered"/>' => '<input style='
            . '"display:none" type="password" name="fakepasswordremembered"/>',
            _('User Name') => sprintf(
                '<input type="text" class="username-input" name='
                . '"name" value="%s" autocomplete="off"/>',
                $_REQUEST['name']
            ),
            _('User Password') => '<input type="password" class='
            . '"password-input1" name="password" value="" autocomplete='
            . '"off" id="password"/>',
            _('User Password (confirm)') => '<input type="password" class='
            . '"password-input2" name="password_confirm" value='
            . '"" autocomplete="off"/>',
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
            ) =>  '<input type="checkbox" name="isGuest" autocomplete="off"/>',
            '&nbsp;' => sprintf(
                '<input name="add" type="submit" value="%s"/>',
                _('Create User')
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
                ->set('type', isset($_REQUEST['isGuest']))
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
        $this->setMessage($msg);
        $this->redirect($this->formAction);
    }
    /**
     * Enable user to edit a user.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        $fields = array(
            '<input style="display:none" type='
            . '"text" name="fakeusernameremembered"/>' => '<input style='
            . '"display:none" type="password" name="fakepasswordremembered"/>',
            _('User Name') => sprintf(
                '<input type="text" class="username-input" name='
                . '"name" value="%s" autocomplete="off"/>',
                $this->obj->get('name')
            ),
            _('User Password') => '<input type="password" class='
            . '"password-input1" name="password" value="" autocomplete='
            . '"off" id="password"/>',
            _('User Password (confirm)') => '<input type="password" class='
            . '"password-input2" name="password_confirm" value='
            . '"" autocomplete="off"/>',
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
                '<input type="checkbox" name="isGuest" autocomplete="off"%s/>&nbsp;',
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
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        echo '</form>';
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
            if ($name != trim($this->obj->get('name'))
                && $this->obj->getManager()->exists($name, $this->obj->get('id'))
            ) {
                throw new Exception(_('Username already exists'));
            }
            $this->obj
                ->set('name', $name)
                ->set('type', isset($_REQUEST['isGuest']))
                ->set('password', $_REQUEST['password']);
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
        $this->setMessage($msg);
        $this->redirect($this->formAction);
    }
}
