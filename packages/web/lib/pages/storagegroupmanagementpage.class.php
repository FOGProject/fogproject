<?php
/**
 * Displays the storage group information.
 *
 * PHP version 5
 *
 * @category StorageGroupManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays the storage group information.
 *
 * @category StorageGroupManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageGroupManagementPage extends FOGPage
{
    /**
     * Node this class works from.
     *
     * @var string
     */
    public $node = 'storagegroup';
    /**
     * Initializes the storage page.
     *
     * @param string $name Name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Storage Group Management';
        parent::__construct($this->name);
        $this->headerData = [
            self::$foglang['SG'],
            _('Total Clients')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Create a new storage group.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Storage Group');
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $fields = [
            '<label class="col-sm-2 control-label" for="name">'
            . _('Storage Group Name')
            . '</label>' => '<input type="text" name="name" '
            . 'value="'
            . $name
            . '" class="storagegroupname-input form-control" '
            . 'id="name" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Storage Group Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>'
        ];
        self::$HookManager
            ->processEvent(
                'STORAGEGROUP_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'StorageGroup' => self::getClass('StorageGroup')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="storagegroup-create">';
        echo '<form id="storagegroup-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Storage Group -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h3 class="box-title">';
        echo _('Create New Storage Group');
        echo '</h3>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="send">'
            . _('Create')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * Actually create the new group.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-Type: application/json');
        self::$HookManager->processEvent('STORAGEGROUP_ADD_POST');
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $serverFault = false;
        try {
            if (empty($name)) {
                throw new Exception(self::$foglang['SGNameReq']);
            }
            if (self::getClass('StorageGroupManager')->exists($name)) {
                throw new Exception(self::$foglang['SGExist']);
            }
            $StorageGroup = self::getClass('StorageGroup')
                ->set('name', $name)
                ->set('description', $desc);
            if (!$StorageGroup->save()) {
                $serverFault = true;
                throw new Exception(self::$foglang['DBupfailed']);
            }
            $code = 201;
            $hook = 'STORAGEGROUP_ADD_POST_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => self::$foglang['SGCreated'],
                    'title' => _('Storage Group Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'STORAGEGROUP_ADD_POST_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Group Create Fail')
                ]
            );
        }
        //header('Location: ../management/index.php?node=storagegroup&sub=edit&id=' . $StorageGroup->get('id'));
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'StorageGroup' => &$StorageGroup,
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        http_response_code($code);
        unset($StorageGroup);
        echo $msg;
        exit;
    }
    /**
     * Presents the storage group general.
     *
     * @return void
     */
    public function storagegroupGeneral()
    {
        $name = filter_input(INPUT_POST, 'name') ?:
            $this->obj->get('name');
        $description = filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description');
        $fields = [
            '<label class="col-sm-2 control-label" for="name">'
            . _('Storage Group Name')
            . '</label>' => '<input type="text" name="name" '
            . 'value="'
            . $name
            . '" class="storagegroupname-input form-control" '
            . 'id="name" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Storage Group Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>'
        ];
        self::$HookManager
            ->processEvent(
                'STORAGEGROUP_GENERAL_FIELDS',
                [
                    'fields' => &$fields,
                    'StorageGroup' => &$this->obj
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<form id="storagegroup-general-form" class="form-horizontal" '
            . 'method="post" action="'
            . self::makeTabUpdateURL('storagegroup-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="general-send">'
            . _('Update')
            . '</button>';
        echo '<button class="btn btn-danger pull-right" id="general-delete">'
            . _('Delete')
            . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the storage group general elements.
     *
     * @return void
     */
    public function storagegroupGeneralPost()
    {
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $exists = self::getClass('StorageGroupManager')->exists(
            $name,
            $this->obj->get('id')
        );
        if (!$name) {
            throw new Exception(self::$foglang['SGName']);
        }
        if ($this->obj->get('name') != $name
            && $exists
        ) {
            throw new Exception(self::$foglang['SGExist']);
        }
        $this->obj
            ->set('name', $name)
            ->set('description', $desc);
    }
    /**
     * Presents the storage group membership.
     *
     * @return void
     */
    public function storagegroupMembership()
    {
        global $id;
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=storagegroup-membership" ';

        echo '<!-- Storage Nodes -->';
        echo '<div class="box-group" id="membership">';
        $buttons = self::makeButton(
            'membership-master',
            _('Update Master Node'),
            'btn btn-primary master' . $id,
            $props
        );
        $buttons .= self::makeButton(
            'membership-add',
            _('Add selected'),
            'btn btn-success',
            $props
        );
        $buttons .= self::makeButton(
            'membership-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );

        $this->headerData = [
            _('Storage Node Name'),
            _('Storage Node Master'),
            _('Storage Node Associated')
        ];
        $this->templates = [
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            [],
            []
        ];
        echo '<div class="box box-solid">';
        echo '<div id="updatestoragenodes" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'storagegroup-membership-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the storage group membership.
     *
     * @return void
     */
    public function storagegroupMembershipPost()
    {

        if (isset($_POST['updatemembership'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membership' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membership'];
            if (count($membership ?: []) > 0) {
                $this->obj->addNode($membership);
            }
        }
        if (isset($_POST['membershipdel'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membershipRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membershipRemove'];
            if (count($membership ?: []) > 0) {
                $this->obj->removeNode($membership);
            }
        }
        if (isset($_POST['mastersel'])) {
            $master = filter_input(
                INPUT_POST,
                'master'
            );
            self::getClass('StorageNodeManager')->update(
                [
                    'storagegroupID' => $this->obj->get('id'),
                    'isMaster' => '1'
                ],
                '',
                [
                    'isMaster' => '0'
                ]
            );
            if ($master) {
                self::getClass('StorageNodeManager')->update(
                    [
                        'storagegroupID' => $this->obj->get('id'),
                        'id' => $master
                    ],
                    '',
                    [
                        'isMaster' => '1'
                    ]
                );
            }
        }
    }
    /**
     * Edit a storage group.
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
            'id' => 'storagegroup-general',
            'generator' => function() {
                $this->storagegroupGeneral();
            }
        ];

        // Membership
        $tabData[] = [
            'name' => _('Membership'),
            'id' => 'storagegroup-membership',
            'generator' => function() {
                $this->storagegroupMembership();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually submit the changes.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'STORAGEGROUP_EDIT_POST',
            ['StorageGroup' => &$this->obj]
        );
        $serverFault = false;
        try{
            global $tab;
            switch ($tab) {
            case 'storagegroup-general':
                $this->storagegroupGeneralPost();
                break;
            case 'storagegroup-membership':
                $this->storagegroupMembershipPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Storage Group Update Failed'));
            }
            $code = 201;
            $hook = 'STORAGEGROUP_EDIT_POST_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Storage Group updated!'),
                    'title' => _('Storage Group Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'STORAGEGROUP_EDIT_POST_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Group Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'StorageGroup' => &$this->obj,
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
     * Presents the Storage nodes list table.
     *
     * @return void
     */
    public function getStorageNodesList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $where = "`nfsGroups`.`ngID` = '"
            . $this->obj->get('id')
            . "'";

        $storagegroupsSqlStr = "SELECT `%s`,"
            . "`ngmGroupID` AS `origID`,IF(`ngmGroupID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `ngmGroupID`
            FROM `%s`
            CROSS JOIN `nfsGroups`
            %s
            %s
            %s";
        $storagegroupsFilterStr = "SELECT COUNT(`%s`),"
            . "`ngmGroupID` AS `origID`,IF(`ngmGroupID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `ngmGroupID`
            FROM `%s`
            CROSS JOIN `nfsGroups`
            %s";
        $storagegroupsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('StorageNodeManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = [
            'db' => 'ngmGroupID',
            'dt' => 'association'
        ];
        $columns[] = [
            'db' => 'origID',
            'dt' => 'origID',
            'removeFromQuery' => true
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'nfsGroupMembers',
                'ngmID',
                $columns,
                $storagegroupsSqlStr,
                $storagegroupsFilterStr,
                $storagegroupsTotalStr,
                $where
            )
        );
        exit;
    }
}
