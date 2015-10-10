<?php
class TasktypeeditManagementPage extends FOGPage {
    public $node = 'tasktypeedit';
    public function __construct($name = '') {
        $this->name = 'Task Type Management';
        // Call parent constructor
        parent::__construct($this->name);
        $this->menu = array(
            'search' => $this->foglang[NewSearch],
            'list' => sprintf($this->foglang[ListAll],_('Task Types')),
            'add' => sprintf($this->foglang[CreateNew],_('Task Type')),
        );
        if ($_REQUEST[id]) {
            $this->obj = $this->getClass(TaskType,$_REQUEST[id]);
            $this->notes = array(
                _('Name')=>$this->obj->get(name),
                _('Icon')=>'<i class="fa fa-'.$this->obj->get(icon).' fa-2x"></i>',
                _('Type')=>$this->obj->get(type),
            );
        }
        // Header row
        $this->headerData = array(
            _('Name'),
            _('Access'),
            _('Kernel Args'),
        );
        // Row templates
        $this->templates = array(
            '<a href="?node='.$this->node.'&sub=edit&id=${id}" title="Edit"><i class="fa fa-${icon} fa-1x"> ${name}</i></a>',
            '${access}',
            '${args}',
        );
        $this->attributes = array(
            array('class'=>l),
            array('class'=>c),
            array('class'=>r),
        );
    }
    // Pages
    public function index() {
        // Set title
        $this->title = _('All Task Types');
        if ($this->FOGCore->getSetting(FOG_DATA_RETURNED)>0 && $this->getClass(TaskTypeManager)->count() > $this->FOGCore->getSetting(FOG_DATA_RETURNED) && $_REQUEST[sub] != 'list')
            $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        // Find data
        $TaskTypes = $this->getClass(TaskTypeManager)->find('','','id');
        // Row data
        foreach ((array)$TaskTypes AS $i => &$TaskType) {
            $this->data[] = array(
                icon=>$TaskType->get(icon),
                id=>$TaskType->get(id),
                name=>$TaskType->get(name),
                access=>$TaskType->get(access),
                args=>$TaskType->get(kernelArgs),
            );
        }
        unset($TaskType);
        // Hook
        $this->HookManager->event[] = 'TASKTYPE_DATA';
        $this->HookManager->processEvent(TASKTYPE_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function search_post() {
        // Variables
        $keyword = preg_replace('#%+#','%','%'.preg_replace('#[[:space:]]#','%',$_REQUEST[crit]).'%');
        foreach ($this->getClass(TaskType)->databaseFields AS $field => &$val) $where[$field] = $keyword;
        // Find data -> Push data
        $TaskTypes = $this->getClass(TaskTypeManager)->search();
        foreach ($TaskTypes AS $i => &$TaskType) {
            $this->data[] = array(
                icon=>$TaskType->get(icon),
                id=>$TaskType->get(id),
                name=>$TaskType->get(name),
                access=>$TaskType->get(access),
                args=>$TaskType->get(kernelArgs),
            );
        }
        unset($TaskType);
        // Hook
        $this->HookManager->event[] = 'TASKTYPE_DATA';
        $this->HookManager->processEvent(TASKTYPE_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function add() {
        $this->title = 'New Task Type';
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $accessTypes = array('both','host','group');
        $access_opt = '';
        foreach ($accessTypes AS $i => &$type) $access_opt .= sprintf('<option value="%s"%s>%s</option>',$type,$_REQUEST[access] == $type ? ' selected' : '',ucfirst($type));
        $fields = array(
            _('Name') => sprintf('<input type="text" name="name" class="smaller" value="%s"/>',$_REQUEST[name]),
            _('Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$_REQUEST[description]),
            _('Icon') => $this->getClass(TaskType)->iconlist($_REQUEST[icon]),
            _('Kernel') => sprintf('<input type="text" name="kernel" class="smaller" value="%s"/>',$_REQUEST[kernel]),
            _('Kernel Arguments') => sprintf('<input type="text" name="kernelargs" class="smaller" value="%s"/>',$_REQUEST[kernelargs]),
            _('Type') => sprintf('<input type="text" name="type" class="smaller" value="%s"/>',$_REQUEST[type]),
            _('Is Advanced') => '<input type="checkbox" name="advanced" '.(isset($_REQUEST[advanced]) ? 'checked' : '').'/>',
            _('Accessed By') => '<select name="access">'.$access_opt.'</select>',
            '&nbsp;'=>'<input class="smaller" type="submit" value="'._('Add').'"/>',
        );
        echo '<form method="post" action="'.$this->formAction.'">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
            );
        }
        unset($input);
        // Hook
        $this->HookManager->event[] = 'TASKTYPE_ADD';
        $this->HookManager->processEvent(TASKTYPE_ADD,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            $name = trim($_REQUEST[name]);
            $description = trim($_REQUEST[description]);
            $icon = trim($_REQUEST[icon]);
            $kernel = trim($_REQUEST[kernel]);
            $kernelargs = trim($_REQUEST[kernelargs]);
            $type = trim($_REQUEST[type]);
            $advanced = (int)isset($_REQUEST[advanced]);
            $access = trim($_REQUEST[access]);
            if (!$name) throw new Exception(_('You must enter a name'));
            if ($this->getClass(TaskTypeManager)->exists($name)) throw new Exception(_('Task type already exists, please try again.'));
            $TaskType = $this->getClass(TaskType)
                ->set(name,$name)
                ->set(description,$description)
                ->set(icon,$icon)
                ->set(kernel,$kernel)
                ->set(kernelArgs,$kernelargs)
                ->set(type,$type)
                ->set(isAdvanced,$advanced)
                ->set(access,$access);
            if ($TaskType->save()) {
                $this->setMessage(_('Task Type added, editing'));
                $this->redirect(sprintf('?node=%s&sub=edit&id=%s',$this->node,$TaskType->get(id)));
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        // Get the Storage Node ID if it's set
        // Title
        $this->title = sprintf('%s: %s', 'Edit', $this->obj->get(name));
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $accessTypes = array('both','host','group');
        $access_opt = '';
        foreach ($accessTypes AS $i => &$type) $access_opt .= sprintf('<option value="%s"%s>%s</option>',$type,$this->obj->get(access) == $type ? ' selected' : '',ucfirst($type));
        $fields = array(
            _('Name') => sprintf('<input type="text" name="name" class="smaller" value="%s"/>',$this->obj->get(name)),
            _('Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get(description)),
            _('Icon') => sprintf('<input type="text" name="icon" class="smaller" value="%s"/>',$this->obj->get(icon)),
            _('Icon') => $this->getClass(TaskType)->iconlist($this->obj->get(icon)),
            _('Kernel') => sprintf('<input type="text" name="kernel" class="smaller" value="%s"/>',$this->obj->get(kernel)),
            _('Kernel Arguments') => sprintf('<input type="text" name="kernelargs" class="smaller" value="%s"/>',$this->obj->get(kernelArgs)),
            _('Type') => sprintf('<input type="text" name="type" class="smaller" value="%s"/>',$this->obj->get(type)),
            _('Is Advanced') => '<input type="checkbox" name="advanced" '.($this->obj->get(isAdvanced) ? 'checked' : '').'/>',
            _('Accessed By') => '<select name="access">'.$access_opt.'</select>',
            '&nbsp;'=>'<input class="smaller" type="submit" value="'._('Update').'"/>',
        );
        echo '<form method="post" action="'.$this->formAction.'">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
            );
        }
        unset($input);
        // Hook
        $this->HookManager->event[] = 'TASKTYPE_EDIT';
        $this->HookManager->processEvent(TASKTYPE_EDIT,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        echo '</form>';
    }
    public function edit_post() {
        $this->HookManager->event[] = 'TASKTYPE_EDIT_POST';
        $this->HookManager->processEvent(TASKTYPE_EDIT_POST,array(TaskType=>&$this->obj));
        try {
            $name = trim($_REQUEST[name]);
            $description = trim($_REQUEST[description]);
            $icon = trim($_REQUEST[icon]);
            $kernel = trim($_REQUEST[kernel]);
            $kernelargs = trim($_REQUEST[kernelargs]);
            $type = trim($_REQUEST[type]);
            $advanced = (int)isset($_REQUEST[advanced]);
            $access = trim($_REQUEST[access]);
            if (!$name) throw new Exception(_('You must enter a name'));
            if ($this->obj->get(name) != $name && $this->getClass(TaskTypeManager)->exists($name)) throw new Exception(_('Task type already exists, please try again.'));
            $this->obj
                ->set(name,$name)
                ->set(description,$description)
                ->set(icon,$icon)
                ->set(kernel,$kernel)
                ->set(kernelArgs,$kernelargs)
                ->set(type,$type)
                ->set(isAdvanced,$advanced)
                ->set(access,$access);
            if ($this->obj->save()) {
                $this->setMessage('TaskType Updated');
                $this->redirect('?node='.$this->node.'&sub=edit&id='.$this->obj->get(id));
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
