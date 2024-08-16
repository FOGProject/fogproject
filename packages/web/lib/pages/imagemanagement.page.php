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
        $this->name = 'Image Management';
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
     * The form to display when adding a new image
     * definition.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Image');

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
            Route::ids('storagegroup', false);
            $sgID = @min(json_decode(Route::getData(), true));
        }
        $StorageGroup = new StorageGroup($sgID);
        $StorageGroups = self::getClass('StorageGroupManager')
            ->buildSelectBox(
                $sgID,
                '',
                'id'
            );
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

        $labelClass = 'col-sm-3 control-label';

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
            . '<span class="input-group-addon">'
            . $StorageNode->get('path')
            . '/'
            . '</span>'
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
                    _(
                        'Image Type is a very important setting and can have'
                        . ' a major impact on how your imaging works or fails.'
                        . ' Please read more about the different image types'
                        . ' and how to use them'
                        . ' <a href="https://wiki.fogproject.org/wiki/index.php'
                        . '?title=Managing_FOG#Images" target="_blank">'
                        . 'in our wiki</a> before you choose!'
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

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'IMAGE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Image' => self::getClass('Image')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'image-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="image-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Image');
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
     * The form to display when adding a new image
     * definition.
     *
     * @return void
     */
    public function addModal()
    {
        $image = filter_input(INPUT_POST, 'image');
        $description = filter_input(INPUT_POST, 'description');
        $storagegroup = (int)filter_input(INPUT_POST, 'storagegroup');
        $os = (int)filter_input(INPUT_POST, 'os');
        $imagetype = (int)filter_input(INPUT_POST, 'imagetype');
        $imagepartitiontype = (int)filter_input(INPUT_POST, 'imagepartitiontype');
        $compress = (int)filter_input(INPUT_POST, 'compress');
        $imagemanage = filter_input(INPUT_POST, 'imagemanage');
        $path = filter_input(INPUT_POST, 'path');
        if ($storagegroup > 0) {
            $sgID = $storagegroup;
        } else {
            Route::ids('storagegroup', false);
            $sgID = @min(json_decode(Route::getData(), true));
        }
        $StorageGroup = new StorageGroup($sgID);
        $StorageGroups = self::getClass('StorageGroupManager')
            ->buildSelectBox(
                $sgID,
                '',
                'id'
            );
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

        $labelClass = 'col-sm-3 control-label';

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
            . '<span class="input-group-addon">'
            . $StorageNode->get('path')
            . '/'
            . '</span>'
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
                    _(
                        'Image Type is a very important setting and can have'
                        . ' a major impact on how your imaging works or fails.'
                        . ' Please read more about the different image types'
                        . ' and how to use them'
                        . ' <a href="https://wiki.fogproject.org/wiki/index.php'
                        . '?title=Managing_FOG#Images" target="_blank">'
                        . 'in our wiki</a> before you choose!'
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

        self::$HookManager->processEvent(
            'IMAGE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Image' => self::getClass('Image')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=image&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually save the new node.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('IMAGE_ADD_POST');
        $image = trim(
            filter_input(INPUT_POST, 'image')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $storagegroup = (int)trim(
            filter_input(INPUT_POST, 'storagegroup')
        );
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

        $serverFault = false;
        try {
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
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'IMAGE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Image added!'),
                    'title' => _('Image Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'IMAGE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Image Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=image&sub=edit&id='
        //    . $Image->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'Image' => &$Image,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
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
            filter_input(INPUT_POST, 'image') ?:
            ($this->obj->get('name') ?: '')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            ($this->obj->get('description') ?: '')
        );
        $StorageNode = $this->obj->getStorageGroup()->getMasterStorageNode();
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

        $labelClass = 'col-sm-3 control-label';

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
            . '<span class="input-group-addon">'
            . $StorageNode->get('path')
            . '/'
            . '</span>'
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
                    _(
                        'Image Type is a very important setting and can have'
                        . ' a major impact on how your imaging works or fails.'
                        . ' Please read more about the different image types'
                        . ' and how to use them'
                        . ' <a href="https://wiki.fogproject.org/wiki/index.php'
                        . '?title=Managing_FOG#Images" target="_blank">'
                        . 'in our wiki</a> before you choose!'
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
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
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

        echo self::makeFormTag(
            'form-horizontal',
            'image-general-form',
            self::makeTabUpdateURL(
                'image-general',
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
     * Update the general post
     *
     * @return void
     */
    public function imageGeneralPost()
    {
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
        $this->headerData = [
            _('Storage Group Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'image-storagegroup',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'image-storagegroup-send',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'image-storagegroup-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Image Storage Group Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'image-storagegroup-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('storagegroup');
        echo '</div>';
        echo '</div>';

        // Primary Storage Group
        $buttons = self::makeButton(
            'image-storagegroup-primary-send',
            _('Update'),
            'btn btn-info pull-right',
            $props
        );
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Image Primary Storage Group');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<span id="storagegroupselector"></span>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
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
        $this->headerData = [
            _('Host name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'image-host',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'image-host-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'image-host-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Image Host Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'image-host-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('host');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Image host post elements
     *
     * @return void
     */
    public function imageHostPost()
    {
        if (isset($_POST['confirmadd'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['additems'];
            if (count($hosts ?: []) > 0) {
                $this->obj->addHost($hosts);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['remitems'];
            if (count($hosts ?: []) > 0) {
                $this->obj->removeHost($hosts);
            }
        }
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

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Creates the image information tab.
     *
     * @return void
     */
    public function imageInformation()
    {
        // Size on server
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
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
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo self::formatByteSize($this->obj->get('srvsize'));
        echo '</div>';
        echo '</div>';
        // Client Size needed
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
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
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
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
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'IMAGE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Image updated!'),
                    'title' => _('Image Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'IMAGE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Image Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Image' => &$this->obj,
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
     * Creates the session create form modal elements.
     *
     * @return string
     */
    public function sessionCreateModal()
    {
        $sessionname = filter_input(INPUT_POST, 'sessionname');
        $sessioncount = filter_input(INPUT_POST, 'sessioncount');
        $timeout = (int)filter_input(INPUT_POST, 'sessiontimeout');
        $shutdown = (
            isset($_POST['sessionshutdown']) ?
            ' checked' :
            ''
        );
        $image = filter_input(INPUT_POST, 'image');

        $images = self::getClass('ImageManager')->buildSelectBox(
            $image
        );

        $labelClass = 'col-sm-3 control-label';

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
            'form-horizontal',
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

        echo '<div class="box box-solid">';
        echo '<div class="box-body">';

        echo '<!-- Create New Multicast Session -->';
        echo '<div class="box-group" id="multicastsessions">';

        // The Current running tasks.
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'session-cancel'
            )
            . '" ';

        $buttons = self::makeButton(
            'session-create',
            _('Create'),
            'btn btn-primary pull-right'
        );

        $buttons .= self::makeButton(
            'session-cancel',
            _('Cancel Selected'),
            'btn btn-danger pull-left',
            $props
        );
        $buttons .= self::makeButton(
            'session-pause',
            _('Pause Reload'),
            'btn btn-warning pull-left'
        );
        $buttons .= self::makeButton(
            'session-resume',
            _('Resume Reload'),
            'btn btn-success pull-right'
        );

        $modalBtns = self::makeButton(
            'cancelModalBtn',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $modalBtns .= self::makeButton(
            'confirmModalBtn',
            _('Confirm'),
            'btn btn-outline pull-right'
        );

        $modalCreateBtns = self::makeButton(
            'createCancelModalBtn',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $modalCreateBtns .= self::makeButton(
            'createConfirmModalBtn',
            _('Create'),
            'btn btn-outline pull-right',
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

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Multicast Sessions');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'multicast-sessions-table', $buttons);
        echo '</div>';
        echo '</div>';

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
            Route::count('host');
            $hosts = json_decode(Route::getData());
            $sessioncount = $hosts->total;
        }
        if (!$sessiontimeout) {
            $sessiontimeout = self::getSetting('FOG_UDPCAST_MAXWAIT') * 60;
        }
        Route::count(
            'multicastsession',
            [
                'stateID' => self::fastmerge(
                    (array)self::getQueuedStates(),
                    (array)self::getProgressState()
                )
            ]
        );
        $countmc = json_encode(Route::getData());
        $countmc = $countmc->total;
        $countmctot = self::getSetting('FOG_MULTICAST_MAX_SESSIONS');
        if ($countmc >= $countmctot) {
            throw new Exception(
                _(
                    'Server is only configured to run '
                    . $countmctot
                    . ' multicast tasks!'
                )
            );
        }
        $StorageGroup = $Image->getStorageGroup();
        $StorageNode = $StorageGroup->getMasterStorageNode();
        return self::getClass('MulticastSession')
            ->set('name', $sessionname)
            ->set('port', self::getSetting('FOG_UDPCAST_STARTINGPORT'))
            ->set('image', $Image->get('id'))
            ->set('stateID', 0)
            ->set('sessclients', $sessioncount)
            ->set('isDD', $Image->get('imageTypeID'))
            ->set('starttime', self::formatTime('now', 'Y-m-d H:i:s'))
            ->set('interface', $StorageNode->get('interface'))
            ->set('logpath', $Image->get('path'))
            ->set('storagegroupID', $StorageNode->get('id'))
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
        http_response_code($code);
        echo $msg;
        unset($MulticastSession);
        exit;
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
        $join = [
            'LEFT OUTER JOIN `images` ON '
            . '`images`.`imageID` = `hosts`.`hostImage` '
            . "AND `hosts`.`hostImage` = '" . $this->obj->get('id') . "'"
        ];

        $columns[] = [
            'db' => 'imageAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'host',
            'image',
            $join,
            '',
            $columns
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
        Route::ids(
            'imageassociation',
            ['imageID' => $this->obj->get('id')],
            'storagegroupID'
        );
        $storagegroupsAssigned = json_decode(Route::getData(), true);
        if (!count($storagegroupsAssigned ?: [])) {
            echo json_encode(
                [
                    'content' => _('No storagegroups assigned to this image'),
                    'disablebtn' => true
                ]
            );
            exit;
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
        Route::ids(
            'imageassociation',
            [
                'imageID' => $this->obj->get('id'),
                'primary' => '1'
            ],
            'storagegroupID'
        );
        $primarystoragegroup = json_decode(Route::getData(), true);
        $primarystoragegroup = array_shift($primarystoragegroup);
        $storagegroupSelector = self::selectForm(
            'storagegroup',
            $storagegroups,
            $primarystoragegroup,
            true,
            '',
            true
        );
        echo json_encode(
            [
                'content' => $storagegroupSelector,
                'disablebtn' => false
            ]
        );
        exit;
    }
}
