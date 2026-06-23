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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    private function _addFields()
    {
        $accesscontrol = filter_input(INPUT_POST, 'accesscontrol');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 control-label';

        return [
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
    }
    /**
     * Create new accesscontrol.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Accesscontrol');

        $fields = $this->_addFields();

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

        $this->renderCreateForm(
            'accesscontrol',
            [[_('Create New Accesscontrol'), $rendered]],
            $buttons
        );
    }
    /**
     * Create new accesscontrol.
     *
     * @return void
     */
    public function addModal()
    {
        $fields = $this->_addFields();

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
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('ACCESSCONTROL_ADD_POST');
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
        $this->jsonHookResponse(
            [
                'AccessControl' => &$AccessControl,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
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
        self::checkAuthAndCSRF();
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
        $this->renderAssocTab(
            'accesscontrol-user',
            _('Accesscontrol User Associations'),
            _('User Name'),
            'user'
        );
    }
    /**
     * Update users.
     *
     * @return void
     */
    public function accesscontrolUserPost()
    {
        $this->assocPost('addUser', 'removeUser');
    }
    /**
     * Preset the rules page.
     *
     * @return void
     */
    public function accesscontrolRules()
    {
        $this->renderAssocTab(
            'accesscontrol-rule',
            _('Accesscontrol Rule Associations'),
            _('Accesscontrol Rule Name'),
            'rule'
        );
    }
    /**
     * Update rules.
     *
     * @return void
     */
    public function accesscontrolRulePost()
    {
        $this->assocPost('addRule', 'removeRule');
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
        self::checkAuthAndCSRF();
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
        $this->jsonHookResponse(
            [
                'AccessControl' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Gets the user list.
     *
     * @return void
     */
    public function getUsersList()
    {
        return $this->assocItemsList(
            'user',
            'accesscontrolassociation',
            'roleUserAssoc',
            '`users`.`uID`',
            '`roleUserAssoc`.`ruaUserID`',
            '`roleUserAssoc`.`ruaRoleID`',
            [
                [
                    'db' => 'accesscontrolAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the rules list.
     *
     * @return void
     */
    public function getRulesList()
    {
        return $this->assocItemsList(
            'accesscontrolrule',
            'accesscontrolruleassociation',
            'roleRuleAssoc',
            '`rules`.`ruleID`',
            '`roleRuleAssoc`.`rraRuleID`',
            '`roleRuleAssoc`.`rraRoleID`',
            [
                [
                    'db' => 'accesscontrolAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
