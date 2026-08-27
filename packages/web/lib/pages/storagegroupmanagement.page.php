<?php
/**
 * Displays the storage group information.
 *
 * PHP version 7.4+
 *
 * @category StorageGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
        $this->name = _('Storage Group Management');
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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $description = filter_input(INPUT_POST, 'description');
        $trustedcidrs = filter_input(INPUT_POST, 'trustedcidrs');

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
            ),
            self::makeLabel(
                $labelClass,
                'trustedcidrs',
                _('Trusted Node CIDRs')
            ) => self::makeTextarea(
                'form-control storagegrouptrustedcidrs-input',
                'trustedcidrs',
                _('One IP or CIDR range per line (or comma separated)'),
                'trustedcidrs',
                $trustedcidrs,
                false
            )
        ];
    }
    /**
     * Create a new storage group.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'storagegroup',
            _('Create New Storage Group'),
            'STORAGEGROUP_ADD_FIELDS',
            'StorageGroup'
        );
    }
    /**
     * Create a new storage group.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'storagegroup',
            'STORAGEGROUP_ADD_FIELDS',
            'StorageGroup'
        );
    }
    /**
     * Actually create the new group.
     *
     * @return void
     */
    public function addPost()
    {
        self::checkAuthAndCSRF();
        header('Content-Type: application/json');
        self::$HookManager->processEvent('STORAGEGROUP_ADD_POST');
        $storagegroup = trim(
            filter_input(INPUT_POST, 'storagegroup')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $trustedcidrs = trim(
            filter_input(INPUT_POST, 'trustedcidrs')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('StorageGroupManager')
                ->exists($storagegroup);
            if ($exists) {
                throw new \Exception(
                    _('A storage group exists with this name!')
                );
            }
            $StorageGroup = self::getClass('StorageGroup')
                ->set('name', $storagegroup)
                ->set('description', $description)
                ->set('trustedcidrs', $trustedcidrs);
            if (!$StorageGroup->save()) {
                $serverFault = true;
                throw new \Exception(self::$foglang['DBupfailed']);
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'STORAGEGROUP_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => self::$foglang['SGCreated'],
                    'title' => _('Storage Group Create Success')
                ]
            );
        } catch (\Exception $e) {
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
        $this->jsonHookResponse(
            [
                'StorageGroup' => &$StorageGroup,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
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
        $trustedcidrs = (
            filter_input(INPUT_POST, 'trustedcidrs') ?:
            $this->obj->get('trustedcidrs')
        );

        $labelClass = 'col-sm-3 col-form-label';

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
            ),
            self::makeLabel(
                $labelClass,
                'trustedcidrs',
                _('Trusted Node CIDRs')
            ) => self::makeTextarea(
                'form-control storagegrouptrustedcidrs-input',
                'trustedcidrs',
                _('One IP or CIDR range per line (or comma separated)'),
                'trustedcidrs',
                $trustedcidrs,
                false
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
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

        $this->renderGeneralForm('storagegroup', $rendered, $buttons);
    }
    /**
     * Updates the storage group general elements.
     *
     * @return void
     */
    public function storagegroupGeneralPost()
    {
        self::checkAuthAndCSRF();
        $storagegroup = trim(
            filter_input(INPUT_POST, 'storagegroup')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $trustedcidrs = trim(
            filter_input(INPUT_POST, 'trustedcidrs')
        );

        $exists = self::getClass('StorageGroupManager')
            ->exists($storagegroup);
        if ($storagegroup != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(
                _('A storage group already exists with this name!')
            );
        }

        $this->obj
            ->set('name', $storagegroup)
            ->set('description', $description)
            ->set('trustedcidrs', $trustedcidrs);
    }
    /**
     * Display storage group images.
     *
     * @return void
     */
    public function storagegroupImages()
    {
        // Image Associations
        $this->renderAssocTab(
            'storagegroup-image',
            _('Storage Group Image Associations'),
            _('Image Name'),
            'image',
            'btn btn-primary float-end'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-image',
                $this->obj->get('id')
            )
            . '" ';

        // Make this storage group primary for these images?
        $this->headerData[1] = _('Primary');
        $buttons = self::makeButton(
            'storagegroup-image-primary-send',
            _('Make primary'),
            'btn btn-primary float-end',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroup-image-primary-remove',
            _('Unset primary'),
            'btn btn-warning float-start',
            $props
        );
        echo '<div class="card card-info card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Set Storage Group as Primary for Images');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, 'storagegroup-image-primary-table', $buttons);
        echo '</div>';
        echo '<div class="card-footer">';
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
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            )
            . self::makeButton(
                "confirmImagePrimaryDeleteModal",
                _('Unset'),
                'btn btn-outline-secondary float-end'
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
        self::checkAuthAndCSRF();
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
        $this->renderAssocTab(
            'storagegroup-snapin',
            _('Storage Group Snapin Associations'),
            _('Snapin Name'),
            'snapin',
            'btn btn-primary float-end'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-snapin',
                $this->obj->get('id')
            )
            . '" ';

        // Make this storage group primary for these snapins?
        $this->headerData[1] = _('Primary');
        $buttons = self::makeButton(
            'storagegroup-snapin-primary-send',
            _('Make primary'),
            'btn btn-primary float-end',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroup-snapin-primary-remove',
            _('Unset primary'),
            'btn btn-warning float-start',
            $props
        );
        echo '<div class="card card-info card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Set Storage Group as Primary for Snapins');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, 'storagegroup-snapin-primary-table', $buttons);
        echo '</div>';
        echo '<div class="card-footer">';
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
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            )
            . self::makeButton(
                "confirmSnapinPrimaryDeleteModal",
                _('Unset'),
                'btn btn-outline-secondary float-end'
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
        self::checkAuthAndCSRF();
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
        $this->renderAssocTab(
            'storagegroup-storagenode',
            _('Storage Group Storage Node Associations'),
            _('Storage Node Name'),
            'storagenode',
            'btn btn-primary float-end'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'storagegroup-storagenode',
                $this->obj->get('id')
            )
            . '" ';

        // Master Storage Node
        $buttons = self::makeButton(
            'storagegroup-storagenode-master-send',
            _('Update'),
            'btn btn-primary float-end',
            $props
        );
        echo '<div class="card card-info card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Storage Group Master Storage Node');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<span id="storagenodeselector"></span>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
    }
    public function storagegroupStoragenodePost()
    {
        self::checkAuthAndCSRF();
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
        $master = $this->obj->getMasterStorageNode();
        $this->notes = [
            _('Storage Group') => $this->obj->get('name'),
            _('Master Node') => (
                $master instanceof StorageNode && $master->isValid() ?
                $master->get('name') :
                _('None')
            ),
            _('Storage Nodes') => (string)count((array)$this->obj->get('allnodes'))
        ];
        // Info-card notes that mirror a General-tab control, so the card
        // tracks the form instead of going stale until the next page
        // load. Keys must match $notes exactly; notes left out here (the
        // association counts, and anything no control on this page can
        // change) keep their server-rendered value.
        $this->noteSources = [
            _('Storage Group') => '#storagegroup'
        ];
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
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Actually submit the changes.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'StorageGroup',
            'STORAGEGROUP_EDIT',
            _('Storage Group updated!'),
            _('Storage Group Update Success'),
            _('Storage Group Update Fail'),
            function (&$serverFault) {
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
                    throw new \Exception(_('Storage Group Update Failed'));
                }
            }
        );
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
        $storagenodesAssigned = Route::getIds(
            'storagenode',
            ['storagegroupID' => $this->obj->get('id')]
        );
        if (!count($storagenodesAssigned ?: [])) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'content' => _('No storage nodes assigned to this storage group'),
                    'disablebtn' => true
                ]
            ));
        }
        // getNames(): names() answers with its rows under a `data`
        // envelope, and this wants the rows.
        $storagenodeNames = Route::getNames(
            'storagenode',
            ['id' => $storagenodesAssigned]
        );
        foreach ($storagenodeNames as &$storagenode) {
            $storagenodes[$storagenode->id] = $storagenode->name;
            unset($storagenode);
        }
        unset($storagenodeNames);
        $masterstoragenode = Route::getIds(
            'storagenode',
            [
                'storagegroupID' => $this->obj->get('id'),
                'isMaster' => '1'
            ],
            'id'
        );
        $masterstoragenode = array_shift($masterstoragenode);
        $storagenodeSelector = self::selectForm(
            'storagenode',
            $storagenodes,
            $masterstoragenode,
            true,
            '',
            true
        );
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'content' => $storagenodeSelector,
                'disablebtn' => false
            ]
        ));
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\StorageGroupManagement', 'StorageGroupManagement');
