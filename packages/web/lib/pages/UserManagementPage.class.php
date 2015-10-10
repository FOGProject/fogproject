<?php
class UserManagementPage extends FOGPage {
    public $node = 'user';
    // __construct
    public function __construct($name = '') {
        $this->name = 'User Management';
        // Call parent constructor
        parent::__construct($this->name);
        if ($_REQUEST[id]) {
            $this->subMenu = array(
                $this->linkformat => $this->foglang[General],
                $this->delformat => $this->foglang[Delete],
            );
            $this->obj = $this->getClass(User,$_REQUEST[id]);
            $this->notes = array(
                $this->foglang[User] => $this->obj->get(name)
            );
        }
        $this->HookManager->processEvent(SUB_MENULINK_DATA,array(menu=>&$this->menu,submenu=>&$this->subMenu,id=>&$this->id,notes=>&$this->notes));
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Username'),
            _('Edit')
        );
        // Row templates
        $this->templates = array(
            '<input type="checkbox" name="user[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit User')),
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><i class="icon fa fa-pencil"></i></a>', $this->node, $this->id, _('Edit User'))
        );
        // Row attributes
        $this->attributes = array(
            array('class'=>'c filter-false',width=>16),
            array(),
            array('class'=>'c filter-false',width=>55),
        );
    }
    // Pages
    public function index() {
        // Set title
        $this->title = _('All Users');
        if ($_SESSION[DataReturn] > 0 && $_SESSION[UserCount] > $_SESSION[DataReturn] && $_REQUEST[sub] != 'list') $this->redirect(sprintf('%s?node=%s&sub=search', $_SERVER[PHP_SELF], $this->node));
        // Find data
        $Users = $this->getClass(UserManager)->find();
        // Row data
        foreach ($Users AS $i => &$User) {
            if ($User->isValid()) {
                $this->data[] = array(
                    id=>$User->get(id),
                    name=>$User->get(name)
                );
            }
        }
        unset($User);
        // Hook
        $this->HookManager->processEvent(USER_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function search() {
        // Set title
        $this->title = _('Search');
        // Set search form
        $this->searchFormURL = sprintf('%s?node=%s&sub=search',$_SERVER[PHP_SELF],$this->node);
        // Hook
        $this->HookManager->processEvent(USER_SEARCH);
        // Output
        $this->render();
    }
    public function search_post() {
        // Find data -> Push data
        $Users = $this->getClass(UserManager)->search();
        foreach ($Users AS $i => &$User) {
            $this->data[] = array(
                id=>$User->get(id),
                name=>$User->get(name)
            );
        }
        unset($User);
        // Hook
        $this->HookManager->processEvent(USER_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function add() {
        // Set title
        $this->title = _('New User');
        unset ($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            '<input style="display:none" type="text" name="fakeusernameremembered"/>'=>'<input style="display:none" type="password" name="fakepasswordremembered"/>',
            _('User Name') => '<input type="text" name="name" value="'.$_REQUEST[name].'" autocomplete="off" />',
            _('User Password') => '<input type="password" name="password" value="" autocomplete="off" />',
            _('User Password (confirm)') => '<input type="password" name="password_confirm" value="" autocomplete="off" />',
            _('Mobile/Quick Image Access Only?').'&nbsp;'.'<span class="icon icon-help hand" title="'._('Warning - if you tick this box, this user will not be able to log into this FOG Management Console in the future.').'"></span>' => '<input type="checkbox" name="isGuest" autocomplete="off" />',
            '&nbsp;' => '<input type="submit" value="'._('Create User').'" />',
        );
        echo '<h2>'._('Add new user account').'</h2><form method="post" action="'.$this->formAction.'"><input type="hidden" name="add" value="1" />';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
            );
        }
        unset($input);
        $this->HookManager->processEvent(USER_ADD,array(data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        // Hook
        $this->HookManager->processEvent(USER_ADD_POST);
        // POST
        try {
            // Error checking
            if ($this->getClass(UserManager)->exists($_REQUEST[name])) throw new Exception(_('Username already exists'));
            if (!$this->getClass(UserManager)->isPasswordValid($_REQUEST[password],$_REQUEST[password_confirm])) throw new Exception(_('Password is invalid'));
            // Create new Object
            $User = $this->getClass(User)
                ->set(name,$_REQUEST[name])
                ->set(type,isset($_REQUEST[isGuest]))
                ->set(password,$_REQUEST[password]);
            // Save
            if (!$User->save()) throw new Exception(_('Failed to create user'));
            // Hook
            $this->HookManager->processEvent(USER_ADD_SUCCESS,array(User=>&$User));
            // Set session message
            $this->setMessage(_('User created').'<br>'._('You may now create another'));
            // Redirect to new entry
            $this->redirect(sprintf('?node=%s&sub=add',$_REQUEST[node],$this->id,$User->get(id)));
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent(USER_ADD_FAIL,array(User=>&$User));
            // Set session message
            $this->setMessage($e->getMessage());
            // Redirect to new entry
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        // Title
        $this->title = sprintf('%s: %s',_('Edit'),$this->obj->get(name));
        $fields = array(
            _('User Name') => '<input type="text" name="name" value="'.$this->obj->get(name).'" />',
            _('New Password') => '<input type="password" name="password" value="" />',
            _('New Password (confirm)') => '<input type="password" name="password_confirm" value="" />',
            _('Mobile/Quick Image Access Only?').'&nbsp;'.'<span class="icon icon-help hand" title="'._('Warning - if you tick this box, this user     will not be able to log into this FOG Management Console in the future.').'"></span>' => '<input type="checkbox" name="isGuest" '.($this->obj->get(type) == 1 ? 'checked' : '').' />',
            '&nbsp;' => '<input type="submit" value="'._('Update').'" />',
        );
        unset ($this->headerData);
        $this->templates = array(
            '${field}',
            '${formData}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        echo '<form method="post" action="'.$this->formAction.'"><input type="hidden" name="update" value="'.$this->obj->get(id).'" />';
        foreach ((array)$fields AS $field => &$formData) {
            $this->data[] = array(
                field=>$field,
                formData=>$formData,
            );
        }
        unset($formData);
        $this->HookManager->processEvent(USER_EDIT,array(data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function edit_post() {
        // Find
        $User = $this->obj;
        // Hook
        $this->HookManager->processEvent(USER_EDIT_POST,array(User=>&$this->obj));
        // POST
        try {
            $name = trim($_REQUEST[name]);
            // Error checking
            if ($name != trim($this->obj->get(name)) && $this->obj->getManager()->exists($name,$this->obj->get(id))) throw new Exception(_('Username already exists'));
            if ($_REQUEST[password] && $_REQUEST[password_confirm]) {
                if (!$this->obj->getManager()->isPasswordValid($_REQUEST[password],$_REQUEST[password_confirm])) throw new Exception(_('Password is invalid'));
            }
            $this->obj
                ->set(name,$name)
                ->set(type,(int)isset($_REQUEST[isGuest]))
                ->set(password,$_REQUEST[password]);
            if (!$this->obj->save()) throw new Exception(_('User update failed'));
            // Hook
            $this->HookManager->processEvent(USER_UPDATE_SUCCESS,array(User=>&$this->obj));
            // Set session message
            $this->setMessage(_('User updated'));
            // Redirect to new entry
            $this->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request[node],$this->id,$this->obj->get(id)));
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent(USER_UPDATE_FAIL,array(User=>&$User));
            // Set session message
            $this->setMessage($e->getMessage());
            // Redirect to new entry
            $this->redirect($this->formAction);
        }
    }
}
