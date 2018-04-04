<?php
/**
 * Access Control plugin
 *
 * PHP version 7
 *
 * @category AccessControlManagement
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlManagement
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlManagement extends FOGPage
{
    /**
     * The node of this page.
     *
     * @var string
     */
    public $node = 'accesscontrol';
    /**
     * Constructor
     *
     * @param string $name The name for the page.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        /**
         * The name to give.
         */
        $this->name = 'Role Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Role Name'),
            _('Role Description')
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Create new role.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Role');

        $role = filter_input(INPUT_POST, 'role');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'role',
                _('Role Name')
            ) => self::makeInput(
                'form-control rolename-input',
                'role',
                _('Access Control Name'),
                'text',
                'role',
                $role,
                true
            ),
            self::makelabel(
                $labelClass,
                'description',
                _('Role Description')
            ) => self::makeTextarea(
                'form-control roledescription-input',
                'description',
                _('Role Description'),
                'description',
                $description
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );

        self::$HookManager->processEvent(
            'ACCESSCONTROL_ADD_FIELDS',
            [
                'fields' => &$fields,
                'button' => &$buttons,
                'AccessControl' => self::getClass('AccessControl')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'role-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="role-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Role');
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
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManger->processEvent('ACCESSCONTROL_ADD_POST');
        $role = trim(
            filter_input(INPUT_POST, 'role')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $serverFault = false;
        try {
            if (!$role) {
                throw new Exception(
                    _('A role name is required!')
                );
            }
            if (self::getClass('AccessControlManager')->exists($role)) {
                throw new Exception(
                    _('A role already exists with this name!')
                );
            }
            $AccessControl = self::getClass('AccessControl')
                ->set('name', $role)
                ->set('description', $description);
            if (!$AccessControl->save()) {
                $serverFault = true;
                throw new Exception(_('Add access control failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'ACCESSCONTROL_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Access Control added!'),
                    'title' => _('Access Control Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'ACCESSCONTROL_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Access Control Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=accesscontrol&sub=edit&id='
        //    . $AccessControl->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'AccessControl' => &$AccessControl,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_Code($code);
        unset($AccessControl);
        echo $msg;
        exit;
    }
    /**
     * Displays the access control general tab.
     *
     * @return void
     */
    public function roleGeneral()
    {
        $role = (
            filter_input(INPUT_POST, 'role') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'role',
                _('Role Name')
            ) => self::makeInput(
                'form-control rolename-input',
                'role',
                _('Access Control Name'),
                'text',
                'role',
                $role,
                true
            ),
            self::makelabel(
                $labelClass,
                'description',
                _('Role Description')
            ) => self::makeTextarea(
                'form-control roledescription-input',
                'description',
                _('Role Description'),
                'description',
                $description
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-right'
        );

        self::$HookManager->processEvent(
            'ACCESSCONTROL_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'AccessControl' => &$this->obj
            ]
        );

        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'role-general-form',
            self::makeTabUpdateURL(
                'role-general',
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
     * Updates the access control general element.
     *
     * @return void
     */
    public function roleGeneralPost()
    {
        $role = trim(
            filter_input(INPUT_POST, 'role')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        if ($role != $this->obj->get('name')
            && self::getClass('AccessControlManager')->exists($role)
        ) {
            throw new Exception(_('A role with this name already exists!'));
        }
        $this->obj
            ->set('name', $role)
            ->set('description', $description);
    }
    /**
     * The edit element.
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
            'id' => 'role-general',
            'generator' => function () {
                $this->roleGeneral();
            }
        ];

        echo self::tabFields($tabData);
    }
    /**
     * Update the edit elements.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'ROLE_EDIT_POST',
            ['AccessControl' => &$this->obj]
        );
        $serverFault = false;
        try{
            global $tab;
            switch ($tab) {
            case 'role-general':
                $this->roleGeneralPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Role update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'ROLE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Role updated!'),
                    'title' => _('Rule Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'ROLE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Role Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'AccessControl' => &$this->obj,
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
    /**
     * Present the export information.
     *
     * @return void
     */
    public function export()
    {
        // The data to use for building our table.
        $this->headerData = [];
        $this->attributes = [];

        $obj = self::getClass('AccessControlManager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                continue;
            }
            $this->headerData[] = $common;
            $this->attributes[] = [];
            unset($real);
        }

        $this->title = _('Export Rules');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export Roles');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported.');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _(
            'When you click on the item you want to export, it can only select '
            . 'what is currently viewable on the screen. This includes searched '
            . 'and the current page. Please use the selector to choose the amount '
            . 'of items you would like to export.'
        );
        echo '</p>';
        $this->render(12, 'role-export-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Present the export list.
     *
     * @return void
     */
    public function getExportList()
    {
        header('Content-type: application/json');
        $obj = self::getClass('AccessControlManager');
        $table = $obj->getTable();
        $sqlstr = $obj->getQueryStr();
        $filterstr = $obj->getFilterStr();
        $totalstr = $obj->getTotalStr();
        $dbcolumns = $obj->getColumns();
        $pass_vars = $columns = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        // Setup our columns for the CSVn.
        // Automatically removes the id column.
        foreach ($dbcolumns as $common => &$real) {
            if ('id' == $common) {
                $tableID = $real;
                continue;
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        self::$HookManager->processEvent(
            'ROLE_EXPORT_ITEMS',
            [
                'table' => &$table,
                'sqlstr' => &$sqlstr,
                'filterstr' => &$filterstr,
                'totalstr' => &$totalstr,
                'columns' => &$columns
            ]
        );
        echo json_encode(
            FOGManagerController::simple(
                $pass_vars,
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $filterstr,
                $totalstr
            )
        );
        exit;
    }
}
