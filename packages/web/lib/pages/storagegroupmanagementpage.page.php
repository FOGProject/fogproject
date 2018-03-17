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
            _('Storage Group Name'),
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

        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'storagegroup',
                _('Storage Group Name')
            ) => self::makeInput(
                'form-control storagegroupname-input',
                'storagegroup',
                _('Storage Group name'),
                'text',
                'storagegroup',
                $storagegroup,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Storage Group Description')
            ) => self::makeTextarea(
                'form-control storagegroupdescription-input',
                'description',
                _('Storage Group Description'),
                'description',
                $description,
                false
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );

        self::$HookManager->processEvent(
            'STORAGEGROUP_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'StorageGroup' => self::getClass('StorageGroup')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        self::makeFormTag(
            'form-horizontal',
            'storagegroup-create-form',
            $this->formAcion,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="storagegroup-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Storage Group');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
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
        $storagegroup = trim(
            filter_input(INPUT_POST, 'storagegroup')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('StorageGroupManager')
                ->exists($storagegroup);
            if ($exists) {
                throw new Exception(
                    _('A storage group exists with this name!')
                );
            }
            $StorageGroup = self::getClass('StorageGroup')
                ->set('name', $storagegroup)
                ->set('description', $description);
            if (!$StorageGroup->save()) {
                $serverFault = true;
                throw new Exception(self::$foglang['DBupfailed']);
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'STORAGEGROUP_ADD_POST_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => self::$foglang['SGCreated'],
                    'title' => _('Storage Group Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'STORAGEGROUP_ADD_POST_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Group Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=storagegroup&sub=edit&id='
        //    . $StorageGroup->get('id')
        //);
        self::$HookManager->processEvent(
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
        $storagegroup = (
            filter_input(INPUT_POST, 'storagegroup') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'storagegroup',
                _('Storage Group Name')
            ) => self::makeInput(
                'form-control storagegroupname-input',
                'storagegroup',
                _('Storage Group name'),
                'text',
                'storagegroup',
                $storagegroup,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Storage Group Description')
            ) => self::makeTextarea(
                'form-control storagegroupdescription-input',
                'description',
                _('Storage Group Description'),
                'description',
                $description,
                false
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
            'STORAGEGROUP_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'StorageGroup' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo  self::makeFormTag(
            'form-horizontal',
            'storagegroup-general-form',
            self::makeTabUpdateURL(
                'storagegroup-general',
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
        $storagegroup = trim(
            filter_input(INPUT_POST, 'storagegroup')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        $exists = self::getClass('StorageGroupManager')
            ->exists($storagegroup);
        if ($storagegroup != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A storage group already exists with this name!')
            );
        }

        $this->obj
            ->set('name', $storagegroup)
            ->set('description', $description);
    }
    /**
     * Presents the storage group membership.
     *
     * @return void
     */
    public function storagegroupMembership()
    {
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-membership',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'membership-master',
            _('Update Master Node'),
            'btn btn-primary master',
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

        echo '<!-- Storage Nodes -->';
        echo '<div class="box-group" id="membership">';
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
            'generator' => function () {
                $this->storagegroupGeneral();
            }
        ];

        // Membership
        $tabData[] = [
            'name' => _('Membership'),
            'id' => 'storagegroup-membership',
            'generator' => function () {
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
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'STORAGEGROUP_EDIT_POST_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Storage Group updated!'),
                    'title' => _('Storage Group Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'STORAGEGROUP_EDIT_POST_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Group Update Fail')
                ]
            );
        }

        self::$HookManager->processEvent(
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
    /**
     * Present the export information.
     *
     * @return void
     */
    public function export()
    {
        // The data to use for building our table.
        $this->headerData = [];
        $this->templates = [];
        $this->attributes = [];

        $obj = self::getClass('StorageGroupManager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                continue;
            }
            array_push($this->headerData, $common);
            array_push($this->templates, '');
            array_push($this->attributes, []);
            unset($real);
        }

        $this->title = _('Export Storage Groups');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export Storage Nodes');
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
        $this->render(12, 'storagegroup-export-table');
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
        $obj = self::getClass('StorageGroupManager');
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
        // Setup our columns for the CSV.
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
            'STORAGENODE_EXPORT_ITEMS',
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
