<?php
/**
 * Image management page
 *
 * PHP version 5
 *
 * @category ImageManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Image management page
 *
 * @category ImageManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageManagementPage extends FOGPage
{
    /**
     * The node this page operates off of.
     *
     * @var string
     */
    public $node = 'image';
    /**
     * Initializes the image page class.
     *
     * @param string $name the name to pass
     *
     * @return void
     */
    public function __construct($name = '')
    {
        /**
         * The real name not using our name passer.
         */
        $this->name = 'Image Management';
        /**
         * Pull in the FOGPage class items.
         */
        parent::__construct($this->name);
        /**
         * If we want the Server size taken by the image.
         */
        $SizeServer = self::getSetting('FOG_FTP_IMAGE_SIZE');
        /**
         * The header data for list/search.
         */
        $this->headerData = [
            _('Image Name'),
            _('Protected'),
            _('Enabled'),
            _('Captured')
        ];
        /**
         * The template for the list/search elements.
         */
        $this->templates = [
            '',
            '',
            '',
            ''
        ];
        /**
         * The attributes for the table items.
         */
        $this->attributes = [
            [],
            [],
            [],
            []
        ];
    }
    /**
     * The form to display when adding a new image
     * definition.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Image');
        /**
         * Setup our variables for back up/incorrect settings without
         * making the user reset entirely
         */
        $image = filter_input(INPUT_POST, 'image');
        $description = filter_input(INPUT_POST, 'description');
        $storagegroup = (int)filter_input(INPUT_POST, 'storagegroup');
        $os = (int)filter_input(INPUT_POST, 'os');
        $imagetype = (int)filter_input(INPUT_POST, 'imagetype');
        $imagepartitiontype = (int)filter_input(INPUT_POST, 'imagepartitiontype');
        $compress = (int)filter_input(INPUT_POST, 'compress');
        $imagemanage = filter_input(INPUT_POST, 'imagemanage');
        $file = filter_input(INPUT_POST, 'file');
        if ($storagegroup > 0) {
            $sgID = $storagegroup;
        } else {
            $sgID = @min(self::getSubObjectIDs('StorageGroup'));
        }
        /**
         * Set our storage group object.
         */
        $StorageGroup = new StorageGroup($sgID);
        $StorageGroups = self::getClass('StorageGroupManager')
            ->buildSelectBox(
                $sgID,
                '',
                'id'
            );
        /**
         * Get the master storage node.
         */
        $StorageNode = $StorageGroup->getMasterStorageNode();
        $OSs = self::getClass('OSManager')
            ->buildSelectBox($os);
        $itID = 1;
        if ($imagetype > 0) {
            $itID = $imagetype;
        }
        $ImageTypes = self::getClass('ImageTypeManager')
            ->buildSelectBox(
                $itID,
                '',
                'id'
            );
        $iptID = 1;
        if ($imagepartitiontype > 0) {
            $iptID = $imagepartitiontype;
        } else {
            $iptID = 1;
        }
        $ImagePartitionTypes = self::getClass('ImagePartitionTypeManager')
            ->buildSelectBox(
                $iptID,
                '',
                'id'
            );
        $compression = self::getSetting('FOG_PIGZ_COMP');
        if ($compress < 0 || $compress > 23) {
            $compression = $compress;
        }
        if (!isset($imagemanage)) {
            $imagemanage = self::getSetting('FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT');
        }
        $format = sprintf(
            '<select name="imagemanage" id="imagemanage" class="form-control">'
            . '<option value="0"%s>%s</option>'
            . '<option value="1"%s>%s</option>'
            . '<option value="2"%s>%s</option>'
            . '<option value="3"%s>%s</option>'
            . '<option value="4"%s>%s</option>'
            . '<option value="5"%s>%s</option>'
            . '<option value="6"%s>%s</option>'
            . '</select>',
            (
                !$imagemanage || $imagemanage == 0 ?
                ' selected' :
                ''
            ),
            _('Partclone Gzip'),
            (
                $imagemanage == 1 ?
                ' selected' :
                ''
            ),
            _('Partimage'),
            (
                $imagemanage == 2 ?
                ' selected' :
                ''
            ),
            _('Partclone Gzip Split 200MiB'),
            (
                $imagemanage == 3 ?
                ' selected' :
                ''
            ),
            _('Partclone Uncompressed'),
            (
                $imagemanage == 4 ?
                ' selected' :
                ''
            ),
            _('Partclone Uncompressed Split 200MiB'),
            (
                $imagemanage == 5 ?
                ' selected' :
                ''
            ),
            _('Partclone Zstd'),
            (
                $imagemanage == 6 ?
                ' selected' :
                ''
            ),
            _('Partclone Zstd Split 200MiB')
        );
        $fields = [
            '<label class="col-sm-2 control-label" for="image">'
            . _('Image Name')
            . '</label>' => '<input type="text" name="image" '
            . 'value="'
            . $image
            . '" class="imagename-input form-control" '
            . 'id="image" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Image Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="storagegroup">'
            . _('Storage Group')
            . '</label>' => $StorageGroups,
            '<label class="col-sm-2 control-label" for="os">'
            . _('Operating System')
            . '</label>' => $OSs,
            '<label class="col-sm-2 control-label" for="file">'
            . _('Image Path')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . $StorageNode->get('path')
            . '/'
            . '</span>'
            . '<input type="text" name="file" '
            . 'value="'
            . $file
            . '" class="form-control" id="file" required/></div>',
            '<label class="col-sm-2 control-label" for="imagetype">'
            . _('Image Type')
            . '</label>' => $ImageTypes,
            '<label class="col-sm-2 control-label" for="imagepartitiontype">'
            . _('Partition')
            . '</label>' => $ImagePartitionTypes,
            '<label class="col-sm-2 control-label" for="isEnabled">'
            . _('Image Enabled')
            . '</label>' => '<input type="checkbox" '
            . 'name="isEnabled" id="isEnabled" checked/>',
            '<label class="col-sm-2 control-label" for="toRep">'
            . _('Replicate')
            . '</label>' => '<input type="checkbox" '
            . 'name="toReplicate" id="toRep" checked/>',
            '<label class="col-sm-2 control-label" for="pigzcomp">'
            . _('Compression')
            . '</label>' => '<input type="text" value="'
            . $compression
            . '" class="slider form-control" '
            . 'data-slider-min="0" data-slider-max="22" data-slider-step="1" '
            . 'data-slider-value="'
            . $compression
            . '" data-slider-orientation="horizontal" '
            . 'data-slider-selection="before" data-slider-tooltip="show" '
            . 'data-slider-id="blue"/>',
            '<label class="col-sm-2 control-label" for="imagemanage">'
            . _('Image Manager')
            . '</label>' => $format
        ];
        self::$HookManager
            ->processEvent(
                'IMAGE_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'Image' => self::getClass('Image')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="image-create">';
        echo '<form id="image-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h3 class="box-title">';
        echo _('Create New Image');
        echo '</h3>';
        echo '</div>';
        echo '<!-- Image General -->';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
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
     * Actually submit the creation of the image.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('IMAGE_ADD_POST');
        $image = trim(
            filter_input(
                INPUT_POST,
                'image')
        );
        $description = trim(
            filter_input(
                INPUT_POST,
                'description'
            )
        );
        $storagegroup = (int)trim(
            filter_input(
                INPUT_POST,
                'storagegroup'
            )
        );
        $os = (int)trim(
            filter_input(
                INPUT_POST,
                'os'
            )
        );
        $file = trim(
            filter_input(
                INPUT_POST,
                'file'
            )
        );
        $imagetype = (int)trim(
            filter_input(
                INPUT_POST,
                'imagetype'
            )
        );
        $imagepartitiontype = (int)trim(
            filter_input(
                INPUT_POST,
                'imagepartitiontype'
            )
        );
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $compress = (int)trim(
            filter_input(
                INPUT_POST,
                'compress'
            )
        );
        $imagemanage = (int)trim(
            filter_input(
                INPUT_POST,
                'imagemanage'
            )
        );
        $serverFault = false;
        try {
            if (!$image) {
                throw new Exception(
                    _('An image name is required!')
                );
            }
            if (self::getClass('ImageManager')->exists($image)) {
                throw new Exception(
                    _('An image already exists with this name!')
                );
            }
            if (in_array($file, ['postdownloadscripts','dev'])) {
                throw new Exception(
                    _('Please choose a different filename/path as this is reserved')
                );
            }
            if (self::getClass('ImageManager')->exists($file, '', 'path')) {
                throw new Exception(
                    _('The path requested is already in use by another image!')
                );
            }
            $Image = self::getClass('Image')
                ->set('name', $image)
                ->set('description', $description)
                ->set('osID', $os)
                ->set('path', $file)
                ->set('imageTypeID', $imagetype)
                ->set('imagePartitionTypeID', $imagepartitiontype)
                ->set('compress', $compress)
                ->set('isEnabled', $isEnabled)
                ->set('format', $imagemanage)
                ->set('toReplicate', $toReplicate)
                ->addGroup($storagegroup);
            if (!$Image->save()) {
                $serverFault = true;
                throw new Exception(_('Add image failed!'));
            }
            /**
             * During image creation we only allow a single group anyway.
             * This will set it to be the primary master.
             */
            $Image->setPrimaryGroup($storagegroup);
            $code = 201;
            $hook = 'IMAGE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Image added!'),
                    'title' => _('Image Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'IMAGE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Image Create Fail')
                ]
            );
        }
        http_response_code($code);
        //header('Location: ../management/index.php?node=host&sub=edit&id=' . $Image->get('id'));
        self::$HookManager
            ->processEvent(
                $hook,
                ['Image' => &$Image]
            );
        unset($Image);
        echo $msg;
        exit;
    }
    /**
     * Diplay image general information.
     *
     * @return void
     */
    public function imageGeneral()
    {
        $image = (
            filter_input(
                INPUT_POST,
                'image'
            ) ?: $this->obj->get('name')
        );
        $description = (
            filter_input(
                INPUT_POST,
                'description'
            ) ?: $this->obj->get('description')
        );
        $StorageNode = $this->obj->getStorageGroup()->getMasterStorageNode();
        $osID = (int)(
            filter_input(
                INPUT_POST,
                'os'
            ) ?: $this->obj->get('osID')
        );
        $OSs = self::getClass('OSManager')
            ->buildSelectBox(
                $osID,
                '',
                'id'
            );
        $file = (
            filter_input(
                INPUT_POST,
                'file'
            ) ?: $this->obj->get('path')
        );
        $itID = (int)(
            filter_input(
                INPUT_POST,
                'imagetype'
            ) ?: $this->obj->get('imageTypeID')
        );
        $ImageTypes = self::getClass('ImageTypeManager')
            ->buildSelectBox(
                $itID,
                '',
                'id'
            );
        $iptID = (int)(
            filter_input(
                INPUT_POST,
                'imagepartitiontype'
            ) ?: $this->obj->get('imagePartitionTypeID')
        );
        $ImagePartitionTypes = self::getClass('ImagePartitionTypeManager')
            ->buildSelectBox(
                $iptID,
                '',
                'id'
            );
        $toprot = (int)isset($_POST['isProtected']) ?: $this->obj->get('protected');
        if ($toprot) {
            $toprot = ' checked';
        } else {
            $toprot = '';
        }
        $isen = (int)isset($_POST['isEnabled']) ?: $this->obj->get('isEnabled');
        if ($isen) {
            $isen = ' checked';
        } else {
            $isen = '';
        }
        $torep = (int)isset($_POST['toReplicate']) ?: $this->obj->get('toReplicate');;
        if ($torep) {
            $torep = ' checked';
        } else {
            $torep = '';
        }
        $compression = (int)(
            filter_input(
                INPUT_POST,
                'compress'
            ) ?: $this->obj->get('compress')
        );
        $imagemanage = (int)(
            filter_input(
                INPUT_POST,
                'imagemanage'
            ) ?: $this->obj->get('format')
        );
        $format = sprintf(
            '<select name="imagemanage" id="imagemanage" class="form-control">'
            . '<option value="0"%s>%s</option>'
            . '<option value="1"%s>%s</option>'
            . '<option value="2"%s>%s</option>'
            . '<option value="3"%s>%s</option>'
            . '<option value="4"%s>%s</option>'
            . '<option value="5"%s>%s</option>'
            . '<option value="6"%s>%s</option>'
            . '</select>',
            (
                !$imagemanage ?
                ' selected' :
                ''
            ),
            _('Partclone Gzip'),
            (
                $imagemanage == 1 ?
                ' selected' :
                ''
            ),
            _('Partimage'),
            (
                $imagemanage == 2 ?
                ' selected' :
                ''
            ),
            _('Partclone Gzip Split 200MiB'),
            (
                $imagemanage == 3 ?
                ' selected' :
                ''
            ),
            _('Partclone Uncompressed'),
            (
                $imagemanage == 4 ?
                ' selected' :
                ''
            ),
            _('Partclone Uncompressed Split 200MiB'),
            (
                $imagemanage == 5 ?
                ' selected' :
                ''
            ),
            _('Partclone Zstd'),
            (
                $imagemanage == 6 ?
                ' selected' :
                ''
            ),
            _('Partclone Zstd Split 200MiB')
        );
        $fields = [
            '<label class="col-sm-2 control-label" for="image">'
            . _('Image Name')
            . '</label>' => '<input type="text" name="image" '
            . 'value="'
            . $image
            . '" class="imagename-input form-control" '
            . 'id="image" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Image Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="os">'
            . _('Operating System')
            . '</label>' => $OSs,
            '<label class="col-sm-2 control-label" for="file">'
            . _('Image Path')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . $StorageNode->get('path')
            . '/'
            . '</span>'
            . '<input type="text" name="file" '
            . 'value="'
            . $file
            . '" class="form-control" id="file" required/></div>',
            '<label class="col-sm-2 control-label" for="imagetype">'
            . _('Image Type')
            . '</label>' => $ImageTypes,
            '<label class="col-sm-2 control-label" for="imagepartitiontype">'
            . _('Partition')
            . '</label>' => $ImagePartitionTypes,
            '<label class="col-sm-2 control-label" for="isProtected">'
            . _('Image Protected')
            . '</label>' => '<input type="checkbox" '
            . 'name="isProtected" id="isProtected"'
            . $toprot
            . '/>',
            '<label class="col-sm-2 control-label" for="isEnabled">'
            . _('Image Enabled')
            . '</label>' => '<input type="checkbox" '
            . 'name="isEnabled" id="isEnabled"'
            . $isen
            . '/>',
            '<label class="col-sm-2 control-label" for="toRep">'
            . _('Replicate')
            . '</label>' => '<input type="checkbox" '
            . 'name="toReplicate" id="toRep"'
            . $torep
            . '/>',
            '<label class="col-sm-2 control-label" for="pigzcomp">'
            . _('Compression')
            . '</label>' => '<input type="text" value="'
            . $compression
            . '" class="slider form-control" '
            . 'data-slider-min="0" data-slider-max="22" data-slider-step="1" '
            . 'data-slider-value="'
            . $compression
            . '" data-slider-orientation="horizontal" '
            . 'data-slider-selection="before" data-slider-tooltip="show" '
            . 'data-slider-id="blue"/>',
            '<label class="col-sm-2 control-label" for="imagemanage">'
            . _('Image Manager')
            . '</label>' => $format
        ];
        self::$HookManager
            ->processEvent(
                'IMAGE_GENERAL_FIELDS',
                [
                    'fields' => &$fields,
                    'obj' => &$this->obj
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<form id="image-general-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('image-general', $this->obj->get('id'))
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
     * Update the general post
     *
     * return void
     */
    public function imageGeneralPost()
    {
        $image = trim(
            filter_input(
                INPUT_POST,
                'image'
            )
        );
        $description = trim(
            filter_input(
                INPUT_POST,
                'description'
            )
        );
        $osID = (int)trim(
            filter_input(
                INPUT_POST,
                'os'
            )
        );
        $file = trim(
            filter_input(
                INPUT_POST,
                'file'
            )
        );
        $itID = (int)trim(
            filter_input(
                INPUT_POST,
                'imagetype'
            )
        );
        $iptID = (int)trim(
            filter_input(
                INPUT_POST,
                'imagepartitiontype'
            )
        );
        $protected = (int)isset($_POST['isProtected']);
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $this->obj
            ->set('name', $image)
            ->set('description', $description)
            ->set('osID', $osID)
            ->set('path', $file)
            ->set('imageTypeID', $itID)
            ->set('imagePartitionTypeID', $iptID)
            ->set('format', $imagemanage)
            ->set('protected', $protected)
            ->set('compress', $compress)
            ->set('isEnabled', $isEnabled)
            ->set('toReplicate', $toReplicate);
    }
    /**
     * Display image storage groups.
     *
     * @return void
     */
    public function imageStoragegroups()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=image-storagegroups" ';

        echo '<!-- Storage Groups -->';
        echo '<div class="box-group" id="storagegroups">';
        // =================================================================
        // Associated Storage Groups
        $buttons = self::makeButton(
            'storagegroups-primary',
            _('Update Primary Group'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroups-add',
            _('Add selected'),
            'btn btn-success',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroups-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );
        $this->headerData = [
            _('Storage Group Name'),
            _('Storage Group Primary'),
            _('Storage Group Associated')
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
        echo '<div class="updatestoragegroups" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'image-storagegroups-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Image storage groups post.
     *
     * @return void
     */
    public function imageStoragegroupsPost()
    {
        if (isset($_POST['updatestoragegroups'])) {
            $storagegroup = filter_input_array(
                INPUT_POST,
                [
                    'storagegroups' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagegroup = $storagegroup['storagegroups'];
            if (count($storagegroup ?: []) > 0) {
                $this->obj->addGroup($storagegroup);
            }
        }
        if (isset($_POST['storagegroupdel'])) {
            $storagegroup = filter_input_array(
                INPUT_POST,
                [
                    'storagegroupRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagegroup = $storagegroup['storagegroupRemove'];
            if (count($storagegroup ?: []) > 0) {
                $this->obj->removeGroup($storagegroup);
            }
        }
        if (isset($_POST['primarysel'])) {
            $primary = filter_input(
                INPUT_POST,
                'primary'
            );
            self::getClass('ImageAssociationManager')->update(
                [
                    'imageID' => $this->obj->get('id'),
                    'primary' => '1'
                ],
                '',
                [
                    'primary' => '0'
                ]
            );
            if ($primary) {
                self::getClass('ImageAssociationManager')->update(
                    [
                        'imageID' => $this->obj->get('id'),
                        'storagegroupID' => $primary
                    ],
                    '',
                    [
                        'primary' => '1'
                    ]
                );
            }
        }
    }
    /**
     * Image Membership tab
     *
     * @return void
     */
    public function imageMembership()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=image-membership" ';

        echo '<!-- Host Membership -->';
        echo '<div class="box-group" id="membership">';
        // =================================================================
        // Associated Storage Groups
        $buttons = self::makeButton(
            'membership-add',
            _('Add selected'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'membership-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );
        $this->headerData = [
            _('Host Name'),
            _('Host Associated')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div class="updatemembership" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'image-membership-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Edit this image
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

        $tabData[] = [
            'name' => _('General'),
            'id' => 'image-general',
            'generator' => function() {
                $this->imageGeneral();
            }
        ];

        $tabData[] = [
            'name' => _('Storage Groups'),
            'id' => 'image-storagegroups',
            'generator' => function() {
                $this->imageStoragegroups();
            }
        ];

        $tabData[] = [
            'name' => _('Host Membership'),
            'id' => 'image-membership',
            'generator' => function() {
                $this->imageMembership();
            }
        ];

        echo self::tabFields($tabData);
    }
    /**
     * Submit save/update the image.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'IMAGE_EDIT_POST',
            ['Image' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'image-general':
                $this->imageGeneralPost();
                break;
            case 'image-storagegroups':
                $this->imageStoragegroupsPost();
                break;
            case 'image-membership':
                $this->imageMembershipPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Image Update Failed'));
            }
            $code = 201;
            $hook = 'IMAGE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Image updated!'),
                    'title' => _('Image Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'IMAGE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Image Update Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager->processEvent(
            $hook,
            ['obj' => &$this->obj]
        );
        echo $msg;
        exit;
    }
    /**
     * Presents the form to created named multicast
     * sessions.
     *
     * @return void
     */
    public function multicast()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = self::$foglang['Multicast'];
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $name = filter_input(INPUT_POST, 'name');
        $count = (int)filter_input(INPUT_POST, 'count');
        $timeout = (int)filter_input(INPUT_POST, 'timeout');
        $image = (int)filter_input(INPUT_POST, 'image');
        $fields = array(
            '<label for="iName">'
            . _('Session Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" name="name" id="iName" '
            . 'autocomplete="off" value="'
            . $name
            . '"/>'
            . '</div>',
            '<label for="iCount">'
            . _('Client Count')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="number" name="count" id="iCount" '
            . 'autocomplete="off" value="'
            . $count
            . '"/>'
            . '</div>',
            '<label for="iTimeout">'
            . _('Timeout')
            . ' ('
            . _('minutes')
            . ')'
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="number" name=timeout" '
            . 'id="iTimeout" autocomplete="off" value="'
            . $timeout
            . '"/>'
            . '</div>',
            '<label for="image">'
            . _('Select Image')
            . '</label>' => self::getClass('ImageManager')->buildSelectBox(
                $image,
                '',
                'name'
            ),
            '<label for="start">'
            . _('Start Session')
            . '</label>' => '<button class="btn btn-info btn-block" type="submit" '
            . 'name="start" id="start">'
            . _('Start')
            . '</button>'
        );
        $rendered = self::formFields($fields);
        self::$HookManager
            ->processEvent(
                'IMAGE_MULTICAST_SESS',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Multicast Image');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Start Multicast Session');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        unset(
            $this->form,
            $this->data,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            _('Task Name'),
            _('Clients'),
            _('Start Time'),
            _('Percent'),
            _('State'),
            _('Stop Task'),
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array('class' => 'text-center'),
            array('class'=>'filter-false'),
        );
        $this->templates = array(
            '${mc_name}<br/><small>${image_name}:${os}</small>',
            '${mc_count}',
            '<small>${mc_start}</small>',
            '${mc_percent}',
            '<i class="fa fa-${mc_state}"></i>',
            '<a href="?node='
            . $this->node
            . '&sub=stop&mcid=${mc_id}" '
            . 'title="'
            . _('Remove')
            . '" data-toggle="tooltip" data-placement="top">'
            . '<i class="fa fa-minus-circle"></i>'
            . '</a>'
        );
        $find = array(
            'stateID' => self::fastmerge(
                (array)self::getQueuedStates(),
                (array)self::getProgressState()
            )
        );
        Route::active('multicastsession');
        $MulticastSessions = json_decode(
            Route::getData()
        );
        $MulticastSessions = $MulticastSessions->multicastsessions;
        foreach ((array)$MulticastSessions as &$MulticastSession) {
            $Image = $MulticastSession->image;
            if (!$Image->id) {
                continue;
            }
            $this->data[] = array(
                'mc_name' => $MulticastSession->name,
                'mc_count' => $MulticastSession->sessclients,
                'image_name' => $Image->name,
                'os' => $Image->os->name,
                'mc_start' => self::formatTime(
                    $MulticastSession->starttime,
                    'Y-m-d H:i:s'
                ),
                'mc_percent' => $MulticastSession->percent,
                'mc_state' => $MulticastSession->state->icon,
                'mc_id' => $MulticastSession->id,
            );
            unset($MulticastSession);
        }
        self::$HookManager
            ->processEvent(
                'IMAGE_MULTICAST_START',
                array(
                    'data' => &$this->data,
                    'headerData' => &$this->headerData,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Current Sessions');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Submit the mutlicast form.
     *
     * @return void
     */
    public function multicastPost()
    {
        try {
            $name = trim(
                filter_input(INPUT_POST, 'name')
            );
            $image = (int)filter_input(INPUT_POST, 'image');
            $timeout = (int)filter_input(INPUT_POST, 'timeout');
            $count = (int)filter_input(INPUT_POST, 'count');
            if (!$name) {
                throw new Exception(_('Please input a session name'));
            }
            if (!$image) {
                throw new Exception(_('Please choose an image'));
            }
            if (self::getClass('MulticastSessionManager')->exists($name)) {
                throw new Exception(_('Session with that name already exists'));
            }
            if (self::getClass('HostManager')->exists($name)) {
                throw new Exception(
                    _('Session name cannot be the same as an existing hostname')
                );
            }
            if ($timeout > 0) {
                self::setSetting('FOG_UDPCAST_MAXWAIT', $timeout);
            }
            $countmc = self::getClass('MulticastSessionManager')
                ->count(
                    array(
                        'stateID' => self::fastmerge(
                            (array)self::getQueuedStates(),
                            (array)self::getProgressState()
                        )
                    )
                );
            $countmctot = self::getSetting('FOG_MULTICAST_MAX_SESSIONS');
            $Image = new Image($image);
            $StorageGroup = $Image->getStorageGroup();
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if ($countmc >= $countmctot) {
                throw new Exception(
                    sprintf(
                        '%s<br/>%s %s %s<br/>%s %s',
                        _('Please wait until a slot is open'),
                        _('There are currently'),
                        $countmc,
                        _('tasks in queue'),
                        _('Your server only allows'),
                        $countmctot
                    )
                );
            }
            $MulticastSession = self::getClass('MulticastSession')
                ->set('name', $name)
                ->set('port', self::getSetting('FOG_UDPCAST_STARTINGPORT'))
                ->set('image', $Image->get('id'))
                ->set('stateID', 0)
                ->set('sessclients', $count)
                ->set('isDD', $Image->get('imageTypeID'))
                ->set('starttime', self::formatTime('now', 'Y-m-d H:i:s'))
                ->set('interface', $StorageNode->get('interface'))
                ->set('logpath', $Image->get('path'))
                ->set('storagegroupID', $StorageNode->get('id'))
                ->set('clients', -2);
            if (!$MulticastSession->save()) {
                self::setMessage(_('Failed to create Session'));
            }
            $randomnumber = mt_rand(24576, 32766)*2;
            while ($randomnumber == $MulticastSession->get('port')) {
                $randomnumber = mt_rand(24576, 32766)*2;
            }
            self::setSetting('FOG_UDPCAST_STARTINGPORT', $randomnumber);
            self::setMessage(
                sprintf(
                    '%s<br/>%s %s %s',
                    _('Multicast session created'),
                    $MulticastSession->get('name'),
                    _('has been started on port'),
                    $MulticastSession->get('port')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
        }
        self::redirect(
            sprintf(
                '?node=%s&sub=multicast',
                $this->node
            )
        );
    }
    /**
     * Stops/Cancels the mutlicast session(s).
     *
     * @return void
     */
    public function stop()
    {
        $mcid = (int)filter_input(INPUT_GET, 'mcid');
        if ($mcid < 1) {
            self::redirect(
                sprintf('?node=%s&sub=multicast', $this->node)
            );
        }
        self::getClass('MulticastSessionManager')->cancel($mcid);
        self::setMessage(
            sprintf(
                '%s%s',
                _('Cancelled task'),
                (
                    count($mcid) !== 1 ?
                    's' :
                    ''
                )
            )
        );
        self::redirect(sprintf('?node=%s&sub=multicast', $this->node));
    }
    /**
     * Presents the storage groups list table.
     *
     * @return void
     */
    public function getStoragegroupsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $where = "`images`.`imageID` = '"
            . $this->obj->get('id')
            . "'";

        // Workable Queries
        $storagegroupsSqlStr = "SELECT `%s`,"
            . "`igaImageID` AS `origID`,IF (`igaImageID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `igaImageID`,`igaPrimary`,`imageID`
            FROM `%s`
            CROSS JOIN `images`
            LEFT OUTER JOIN `imageGroupAssoc`
            ON `nfsGroups`.`ngID` = `imageGroupAssoc`.`igaStorageGroupID`
            AND `images`.`imageID` = `imageGroupAssoc`.`igaImageID`
            %s
            %s
            %s";
        $storagegroupsFilterStr = "SELECT COUNT(`%s`),"
            . "`igaImageID` AS `origID`,IF (`igaImageID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `igaImageID`,`igaPrimary`,`imageID`
            FROM `%s`
            CROSS JOIN `images`
            LEFT OUTER JOIN `imageGroupAssoc`
            ON `nfsGroups`.`ngID` = `imageGroupAssoc`.`igaStorageGroupID`
            AND `images`.`imageID` = `imageGroupAssoc`.`igaImageID`
            %s";
        $storagegroupsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('StorageGroupManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = [
            'db' => 'igaPrimary',
            'dt' => 'primary'
        ];
        $columns[] = [
            'db' => 'igaImageID',
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
                'nfsGroups',
                'ngID',
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
     * Image -> host membership list
     *
     * @return void
     */
    public function getHostsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $hostsSqlStr = "SELECT `%s`,"
            . "IF(`hostImage` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `hostImage`
            FROM `%s`
            LEFT OUTER JOIN `images`
            ON `hosts`.`hostImage` = `images`.`imageID`
            %s
            %s
            %s";
        $hostsFilterStr = "SELECT COUNT(`%s`),"
            . "IF(`hostImage` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `hostImage`
            FROM `%s`
            LEFT OUTER JOIN `images`
            ON `hosts`.`hostImage` = `images`.`imageID`
            %s";
        $hostsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('HostManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
        }
        $columns[] = [
            'db' => 'hostImage',
            'dt' => 'association'
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'hosts',
                'hostID',
                $columns,
                $hostsSqlStr,
                $hostsFilterStr,
                $hostsTotalStr
            )
        );
        exit;
    }
    /**
     * Image membership post elements
     *
     * @return void
     */
    public function imageMembershipPost()
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
            self::getClass('HostManager')->update(
                [
                    'id' => $membership
                ],
                '',
                [
                    'imageID' => $this->obj->get('id')
                ]
            );
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
            self::getClass('HostManager')->update(
                [
                    'id' => $membership,
                    'imageID' => $this->obj->get('id')
                ],
                '',
                [
                    'imageID' => '0'
                ]
            );
        }
    }
}
