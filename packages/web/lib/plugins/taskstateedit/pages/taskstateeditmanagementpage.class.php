<?php
/**
 * Task state edit page.
 *
 * PHP Version 5
 *
 * @category TaskstateeditManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task state edit page.
 *
 * @category TaskstateeditManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskstateeditManagementPage extends FOGPage
{
    /**
     * The node to work from.
     *
     * @var string
     */
    public $node = 'taskstateedit';
    /**
     * Initialize our page.
     *
     * @param string $name The name to setup.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Task State Management');
        self::$foglang['ExportTaskstateedit'] = _('Export Task States');
        self::$foglang['ImportTaskstateedit'] = _('Import Task States');
        parent::__construct($this->name);
        $this->menu['list'] = sprintf(self::$foglang['ListAll'], _('Task States'));
        $this->menu['add'] = sprintf(self::$foglang['CreateNew'], _('Task State'));
        global $id;
        global $sub;
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat#taskstate-gen" => self::$foglang['General'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('Name') => $this->obj->get('name'),
                _('Icon') => sprintf(
                    '<i class="fa fa-%s"></i>',
                    $this->obj->get('icon')
                ),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Icon'),
            _('Name'),
        );
        $this->templates = array(
            '<input type="checkbox" name="taskstateedit[]" value='
            . '"${id}" class="toggle-action"/>',
            '<i class="fa fa-${icon} fa-1x"></i>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${id}" title='
                . '"%s">&nbsp;&nbsp;${name}</a>',
                $this->node,
                _('Edit')
            ),
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'parser-false filter-false'
            ),
            array(
                'width' => 22,
                'class' => 'filter-false'
            ),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $TaskState the object to use
         *
         * @return void
         */
        self::$returnData = function (&$TaskState) {
            $this->data[] = array(
                'id' => $TaskState->id,
                'name' => $TaskState->name,
                'icon' => $TaskState->icon,
            );
            unset($TaskState);
        };
    }
    /**
     * Create new state.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Task State');
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
        $additional = filter_input(
            INPUT_POST,
            'additional'
        );
        $fields = array(
            '<label for="name">'
            . _('Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="name" id='
            . '"name" value="'
            . $name
            . '" required/>'
            . '</div>',
            '<label for="desc">'
            . _('Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" class="form-control" id="desc">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="icon">'
            . _('Icon')
            . '</label>' => self::getClass('TaskType')->iconlist($icon),
            '<label for="additional">'
            . _('Additional Icon elements')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="additional" id='
            . '"additional" value="'
            . $additional
            . '"/>'
            . '</div>',
            '<label for="add">'
            . _('Create Task state')
            . '</label>' => '<button class="btn btn-info btn-block" type="submit" '
            . 'id="add" name="add">'
            . _('Add')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'TASKSTATE_FIELDS',
                array(
                    'fields' => &$fields,
                    'TaskState' => self::getClass('TaskState')
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'TASKSTATE_ADD',
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
     * Create the item.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('TASK_STATE');
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
        $additional = filter_input(
            INPUT_POST,
            'additional'
        );
        $iconval = $icon
            . ' '
            . $additional;
        try {
            if (self::getClass('TaskStateManager')->exists($name)) {
                throw new Exception(
                    _('A task state already exists with this name!')
                );
            }
            $TaskState = self::getClass('TaskState')
                ->set('name', $name)
                ->set('description', $description)
                ->set('icon', $iconval);
            if (!$TaskState->save()) {
                throw new Exception(_('Add task state failed!'));
            }
            $TaskState->set(
                'order',
                $TaskState->get('id')
            )->save();
            $hook = 'TASK_STATE_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Task State added!'),
                    'title' => _('Task State Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'TASK_STATE_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Task State Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('TaskState' => &$TaskState)
            );
        unset($TaskState);
        echo $msg;
        exit;
    }
    /**
     * TaskState Edit General Information.
     *
     * @return void
     */
    public function taskStateGeneral()
    {
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
        $iconarr = explode(
            ' ',
            $this->obj->get('icon')
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
            ) ?: array_shift($iconarr)
        );
        $additional = (
            filter_input(
                INPUT_POST,
                'additiona'
            ) ?: implode(' ', (array)$iconarr)
        );
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
            . _('Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="name" id='
            . '"name" value="'
            . $name
            . '" required/>'
            . '</div>',
            '<label for="desc">'
            . _('Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" class="form-control" id="desc">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="icon">'
            . _('Icon')
            . '</label>' => self::getClass('TaskType')->iconlist($icon),
            '<label for="additional">'
            . _('Additional Icon elements')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="additional" id='
            . '"additional" value="'
            . $additional
            . '"/>'
            . '</div>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button class="btn btn-info btn-block" type="submit" '
            . 'id="update" name="update">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'TASKSTATE_FIELDS',
                array(
                    'fields' => &$fields,
                    'TaskState' => self::getClass('TaskState')
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'TASKSTATE_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
            );
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="taskstate-gen">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Task State General');
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
     * Update a state.
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->taskStateGeneral();
        echo '</div>';
    }
    /**
     * Actually store the update.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'TASKSTATE_EDIT_POST',
                array(
                    'TaskState' => &$this->obj
                )
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
        $additional = filter_input(
            INPUT_POST,
            'additional'
        );
        $iconval = $icon
            . ' '
            . $additional;
        try {
            if ($this->obj->get('name') != $name
                && elf::getClass('TaskStateManager')->exists($name)
            ) {
                throw new Exception(
                    _('A task state already exists with this name!')
                );
            }
            $this->obj
                ->set('name', $name)
                ->set('description', $description)
                ->set('icon', $iconval);
            if (!$this->obj->save()) {
                throw new Exception(_('Update task state failed!'));
            }
            $hook = 'TASK_STATE_EDIT_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Task State Updated!'),
                    'title' => _('Task State Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'TASK_STATE_EDIT_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Task State Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('TaskState' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
