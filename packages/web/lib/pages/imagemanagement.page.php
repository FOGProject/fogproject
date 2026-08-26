<?php
/**
 * Image management page
 *
 * PHP version 7.4+
 *
 * @category ImageManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
            _('Architecture'),
            _('Sector Size'),
            _('Protected'),
            _('Enabled'),
            _('Captured')
        ];
        // Each <th> carries its DataTables data key, the same contract the
        // host grid uses. fog.image.list.js builds both its column list and
        // its columnDefs targets from these rather than from hardcoded
        // indexes: the renders here used to be pinned to targets 1 and 2, so
        // inserting Architecture at position 1 slid the lock render onto the
        // new column and the enabled render onto Protected, silently. Names
        // cannot slide.
        $this->attributes = [
            ['data-col' => 'mainlink'],
            ['data-col' => 'arch'],
            ['data-col' => 'sectorsize'],
            ['data-col' => 'protected'],
            ['data-col' => 'isEnabled'],
            ['data-col' => 'deployed']
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
        } catch (\Exception $e) {
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
                    'icon fas fa-circle-info fa-lg hand',
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
                    throw new \Exception(
                        _('An image already exists with this name!')
                    );
                }
                if (in_array($path, ['postdownloadscripts','dev'])) {
                    throw new \Exception(
                        _('Please choose a different filename/path as this is reserved')
                    );
                }
                $exists = self::getClass('ImageManager')
                    ->exists($path, '', 'path');
                if ($exists) {
                    throw new \Exception(
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
                    throw new \Exception(_('Add image failed!'));
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
        // The architectures an admin may pick on an IMAGE, which is what
        // `architectures.archIsAccess` is for -- the same flag taskTypes uses
        // to say a task type belongs to hosts, to groups, or to both. An
        // architecture flagged host-only never appears here.
        //
        // Editable, unlike the read-only Information card it replaces. A
        // capture stamps this from the capturing host and always wins, but
        // every image captured before schema step 370 reads "not recorded"
        // and keeps reading that until someone recaptures it -- hours of work
        // to record one fact the admin already knows. Setting it by hand is
        // how those images join the compatibility check.
        //
        // Edit form only. An image being created has been captured from
        // nothing, so the add form and its modal have nothing to offer but a
        // guess.
        //
        // buildSelectBox() treats an empty filter as "no filter" and would
        // then offer every row, so an empty pick list has to be spelled as an
        // id that matches nothing rather than as nothing.
        $archID = (
            filter_input(INPUT_POST, 'archID') ?:
            ($this->obj->get('archID') ?: '')
        );
        $archIds = array_keys(Architecture::pickable('image'));
        if (count($archIds) < 1) {
            $archIds = [0];
        }
        $Architectures = self::getClass('ArchitectureManager')
            ->buildSelectBox($archID, 'archID', 'name', $archIds);
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
                    'icon fas fa-circle-info fa-lg hand',
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
                'archID',
                _('Architecture')
            ) => $Architectures,
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
        // The blank "- Please select -" option means "not recorded", which is
        // a real value here and not the absence of one: Architecture::canRun()
        // reads it as "nothing to contradict" and allows the deploy. Stored as
        // NULL rather than 0 so it reads the same as an image nobody has ever
        // touched.
        $archID = trim(
            (string)filter_input(INPUT_POST, 'archID')
        );
        $archID = '' === $archID ? null : (int)$archID;
        $protected = (int)isset($_POST['isProtected']);
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $exists = self::getClass('ImageManager')->exists($image);
        $compress = (int)trim(
            filter_input(INPUT_POST, 'compression')
        );
        if ($this->obj->get('name') != $image && $exists) {
            throw new \Exception(_('An image with this name already exists!'));
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
            ->set('archID', $archID)
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
            'btn btn-primary float-end',
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
        $this->notes = [
            _('Image') => $this->obj->get('name'),
            _('OS') => $this->obj->getOS()->get('name'),
            _('Image Type') => $this->obj->getImageType()->get('name'),
            _('Last Captured') => self::dateOrNever($this->obj->get('deployed')),
            _('Size on Server') => self::formatByteSize($this->obj->get('srvsize')),
            _('Primary Storage Group') => $this->obj->getStorageGroup()->get('name')
        ];
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
        // Architecture. Read-only HERE -- it is stamped from the capturing
        // host by TaskQueue::_moveUpload(), so what this tab shows is an
        // observation. The picker on the General tab is for the other case:
        // an image captured before schema step 370, which has no observation
        // and never will unless it is recaptured.
        //
        // Named rather than blank when unrecorded: a blank cell reads as x86
        // to anyone scanning, which is the assumption this whole relation
        // exists to stop.
        $imgArch = (string)$this->obj->get('arch')->get('name');
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Architecture');
        echo '</h4>';
        echo '<br/>';
        echo _('The architecture of the machine this image was captured from.');
        echo ' ';
        echo _('FOG refuses to deploy an image to a host that cannot run it.');
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        if ('' === $imgArch) {
            echo '<span class="text-muted">';
            echo _('Not recorded');
            echo ' &mdash; ';
            echo _('captured before FOG tracked this. Set it on the General tab, or it will be set the next time this image is captured.');
            echo '</span>';
        } else {
            echo '<code>' . htmlentities($imgArch, ENT_QUOTES, 'utf-8') . '</code>';
        }
        echo '</div>';
        echo '</div>';
        // Sector size. The other half of "can this image go on that disk",
        // and the half FOS has been refusing on since ADR-0005 without the
        // server ever being able to show it.
        $sectorLabel = Image::sectorSizeLabel($this->obj->get('sectorsize'));
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Sector Size');
        echo '</h4>';
        echo '<br/>';
        echo _('The logical sector size of the disk this image was captured from.');
        echo ' ';
        echo _('An image cannot be deployed to a disk with a different logical sector size -- partition and filesystem geometry cannot be translated between them.');
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        if ('' === $sectorLabel) {
            echo '<span class="text-muted">';
            echo _('Not recorded');
            echo ' &mdash; ';
            echo _('captured before FOG tracked this, or by a FOS build whose partition dump predates the sector-size field. It will be set the next time this image is captured.');
            echo '</span>';
        } else {
            echo '<code>' . htmlentities($sectorLabel, ENT_QUOTES, 'utf-8') . '</code>';
        }
        echo '</div>';
        echo '</div>';
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
                    throw new \Exception(_('Image update failed!'));
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
     * One place to see what architecture everything is.
     *
     * Architecture is the one property that belongs to a host AND to an image
     * and only means something when you can see both at once. A per-image tab
     * cannot show which machines an image is pointed at, and a per-host tab
     * cannot show whether the image assigned to it is one it could run -- so
     * a mismatch was invisible from either side, which is how an x86 image
     * gets assigned to an ARM host and stays that way until somebody deploys.
     *
     * Server-rendered rather than a DataTables endpoint: this is a report
     * read occasionally, not a grid that needs paging and sorting, and three
     * joined queries answer it outright with no per-row lookups.
     *
     * Compatibility is decided by Architecture::canRun(), never re-implemented
     * here in SQL. It is the same call Host::createImagePackage() refuses on,
     * so what this page flags and what a deploy rejects cannot drift apart.
     *
     * @return void
     */
    public function architectures()
    {
        $this->title = _('Architectures');

        // Refuse to render against a database that has not been migrated.
        //
        // Not hypothetical: this page was reachable on a live 1.6 server
        // sitting at schema version 368 while FOG_SCHEMA still said 368, so
        // the updater never offered steps 369/370. The queries below then
        // failed with 1054 Unknown column, PDODB swallowed the exception and
        // returned false, and the tables rendered one blank row each -- which
        // looks like "no data" rather than "broken", and is the worst of both.
        //
        // tableColumns() returns [] when it cannot read the table at all, and
        // its contract is that empty means "don't know", so only a populated
        // list missing the column counts as proof of an unmigrated database.
        $hostCols = DatabaseManager::tableColumns('hosts');
        $imageCols = DatabaseManager::tableColumns('images');
        if ((count($hostCols) && !in_array('hostarchid', $hostCols))
            || (count($imageCols) && !in_array('imagearchid', $imageCols))
        ) {
            echo '<div class="alert alert-warning">';
            echo '<strong>' . _('Database update required.') . '</strong> ';
            echo _('The architecture columns do not exist yet. Run the Database Schema Installer / Updater, then reload this page.');
            echo '</div>';
            return;
        }

        // Two more LEFT JOINs onto `architectures` since schema step 372, one
        // per side. LEFT, not INNER, on all four: a host with no image, or
        // either side with no architecture recorded, is the ordinary state on
        // an upgraded server and must still appear in this report -- an INNER
        // join would quietly hide exactly the rows an admin came here to find.
        $rows = self::$DB->query(
            "SELECT `hosts`.`hostID` AS `id`, `hosts`.`hostName` AS `host`, "
            . "`ha`.`archName` AS `hostArch`, "
            . "`images`.`imageName` AS `image`, "
            . "`ia`.`archName` AS `imageArch` "
            . "FROM `hosts` "
            . "LEFT OUTER JOIN `images` "
            . "ON `hosts`.`hostImage` = `images`.`imageID` "
            . "LEFT OUTER JOIN `architectures` `ha` "
            . "ON `ha`.`archID` = `hosts`.`hostArchID` "
            . "LEFT OUTER JOIN `architectures` `ia` "
            . "ON `ia`.`archID` = `images`.`imageArchID` "
            . "ORDER BY `hosts`.`hostName`"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $rows = is_array($rows) ? $rows : [];

        $images = self::$DB->query(
            "SELECT `images`.`imageID` AS `id`, "
            . "`images`.`imageName` AS `image`, "
            . "`ia`.`archName` AS `imageArch`, "
            . "`images`.`imageSectorSize` AS `sectorsize`, "
            . "`images`.`imageDateTime` AS `captured`, "
            . "COUNT(`hosts`.`hostID`) AS `assigned` "
            . "FROM `images` "
            . "LEFT OUTER JOIN `hosts` "
            . "ON `hosts`.`hostImage` = `images`.`imageID` "
            . "LEFT OUTER JOIN `architectures` `ia` "
            . "ON `ia`.`archID` = `images`.`imageArchID` "
            . "GROUP BY `images`.`imageID`, `images`.`imageName`, "
            . "`ia`.`archName`, `images`.`imageSectorSize`, "
            . "`images`.`imageDateTime` "
            . "ORDER BY `images`.`imageName`"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $images = is_array($images) ? $images : [];

        // Counted in PHP off the same rows the table renders, so the summary
        // and the detail cannot disagree.
        $byArch = [];
        $unknownHosts = 0;
        $mismatched = [];
        foreach ($rows as $row) {
            $hostArch = Architecture::normalizeName($row['hostArch'] ?? '');
            if ('' === $hostArch) {
                $unknownHosts++;
            } else {
                if (!isset($byArch[$hostArch])) {
                    $byArch[$hostArch] = 0;
                }
                $byArch[$hostArch]++;
            }
            if (!Architecture::canRun($row['imageArch'] ?? '', $row['hostArch'] ?? '')) {
                $mismatched[] = $row;
            }
        }
        ksort($byArch);

        // --- summary ---------------------------------------------------
        echo '<div class="row">';
        foreach ($byArch as $arch => $count) {
            $this->_archStat($count, sprintf(_('%s hosts'), $arch), false);
        }
        $this->_archStat($unknownHosts, _('not yet seen'), false);
        $this->_archStat(count($mismatched), _('mismatched'), count($mismatched) > 0);
        echo '</div>';

        if (count($mismatched) > 0) {
            echo '<div class="alert alert-danger">';
            echo '<strong>';
            printf(
                _('%d host(s) are assigned an image they cannot run.'),
                count($mismatched)
            );
            echo '</strong> ';
            echo _('FOG will refuse these deploy tasks. Assign an image built for the same architecture, or capture one.');
            echo '</div>';
        }

        // --- the architectures themselves ------------------------------
        // Where `archIsAccess` is actually set. Same shape as
        // taskTypes.ttIsAccess, which the Task Type Edit plugin exposes on
        // each task type's own form; there is no architecture management node
        // to hang a form off, and adding one to carry three rows would be a
        // node, a menu entry, a list grid and a permission entry for a table
        // nobody adds to. It lives here instead, on the page that already
        // exists to answer "what architectures does this server know about".
        //
        // Name is not editable. It is not a label -- BootMenu matches the
        // string iPXE reports against it, and Architecture::normalizeName()
        // folds FOS's spellings onto it -- so renaming a row would silently
        // stop hosts from ever matching it again. Adding and removing rows is
        // out for the same reason: an architecture FOG cannot boot or capture
        // is not made real by a row saying so.
        $Archs = Route::getList('architecture', false, 'AND', 'name');
        echo self::makeFormTag(
            '',
            'architectures-form',
            '../management/index.php?node=image&sub=architectures',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header"><h4 class="card-title">';
        echo _('Architectures');
        echo '</h4><br/>';
        echo _('Which side of a deploy each architecture may be picked on. An architecture set to Hosts only is not offered on an image, and one set to Images only is not offered on a host. This never affects what a host reports at boot or what a capture records -- only what a person may choose.');
        echo '</div>';
        echo '<div class="card-body table-responsive">';
        // NOT a DataTable, unlike the two report tables below, and it must not
        // become one: every row here is a pair of form inputs, and DataTables
        // removes the rows it has paged away from the DOM -- so anything past
        // page one would silently stop being submitted. Three rows need no
        // paging anyway.
        echo '<table class="table table-hover"><thead><tr>';
        echo '<th>' . _('Architecture') . '</th>';
        echo '<th>' . _('Description') . '</th>';
        echo '<th>' . _('Selectable on') . '</th>';
        echo '</tr></thead><tbody>';
        $accessLabels = [
            'both' => _('Hosts and images'),
            'host' => _('Hosts only'),
            'image' => _('Images only')
        ];
        foreach ((array)$Archs as $Arch) {
            $aid = (int)$Arch->id;
            echo '<tr>';
            echo '<td><code>'
                . htmlentities((string)$Arch->name, ENT_QUOTES, 'utf-8')
                . '</code></td>';
            echo '<td><input type="text" class="form-control" name="description['
                . $aid
                . ']" value="'
                . htmlentities((string)$Arch->description, ENT_QUOTES, 'utf-8')
                . '"/></td>';
            echo '<td><select class="form-control" name="access[' . $aid . ']">';
            foreach ($accessLabels as $value => $label) {
                echo '<option value="' . $value . '"'
                    . ($value === (string)$Arch->access ? ' selected' : '')
                    . '>'
                    . htmlentities($label, ENT_QUOTES, 'utf-8')
                    . '</option>';
            }
            echo '</select></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo self::makeButton(
            'architectures-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        echo '</div></div>';
        echo '</form>';

        // --- images ----------------------------------------------------
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header"><h4 class="card-title">';
        echo _('Images');
        echo '</h4></div>';
        echo '<div class="card-body table-responsive">';
        echo '<table id="architectures-images" class="table table-hover">';
        echo '<thead><tr>';
        echo '<th>' . _('Image') . '</th>';
        echo '<th>' . _('Architecture') . '</th>';
        echo '<th>' . _('Sector Size') . '</th>';
        echo '<th>' . _('Captured') . '</th>';
        echo '<th>' . _('Hosts assigned') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($images as $img) {
            $arch = Architecture::normalizeName($img['imageArch'] ?? '');
            echo '<tr>';
            echo '<td>' . htmlentities($img['image'], ENT_QUOTES, 'utf-8') . '</td>';
            echo '<td>' . $this->_archCell($arch, _('Not recorded')) . '</td>';
            // Sector size is not compared against anything here: the server
            // never learns a host disk's sector size, so unlike architecture
            // there is no mismatch to flag. FOS does that check at deploy.
            echo '<td>'
                . $this->_archCell(
                    Image::sectorSizeLabel($img['sectorsize'] ?? 0),
                    _('Not recorded')
                )
                . '</td>';
            echo '<td>' . htmlentities((string)$img['captured'], ENT_QUOTES, 'utf-8') . '</td>';
            echo '<td>' . (int)$img['assigned'] . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';

        // --- hosts -----------------------------------------------------
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header"><h4 class="card-title">';
        echo _('Hosts');
        echo '</h4></div>';
        echo '<div class="card-body table-responsive">';
        echo '<table id="architectures-hosts" class="table table-hover">';
        echo '<thead><tr>';
        echo '<th>' . _('Host') . '</th>';
        echo '<th>' . _('Architecture') . '</th>';
        echo '<th>' . _('Assigned image') . '</th>';
        echo '<th>' . _('Image architecture') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $bad = !Architecture::canRun($row['imageArch'] ?? '', $row['hostArch'] ?? '');
            // data-mismatch as well as the class: this table is a DataTable
            // and redraws its rows when paged, so fog.image.architectures.js
            // re-applies the highlight from the attribute on every draw. The
            // class stays for the first paint and for anyone reading the page
            // with JavaScript off.
            echo $bad
                ? '<tr class="table-danger" data-mismatch="1">'
                : '<tr>';
            echo '<td>' . htmlentities($row['host'], ENT_QUOTES, 'utf-8') . '</td>';
            echo '<td>'
                . $this->_archCell(
                    Architecture::normalizeName($row['hostArch'] ?? ''),
                    _('Not yet seen')
                )
                . '</td>';
            echo '<td>'
                . (
                    $row['image']
                    ? htmlentities($row['image'], ENT_QUOTES, 'utf-8')
                    : '<span class="text-muted">' . _('None') . '</span>'
                )
                . '</td>';
            echo '<td>'
                . $this->_archCell(
                    Architecture::normalizeName($row['imageArch'] ?? ''),
                    _('Not recorded')
                )
                . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }
    /**
     * Saves the per-architecture flags from the Architectures page.
     *
     * Only `access` and `description` are writable. The name is the string
     * BootMenu matches an iPXE report against, so renaming a row would stop
     * hosts matching it with no error anywhere; rows cannot be added or
     * removed here for the same reason. See architectures().
     *
     * @return void
     */
    public function architecturesPost()
    {
        self::checkAuthAndCSRF();
        $access = (array)filter_input(
            INPUT_POST,
            'access',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );
        $descriptions = (array)filter_input(
            INPUT_POST,
            'description',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );
        $valid = ['both', 'host', 'image'];
        $saved = 0;
        foreach ($access as $id => $value) {
            $id = (int)$id;
            // A value outside the enum is skipped rather than clamped to a
            // default: MySQL stores an unknown enum member as '' with only a
            // warning, and '' matches neither side of the pickable() query,
            // so a typo'd POST would quietly remove an architecture from both
            // pickers with nothing on screen to say why.
            if ($id < 1 || !in_array($value, $valid, true)) {
                continue;
            }
            $Arch = self::getClass('Architecture', $id);
            if (!$Arch->isValid()) {
                continue;
            }
            $Arch->set('access', $value);
            if (array_key_exists($id, $descriptions)) {
                $Arch->set('description', trim((string)$descriptions[$id]));
            }
            $Arch->save();
            $saved++;
        }
        self::setMessage(
            sprintf(_('%d architecture(s) updated.'), $saved),
            _('Architectures'),
            'success'
        );
        self::redirect('../management/index.php?node=image&sub=architectures');
    }
    /**
     * One summary tile on the architectures page.
     *
     * @param int    $value the number
     * @param string $label what it counts
     * @param bool   $warn  render it as a problem
     *
     * @return void
     */
    private function _archStat($value, $label, $warn)
    {
        // A card, not AdminLTE's `small-box`. small-box is built for a
        // saturated background with white text forced on top of it, so
        // `bg-light` -- the only neutral in that family -- paints a near-white
        // box that the dark theme then writes its light text onto, and the
        // number is invisible. Nothing else in FOG used small-box, so there
        // was no house tile to inherit a fix from.
        //
        // The card idiom below is the one the Images and Hosts cards on this
        // same page already use. The theme styles cards in both modes, so this
        // needs no colour of its own and cannot drift from the rest of the
        // page. Only the warning tile takes a colour, and it takes it from
        // card-danger, which forces its own contrast.
        echo '<div class="col-sm-6 col-md-3">';
        echo '<div class="card '
            . ($warn && $value > 0 ? 'card-danger' : 'card-primary')
            . ' card-outline">';
        echo '<div class="card-body text-center">';
        echo '<h3 class="mb-0">' . (int)$value . '</h3>';
        echo '<p class="mb-0 text-muted">'
            . htmlentities($label, ENT_QUOTES, 'utf-8')
            . '</p>';
        echo '</div></div></div>';
    }
    /**
     * An architecture table cell.
     *
     * Unknown is spelled out rather than left blank on purpose: an empty cell
     * reads as x86 to anyone scanning a list, which is the exact assumption
     * these columns exist to stop.
     *
     * @param string $arch    the normalised architecture, '' when unknown
     * @param string $unknown what to say when it is unknown
     *
     * @return string
     */
    private function _archCell($arch, $unknown)
    {
        if ('' === $arch) {
            return '<span class="text-muted">'
                . htmlentities($unknown, ENT_QUOTES, 'utf-8')
                . '</span>';
        }

        return '<code>' . htmlentities($arch, ENT_QUOTES, 'utf-8') . '</code>';
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
        // One self-relabelling toggle, not a pause/resume pair -- pausing the
        // auto-refresh destroys nothing so it never belonged on the left with
        // Cancel Selected, and only ever one of the two was pressable. Create
        // holds primary as the rightmost of this cluster, so the toggle is
        // secondary; bare float-end siblings in this block footer land
        // first-emitted-rightmost, hence Create is emitted before it.
        $buttons .= self::makeReloadToggle(
            'session-reload-toggle',
            'btn btn-secondary float-end'
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
            throw new \Exception(_('Please choose an image'));
        }
        $Image = new Image($image);
        if (!$Image->isValid()) {
            throw new \Exception(
                _('Please select a valid image')
            );
        }
        if (self::getClass('MulticastSessionManager')->exists($sessionname)) {
            throw new \Exception(_('Session with that name already exists!'));
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
                        throw new \Exception(_('Failed to create Session'));
                    }

                    // The port was already chosen by
                    // MulticastSession::allocatePort() above. This used to
                    // then overwrite FOG_UDPCAST_STARTINGPORT with a fresh
                    // random port -- a second, independent copy of a rotation
                    // that allocatePort() was doing as well, clobbering an
                    // admin-editable setting from two places at once. The
                    // allocator now picks the lowest free port in a bounded
                    // window and the setting is left alone as the base of it.
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
        } catch (\Exception $e) {
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
        // asValue(): names() has no wrapper of its own, and its payload is
        // a bare list with nothing to unwrap.
        $storagegroupNames = Route::asValue(
            function () use ($storagegroupsAssigned) {
                Route::names(
                    'storagegroup',
                    ['id' => $storagegroupsAssigned]
                );
            }
        );
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ImageManagement', 'ImageManagement');
