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
        $this->renderAddForm(
            'accesscontrol',
            _('Create New Accesscontrol'),
            'ACCESSCONTROL_ADD_FIELDS',
            'AccessControl'
        );
    }
    /**
     * Create new accesscontrol.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'accesscontrol',
            'ACCESSCONTROL_ADD_FIELDS',
            'AccessControl'
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
            'AccessControl',
            'ACCESSCONTROL_ADD',
            _('Accesscontrol added!'),
            _('Accesscontrol Create Success'),
            _('Accesscontrol Create Fail'),
            function (&$serverFault) {
                $accesscontrol = trim(
                    filter_input(INPUT_POST, 'accesscontrol')
                );
                $description = trim(
                    filter_input(INPUT_POST, 'description')
                );
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
                return $AccessControl;
            }
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
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Update the edit elements.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'AccessControl',
            'ACCESSCONTROL_EDIT',
            _('Accesscontrol updated!'),
            _('Accesscontrol Update Success'),
            _('Accesscontrol Update Fail'),
            function (&$serverFault) {
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
            }
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
