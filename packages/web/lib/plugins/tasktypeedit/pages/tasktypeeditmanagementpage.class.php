<?php
/**
 * Task type edit page.
 *
 * PHP Version 5
 *
 * @category TasktypeeditManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task type edit page.
 *
 * @category TasktypeeditManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TasktypeeditManagementPage extends FOGPage
{
    /**
     * The node to work from.
     *
     * @var string
     */
    public $node = 'tasktypeedit';
    /**
     * The initializor for the class.
     *
     * @param string $name What to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Task Type Management';
        self::$foglang['ExportTasktypeedit'] = _('Export Task Types');
        self::$foglang['ImportTasktypeedit'] = _('Import Task Types');
        parent::__construct($this->name);
        $this->menu['list'] = sprintf(self::$foglang['ListAll'], _('Task Types'));
        $this->menu['add'] = sprintf(self::$foglang['CreateNew'], _('Task Type'));
        global $id;
        global $sub;
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat#tasktype-gen" => self::$foglang['General'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('Name') => $this->obj->get('name'),
                _('Icon') => sprintf(
                    '<i class="fa fa-%s"></i>',
                    $this->obj->get('icon')
                ),
                _('Type') => $this->obj->get('type'),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Name'),
            _('Access'),
            _('Kernel Args'),
        );
        $this->templates = array(
            '<input type="checkbox" name="tasktypeedit[]" value='
            . '"${id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${id}" title='
                . '"Edit"><i class="fa fa-${icon} fa-1x"> ${name}</i></a>',
                $this->node
            ),
            '${access}',
            '${args}',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $TaskType the object to use
         *
         * @return void
         */
        self::$returnData = function (&$TaskType) {
            $this->data[] = array(
                'icon' => $TaskType->icon,
                'id' => $TaskType->id,
                'name' => $TaskType->name,
                'access' => $TaskType->access,
                'args' => $TaskType->kernelArgs,
            );
            unset($TaskType);
        };
    }
    /**
     * Create new task type.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Task Type');
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $icon = filter_input(
            INPUT_POST,
            'icon'
        );
        $kernel = filter_input(
            INPUT_POST,
            'kernel'
        );
        $kernelargs = filter_input(
            INPUT_POST,
            'kernelargs'
        );
        $initrd = filter_input(
            INPUT_POST,
            'initrd'
        );
        $type = filter_input(
            INPUT_POST,
            'type'
        );
        $access = filter_input(
            INPUT_POST,
            'access'
        );
        $advanced = isset($_POST['advanced']);
        $isAd = (
            $advanced ?
            ' checked' :
            ''
        );
        $accessTypes = array(
            'both',
            'host',
            'group'
        );
        $accessSel = self::selectForm(
            'access',
            $accessTypes,
            $access
        );
        unset($accessTypes);
        $fields = array(
            '<label for="name">'
            . _('Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" value="'
            . $name
            . '" class="form-control" autocomplete="off" '
            . 'required/>'
            . '</div>',
            '<label for="description">'
            . _('Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="description" class="form-control">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="icon">'
            . _('Icon')
            . '</label>' => self::getClass('TaskType')->iconlist($icon),
            '<label for="kernel">'
            . _('Kernel')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="kernel" id="kernel" '
            . 'value="'
            . $kernel
            . '"/>'
            . '</div>',
            '<label for="kernargs">'
            . _('Kernel Arguments')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="kernelargs" id='
            . '"kernargs" value="'
            . $kernelargs
            . '"/>'
            . '</div>',
            '<label for="initrd">'
            . _('Init')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="initrd" class='
            . '"form-control" id="initrd">'
            . $initrd
            . '</textarea>'
            . '</div>',
            '<label for="type">'
            . _('Type')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="type" id='
            . '"type" value="'
            . $type
            . '"/>'
            . '</div>',
            '<label for="isAd">'
            . _('Is Advanced')
            . '</label>' => '<input type="checkbox" name="advanced" id='
            . '"isAd"'
            . $isAd
            . '/>',
            '<label for="access">'
            . _('Accessed By')
            . '</label>' => $accessSel,
            '<label for="add">'
            . _('Create Task type')
            . '</label>' => '<button class="btn btn-info btn-block" type="submit" '
            . 'id="add" name="add">'
            . _('Add')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'TASKTYPE_FIELDS',
                array(
                    'fields' => &$fields,
                    'TaskType' => self::getClass('TaskType')
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'TASKTYPE_ADD',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
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
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Create the new type.
     *
     * @return void
     */
    public function addPost()
    {
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $icon = filter_input(
            INPUT_POST,
            'icon'
        );
        $kernel = filter_input(
            INPUT_POST,
            'kernel'
        );
        $kernelargs = filter_input(
            INPUT_POST,
            'kernelargs'
        );
        $initrd = filter_input(
            INPUT_POST,
            'initrd'
        );
        $type = filter_input(
            INPUT_POST,
            'type'
        );
        $access = filter_input(
            INPUT_POST,
            'access'
        );
        $advanced = isset($_POST['advanced']);
        try {
            if (self::getClass('TaskTypeManager')->exists($name)) {
                throw new Exception(
                    _('A task type already exists with this name!')
                );
            }
            $TaskType = self::getClass('TaskType')
                ->set('name', $name)
                ->set('description', $description)
                ->set('icon', $icon)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $kernelargs)
                ->set('initrd', $initrd)
                ->set('type', $type)
                ->set('isAdvanced', $advanced)
                ->set('access', $access);
            if (!$TaskType->save()) {
                throw new Exception(_('Add task type failed!'));
            }
            $hook = 'TASK_TYPE_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Task Type added!'),
                    'title' => _('Task Type Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'TASK_TYPE_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Task Type Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('TaskType' => &$TaskType)
            );
        unset($TaskType);
        echo $msg;
        exit;
    }
    /**
     * TaskType Edit General Information.
     *
     * @return void
     */
    public function taskTypeGeneral()
    {
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
        $name = (
            filter_input(
                INPUT_POST,
                'name'
            ) ?: $this->obj->get('name')
        );
        $description = (
            filter_input(
                INPUT_POST,
                'description'
            ) ?: $this->obj->get('description')
        );
        $icon = (
            filter_input(
                INPUT_POST,
                'icon'
            ) ?: $this->obj->get('icon')
        );
        $kernel = (
            filter_input(
                INPUT_POST,
                'kernel'
            ) ?: $this->obj->get('kernel')
        );
        $kernelargs = (
            filter_input(
                INPUT_POST,
                'kernelargs'
            ) ?: $this->obj->get('kernelargs')
        );
        $initrd = (
            filter_input(
                INPUT_POST,
                'initrd'
            ) ?: $this->obj->get('initrd')
        );
        $type = (
            filter_input(
                INPUT_POST,
                'type'
            ) ?: $this->obj->get('type')
        );
        $access = (
            filter_input(
                INPUT_POST,
                'access'
            ) ?: $this->obj->get('access')
        );
        $advanced = (
            isset($_POST['advanced']) ?: $this->obj->get('advanced')
        );
        $isAd = (
            $advanced ?
            ' checked' :
            ''
        );
        $accessTypes = array(
            'both',
            'host',
            'group'
        );
        $accessSel = self::selectForm(
            'access',
            $accessTypes,
            $access
        );
        unset($accessTypes);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<label for="name">'
            . _('Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" value="'
            . $name
            . '" class="form-control" autocomplete="off" '
            . 'required/>'
            . '</div>',
            '<label for="description">'
            . _('Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="description" class="form-control">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="icon">'
            . _('Icon')
            . '</label>' => self::getClass('TaskType')->iconlist($icon),
            '<label for="kernel">'
            . _('Kernel')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="kernel" id="kernel" '
            . 'value="'
            . $kernel
            . '"/>'
            . '</div>',
            '<label for="kernargs">'
            . _('Kernel Arguments')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="kernelargs" id='
            . '"kernargs" value="'
            . $kernelargs
            . '"/>'
            . '</div>',
            '<label for="initrd">'
            . _('Init')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="initrd" class='
            . '"form-control" id="initrd">'
            . $initrd
            . '</textarea>'
            . '</div>',
            '<label for="type">'
            . _('Type')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="type" id='
            . '"type" value="'
            . $type
            . '"/>'
            . '</div>',
            '<label for="isAd">'
            . _('Is Advanced')
            . '</label>' => '<input type="checkbox" name="advanced" id='
            . '"isAd"'
            . $isAd
            . '/>',
            '<label for="access">'
            . _('Accessed By')
            . '</label>' => $accessSel,
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button class="btn btn-info btn-block" type="submit" '
            . 'id="update" name="update">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'TASKTYPE_FIELDS',
                array(
                    'fields' => &$fields,
                    'TaskType' => self::getClass('TaskState')
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'TASKTYPE_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
            );
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="tasktype-gen">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Task Type General');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab="taskstate-gen">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
    }
    /**
     * Edit the current type.
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->taskTypeGeneral();
        echo '</div>';
    }
    /**
     * Update the item.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'TASKTYPE_EDIT_POST',
                array('TaskType' => &$this->obj)
            );
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $icon = filter_input(
            INPUT_POST,
            'icon'
        );
        $kernel = filter_input(
            INPUT_POST,
            'kernel'
        );
        $kernelargs = filter_input(
            INPUT_POST,
            'kernelargs'
        );
        $initrd = filter_input(
            INPUT_POST,
            'initrd'
        );
        $type = filter_input(
            INPUT_POST,
            'type'
        );
        $access = filter_input(
            INPUT_POST,
            'access'
        );
        $advanced = isset($_POST['advanced']);
        try {
            if ($this->obj->get('name') != $name
                && self::getClass('TaskTypeManager')->exists($name)
            ) {
                throw new Exception(
                    _('A task type already exists with this name!')
                );
            }
            $this->obj
                ->set('name', $name)
                ->set('description', $description)
                ->set('icon', $icon)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $kernelargs)
                ->set('initrd', $initrd)
                ->set('type', $type)
                ->set('isAdvanced', $advanced)
                ->set('access', $access);
            if (!$this->obj->save()) {
                throw new Exception(_('Update task state failed!'));
            }
            $hook = 'TASK_TYPE_EDIT_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Task Type Updated!'),
                    'title' => _('Task Type Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'TASK_TYPE_EDIT_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Task Type Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('TaskType' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
