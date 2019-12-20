<?php
/**
 * Displays the storage group information.
 *
 * PHP version 5
 *
 * @category StorageGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays the storage group information.
 *
 * @category StorageGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageGroupManagement extends FOGPage
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

        $labelClass = 'col-sm-3 control-label';

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
            'btn btn-primary pull-right'
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

        echo self::makeFormTag(
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
     * Create a new storage group.
     *
     * @return void
     */
    public function addModal()
    {
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 control-label';

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

        self::$HookManager->processEvent(
            'STORAGEGROUP_ADD_FIELDS',
            [
                'fields' => &$fields,
                'StorageGroup' => self::getClass('StorageGroup')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=storagegroup&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
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
            $hook = 'STORAGEGROUP_ADD_SUCCESS';
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
            $hook = 'STORAGEGROUP_ADD_FAIL';
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

        $labelClass = 'col-sm-3 control-label';

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
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
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
        echo $this->deleteModal();
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
     * Presents the image membership.
     *
     * @return void
     */
    public function imageMembership()
    {
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-image',
                $this->obj->get('id')
            )
            . '" ';

        $buttons .= self::makeButton(
            'image-add',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'image-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        $this->headerData = [
            _('Image Name'),
            _('Primary'),
            _('Image Associated')
        ];
        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<!-- Images -->';
        echo '<div class="box-group" id="image">';
        echo '<div class="box box-solid">';
        echo '<div id="updateimage" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'image-membership-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Presents the snapin membership.
     *
     * @return void
     */
    public function snapinMembership()
    {
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-snapin',
                $this->obj->get('id')
            )
            . '" ';

        $buttons .= self::makeButton(
            'snapin-add',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'snapin-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        $this->headerData = [
            _('Snapin Name'),
            _('Primary'),
            _('Snapin Associated')
        ];
        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<!-- Snapins -->';
        echo '<div class="box-group" id="snapin">';
        echo '<div class="box box-solid">';
        echo '<div id="updatesnapins" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'snapin-membership-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
            'btn btn-primary master pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'membership-add',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'membership-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        $this->headerData = [
            _('Storage Node Name'),
            _('Storage Node Master'),
            _('Storage Node Associated')
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
                ['isMaster' => '0']
            );
            if ($master) {
                self::getClass('StorageNodeManager')->update(
                    [
                        'storagegroupID' => $this->obj->get('id'),
                        'id' => $master
                    ],
                    '',
                    ['isMaster' => '1']
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

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Images'),
                        'id' => 'image-membership',
                        'generator' => function () {
                            $this->imageMembership();
                        }
                    ],
                    [
                        'name' => _('Snapins'),
                        'id' => 'snapin-membership',
                        'generator' => function () {
                            $this->snapinMembership();
                        }
                    ],
                    [
                        'name' => _('Storage Nodes'),
                        'id' => 'storagegroup-membership',
                        'generator' => function () {
                            $this->storagegroupMembership();
                        }
                    ]
                ]
            ]
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
            case 'storagegroup-snapin':
                $this->snapinMembershipPost();
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
            $hook = 'STORAGEGROUP_EDIT_SUCCESS';
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
            $hook = 'STORAGEGROUP_EDIT_FAIL';
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
     * Presents the images list table.
     *
     * @return void
     */
    public function getImagesList()
    {
        $join = [
            'LEFT OUTER JOIN `imageGroupAssoc` ON '
            . "`images`.`imageID` = `imageGroupAssoc`.`igaImageID`"
            . "AND `imageGroupAssoc`.`igaStorageGroupID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'igaImageID',
            'dt' => 'origID'
        ];
        $columns[] = [
            'db' => 'igaPrimary',
            'dt' => 'primary'
        ];
        $columns[] = [
            'db' => 'storagegroupAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'image',
            'imageassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Presents the snapins list table.
     *
     * @return void
     */
    public function getSnapinsList()
    {
        $join = [
            'LEFT OUTER JOIN `snapinGroupAssoc` ON '
            . "`snapins`.`sID` = `snapinGroupAssoc`.`sgaSnapinID`"
            . "AND `snapinGroupAssoc`.`sgaStorageGroupID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'sgaSnapinID',
            'dt' => 'origID'
        ];
        $columns[] = [
            'db' => 'sgaPrimary',
            'dt' => 'primary'
        ];
        $columns[] = [
            'db' => 'storagegroupAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'snapin',
            'snapingroupassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Presents the Storage nodes list table.
     *
     * @return void
     */
    public function getStorageNodesList()
    {
        $join = [
            'LEFT OUTER JOIN `nfsGroups` ON '
            . "`nfsGroups`.`ngID` = `nfsGroupMembers`.`ngmGroupID` "
            . "AND `nfsGroups`.`ngID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'storagegroupAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        $columns[] = [
            'db' => 'ngmGroupID',
            'dt' => 'origID',
        ];

        return $this->obj->getItemsList(
            'storagenode',
            'storagegroup',
            $join,
            '',
            $columns
        );
    }
}
