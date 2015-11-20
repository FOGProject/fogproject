<?php
class UserManagementPage extends FOGPage {
    public $node = 'user';
    public $name = 'User Management';
    public function __construct($name = '') {
        parent::__construct($this->name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                $this->linkformat => $this->foglang['General'],
                $this->delformat => $this->foglang['Delete'],
            );
            $this->notes = array(
                $this->foglang['User'] => $this->obj->get('name'),
            );
        }
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Username'),
            _('Edit')
        );
        $this->templates = array(
            '<input type="checkbox" name="user[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit User')),
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><i class="icon fa fa-pencil"></i></a>', $this->node, $this->id, _('Edit User'))
        );
        $this->attributes = array(
            array('class'=>'c filter-false','width'=>16),
            array(),
            array('class'=>'c filter-false','width'=>55),
        );
    }
    public function index() {
        $this->title = _('All Users');
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['UserCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node));
        foreach ((array)$this->getClass('UserManager')->find() AS $i => &$User) {
            if (!$User->isValid()) continue;
            $this->data[] = array(
                'id' => $User->get('id'),
                'name' => $User->get('name'),
            );
            unset($User);
        }
        $this->HookManager->processEvent('USER_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $ids = $this->getClass('UserManager')->search();
        foreach ((array)$this->getClass('UserManager')->search('',true) AS $i => &$User) {
            if (!$User->isValid()) continue;
            $this->data[] = array(
                'id' => $User->get('id'),
                'name' => $User->get('name'),
            );
            unset($User);
        }
        $this->HookManager->processEvent('USER_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
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
            _('User Name') => sprintf('<input type="text" name="name" value="%s" autocomplete="off"/>',$_REQUEST['name']),
            _('User Password') => '<input type="password" name="password" value="" autocomplete="off"/>',
            _('User Password (confirm)') => '<input type="password" name="password_confirm" value="" autocomplete="off"/>',
            sprintf('%s&nbsp;<i class="icon icon-help hand fa fa-question" title="%s"></i>',_('Mobile/Quick Image Access Only?'),_('Warning - if you tick this box, this user will not be able to log into this FOG Management Console in the future.')) => '<input type="checkbox" name="isGuest" autocomplete="off"/>',
            '&nbsp;' => sprintf('<input name="add" type="submit" value="%s"/>',_('Create User')),
        );
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        unset($input,$fields);
        $this->HookManager->processEvent('USER_ADD',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<h2>%s</h2><form method="post" action="%s">',_('Add new user account'),$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        $this->HookManager->processEvent('USER_ADD_POST');
        try {
            if ($this->getClass('UserManager')->exists($_REQUEST['name'])) throw new Exception(_('Username already exists'));
            if (!$this->getClass('UserManager')->isPasswordValid($_REQUEST['password'],$_REQUEST['password_confirm'])) throw new Exception(_('Password is invalid'));
            $User = $this->getClass('User')
                ->set('name',$_REQUEST['name'])
                ->set('type',(int)isset($_REQUEST['isGuest']))
                ->set('password',$_REQUEST['password']);
            if (!$User->save()) throw new Exception(_('Failed to create user'));
            $this->HookManager->processEvent('USER_ADD_SUCCESS',array('User'=>&$User));
            $this->setMessage(sprintf('%s<br/>%s',_('User created'),_('You may now create another')));
        } catch (Exception $e) {
            $this->HookManager->processEvent('USER_ADD_FAIL',array('User'=>&$User));
            $this->setMessage($e->getMessage());
        }
        unset($User);
        $this->redirect($this->formAction);
    }
    public function edit() {
        $this->title = sprintf('%s: %s',_('Edit'),$this->obj->get('name'));
        $fields = array(
            _('User Name') => sprintf('<input type="text" name="name" value="%s"/>',$this->obj->get('name')),
            _('New Password') => '<input type="password" name="password" value=""/>',
            _('New Password (confirm)') => '<input type="password" name="password_confirm" value=""/>',
            _('Mobile/Quick Image Access Only?').'&nbsp;'.'<span class="icon icon-help hand" title=<"'._('Warning - if you tick this box, this user     will not be able to log into this FOG Management Console in the future.').'"></span>' => '<input type="checkbox" name="isGuest" '.($this->obj->get('type') == 1 ? 'checked' : '').' />',
            sprintf('%s&nbsp;<i class="icon icon-help hand fa fa-question" title="%s"></i>',_('Mobile/Quick Image Access Only?'),_('Warning - if you tick this box, this user will not be able to log into this FOG Management Console in the future.')) => sprintf('<input type="checkbox" name="isGuest" autocomplete="off"%s/>',($this->obj->get('type') == 1 ? ' checked' : '')),
            '&nbsp;' => sprintf('<input name="update" type="submit" value="%s"/>',_('Update')),
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
        foreach ((array)$fields AS $field => &$formData) {
            $this->data[] = array(
                'field'=>$field,
                'formData'=>$formData,
            );
        }
        unset($fields,$formData);
        $this->HookManager->processEvent('USER_EDIT',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function edit_post() {
        $this->HookManager->processEvent('USER_EDIT_POST',array('User'=>&$this->obj));
        try {
            $name = trim($_REQUEST['name']);
            if ($name != trim($this->obj->get('name')) && $this->obj->getManager()->exists($name,$this->obj->get('id'))) throw new Exception(_('Username already exists'));
            if ($_REQUEST['password'] && $_REQUEST['password_confirm']) {
                if (!$this->obj->getManager()->isPasswordValid($_REQUEST['password'],$_REQUEST['password_confirm'])) throw new Exception(_('Password is invalid'));
            }
            $this->obj
                ->set('name',$name)
                ->set('type',(int)isset($_REQUEST['isGuest']))
                ->set('password',$_REQUEST['password']);
            if (!$this->obj->save()) throw new Exception(_('User update failed'));
            $this->HookManager->processEvent('USER_UPDATE_SUCCESS',array('User'=>&$this->obj));
            $this->setMessage(_('User updated'));
        } catch (Exception $e) {
            $this->HookManager->processEvent('USER_UPDATE_FAIL',array('User'=>&$this->obj));
            $this->setMessage($e->getMessage());
        }
        $this->redirect(sprintf('%s#%s',$this->formAction,$_REQUEST['tab']));
    }
}
