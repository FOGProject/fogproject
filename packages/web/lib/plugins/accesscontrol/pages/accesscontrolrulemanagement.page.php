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
        $this->name = 'Accesscontrol Rule Management';
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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    private function _addFields()
    {
        $type = filter_input(INPUT_POST, 'type');
        $parent = filter_input(INPUT_POST, 'parent');
        $node = filter_input(INPUT_POST, 'node');
        $value = filter_input(INPUT_POST, 'value');

        $labelClass = 'col-sm-3 control-label';

        return [
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
    }
    /**
     * Create new role.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'accesscontrolrule',
            _('Create New Rule'),
            'ACCESSCONTROLRULE_ADD_FIELDS',
            'AccessControlRule'
        );
    }
    /**
     * Create new role.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'accesscontrolrule',
            'ACCESSCONTROLRULE_ADD_FIELDS',
            'AccessControlRule'
        );
    }
    /**
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'AccessControlRule',
            'ACCESSCONTROLRULE_ADD',
            _('Rule added!'),
            _('Rule Create Success'),
            _('Rule Create Fail'),
            function (&$serverFault) {
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
                $exists = self::getClass('AccessControlRuleManager')
                    ->exists($name);
                if ($exists) {
                    throw new Exception(
                        _('A rule already exists with that type-value pair!')
                    );
                }
                /*$exists = self::getClass('AccessControlRuleManager')->exists(
                    $value,
                    '',
                    'value'
                );
                if ($exists) {
                    throw new Exception(
                        _('A rule already exists with this value!')
                    );
                }*/
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
                return $AccessControlRule;
            }
        );
    }
    /**
     * Displays the access control general tab.
     *
     * @return void
     */
    public function accesscontrolruleGeneral()
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
            'ACCESSCONTROLRULE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'AccessControlRule' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'accesscontrolrule-general-form',
            self::makeTabUpdateURL(
                'accesscontrolrule-general',
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
    public function accesscontrolruleGeneralPost()
    {
        self::checkAuthAndCSRF();
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
        //$valexists = $this->obj->getManager()->exists($value, '', 'value');
        $nameexists = $this->obj->getManager()->exists($name);

        //if ($value != $this->obj->get('value')
        //    && $valexists
        //) {
        //    throw new Exception(_('A value already exists with this content!'));
        //}
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
     * The role rules presentation.
     *
     * @return void
     */
    public function accesscontrolruleRoles()
    {
        $this->headerData = [
            _('Role Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'accesscontrolrule-role',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'accesscontrolrule-role-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'accesscontrolrule-role-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Accesscontrol Rule Role Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '</h4>';
        echo '<div class="box-body">';
        $this->render(12, 'accesscontrolrule-role-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('role');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually update the role rules assocation.
     *
     * @return void
     */
    public function accesscontrolruleRolePost()
    {
        self::checkAuthAndCSRF();
        if (isset($_POST['confirmadd'])) {
            $roles = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $roles = $roles['additems'];
            if (count($roles ?: []) > 0) {
                $this->obj->addRole($roles);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $roles = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $roles = $roles['remitems'];
            if (count($roles ?: []) > 0) {
                $this->obj->removeRole($roles);
            }
        }
    }
    /**
     * The edit element.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s %s: %s',
            _('Edit'),
            $this->obj->get('name'),
            _('ID'),
            $this->obj->get('id')
        );

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'accesscontrolrule-general',
            'generator' => function () {
                $this->accesscontrolruleGeneral();
            }
        ];

        // Roles
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Role Association'),
                        'id' => 'accesscontrolrule-role',
                        'generator' => function () {
                            $this->accesscontrolruleRoles();
                        }
                    ]
                ]
            ]
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
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'ACCESSCONTROLRULE_EDIT_POST',
            ['AccessControlRule' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'accesscontrolrule-general':
                    $this->accesscontrolruleGeneralPost();
                    break;
                case 'accesscontrolrule-role':
                    $this->accesscontrolruleRolePost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Rule update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'ACCESSCONTROLRULE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Accesscontrol Rule updated!'),
                    'title' => _('Accesscontrol Rule Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'ACCESSCONTROLRULE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Accesscontrol Rule Update Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'AccessControlRule' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Gets the roles list.
     *
     * @return void
     */
    public function getRolesList()
    {
        return $this->assocItemsList(
            'accesscontrol',
            'accesscontrolruleassociation',
            'roleRuleAssoc',
            '`roles`.`rID`',
            '`roleRuleAssoc`.`rraRoleID`',
            '`roleRuleAssoc`.`rraRuleID`',
            [
                [
                    'db' => 'accesscontrolruleAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
