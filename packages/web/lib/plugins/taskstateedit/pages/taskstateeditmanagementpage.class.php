<?php
class TaskstateeditManagementPage extends FOGPage {
    public $node = 'taskstateedit';
    public function __construct($name = '') {
        $this->name = 'Task State Management';
        parent::__construct($this->name);
        $this->menu = array(
            'search' => self::$foglang['NewSearch'],
            'list' => sprintf(self::$foglang['ListAll'],_('Task States')),
            'add' => sprintf(self::$foglang['CreateNew'],_('Task State')),
        );
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('Name')=>$this->obj->get('name'),
                _('Icon')=>sprintf('<i class="fa fa-%s fa-fw fa-2x"></i>',$this->obj->get('icon')),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Icon'),
            _('Name'),
        );
        $this->templates = array(
            '<input type="checkbox" name="taskstateedit[]" value="${id}" class="toggle-action"/>',
            '<i class="fa fa-${icon} fa-1x"></i>',
            sprintf('<a href="?node=%s&sub=edit&id=${id}" title="%s">&nbsp;&nbsp;${name}</a>',$this->node,_('Edit')),
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>22,'class'=>'l filter-false'),
            array('class'=>'l'),
        );
        self::$returnData = function(&$TaskState) {
            if (!$TaskState->isValid()) return;
            $this->data[] = array(
                'id'=>$TaskState->get('id'),
                'name'=>$TaskState->get('name'),
                'icon'=>$TaskState->get('icon'),
            );
            unset($TaskState);
        };
    }
    public function index() {
        $this->title = _('All Task States');
        if ($this->getSetting('FOG_DATA_RETURNED')>0 && self::getClass($this->childClass)->getManager()->count() > $this->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('TASKSTATE_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->search('',true));
        self::$HookManager->processEvent('TASKSTATE_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = _('New Task State');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Name') => sprintf('<input type="text" name="name" class="smaller" value="%s"/>',$_REQUEST['name']),
            _('Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$_REQUEST['description']),
            _('Icon') => self::getClass('TaskType')->iconlist($_REQUEST['icon']),
            _('Additional Icon elements') => sprintf('<input type="text" value="%s" name="additional"/>',$_REQUEST['additional']),
            '&nbsp;'=> sprintf('<input class="smaller" type="submit" value="%s"/>',_('Add'))
        );
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager->processEvent('TASKSTATE_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            $name = $_REQUEST['name'];
            $description = $_REQUEST['description'];
            $icon = trim("{$_REQUEST['icon']} {$_REQUEST['additional']}");
            if (!$name) throw new Exception(_('You must enter a name'));
            if (self::getClass('TaskStateManager')->exists($name)) throw new Exception(_('Task state already exists, please try again.'));
            $TaskState = self::getClass('TaskState')
                ->set('name',$name)
                ->set('description',$description)
                ->set('icon',$icon);
            if (!$TaskState->save()) throw new Exception(_('Failed to create'));
            $TaskState->set('order',$TaskState->get('id'))->save();
            $this->setMessage(_('Task State added, editing'));
            $this->redirect(sprintf('?node=%s&sub=edit&id=%s',$this->node,$TaskState->get('id')));
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $icon = explode(' ',trim($this->obj->get('icon')));
        $fields = array(
            _('Name') => sprintf('<input type="text" name="name" class="smaller" value="%s"/>',$this->obj->get('name')),
            _('Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            _('Icon') => self::getClass('TaskType')->iconlist(@array_shift($icon)),
            _('Additional Icon elements') => sprintf('<input type="text" value="%s" name="additional"/>',implode(' ',(array)$icon)),
            '&nbsp;' => sprintf('<input class="smaller" type="submit" value="%s"/>',_('Update')),
        );
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager->processEvent('TASKSTATE_EDIT',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function edit_post() {
        self::$HookManager->processEvent('TASKSTATE_EDIT_POST',array('TaskState'=>&$this->obj));
        try {
            $name = $_REQUEST['name'];
            $description = $_REQUEST['description'];
            $icon = trim("{$_REQUEST['icon']} {$_REQUEST['additional']}");
            if (!$name) throw new Exception(_('You must enter a name'));
            if ($this->obj->get('name') != $name && self::getClass('TaskStateManager')->exists($name)) throw new Exception(_('Task state already exists, please try again.'));
            $this->obj
                ->set('name',$name)
                ->set('description',$description)
                ->set('icon',$icon);
            if (!$this->obj->save()) throw new Exception(_('Failed to update'));
            $this->setMessage('Task State Updated');
            $this->redirect($this->formAction);
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
