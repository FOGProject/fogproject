<?php
/**
 * Task type edit page.
 *
 * PHP Version 5
 *
 * @category TasktypeeditManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task type edit page.
 *
 * @category TasktypeeditManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TasktypeeditManagement extends FOGPage
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
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Access'),
            _('Kernel Args')
        ];
        $this->attributes = [
            [],
            [],
            []
        ];
    }
    /**
     * Create new task type.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Task Type');

        $tasktype = filter_input(INPUT_POST, 'tasktype');
        $description = filter_input(INPUT_POST, 'description');
        $icon = filter_input(INPUT_POST, 'icon');
        $kernel = filter_input(INPUT_POST, 'kernel');
        $kernelargs = filter_input(INPUT_POST, 'kernelargs');
        $initrd = filter_input(INPUT_POST, 'initrd');
        $type = filter_input(INPUT_POST, 'type');
        $access = filter_input(INPUT_POST, 'access');
        $advanced = isset($_POST['advanced']);
        $isAd = (
            $advanced ?
            ' checked' :
            ''
        );
        $accessTypes = [
            'both',
            'host',
            'group'
        ];
        $accessSel = self::selectForm(
            'access',
            $accessTypes,
            $access
        );
        $iconSel = self::getClass('TaskType')->iconlist($icon);
        unset($accessTypes);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'tasktype',
                _('Task Type Name')
            ) => self::makeInput(
                'form-control tasktypename-input',
                'tasktype',
                _('Task Type Name'),
                'text',
                'tasktype',
                $tasktype,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Task Type Description')
            ) => self::makeTextarea(
                'form-control tasktypedescription-input',
                'description',
                _('Task Type Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'icon',
                _('Task Type Icon')
            ) => $iconSel,
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Kernel')
            ) => self::makeInput(
                'form-control tasktypekernel-input',
                'kernel',
                'bzImage',
                'text',
                'kernel',
                $kernel
            ),
            self::makeLabel(
                $labelClass,
                'kernelargs',
                _('Kernel Arguments')
            ) => self::makeInput(
                'form-control tasktypekernelargs-input',
                'kernelargs',
                'debug acpi=off',
                'text',
                'kernelargs',
                $kernelargs
            ),
            self::makeLabel(
                $labelClass,
                'initrd',
                _('Init FS')
            ) => self::makeInput(
                'form-control tasktypeinit-input',
                'initrd',
                'init.xz',
                'text',
                'initrd',
                $initrd
            ),
            self::makeLabel(
                $labelClass,
                'type',
                _('Type')
            ) => self::makeInput(
                'form-control tasktypetype-input',
                'type',
                'fog',
                'text',
                'type',
                $type
            ),
            self::makeLabel(
                $labelClass,
                'isAd',
                _('Advanced Task')
            ) => self::makeInput(
                '',
                'advanced',
                '',
                'checkbox',
                'isAd',
                false,
                false,
                -1,
                -1,
                $isAd
            ),
            self::makeLabel(
                $labelClass,
                'access',
                _('Accessed By')
            ) => $accessSel
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'TASKTYPEEDIT_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'TaskType' => self::getClass('TaskType')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'tasktype-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="tasktype-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Task Type');
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
     * Create new task type.
     *
     * @return void
     */
    public function addModal()
    {
        $tasktype = filter_input(INPUT_POST, 'tasktype');
        $description = filter_input(INPUT_POST, 'description');
        $icon = filter_input(INPUT_POST, 'icon');
        $kernel = filter_input(INPUT_POST, 'kernel');
        $kernelargs = filter_input(INPUT_POST, 'kernelargs');
        $initrd = filter_input(INPUT_POST, 'initrd');
        $type = filter_input(INPUT_POST, 'type');
        $access = filter_input(INPUT_POST, 'access');
        $advanced = isset($_POST['advanced']);
        $isAd = (
            $advanced ?
            ' checked' :
            ''
        );
        $accessTypes = [
            'both',
            'host',
            'group'
        ];
        $accessSel = self::selectForm(
            'access',
            $accessTypes,
            $access
        );
        $iconSel = self::getClass('TaskType')->iconlist($icon);
        unset($accessTypes);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'tasktype',
                _('Task Type Name')
            ) => self::makeInput(
                'form-control tasktypename-input',
                'tasktype',
                _('Task Type Name'),
                'text',
                'tasktype',
                $tasktype,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Task Type Description')
            ) => self::makeTextarea(
                'form-control tasktypedescription-input',
                'description',
                _('Task Type Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'icon',
                _('Task Type Icon')
            ) => $iconSel,
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Kernel')
            ) => self::makeInput(
                'form-control tasktypekernel-input',
                'kernel',
                'bzImage',
                'text',
                'kernel',
                $kernel
            ),
            self::makeLabel(
                $labelClass,
                'kernelargs',
                _('Kernel Arguments')
            ) => self::makeInput(
                'form-control tasktypekernelargs-input',
                'kernelargs',
                'debug acpi=off',
                'text',
                'kernelargs',
                $kernelargs
            ),
            self::makeLabel(
                $labelClass,
                'initrd',
                _('Init FS')
            ) => self::makeInput(
                'form-control tasktypeinit-input',
                'initrd',
                'init.xz',
                'text',
                'initrd',
                $initrd
            ),
            self::makeLabel(
                $labelClass,
                'type',
                _('Type')
            ) => self::makeInput(
                'form-control tasktypetype-input',
                'type',
                'fog',
                'text',
                'type',
                $type
            ),
            self::makeLabel(
                $labelClass,
                'isAd',
                _('Advanced Task')
            ) => self::makeInput(
                '',
                'advanced',
                '',
                'checkbox',
                'isAd',
                false,
                false,
                -1,
                -1,
                $isAd
            ),
            self::makeLabel(
                $labelClass,
                'access',
                _('Accessed By')
            ) => $accessSel
        ];

        self::$HookManager->processEvent(
            'TASKTYPEEDIT_ADD_FIELDS',
            [
                'fields' => &$fields,
                'TaskType' => self::getClass('TaskType')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=tasktypeedit&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Create the new type.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('TASKTYPEEDIT_ADD_POST');
        $tasktype = trim(
            filter_input(INPUT_POST, 'tasktype')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $icon = trim(
            filter_input(INPUT_POST, 'icon')
        );
        $kernel = trim(
            filter_input(INPUT_POST, 'kernel')
        );
        $kernelargs = trim(
            filter_input(INPUT_POST, 'kernelargs')
        );
        $initrd = trim(
            filter_input(INPUT_POST, 'initrd')
        );
        $type = trim(
            filter_input(INPUT_POST, 'type')
        );
        $access = trim(
            filter_input(INPUT_POST, 'access')
        );
        $advanced = isset($_POST['advanced']);

        $serverFault = false;
        try {
            $exists = self::getClass('TaskTypeManager')
                ->exists($tasktype);
            if ($exists) {
                throw new Exception(
                    _('A task type already exists with this name!')
                );
            }
            $TaskType = self::getClass('TaskType')
                ->set('name', $tasktype)
                ->set('description', $description)
                ->set('icon', $icon)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $kernelargs)
                ->set('initrd', $initrd)
                ->set('type', $type)
                ->set('isAdvanced', $advanced)
                ->set('access', $access);
            if (!$TaskType->save()) {
                $serverFault = true;
                throw new Exception(_('Add task type failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'TASKTYPEEDIT_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Task Type added!'),
                    'title' => _('Task Type Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'TASKTYPEEDIT_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Type Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=tasktypeedit&sub=edit&id='
        //    . $TaskType->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'TaskType' => &$TaskType,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($TaskType);
        echo $msg;
        exit;
    }
    /**
     * TaskType Edit General Information.
     *
     * @return void
     */
    public function tasktypeGeneral()
    {
        $tasktype = (
            filter_input(INPUT_POST, 'tasktype') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $icon = (
            filter_input(INPUT_POST, 'icon') ?:
            $this->obj->get('icon')
        );
        $kernel = (
            filter_input(INPUT_POST, 'kernel') ?:
            $this->obj->get('kernel')
        );
        $kernelargs = (
            filter_input(INPUT_POST, 'kernelargs') ?:
            $this->obj->get('kernelArgs')
        );
        $initrd = (
            filter_input(INPUT_POST, 'initrd') ?:
            $this->obj->get('initrd')
        );
        $type = (
            filter_input(INPUT_POST, 'type') ?:
            $this->obj->get('type')
        );
        $access = (
            filter_input(INPUT_POST, 'access') ?:
            $this->obj->get('access')
        );
        $advanced = (
            isset($_POST['advanced']) ?:
            $this->obj->get('isAdvanced')
        );
        $isAd = (
            $advanced ?
            ' checked' :
            ''
        );
        $accessTypes = [
            'both',
            'host',
            'group'
        ];
        $accessSel = self::selectForm(
            'access',
            $accessTypes,
            $access
        );
        $iconSel = self::getClass('TaskType')->iconlist($icon);
        unset($accessTypes);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'tasktype',
                _('Task Type Name')
            ) => self::makeInput(
                'form-control tasktypename-input',
                'tasktype',
                _('Task Type Name'),
                'text',
                'tasktype',
                $tasktype,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Task Type Description')
            ) => self::makeTextarea(
                'form-control tasktypedescription-input',
                'description',
                _('Task Type Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'icon',
                _('Task Type Icon')
            ) => $iconSel,
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Kernel')
            ) => self::makeInput(
                'form-control tasktypekernel-input',
                'kernel',
                'bzImage',
                'text',
                'kernel',
                $kernel
            ),
            self::makeLabel(
                $labelClass,
                'kernelargs',
                _('Kernel Arguments')
            ) => self::makeInput(
                'form-control tasktypekernelargs-input',
                'kernelargs',
                'debug acpi=off',
                'text',
                'kernelargs',
                $kernelargs
            ),
            self::makeLabel(
                $labelClass,
                'initrd',
                _('Init FS')
            ) => self::makeInput(
                'form-control tasktypeinit-input',
                'initrd',
                'init.xz',
                'text',
                'initrd',
                $initrd
            ),
            self::makeLabel(
                $labelClass,
                'type',
                _('Type')
            ) => self::makeInput(
                'form-control tasktypetype-input',
                'type',
                'fog',
                'text',
                'type',
                $type
            ),
            self::makeLabel(
                $labelClass,
                'isAd',
                _('Advanced Task')
            ) => self::makeInput(
                '',
                'advanced',
                '',
                'checkbox',
                'isAd',
                false,
                false,
                -1,
                -1,
                $isAd
            ),
            self::makeLabel(
                $labelClass,
                'access',
                _('Accessed By')
            ) => $accessSel
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
            'TASKTYPEEDIT_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'TaskType' => self::getClass('TaskType')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'tasktype-general-form',
            self::makeTabUpdateURL(
                'tasktype-general',
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
     * Create the new type.
     *
     * @return void
     */
    public function tasktypeGeneralPost()
    {
        $tasktype = trim(
            filter_input(INPUT_POST, 'tasktype')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $icon = trim(
            filter_input(INPUT_POST, 'icon')
        );
        $kernel = trim(
            filter_input(INPUT_POST, 'kernel')
        );
        $kernelargs = trim(
            filter_input(INPUT_POST, 'kernelargs')
        );
        $initrd = trim(
            filter_input(INPUT_POST, 'initrd')
        );
        $type = trim(
            filter_input(INPUT_POST, 'type')
        );
        $access = trim(
            filter_input(INPUT_POST, 'access')
        );
        $advanced = isset($_POST['advanced']);

        $exists = self::getClass('TaskTypeManager')
            ->exists($tasktype);
        if ($tasktype != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A task type already exists with this name!')
            );
        }
        $this->obj
            ->set('name', $tasktype)
            ->set('description', $description)
            ->set('icon', $icon)
            ->set('kernel', $kernel)
            ->set('kernelArgs', $kernelargs)
            ->set('initrd', $initrd)
            ->set('type', $type)
            ->set('isAdvanced', $advanced)
            ->set('access', $access);
    }
    /**
     * Edit the current type.
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
            'id' => 'tasktype-general',
            'generator' => function () {
                $this->tasktypeGeneral();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Update the item.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'TASKTYPEEDIT_EDIT_POST',
            ['TaskType' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'tasktype-general':
                    $this->tasktypeGeneralPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Task type update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASKTYPEEDIT_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Task Type Updated!'),
                    'title' => _('Task Type Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResposeCodes::HTTP_BAD_REQUEST
            );
            $hook = 'TASKTYPEEDIT_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Type Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'TaskType' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        echo $msg;
        exit;
    }
}
