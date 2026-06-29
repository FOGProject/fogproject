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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $taskstate = filter_input(INPUT_POST, 'taskstate');
        $description = filter_input(INPUT_POST, 'description');
        $icon = filter_input(INPUT_POST, 'icon');
        $additional = filter_input(INPUT_POST, 'additional');
        $iconSel = self::getClass('TaskType')->iconlist($icon);

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
    }
    /**
     * Create new task state entry.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'taskstate',
            _('Create New Task State'),
            'TASKSTATEEDIT_ADD_FIELDS',
            'TaskState'
        );
    }
    /**
     * Create new task state entry.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'taskstateedit',
            'TASKSTATEEDIT_ADD_FIELDS',
            'TaskState'
        );
    }
    /**
     * Actually save the new task state.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'TaskState',
            'TASKSTATEEDIT_ADD',
            _('Task state added!'),
            _('Task State Create SUccess'),
            _('Task State Create Fail'),
            function (&$serverFault) {
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
                return $TaskState;
            }
        );
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

        $labelClass = 'col-sm-3 col-form-label';

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
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
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
            '',
            'taskstate-general-form',
            self::makeTabUpdateURL(
                'taskstate-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
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
        self::checkAuthAndCSRF();
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
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'taskstate-general',
            'generator' => function () {
                $this->taskstateGeneral();
            }
        ];
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Actually store the update.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'TaskState',
            'TASKSTATEEDIT_EDIT',
            _('Task State Updated!'),
            _('Task State Update Success'),
            _('Task State Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'taskstate-general':
                        $this->taskstateGeneralPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('Task state update failed!'));
                }
            }
        );
    }
}
