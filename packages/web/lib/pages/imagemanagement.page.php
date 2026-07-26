<?php
/**
 * Image management page
 *
 * PHP version 5
 *
 * @category ImageManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Image management page
 *
 * @category ImageManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageManagement extends FOGPage
{
    /**
     * The node this works off of.
     *
     * @var string
     */
    public $node = 'image';
    /**
     * Initializes the image class.
     *
     * @param string $name The name to load this as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Image Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Image Name'),
            _('Protected'),
            _('Enabled'),
            _('Captured')
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
        ];
    }
    /**
     * Resolve a storage node purely for displaying the image-path prefix.
     *
     * getMasterStorageNode() only returns a node that probes as online and
     * throws "No master nodes available" otherwise. The path prefix it feeds
     * is informational, so a transiently-offline (or absent) master node must
     * not 500 the whole page. On that throw, fall back to a master node in the
     * group ignoring online status, then to any node, and finally null so the
     * caller can omit the prefix.
     *
     * @param StorageGroup $StorageGroup the group to resolve a node for
     *
     * @return StorageNode|null
     */
    private function _displayStorageNode($StorageGroup)
    {
        try {
            return $StorageGroup->getMasterStorageNode();
        } catch (Exception $e) {
            $getter = count($StorageGroup->get('enablednodes')) > 0
                ? 'enablednodes'
                : 'allnodes';
            $ids = $StorageGroup->get($getter);
            if (count($ids) < 1) {
                return null;
            }
            $masterIds = Route::getIds(
                'storagenode',
                [
                    'id' => $ids,
                    'isEnabled' => 1,
                    'isMaster' => 1
                ]
            );
            if (count($masterIds) < 1) {
                $masterIds = $ids;
            }
            $StorageNode = self::getClass('StorageNode', array_shift($masterIds));
            return $StorageNode->isValid() ? $StorageNode : null;
        }
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $image = filter_input(INPUT_POST, 'image');
        $description = filter_input(INPUT_POST, 'description');
        $storagegroup = (int)filter_input(INPUT_POST, 'storagegroup');
        $os = (int)filter_input(INPUT_POST, 'os');
        $imagetype = (int)filter_input(INPUT_POST, 'imagetype');
        $imagepartitiontype = (int)filter_input(INPUT_POST, 'imagepartitiontype');
        $compress = (int)filter_input(INPUT_POST, 'compression');
        $imagemanage = filter_input(INPUT_POST, 'imagemanage');
        $path = filter_input(INPUT_POST, 'path');
        if ($storagegroup > 0) {
            $sgID = $storagegroup;
        } else {
            $sgID = @min(Route::getIds('storagegroup', false));
        }
        $StorageGroup = new StorageGroup($sgID);
        $StorageGroups = self::getClass('StorageGroupManager')
            ->buildSelectBox(
                $sgID,
                '',
                'id'
            );
        $StorageNode = $this->_displayStorageNode($StorageGroup);
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
        if ($compress < 0 || $compress > 22) {
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
                $imagemanage == 0 ?
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
                !$imagemanage || $imagemanage == 5 ?
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

        $labelClass = 'col-sm-3 col-form-label';

        return [
            // Input/Textarea elements
            self::makeLabel(
                $labelClass,
                'image',
                _('Image Name')
            ) => self::makeInput(
                'form-control imagename-input',
                'image',
                _('Image Name'),
                'text',
                'image',
                $image,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Image Description')
            ) => self::makeTextarea(
                'form-control imagedescription-input',
                'description',
                _('Image Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'path',
                _('Image Path')
            ) => '<div class="input-group">'
            . ($StorageNode
                ? '<span class="input-group-text">'
                . $StorageNode->get('path')
                . '/'
                . '</span>'
                : '')
            . self::makeInput(
                'form-control imagepath-input',
                'path',
                _('Image Path'),
                'text',
                'path',
                $path,
                true
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'compression',
                _('Image Compression Rating')
            ) => self::makeInput(
                'form-control slider imagecompression-input',
                'compression',
                '19',
                'text',
                'compression',
                $compression,
                false,
                false,
                -1,
                -1,
                'data-slider-min="0" '
                . 'data-slider-max="22" '
                . 'data-slider-step="1" '
                . 'data-slider-value="' . $compression . '" '
                . 'data-slider-orientation="horizontal" '
                . 'data-slider-selection="before" '
                . 'data-slider-tooltip="show" '
                . 'data-slider-id="blue" '
            ),
            // Image Select elements.
            self::makeLabel(
                $labelClass,
                'storagegroup',
                _('Image Storage Group')
            ) => $StorageGroups,
            self::makeLabel(
                $labelClass,
                'os',
                _('Image Operating System')
            ) => $OSs,
            self::makeLabel(
                $labelClass,
                'imagetype',
                _('Image Type')
                . '&nbsp;&nbsp;'
                . self::makeInfoTooltip(
                    'icon fa fa-info-circle fa-lg hand',
                    'image-type-info',
                    sprintf(
                        _(
                            'Image Type is a very important setting and can have'
                            . ' a major impact on how your imaging works or fails.'
                            . ' Please read more about the different image types'
                            . ' and how to use them'
                            . ' %1$sin our wiki%2$s before you choose!'
                        ),
                        '<a href="https://wiki.fogproject.org/wiki/index.php'
                        . '?title=Managing_FOG#Images" target="_blank">',
                        '</a>'
                    )
                )
            ) => $ImageTypes,
            self::makeLabel(
                $labelClass,
                'imagepartitiontype',
                _('Image Partition')
            ) => $ImagePartitionTypes,
            self::makeLabel(
                $labelClass,
                'imagemanage',
                _('Image Manager')
            ) => $format,
            // Checkboxes
            self::makeLabel(
                $labelClass,
                'isEnabled',
                _('Image Enabled')
            ) => self::makeInput(
                'imageenabled-input',
                'isEnabled',
                '',
                'checkbox',
                'isEnabled',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'toReplicate',
                _('Image Replicate')
            ) => self::makeInput(
                'imagereplicate-input',
                'toReplicate',
                '',
                'checkbox',
                'toReplicate',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            )
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
        $this->renderAddForm(
            'image',
            _('Create New Image'),
            'IMAGE_ADD_FIELDS',
            'Image'
        );
    }
    /**
     * The form to display when adding a new image
     * definition.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'image',
            'IMAGE_ADD_FIELDS',
            'Image'
        );
    }
    /**
     * Actually save the new node.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Image',
            'IMAGE_ADD',
            _('Image added!'),
            _('Image Create Success'),
            _('Image Create Fail'),
            function (&$serverFault) {
                $image = trim(
                    filter_input(INPUT_POST, 'image')
                );
                $description = trim(
                    filter_input(INPUT_POST, 'description')
                );
                $storagegroup = (int)trim(
                    filter_input(INPUT_POST, 'storagegroup')
                );
                if (!$storagegroup) {
                    $storagegroup = @min(Route::getIds('storagegroup', false));
                }
                $os = (int)trim(
                    filter_input(INPUT_POST, 'os')
                );
                $path = trim(
                    filter_input(INPUT_POST, 'path')
                );
                $imagetype = (int)trim(
                    filter_input(INPUT_POST, 'imagetype')
                );
                $imagepartitiontype = (int)trim(
                    filter_input(INPUT_POST, 'imagepartitiontype')
                );
                $isEnabled = (int)isset($_POST['isEnabled']);
                $toReplicate = (int)isset($_POST['toReplicate']);
                $compress = (int)trim(
                    filter_input(INPUT_POST, 'compression')
                );
                $imagemanage = (int)trim(
                    filter_input(INPUT_POST, 'imagemanage')
                );
                $exists = self::getClass('ImageManager')
                    ->exists($image);
                if ($exists) {
                    throw new Exception(
                        _('An image already exists with this name!')
                    );
                }
                if (in_array($path, ['postdownloadscripts','dev'])) {
                    throw new Exception(
                        _('Please choose a different filename/path as this is reserved')
                    );
                }
                $exists = self::getClass('ImageManager')
                    ->exists($path, '', 'path');
                if ($exists) {
                    throw new Exception(
                        _('The path requested is already in use by another image!')
                    );
                }
                $Image = self::getClass('Image')
                    ->set('name', $image)
                    ->set('description', $description)
                    ->set('osID', $os)
                    ->set('path', $path)
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
                Image::setPrimaryGroup($storagegroup, $Image->get('id'));
                return $Image;
            }
        );
    }
    /**
     * Diplay image general information.
     *
     * @return void
     */
    public function imageGeneral()
    {
        $image = (
            filter_input(INPUT_POST, 'image') ?:
            ($this->obj->get('name') ?: '')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            ($this->obj->get('description') ?: '')
        );
        $StorageNode = $this->_displayStorageNode($this->obj->getStorageGroup());
        $osID = (int)(
            filter_input(INPUT_POST, 'os') ?:
            ($this->obj->get('osID') ?: '')
        );
        $OSs = self::getClass('OSManager')
            ->buildSelectBox($osID, '', 'id');
        $path = (
            filter_input(INPUT_POST, 'path') ?:
            ($this->obj->get('path') ?: '')
        );
        $itID = (int)(
            filter_input(INPUT_POST, 'imagetype') ?:
            ($this->obj->get('imageTypeID') ?: '')
        );
        $ImageTypes = self::getClass('ImageTypeManager')
            ->buildSelectBox($itID, '', 'id');
        $iptID = (int)(
            filter_input(INPUT_POST, 'imagepartitiontype') ?:
            ($this->obj->get('imagePartitionTypeID') ?: '')
        );
        $ImagePartitionTypes = self::getClass('ImagePartitionTypeManager')
            ->buildSelectBox($iptID, '', 'id');
        $isprot = (
            isset($_POST['isProtected']) ? 'checked' :
            ($this->obj->get('protected') ? 'checked' : '')
        );
        $isen = (
            isset($_POST['isEnabled']) ? 'checked' :
            ($this->obj->get('isEnabled') ? 'checked' : '')
        );
        $torep = (
            isset($_POST['toReplicate']) ? 'checked' :
            ($this->obj->get('toReplicate') ? 'checked' : '')
        );
        $compression = (int)(
            filter_input(INPUT_POST, 'compress') ?:
            ($this->obj->get('compress') ?: '')
        );
        $imagemanage = (int)(
            filter_input(INPUT_POST, 'imagemanage') ?:
            ($this->obj->get('format') ?: '')
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

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            // Input/Textarea elements
            self::makeLabel(
                $labelClass,
                'image',
                _('Image Name')
            ) => self::makeInput(
                'form-control imagename-input',
                'image',
                _('Image Name'),
                'text',
                'image',
                $image,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Image Description')
            ) => self::makeTextarea(
                'form-control imagedescription-input',
                'description',
                _('Image Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'path',
                _('Image Path')
            ) => '<div class="input-group">'
            . ($StorageNode
                ? '<span class="input-group-text">'
                . $StorageNode->get('path')
                . '/'
                . '</span>'
                : '')
            . self::makeInput(
                'form-control imagepath-input',
                'path',
                _('Image Path'),
                'text',
                'path',
                $path,
                true
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'compression',
                _('Image Compression Rating')
            ) => self::makeInput(
                'form-control slider imagecompression-input',
                'compression',
                '19',
                'text',
                'compression',
                $compression,
                false,
                false,
                -1,
                -1,
                'data-slider-min="0" '
                . 'data-slider-max="22" '
                . 'data-slider-step="1" '
                . 'data-slider-value="' . $compression . '" '
                . 'data-slider-orientation="horizontal" '
                . 'data-slider-selection="before" '
                . 'data-slider-tooltip="show" '
                . 'data-slider-id="blue" '
            ),
            // Image Select elements.
            self::makeLabel(
                $labelClass,
                'os',
                _('Image Operating System')
            ) => $OSs,
            self::makeLabel(
                $labelClass,
                'imagetype',
                _('Image Type')
                . '&nbsp;&nbsp;'
                . self::makeInfoTooltip(
                    'icon fa fa-info-circle fa-lg hand',
                    'image-type-info',
                    sprintf(
                        _(
                            'Image Type is a very important setting and can have'
                            . ' a major impact on how your imaging works or fails.'
                            . ' Please read more about the different image types'
                            . ' and how to use them'
                            . ' %1$sin our wiki%2$s before you choose!'
                        ),
                        '<a href="https://wiki.fogproject.org/wiki/index.php'
                        . '?title=Managing_FOG#Images" target="_blank">',
                        '</a>'
                    )
                )
            ) => $ImageTypes,
            self::makeLabel(
                $labelClass,
                'imagepartitiontype',
                _('Image Partition')
            ) => $ImagePartitionTypes,
            self::makeLabel(
                $labelClass,
                'imagemanage',
                _('Image Manager')
            ) => $format,
            // Checkboxes
            self::makeLabel(
                $labelClass,
                'isProtected',
                _('Image Protected')
            ) => self::makeInput(
                'imageprotected-input',
                'isProtected',
                '',
                'checkbox',
                'isProtected',
                '',
                false,
                false,
                -1,
                -1,
                $isprot
            ),
            self::makeLabel(
                $labelClass,
                'isEnabled',
                _('Image Enabled')
            ) => self::makeInput(
                'imageenabled-input',
                'isEnabled',
                '',
                'checkbox',
                'isEnabled',
                '',
                false,
                false,
                -1,
                -1,
                $isen
            ),
            self::makeLabel(
                $labelClass,
                'toReplicate',
                _('Image Replicate')
            ) => self::makeInput(
                'imagereplicaet-input',
                'toReplicate',
                '',
                'checkbox',
                'toReplicate',
                '',
                false,
                false,
                -1,
                -1,
                $torep
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
            'IMAGE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Image' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderGeneralForm('image', $rendered, $buttons);
    }
    /**
     * Update the general post
     *
     * @return void
     */
    public function imageGeneralPost()
    {
        self::checkAuthAndCSRF();
        $image = trim(
            filter_input(INPUT_POST, 'image')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $osID = (int)trim(
            filter_input(INPUT_POST, 'os')
        );
        $path = trim(
            filter_input(INPUT_POST, 'path')
        );
        $itID = (int)trim(
            filter_input(INPUT_POST, 'imagetype')
        );
        $iptID = (int)trim(
            filter_input(INPUT_POST, 'imagepartitiontype')
        );
        $protected = (int)isset($_POST['isProtected']);
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $exists = self::getClass('ImageManager')->exists($image);
        $compress = (int)trim(
            filter_input(INPUT_POST, 'compression')
        );
        if ($this->obj->get('name') != $image && $exists) {
            throw new Exception(_('An image with this name already exists!'));
        }
        $imagemanage = (int)trim(
            filter_input(INPUT_POST, 'imagemanage')
        );
        $this->obj
            ->set('name', $image)
            ->set('description', $description)
            ->set('osID', $osID)
            ->set('path', $path)
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
        // Storage Group Associations
        $this->renderAssocTab(
            'image-storagegroup',
            _('Image Storage Group Associations'),
            _('Storage Group Name'),
            'storagegroup',
            'btn btn-primary float-end'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'image-storagegroup',
                $this->obj->get('id')
            )
            . '" ';

        // Primary Storage Group
        $buttons = self::makeButton(
            'image-storagegroup-primary-send',
            _('Update'),
            'btn btn-info float-end',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Image Primary Storage Group');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<span id="storagegroupselector"></span>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Image storage groups post.
     *
     * @return void
     */
    public function imageStoragegroupPost()
    {
        self::checkAuthAndCSRF();
        if (isset($_POST['confirmadd'])) {
            $storagegroup = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagegroup = $storagegroup['additems'];
            if (count($storagegroup ?: []) > 0) {
                $this->obj->addGroup($storagegroup);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $storagegroup = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagegroup = $storagegroup['remitems'];
            if (count($storagegroup ?: []) > 0) {
                $this->obj->removeGroup($storagegroup);
            }
        }
        if (isset($_POST['confirmprimary'])) {
            $primary = filter_input(
                INPUT_POST,
                'primary'
            );
            $storagegroups = array_diff(
                $this->obj->get('storagegroups'),
                [$primary]
            );
            self::getClass('ImageAssociationManager')->update(
                [
                    'imageID' => $this->obj->get('id'),
                    'storagegroupID' => $storagegroups,
                    'primary' => '1'
                ],
                '',
                ['primary' => '0']
            );
            if ($primary) {
                self::getClass('ImageAssociationManager')->update(
                    [
                        'imageID' => $this->obj->get('id'),
                        'storagegroupID' => $primary,
                        'primary' => ['0', '']
                    ],
                    '',
                    ['primary' => 1]
                );
            }
        }
    }
    /**
     * Image hosts tab
     *
     * @return void
     */
    public function imageHosts()
    {
        $this->renderAssocTab(
            'image-host',
            _('Image Host Associations'),
            _('Host name'),
            'host'
        );
    }
    /**
     * Image host post elements
     *
     * @return void
     */
    public function imageHostPost()
    {
        $this->assocPost('addHost', 'removeHost');
    }
    /**
     * Edit this image
     *
     * @return void
     */
    public function edit()
    {
        $tabData = [];

        $tabData[] = [
            'name' => _('General'),
            'id' => 'image-general',
            'generator' => function () {
                $this->imageGeneral();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Hosts'),
                        'id' => 'image-host',
                        'generator' => function () {
                            $this->imageHosts();
                        }
                    ],
                    [
                        'name' => _('Storage Groups'),
                        'id' => 'image-storagegroup',
                        'generator' => function () {
                            $this->imageStoragegroups();
                        }
                    ]
                ]
            ]
        ];

        // Information
        $tabData[] = [
            'name' => _('Information'),
            'id' => 'image-information',
            'generator' => function () {
                $this->imageInformation();
            }
        ];
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Creates the image information tab.
     *
     * @return void
     */
    public function imageInformation()
    {
        // Size on server
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('On Server Size');
        echo '</h4>';
        echo '<br/>';
        echo _('This is the amount of physical disk space');
        echo ' ';
        echo _('this image is taking on the server.');
        echo _('Please note, the data here does not indicate');
        echo ' ';
        echo _('that there is or is not an issue. It just gives you an idea');
        echo ' ';
        echo _('of how much disk space the image is using.');
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        echo self::formatByteSize($this->obj->get('srvsize'));
        echo '</div>';
        echo '</div>';
        // Client Size needed
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Host HDD Size');
        echo '</h4>';
        echo '<br/>';
        echo _('This is the minimum space for the host this is deploying to.');
        echo ' ';
        echo _('Please note, the data here does not indicate');
        echo ' ';
        echo _('that there is or is not an issue. It just gives you an idea');
        echo ' ';
        echo _('of how big a disk the image will need.');
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        echo self::formatByteSize(
            array_sum(
                array_map('intval', explode(':', $this->obj->get('size')) ?? [0])
            )
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Submit save/update the image.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'Image',
            'IMAGE_EDIT',
            _('Image updated!'),
            _('Image Update Success'),
            _('Image Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'image-general':
                        $this->imageGeneralPost();
                        break;
                    case 'image-storagegroup':
                        $this->imageStoragegroupPost();
                        break;
                    case 'image-host':
                        $this->imageHostPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('Image update failed!'));
                }
            }
        );
    }
    /**
     * Creates the session create form modal elements.
     *
     * @return string
     */
    public function sessionCreateModal()
    {
        $sessionname = filter_input(INPUT_POST, 'sessionname');
        $sessioncount = filter_input(INPUT_POST, 'sessioncount');
        $sessiontimeout = (int)filter_input(INPUT_POST, 'sessiontimeout');
        $shutdown = (
            isset($_POST['sessionshutdown']) ?
            ' checked' :
            ''
        );
        $image = filter_input(INPUT_POST, 'image');

        $images = self::getClass('ImageManager')->buildSelectBox(
            $image
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'sessionname',
                _('Session Name')
            ) => self::makeInput(
                'form-control sessionname-input',
                'sessionname',
                _('Session Name'),
                'text',
                'sessionname',
                $sessionname,
                true
            ),
            self::makeLabel(
                $labelClass,
                'sessioncount',
                _('Client Count')
            ) => self::makeInput(
                'form-control sessioncount-input',
                'sessioncount',
                '0',
                'number',
                'sessioncount',
                $sessioncount
            ),
            self::makeLabel(
                $labelClass,
                'sessiontimeout',
                _('Session Timeout')
                . '<br/>('
                . _('minutes')
                . ')'
            ) => self::makeInput(
                'form-control sessiontimeout-input',
                'sessiontimeout',
                '0',
                'number',
                'sessiontimeout',
                $sessiontimeout
            ),
            self::makeLabel(
                $labelClass,
                'image',
                _('Session Image')
            ) => $images,
            self::makeLabel(
                $labelClass,
                'shutdown',
                _('Session Shutdown')
            ) => self::makeInput(
                'form-control sessionshutdown-input',
                'sessionshutdown',
                '',
                'checkbox',
                'shutdown',
                '',
                false,
                false,
                -1,
                -1,
                $shutdown
            )
        ];
        self::$HookManager
            ->processEvent(
                'IMAGE_MULTICAST_SESSION_FIELDS',
                ['fields' => &$fields]
            );

        $rendered = self::formFields($fields);
        unset($fields);

        ob_start();
        // The Create new form.
        echo self::makeFormTag(
            '',
            'session-create-form',
            self::makeTabUpdateURL(
                'session-create'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
        return ob_get_clean();
    }
    /**
     * Presents the form to created named multicast
     * sessions.
     *
     * @return void
     */
    public function multicast()
    {
        $this->title = self::$foglang['Multicast'];

        // This is for the actual current tasks.
        $this->headerData = [
            _('Session Name'),
            _('Image Name'),
            _('Client Count'),
            _('Progress')
        ];
        $this->attributes = [
            [],
            [],
            ['width' => 5],
            []
        ];

        echo '<!-- Create New Multicast Session -->';
        echo '<div id="multicastsessions">';

        // The Current running tasks.
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'session-cancel'
            )
            . '" ';

        $buttons = self::makeButton(
            'session-create',
            _('Create'),
            'btn btn-primary float-end'
        );

        $buttons .= self::makeButton(
            'session-cancel',
            _('Cancel Selected'),
            'btn btn-danger float-start',
            $props
        );
        $buttons .= self::makeButton(
            'session-pause',
            _('Pause Reload'),
            'btn btn-warning float-start'
        );
        $buttons .= self::makeButton(
            'session-resume',
            _('Resume Reload'),
            'btn btn-success float-end'
        );

        $modalBtns = self::makeButton(
            'cancelModalBtn',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $modalBtns .= self::makeButton(
            'confirmModalBtn',
            _('Confirm'),
            'btn btn-outline-secondary float-end'
        );

        $modalCreateBtns = self::makeButton(
            'createCancelModalBtn',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $modalCreateBtns .= self::makeButton(
            'createConfirmModalBtn',
            _('Create'),
            'btn btn-outline-secondary float-end',
            ' method="post" action="'
            . self::makeTabUpdateURL(
                'session-create'
            )
            . '" '
        );

        $buttons .= self::makeModal(
            'cancelModal',
            _('Cancel Selected Tasks'),
            _('Cancel the selected tasks.'),
            $modalBtns,
            '',
            'danger'
        );
        $buttons .= self::makeModal(
            'createModal',
            _('Create new Session Task'),
            $this->sessionCreateModal(),
            $modalCreateBtns,
            '',
            'success'
        );

        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Multicast Sessions');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, 'multicast-sessions-table', $buttons);
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }
    /**
     * Create new session.
     *
     * @return MulticastSession
     */
    public function sessionCreate()
    {
        $sessionname = trim(
            filter_input(INPUT_POST, 'sessionname')
        );
        $image = (int)trim(
            filter_input(INPUT_POST, 'image')
        );
        $sessiontimeout = (int)trim(
            filter_input(INPUT_POST, 'sessiontimeout')
        );
        $sessioncount = (int)trim(
            filter_input(INPUT_POST, 'sessioncount')
        );
        $sessionshutdown = (int)isset($_POST['sessionshutdown']);
        if (!$image) {
            throw new Exception(_('Please choose an image'));
        }
        $Image = new Image($image);
        if (!$Image->isValid()) {
            throw new Exception(
                _('Please select a valid image')
            );
        }
        if (self::getClass('MulticastSessionManager')->exists($sessionname)) {
            throw new Exception(_('Session with that name already exists!'));
        }
        if ($sessioncount < 1) {
            $sessioncount = Route::getCount('host');
        }
        if (!$sessiontimeout) {
            $sessiontimeout = self::getSetting('FOG_UDPCAST_MAXWAIT') * 60;
        }
        MulticastSession::assertCapacity();
        $StorageGroup = $Image->getStorageGroup();
        $StorageNode = $StorageGroup->getMasterStorageNode();
        return self::getClass('MulticastSession')
            ->set('name', $sessionname)
            ->set('port', MulticastSession::allocatePort())
            ->set('image', $Image->get('id'))
            ->set('stateID', 0)
            ->set('sessclients', $sessioncount)
            ->set('isDD', $Image->get('imageTypeID'))
            ->set('starttime', self::formatTime('now', 'Y-m-d H:i:s'))
            ->set('interface', $StorageNode->get('interface'))
            ->set('logpath', $Image->get('path'))
            ->set('storagegroupID', $StorageGroup->get('id'))
            ->set('clients', -2)
            ->set('maxwait', $sessiontimeout)
            ->set('shutdown', $sessionshutdown);
    }
    /**
     * Cancels the selected/passed sessions.
     *
     * @return void
     */
    public function sessionCancel()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'IMAGE_MULTICAST_TASK_CANCEL'
        );
        if (isset($_POST['cancelconfirm'])) {
            $tasks = filter_input_array(
                INPUT_POST,
                [
                    'tasks' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $tasks = $tasks['tasks'];
            self::getClass('MulticastSessionManager')->cancel(
                $tasks
            );
        }
    }
    /**
     * Submit the mutlicast form.
     *
     * @return void
     */
    public function multicastPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'IMAGE_MULTICAST_SESSION_POST'
        );
        global $tab;

        $serverFault = false;
        try {
            switch ($tab) {
                case 'session-create':
                    $msgSuccess = _('Session created!');
                    $titleSuccess = _('Session Create Success');
                    $titleFail = _('Session Create Fail');

                    $MulticastSession = $this->sessionCreate();
                    if (!$MulticastSession->save()) {
                        $serverFault = true;
                        throw new Exception(_('Failed to create Session'));
                    }

                    // Reset our port to a random number within the proper range.
                    $randomnumber = mt_rand(24576, 32766)*2;
                    while ($randomnumber == $MulticastSession->get('port')) {
                        $randomnumber = mt_rand(24576, 32766)*2;
                    }
                    self::setSetting('FOG_UDPCAST_STARTINGPORT', $randomnumber);
                    break;
                case 'session-cancel':
                    $this->sessionCancel();
                    $msgSuccess = _('Sessions cancelled!');
                    $titleSuccess = _('Session Cancel Success');
                    $titleFail = _('Session Cancel Fail');
            }
            $msg = json_encode(
                [
                    'msg' => $msgSuccess,
                    'title' => $titleSuccess
                ]
            );
            $code = 201;
            $hook = 'IMAGE_MULTICAST_SESSION_SUCCESS';
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'IMAGE_MULTICAST_SESSION_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => $titleFail
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Presents the storage groups list table.
     *
     * @return void
     */
    public function getStoragegroupsList()
    {
        $join = [
            'LEFT OUTER JOIN `imageGroupAssoc` ON '
            . "`nfsGroups`.`ngID` = `imageGroupAssoc`.`igaStorageGroupID` "
            . "AND `imageGroupAssoc`.`igaImageID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'igaStoragegroupID',
            'dt' => 'origID'
        ];
        $columns[] = [
            'db' => 'igaPrimary',
            'dt' => 'primary'
        ];
        $columns[] = [
            'db' => 'imageAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'storagegroup',
            'imageassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Image -> host list
     *
     * @return void
     */
    public function getHostsList()
    {
        return $this->assocItemsList(
            'host',
            'image',
            'images',
            '`images`.`imageID`',
            '`hosts`.`hostImage`',
            '`hosts`.`hostImage`',
            [
                [
                    'db' => 'imageAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Get the current active tasks.
     *
     * @return void
     */
    public function getSessionsList()
    {
        header('Content-type: application/json');

        $queued = self::fastmerge(
            (array)self::getProgressState(),
            (array)self::getQueuedStates()
        );
        $queuedStates = implode(',', $queued);

        $join = [
            'LEFT OUTER JOIN `taskStates` ON '
            . '`multicastSessions`.`msState` = `taskStates`.`tsID` '
            . " AND `multicastSessions`.`msState` IN ($queuedStates)",
            'INNER JOIN `images` ON '
            . '`multicastSessions`.`msImage` = `images`.`imageID`'
            . " AND `multicastSessions`.`msImage` = '"
            . $this->obj->get('id')
            . "'"
        ];

        // The multicast sessions datatable renders the image link from
        // row.imageid / row.imagename, which come from the joined images
        // table -- surface them as extra columns (the primary manager only
        // exposes the numeric msImage id).
        $columns = [
            [
                'db' => 'imageID',
                'dt' => 'imageid'
            ],
            [
                'db' => 'imageName',
                'dt' => 'imagename'
            ]
        ];

        return $this->obj->getItemsList(
            'multicastsession',
            '',
            $join,
            '',
            $columns
        );
    }
    /**
     * Gets the storage group selector for setting primary storage groups.
     *
     * @return string
     */
    public function getImagePrimaryStoragegroups()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $storagegroupsAssigned = Route::getIds(
            'imageassociation',
            ['imageID' => $this->obj->get('id')],
            'storagegroupID'
        );
        if (!count($storagegroupsAssigned ?: [])) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'content' => _('No storagegroups assigned to this image'),
                    'disablebtn' => true
                ]
            ));
        }
        Route::names(
            'storagegroup',
            ['id' => $storagegroupsAssigned]
        );
        $storagegroupNames = json_decode(Route::getData());
        foreach ($storagegroupNames as &$storagegroup) {
            $storagegroups[$storagegroup->id] = $storagegroup->name;
            unset($storagegroup);
        }
        unset($storagegroupNames);
        $primarystoragegroup = Route::getIds(
            'imageassociation',
            [
                'imageID' => $this->obj->get('id'),
                'primary' => '1'
            ],
            'storagegroupID'
        );
        $primarystoragegroup = array_shift($primarystoragegroup);
        $storagegroupSelector = self::selectForm(
            'storagegroup',
            $storagegroups,
            $primarystoragegroup,
            true,
            '',
            true
        );
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'content' => $storagegroupSelector,
                'disablebtn' => false
            ]
        ));
    }
}
