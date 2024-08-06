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
            $this->formAction,
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
        echo '<div class="box-footer with-border">';
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
        echo '<div class="box-footer with-border">';
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
     * Display storage group images.
     *
     * @return void
     */
    public function storagegroupImages()
    {
        // Image Associations
        $this->headerData = [
            _('Image Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-image',
                $this->obj->get('id')
            )
            . '" ';

        $buttons .= self::makeButton(
            'storagegroup-image-send',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroup-image-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Storage Group Image Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'storagegroup-image-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('image');
        echo '</div>';
        echo '</div>';

        // Make this storage group primary for these images?
        $this->headerData[1] = _('Primary');
        $buttons = self::makeButton(
            'storagegroup-image-primary-send',
            _('Make primary'),
            'btn btn-info pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroup-image-primary-remove',
            _('Unset primary'),
            'btn btn-warning pull-left',
            $props
        );
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Set Storage Group as Primary for Images');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'storagegroup-image-primary-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo self::makeModal(
            'unsetImagePrimaryModal',
            _('Unset storage group as primary group'),
            _(
                'Please confirm you would like to unset the primary group from the'
                . ' selected images'
            ),
            self::makeButton(
                "closeImagePrimaryDeleteModal",
                _('Cancel'),
                'btn btn-outline pull-left',
                'data-dismiss="modal"'
            )
            . self::makeButton(
                "confirmImagePrimaryDeleteModal",
                _('Unset'),
                'btn btn-outline pull-right'
            ),
            '',
            'warning'
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Storage Group images post
     *
     * @return void
     */
    public function storagegroupImagePost()
    {
        if (isset($_POST['confirmadd'])) {
            $images = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $images = $images['additems'];
            if (count($images ?: []) > 0) {
                $this->obj->addImage($images);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $images = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $images = $images['remitems'];
            if (count($images ?: []) > 0) {
                $this->obj->removeImage($images);
            }
        }
        if (isset($_POST['confirmaddprimary'])) {
            $images = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $images = $images['additems'];
            $imagesToAssoc = array_diff(
                $images,
                $this->obj->get('images')
            );
            if (count($imagesToAssoc ?: []) > 0) {
                $this->obj->addImage($imagesToAssoc)->save();
            }
            if (count($images ?: []) > 0) {
                self::getClass('ImageAssociationManager')->update(
                    [
                        'imageID' => $images,
                        'primary' => 1
                    ],
                    '',
                    ['primary' => '0']
                );
                self::getClass('ImageAssociationManager')->update(
                    [
                        'storagegroupID' => $this->obj->get('id'),
                        'imageID' => $images,
                        'primary' => ['0', '']
                    ],
                    '',
                    ['primary' => '1']
                );
            }
        }
        if (isset($_POST['confirmdelprimary'])) {
            $images = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $images = $images['remitems'];
            if (count($images ?: []) > 0) {
                self::getClass('ImageAssociationManager')->update(
                    [
                        'storagegroupID' => $this->obj->get('id'),
                        'imageID' => $images,
                        'primary' => 1
                    ],
                    '',
                    ['primary' => '0']
                );
            }
        }
    }
    /**
     * Display storage group snapins.
     *
     * @return void
     */
    public function storagegroupSnapins()
    {
        // Snapin Associations
        $this->headerData = [
            _('Snapin Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-snapin',
                $this->obj->get('id')
            )
            . '" ';

        $buttons .= self::makeButton(
            'storagegroup-snapin-send',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroup-snapin-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Storage Group Snapin Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'storagegroup-snapin-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('snapin');
        echo '</div>';
        echo '</div>';

        // Make this storage group primary for these snapins?
        $this->headerData[1] = _('Primary');
        $buttons = self::makeButton(
            'storagegroup-snapin-primary-send',
            _('Make primary'),
            'btn btn-info pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroup-snapin-primary-remove',
            _('Unset primary'),
            'btn btn-warning pull-left',
            $props
        );
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Set Storage Group as Primary for Snapins');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'storagegroup-snapin-primary-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo self::makeModal(
            'unsetSnapinPrimaryModal',
            _('Unset storage group as primary group'),
            _(
                'Please confirm you would like to unset the primary group from the'
                . ' selected snapins'
            ),
            self::makeButton(
                "closeSnapinPrimaryDeleteModal",
                _('Cancel'),
                'btn btn-outline pull-left',
                'data-dismiss="modal"'
            )
            . self::makeButton(
                "confirmSnapinPrimaryDeleteModal",
                _('Unset'),
                'btn btn-outline pull-right'
            ),
            '',
            'warning'
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Storage Group snapins post
     *
     * @return void
     */
    public function storagegroupSnapinPost()
    {
        if (isset($_POST['confirmadd'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $snapins = $snapins['additems'];
            if (count($snapins ?: []) > 0) {
                $this->obj->addSnapin($snapins);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $snapins = $snapins['remitems'];
            if (count($snapins ?: []) > 0) {
                $this->obj->removeSnapin($snapins);
            }
        }
        if (isset($_POST['confirmaddprimary'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $snapins = $snapins['additems'];
            $snapinsToAssoc = array_diff(
                $snapins,
                $this->obj->get('snapins')
            );
            if (count($snapinsToAssoc ?: []) > 0) {
                $this->obj->addSnapin($snapinsToAssoc)->save();
            }
            if (count($snapins ?: []) > 0) {
                self::getClass('SnapinGroupAssociationManager')->update(
                    [
                        'snapinID' => $snapins,
                        'primary' => 1
                    ],
                    '',
                    ['primary' => '0']
                );
                self::getClass('SnapinGroupAssociationManager')->update(
                    [
                        'storagegroupID' => $this->obj->get('id'),
                        'snapinID' => $snapins,
                        'primary' => ['0', '']
                    ],
                    '',
                    ['primary' => '1']
                );
            }
        }
        if (isset($_POST['confirmdelprimary'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $snapins = $snapins['remitems'];
            if (count($snapins ?: []) > 0) {
                self::getClass('SnapinGroupAssociationManager')->update(
                    [
                        'storagegroupID' => $this->obj->get('id'),
                        'snapinID' => $snapins,
                        'primary' => 1
                    ],
                    '',
                    ['primary' => '0']
                );
            }
        }
    }
    /**
     * Display storage group storage nodes.
     *
     * @return void
     */
    public function storagegroupStoragenodes()
    {
        // Storage Node Associations
        $this->headerData = [
            _('Storage Node Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-storagenode',
                $this->obj->get('id')
            )
            . '" ';

        $buttons .= self::makeButton(
            'storagegroup-storagenode-send',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroup-storagenode-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Storage Group Storage Node Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'storagegroup-storagenode-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('storagenode');
        echo '</div>';
        echo '</div>';

        // Master Storage Node
        $buttons = self::makeButton(
            'storagegroup-storagenode-master-send',
            _('Update'),
            'btn btn-info pull-right',
            $props
        );
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Storage Group Master Storage Node');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<span id="storagenodeselector"></span>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
    }
    public function storagegroupStoragenodePost()
    {
        if (isset($_POST['confirmadd'])) {
            $storagenodes = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagenodes = $storagenodes['additems'];
            if (count($storagenodes ?: []) > 0) {
                $this->obj->addNode($storagenodes);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $storagenodes = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagenodes = $storagenodes['remitems'];
            if (count($storagenodes ?: []) > 0) {
                $this->obj->removeNode($storagenodes);
            }
        }
        if (isset($_POST['confirmmaster'])) {
            $master = filter_input(
                INPUT_POST,
                'master'
            );
            $storagenodes = array_diff(
                $this->obj->get('allnodes'),
                [$master]
            );
            self::getClass('StorageNodeManager')->update(
                [
                    'storagegroupID' => $this->obj->get('id'),
                    'id' => $storagenodes,
                    'isMaster' => '1'
                ],
                '',
                ['isMaster' => '0']
            );
            if ($master) {
                self::getClass('StorageNodeManager')->update(
                    [
                        'storagegroupID' => $this->obj->get('id'),
                        'id' => $master,
                        'isMaster' => ['0', '']
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
                        'id' => 'storagegroup-image',
                        'generator' => function () {
                            $this->storagegroupImages();
                        }
                    ],
                    [
                        'name' => _('Snapins'),
                        'id' => 'storagegroup-snapin',
                        'generator' => function () {
                            $this->storagegroupSnapins();
                        }
                    ],
                    [
                        'name' => _('Storage Nodes'),
                        'id' => 'storagegroup-storagenode',
                        'generator' => function () {
                            $this->storagegroupStoragenodes();
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
        try {
            global $tab;
            switch ($tab) {
                case 'storagegroup-general':
                    $this->storagegroupGeneralPost();
                    break;
                case 'storagegroup-image':
                    $this->storagegroupImagePost();
                    break;
                case 'storagegroup-snapin':
                    $this->storagegroupSnapinPost();
                    break;
                case 'storagegroup-storagenode':
                    $this->storagegroupStoragenodePost();
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
            . "AND `snapinGroupAssoc`.`sgaStorageGroupID` = '"
            . $this->obj->get('id')
            . "'"
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
    /**
     * Gets the storage node selector for setting master storage nodes.
     *
     * @return string
     */
    public function getStoragegroupMasterStoragenodes()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        Route::ids(
            'storagenode',
            ['storagegroupID' => $this->obj->get('id')]
        );
        $storagenodesAssigned = json_decode(Route::getData(), true);
        if (!count($storagenodesAssigned ?: [])) {
            echo json_encode(
                [
                    'content' => _('No storage nodes assigned to this storage group'),
                    'disablebtn' => true
                ]
            );
            exit;
        }
        Route::names(
            'storagenode',
            ['id' => $storagenodesAssigned]
        );
        $storagenodeNames = json_decode(Route::getData());
        foreach ($storagenodeNames as &$storagenode) {
            $storagenodes[$storagenode->id] = $storagenode->name;
            unset($storagenode);
        }
        unset($storagenodeNames);
        Route::ids(
            'storagenode',
            [
                'storagegroupID' => $this->obj->get('id'),
                'isMaster' => '1'
            ],
            'id'
        );
        $masterstoragenode = json_decode(Route::getData(), true);
        $masterstoragenode = array_shift($masterstoragenode);
        $storagenodeSelector = self::selectForm(
            'storagenode',
            $storagenodes,
            $masterstoragenode,
            true,
            '',
            true
        );
        echo json_encode(
            [
                'content' => $storagenodeSelector,
                'disablebtn' => false
            ]
        );
        exit;
    }
}
