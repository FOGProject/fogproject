<?php
/**
 * Access Control plugin
 *
 * PHP version 7
 *
 * @category AccessControlRuleManagementPage
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlRuleManagementPage
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlRuleManagementPage extends FOGPage
{
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
        /**
         * The name to give.
         */
        $this->name = 'Rule Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Rule Name'),
            _('Rule Parent'),
            _('Rule Type'),
            _('Rule Value'),
            _('Rule Node')
        ];
        $this->templates = [
            '',
            '',
            '',
            '',
            ''
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

        $labelClass = 'col-sm-2 control-label';

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
                $node,
                true
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
                'node',
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
        echo self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );
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
        $serverFault = false;
        $name = $type
            . '-'
            . $value;
        try {
            if (self::getClass('AccessControlRuleManager')->exists($name)) {
                throw new Exception(
                    _('A rule already exists with the generated name!')
                );
            }
            if (self::getClass('AccessControlRuleManager')->exists($value, '', 'value')) {
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
            $code = 201;
            $hook = 'ACCESSCONTROLRULE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Rule added!'),
                    'title' => _('Rule Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'ACCESSCONTROLRULE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Rule Create Fail')
                ]
            );
        }
        //header('Location: ../management/index.php?node=accesscontrolrule&sub=edit&id=' . $AccessControlRule->get('id'));
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
    }
    /**
     * Updates the access control general element.
     *
     * @return void
     */
    public function ruleGeneralPost()
    {
    }
    /**
     * The edit element.
     *
     * @return void
     */
    public function edit()
    {
    }
    /**
     * Update the edit elements.
     *
     * @return void
     */
    public function editPost()
    {
    }
}
