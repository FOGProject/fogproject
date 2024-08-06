<?php
/**
 * Task state edit page.
 *
 * PHP Version 5
 *
 * @category TaskstateeditManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task state edit page.
 *
 * @category TaskstateeditManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskstateeditManagement extends FOGPage
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
        $this->name = 'Task State Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Icon')
        ];
        $this->attributes = [
            [],
            ['width' => 5]
        ];
    }
    /**
     * Create new task state entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Task State');

        $taskstate = filter_input(INPUT_POST, 'taskstate');
        $description = filter_input(INPUT_POST, 'description');
        $icon = filter_input(INPUT_POST, 'icon');
        $additional = filter_input(INPUT_POST, 'additional');
        $iconSel = self::getClass('TaskType')->iconlist($icon);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'taskstate',
                _('Task State Name')
            ) => self::makeInput(
                'form-control taskstatename-input',
                'taskstate',
                _('Task State Name'),
                'text',
                'taskstate',
                $taskstate,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Task State Description')
            ) => self::makeTextarea(
                'form-control taskstatedescription-input',
                'description',
                _('Task State Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'icon',
                _('Task State Icon')
            ) => $iconSel,
            self::makeLabel(
                $labelClass,
                'additional',
                _('Additional Icon Elements')
            ) => self::makeInput(
                'form-control taskstateadditionalicon-input',
                'additional',
                'fa-spin',
                'text',
                'additional',
                $additional
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'TASKSTATEEDIT_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'TaskState' => self::getClass('TaskState')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'taskstate-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="taskstate-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Task State');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Create new task state entry.
     *
     * @return void
     */
    public function addModal()
    {
        $taskstate = filter_input(INPUT_POST, 'taskstate');
        $description = filter_input(INPUT_POST, 'description');
        $icon = filter_input(INPUT_POST, 'icon');
        $additional = filter_input(INPUT_POST, 'additional');
        $iconSel = self::getClass('TaskType')->iconlist($icon);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'taskstate',
                _('Task State Name')
            ) => self::makeInput(
                'form-control taskstatename-input',
                'taskstate',
                _('Task State Name'),
                'text',
                'taskstate',
                $taskstate,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Task State Description')
            ) => self::makeTextarea(
                'form-control taskstatedescription-input',
                'description',
                _('Task State Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'icon',
                _('Task State Icon')
            ) => $iconSel,
            self::makeLabel(
                $labelClass,
                'additional',
                _('Additional Icon Elements')
            ) => self::makeInput(
                'form-control taskstateadditionalicon-input',
                'additional',
                'fa-spin',
                'text',
                'additional',
                $additional
            )
        ];

        self::$HookManager->processEvent(
            'TASKSTATEEDIT_ADD_FIELDS',
            [
                'fields' => &$fields,
                'TaskState' => self::getClass('TaskState')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=taskstateedit&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually save the new task state.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('TASKSTATEEDIT_ADD_POST');
        $taskstate = trim(
            filter_input(INPUT_POST, 'taskstate')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $icon = trim(
            filter_input(INPUT_POST, 'icon')
        );
        $additional = trim(
            filter_input(INPUT_POST, 'additional')
        );
        $iconval = $icon . ' ' . $additional;

        $serverFault = false;
        try {
            $exists = self::getClass('TaskStateManager')
                ->exists($taskstate);
            if ($exists) {
                throw new Exception(
                    _('A task state already exists with this name!')
                );
            }
            $TaskState = self::getClass('TaskState')
                ->set('name', $taskstate)
                ->set('description', $description)
                ->set('icon', $iconval);
            if (!$TaskState->save()) {
                $serverFault = true;
                throw new Exception(_('Add task state failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'TASKSTATEEDIT_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Task state added!'),
                    'title' => _('Task State Create SUccess')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'TASKSTATEEDIT_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task State Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=taskstateedit&sub=edit&id='
        //    . $TaskState->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'TaskState' => &$TaskState,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($TaskState);
        echo $msg;
        exit;
    }
    /**
     * TaskState Edit General Information.
     *
     * @return void
     */
    public function taskstateGeneral()
    {
        $iconarr = explode(
            ' ',
            $this->obj->get('icon')
        );
        $taskstate = (
            filter_input(INPUT_POST, 'taskstate') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $icon = (
            filter_input(INPUT_POST, 'icon') ?:
            array_shift($iconarr)
        );
        $additional = (
            filter_input(INPUT_POST, 'additional') ?:
            implode(' ', (array)$iconarr)
        );
        $iconSel = self::getClass('TaskType')->iconlist($icon);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'taskstate',
                _('Task State Name')
            ) => self::makeInput(
                'form-control taskstatename-input',
                'taskstate',
                _('Task State Name'),
                'text',
                'taskstate',
                $taskstate,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Task State Description')
            ) => self::makeTextarea(
                'form-control taskstatedescription-input',
                'description',
                _('Task State Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'icon',
                _('Task State Icon')
            ) => $iconSel,
            self::makeLabel(
                $labelClass,
                'additional',
                _('Additional Icon Elements')
            ) => self::makeInput(
                'form-control taskstateadditionalicon-input',
                'additional',
                'fa-spin',
                'text',
                'additional',
                $additional
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
        );

        self::$HookManager->processEvent(
            'TASKSTATEEDIT_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'TaskState' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'taskstate-general-form',
            self::makeTabUpdateURL(
                'taskstate-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo $this->deleteModal();
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Update the general post
     *
     * @return void
     */
    public function taskstateGeneralPost()
    {
        $taskstate = trim(
            filter_input(INPUT_POST, 'taskstate')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $icon = trim(
            filter_input(INPUT_POST, 'icon')
        );
        $additional = trim(
            filter_input(INPUT_POST, 'additional')
        );
        $iconval = $icon . ' ' . $additional;

        $exists = self::getClass('TaskTypeManager')
            ->exists($taskstate);
        if ($taskstate != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A task state already exists with this name!')
            );
        }
        $this->obj
            ->set('name', $taskstate)
            ->set('description', $description)
            ->set('icon', $iconval);
    }
    /**
     * Edit this task state.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'taskstate-general',
            'generator' => function () {
                $this->taskstateGeneral();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually store the update.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'TASKSTATEEDIT_EDIT_POST',
            ['TaskState' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'taskstate-general':
                    $this->taskstateGeneralPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Task state update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASKSTATEEDIT_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Task State Updated!'),
                    'title' => _('Task State Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'TASKSTATEEDIT_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task State Update Fail')
                ]
            );
        }

        self::$HookManager->processEvent(
            $hook,
            [
                'TaskState' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
}
