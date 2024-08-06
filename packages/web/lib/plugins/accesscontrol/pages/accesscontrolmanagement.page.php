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
        $this->name = 'Accesscontrol Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Accesscontrol Name'),
            _('Accesscontrol Description')
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Create new accesscontrol.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Accesscontrol');

        $accesscontrol = filter_input(INPUT_POST, 'accesscontrol');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'accesscontrol',
                _('Accesscontrol Name')
            ) => self::makeInput(
                'form-control accesscontrolname-input',
                'accesscontrol',
                _('Access Control Name'),
                'text',
                'accesscontrol',
                $accesscontrol,
                true
            ),
            self::makelabel(
                $labelClass,
                'description',
                _('Accesscontrol Description')
            ) => self::makeTextarea(
                'form-control accesscontroldescription-input',
                'description',
                _('Accesscontrol Description'),
                'description',
                $description
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'ACCESSCONTROL_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'AccessControl' => self::getClass('AccessControl')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'accesscontrol-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="accesscontrol-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Accesscontrol');
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
     * Create new accesscontrol.
     *
     * @return void
     */
    public function addModal()
    {
        $accesscontrol = filter_input(INPUT_POST, 'accesscontrol');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'accesscontrol',
                _('Accesscontrol Name')
            ) => self::makeInput(
                'form-control accesscontrolname-input',
                'accesscontrol',
                _('Access Control Name'),
                'text',
                'accesscontrol',
                $accesscontrol,
                true
            ),
            self::makelabel(
                $labelClass,
                'description',
                _('Accesscontrol Description')
            ) => self::makeTextarea(
                'form-control accesscontroldescription-input',
                'description',
                _('Accesscontrol Description'),
                'description',
                $description
            )
        ];

        self::$HookManager->processEvent(
            'ACCESSCONTROL_ADD_FIELDS',
            [
                'fields' => &$fields,
                'AccessControl' => self::getClass('AccessControl')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=accesscontrol&sub=add',
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
        self::$HookManger->processEvent('ACCESSCONTROL_ADD_POST');
        $accesscontrol = trim(
            filter_input(INPUT_POST, 'accesscontrol')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('AccessControlManager')
                ->exists($accesscontrol);
            if ($exists) {
                throw new Exception(
                    _('An accesscontrol already exists with this name!')
                );
            }
            $AccessControl = self::getClass('AccessControl')
                ->set('name', $accesscontrol)
                ->set('description', $description);
            if (!$AccessControl->save()) {
                $serverFault = true;
                throw new Exception(_('Add accesscontrol failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'ACCESSCONTROL_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Accesscontrol added!'),
                    'title' => _('Accesscontrol Create Success')
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
                    'title' => _('Accesscontrol Create Fail')
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
    public function accesscontrolGeneral()
    {
        $accesscontrol = (
            filter_input(INPUT_POST, 'accesscontrol') ?:
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
                'accesscontrol',
                _('Accesscontrol Name')
            ) => self::makeInput(
                'form-control accesscontrolname-input',
                'accesscontrol',
                _('Access Control Name'),
                'text',
                'accesscontrol',
                $accesscontrol,
                true
            ),
            self::makelabel(
                $labelClass,
                'description',
                _('Accesscontrol Description')
            ) => self::makeTextarea(
                'form-control accesscontroldescription-input',
                'description',
                _('Accesscontrol Description'),
                'description',
                $description
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
            'accesscontrol-general-form',
            self::makeTabUpdateURL(
                'accesscontrol-general',
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
    public function accesscontrolGeneralPost()
    {
        $accesscontrol = trim(
            filter_input(INPUT_POST, 'accesscontrol')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        $exists = self::getClass('AccessControlManager')
            ->exists($accesscontrol);
        if ($accesscontrol != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('An accesscontrol with this name already exists!')
            );
        }
        $this->obj
            ->set('name', $accesscontrol)
            ->set('description', $description);
    }
    /**
     * Present the users tab.
     *
     * @return void
     */
    public function accesscontrolUsers()
    {
        $this->headerData = [
            _('User Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'accesscontrol-user',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'accesscontrol-user-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'accesscontrol-user-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Accesscontrol User Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'accesscontrol-user-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('user');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Update users.
     *
     * @return void
     */
    public function accesscontrolUserPost()
    {
        if (isset($_POST['confirmadd'])) {
            $users = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $users = $users['additems'];
            if (count($users ?: []) > 0) {
                $this->obj->addUser($users);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $users = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $users = $users['remitems'];
            if (count($users ?: []) > 0) {
                $this->obj->removeUser($users);
            }
        }
    }
    /**
     * Preset the rules page.
     *
     * @return void
     */
    public function accesscontrolRules()
    {
        $this->headerData = [
            _('Accesscontrol Rule Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'accesscontrol-rule',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'accesscontrol-rule-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'accesscontrol-rule-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Accesscontrol Rule Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'accesscontrol-rule-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('rule');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Update rules.
     *
     * @return void
     */
    public function accesscontrolRulePost()
    {
        if (isset($_POST['confirmadd'])) {
            $rules = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $rules = $rules['additems'];
            if (count($rules ?: []) > 0) {
                $this->obj->addRule($rules);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $rules = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $rules = $rules['remitems'];
            if (count($rules ?: []) > 0) {
                $this->obj->removeRule($rules);
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
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'accesscontrol-general',
            'generator' => function () {
                $this->accesscontrolGeneral();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Rule Association'),
                        'id' => 'accesscontrol-rule',
                        'generator' => function () {
                            $this->accesscontrolRules();
                        }
                    ],
                    [
                        'name' => _('User Association'),
                        'id' => 'accesscontrol-user',
                        'generator' => function () {
                            $this->accesscontrolUsers();
                        }
                    ]
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
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
            'ACCESSCONTROL_EDIT_POST',
            ['AccessControl' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'accesscontrol-general':
                    $this->accesscontrolGeneralPost();
                    break;
                case 'accesscontrol-rule':
                    $this->accesscontrolRulePost();
                    break;
                case 'accesscontrol-user':
                    $this->accesscontrolUserPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Accesscontrol update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'ACCESSCONTROL_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Accesscontrol updated!'),
                    'title' => _('Accesscontrol Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'ACCESSCONTROL_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Accesscontrol Update Fail')
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
     * Gets the user list.
     *
     * @return void
     */
    public function getUsersList()
    {
        $join = [
            'LEFT OUTER JOIN `roleUserAssoc` ON '
            . "`users`.`uID` = `roleUserAssoc`.`ruaUserID` "
            . "AND `roleUserAssoc`.`ruaRoleID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'accesscontrolAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'user',
            'accesscontrolassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Gets the rules list.
     *
     * @return void
     */
    public function getRulesList()
    {
        $join = [
            'LEFT OUTER JOIN `roleRuleAssoc` ON '
            . "`rules`.`ruleID` = `roleRuleAssoc`.`rraRuleID` "
            . "AND `roleRuleAssoc`.`rraRoleID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'accesscontrolAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'accesscontrolrule',
            'accesscontrolruleassociation',
            $join,
            '',
            $columns
        );
    }
}
