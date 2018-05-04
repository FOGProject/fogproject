<?php
/**
 * Access Control plugin
 *
 * PHP version 7
 *
 * @category AccessControlRuleManagement
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlRuleManagement
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlRuleManagement extends FOGPage
{
    /**
     * The node this works off.
     *
     * @var string
     */
    public $node = 'accesscontrolrule';
    /**
     * Constructor
     *
     * @param string $name The name for the page.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Rule Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Rule Name'),
            _('Rule Parent'),
            _('Rule Type'),
            _('Rule Value'),
            _('Rule Node')
        ];
        $this->attributes = [
            [],
            [],
            [],
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
        $this->title = _('Create New Rule');

        $type = filter_input(INPUT_POST, 'type');
        $parent = filter_input(INPUT_POST, 'parent');
        $node = filter_input(INPUT_POST, 'node');
        $value = filter_input(INPUT_POST, 'value');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'type',
                _('Rule Type')
            ) => self::makeInput(
                'form-control ruletype-input',
                'type',
                _('Rule Type'),
                'text',
                'type',
                $type,
                true
            ),
            self::makeLabel(
                $labelClass,
                'parent',
                _('Rule Parent')
            ) => self::makeInput(
                'form-control ruleparent-input',
                'parent',
                _('Rule Parent'),
                'text',
                'parent',
                $parent,
                true
            ),
            self::makeLabel(
                $labelClass,
                'node',
                _('Rule Node')
            ) => self::makeInput(
                'form-control rulenode-input',
                'node',
                _('Rule Node'),
                'text',
                'node',
                $node
            ),
            self::makeLabel(
                $labelClass,
                'value',
                _('Rule Value')
            ) => self::makeInput(
                'form-control rulevalue-input',
                'value',
                _('Rule Value'),
                'text',
                'value',
                $value,
                true
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'ACCESSCONTROLRULE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'AccessControlRule' => self::getClass('AccessControlRule')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'rule-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="rule-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Rule');
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
     * Create new role.
     *
     * @return void
     */
    public function addModal()
    {
        $type = filter_input(INPUT_POST, 'type');
        $parent = filter_input(INPUT_POST, 'parent');
        $node = filter_input(INPUT_POST, 'node');
        $value = filter_input(INPUT_POST, 'value');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'type',
                _('Rule Type')
            ) => self::makeInput(
                'form-control ruletype-input',
                'type',
                _('Rule Type'),
                'text',
                'type',
                $type,
                true
            ),
            self::makeLabel(
                $labelClass,
                'parent',
                _('Rule Parent')
            ) => self::makeInput(
                'form-control ruleparent-input',
                'parent',
                _('Rule Parent'),
                'text',
                'parent',
                $parent,
                true
            ),
            self::makeLabel(
                $labelClass,
                'node',
                _('Rule Node')
            ) => self::makeInput(
                'form-control rulenode-input',
                'node',
                _('Rule Node'),
                'text',
                'node',
                $node
            ),
            self::makeLabel(
                $labelClass,
                'value',
                _('Rule Value')
            ) => self::makeInput(
                'form-control rulevalue-input',
                'value',
                _('Rule Value'),
                'text',
                'value',
                $value,
                true
            )
        ];

        self::$HookManager->processEvent(
            'ACCESSCONTROLRULE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'AccessControlRule' => self::getClass('AccessControlRule')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=accesscontrolrule&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
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
        self::$HookManger->processEvent('ACCESSCONTROLRULE_ADD_POST');
        $type = trim(
            filter_input(INPUT_POST, 'type')
        );
        $parent = trim(
            filter_input(INPUT_POST, 'parent')
        );
        $node = trim(
            filter_input(INPUT_POST, 'node')
        );
        $value = trim(
            filter_input(INPUT_POST, 'value')
        );
        $name = $type
            . '-'
            . $value;

        $serverFault = false;
        try {
            $exists = self::getClass('AccessControlRuleManager')
                ->exists($name);
            if ($exists) {
                throw new Exception(
                    _('A rule already exists with that type-value pair!')
                );
            }
            $exists = self::getClass('AccessControlRuleManager')->exists(
                $value,
                '',
                'value'
            );
            if ($exists) {
                throw new Exception(
                    _('A rule already exists with this value!')
                );
            }
            $AccessControlRule = self::getClass('AccessControlRule')
                ->set('type', $type)
                ->set('value', $value)
                ->set('parent', $parent)
                ->set('node', $node)
                ->set('name', $name);
            if (!$AccessControlRule->save()) {
                $serverFault = true;
                throw new Exception(_('Add rule failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'ACCESSCONTROLRULE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Rule added!'),
                    'title' => _('Rule Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'ACCESSCONTROLRULE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Rule Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=accesscontrolrule&sub=edit&id='
        //    . $AccessControlRule->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'AccessControlRule' => &$AccessControlRule,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_Code($code);
        unset($AccessControlRule);
        echo $msg;
        exit;
    }
    /**
     * Displays the access control general tab.
     *
     * @return void
     */
    public function ruleGeneral()
    {
        $type = (
            filter_input(INPUT_POST, 'type') ?:
            $this->obj->get('type')
        );
        $parent = (
            filter_input(INPUT_POST, 'parent') ?:
            $this->obj->get('parent')
        );
        $node = (
            filter_input(INPUT_POST, 'node') ?:
            $this->obj->get('node')
        );
        $value = (
            filter_input(INPUT_POST, 'value') ?:
            $this->obj->get('value')
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'type',
                _('Rule Type')
            ) => self::makeInput(
                'form-control ruletype-input',
                'type',
                _('Rule Type'),
                'text',
                'type',
                $type,
                true
            ),
            self::makeLabel(
                $labelClass,
                'parent',
                _('Rule Parent')
            ) => self::makeInput(
                'form-control ruleparent-input',
                'parent',
                _('Rule Parent'),
                'text',
                'parent',
                $parent,
                true
            ),
            self::makeLabel(
                $labelClass,
                'node',
                _('Rule Node')
            ) => self::makeInput(
                'form-control rulenode-input',
                'node',
                _('Rule Node'),
                'text',
                'node',
                $node
            ),
            self::makeLabel(
                $labelClass,
                'value',
                _('Rule Value')
            ) => self::makeInput(
                'form-control rulevalue-input',
                'value',
                _('Rule Value'),
                'text',
                'value',
                $value,
                true
            )
        ];

        self::$HookManager->processEvent(
            'ACCESSCONTROLRULE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'AccessControlRule' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

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

        echo self::makeFormTag(
            'form-horizontal',
            'rule-general-form',
            self::makeTabUpdateURL(
                'rule-general',
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
        echo '<div class="box-footer">';
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
    public function ruleGeneralPost()
    {
        $type = trim(
            filter_input(INPUT_POST, 'type')
        );
        $parent = trim(
            filter_input(INPUT_POST, 'parent')
        );
        $node = trim(
            filter_input(INPUT_POST, 'node')
        );
        $value = trim(
            filter_input(INPUT_POST, 'value')
        );
        $orgname = $this->obj->get('type')
            . '-'
            . $this->obj->get('value');
        $name = $type
            . '-'
            . $value;
        $valexists = $this->obj->getManager()->exists($value, '', 'value');
        $nameexists = $this->obj->getManager()->exists($name);

        if ($value != $this->obj->get('value')
            && $valexists
        ) {
            throw new Exception(_('A value already exists with this content!'));
        }
        if ($orgname != $name && $nameexists) {
            throw new Exception(
                _('A name with this type-value pair already exists!')
            );
        }
        $this->obj
            ->set('type', $type)
            ->set('value', $value)
            ->set('parent', $parent)
            ->set('node', $node)
            ->set('name', $name);
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
            'id' => 'rule-general',
            'generator' => function () {
                $this->ruleGeneral();
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
            'RULE_EDIT_POST',
            ['AccessControlRule' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'rule-general':
                $this->ruleGeneralPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Rule update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'RULE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Rule updated!'),
                    'title' => _('Rule Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'RULE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Rule Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'AccessControlRule' => &$this->obj,
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

        $obj = self::getClass('AccessControlRuleManager');

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
        echo _('Export Rules');
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
        $this->render(12, 'rule-export-table');
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
        $obj = self::getClass('AccessControlRuleManager');
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
            'RULE_EXPORT_ITEMS',
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
