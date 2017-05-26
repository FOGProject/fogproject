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
         * Add the multicast session items for images.
         */
        $this->menu['multicast'] = sprintf(
            '%s %s',
            self::$foglang['Multicast'],
            self::$foglang['Image']
        );
        /**
         * If we want the Server size taken by the image.
         */
        $SizeServer = self::getSetting('FOG_FTP_IMAGE_SIZE');
        /**
         * Get our nicer names.
         */
        global $id;
        global $sub;
        /**
         * If the id is set load our sub-side menu.
         */
        if ($id) {
            /**
             * The other sub menu items.
             */
            $this->subMenu = array(
                "$this->linkformat#image-gen" => self::$foglang['General'],
                "$this->linkformat#image-storage" => sprintf(
                    '%s %s',
                    self::$foglang['Storage'],
                    self::$foglang['Group']
                ),
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            /**
             * The notes for this item.
             */
            $this->notes = array(
                self::$foglang['Images'] => $this->obj->get('name'),
                self::$foglang['LastCaptured'] => $this->obj->get('deployed'),
                self::$foglang['DeployMethod'] => (
                    $this->obj->get('format') ?
                    _('Partimage') :
                    _('Partclone')
                ),
                self::$foglang['ImageType'] => (
                    $this->obj->getImageType() ?
                    $this->obj->getImageType() :
                    self::$foglang['NoAvail']
                ),
                _('Primary Storage Group') => $this->obj->getStorageGroup()->get(
                    'name'
                )
            );
        }
        /**
         * Allow custom hooks/changes to: Submenu data via.
         *
         * Menu, submenu, id, notes, the main object,
         * linkformat, delformat, and membership information.
         */
        self::$HookManager
            ->processEvent(
                'SUB_MENULINK_DATA',
                array(
                    'menu' => &$this->menu,
                    'submenu' => &$this->subMenu,
                    'id' => &$this->id,
                    'notes' => &$this->notes,
                    'object' => &$this->obj,
                    'linkformat' => &$this->linkformat,
                    'delformat' => &$this->delformat,
                    'membership' => &$this->membership
                )
            );
        /**
         * The header data for list/search.
         */
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler"/>'
            . '<label for="toggler"></label>',
            _('Image Name'),
            _('Storage Group'),
            _('OS'),
            _('Partition'),
            _('Image Size: ON CLIENT'),
        );
        /**
         * If we have the size server enabled
         * inject the on server element.
         */
        if ($SizeServer) {
            array_push(
                $this->headerData,
                _('Image Size: ON SERVER')
            );
        }
        /**
         * Finish our injection of items.
         */
        array_push(
            $this->headerData,
            _('Captured')
        );
        /**
         * The template for the list/search elements.
         */
        $this->templates = array(
            '${protected}',
            '<input type="checkbox" name="image[]" '
            . 'value="${id}" class="toggle-action" id="'
            . 'toggler1"/><label for="toggler1"></label>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${id}" title="%s: '
                . '${name} Last captured: ${deployed}">${name} - '
                . '${id}</a><br/><small>${image_type}</small><br/>'
                . '<small>${type}</small>',
                $this->node,
                _('Edit')
            ),
            '${storageGroup}',
            '${os}',
            '${image_partition_type}',
            '${size}',
        );
        /**
         * If we have the size server enabled
         * inject the on server template.
         */
        if ($SizeServer) {
            array_push(
                $this->templates,
                '${serv_size}'
            );
        }
        /**
         * Finish our injection of template items.
         */
        array_push(
            $this->templates,
            '${deployed}'
        );
        /**
         * The attributes for the table items.
         */
        $this->attributes = array(
            array(
                'width' => 5,
                'class' => 'l filter-false'
            ),
            array(
                'width' => 16,
                'class' => 'l filter-false'
            ),
            array(),
            array(),
            array(),
            array(),
            array(
                'width' => 50,
                'class' => 'c'
            ),
        );
        /**
         * If we have the size server enabled
         * inject the on server attributes.
         */
        if ($SizeServer) {
            array_push(
                $this->attributes,
                array(
                    'width' => 50,
                    'class' => 'c'
                )
            );
        }
        /**
         * Finish our injection of attribute items.
         */
        array_push(
            $this->attributes,
            array()
        );
        /**
         * Lambda functino to manage the output
         * of search/listed items.
         *
         * @param Image $Image the image item.
         *
         * @return void
         */
        self::$returnData = function (&$Image) use ($SizeServer) {
            /**
             * Stores the image on client size.
             */
            $imageSize = self::formatByteSize(
                array_sum(
                    explode(
                        ':',
                        $Image->get('size')
                    )
                )
            );
            /**
             * Stores the items in a nicer name
             */
            /**
             * The id.
             */
            $id = $Image->get('id');
            /**
             * The name.
             */
            $name = $Image->get('name');
            /**
             * The description.
             */
            $description = $Image->get('description');
            /**
             * The storage group name.
             */
            $storageGroup = $Image->getStorageGroup()->get('name');
            /**
             * The os name.
             */
            $os = $Image->getOS()->get('name');
            /**
             * If no os is set/found set to not set.
             */
            if (!$os) {
                $os = _('Not set');
            }
            /**
             * The deployed date.
             */
            $date = $Image->get('deployed');
            /**
             * If the date is valid format in Y-m-d H:i:s
             * and if not set to no valid data.
             */
            if (self::validDate($date)) {
                $date = self::formatTime($date, 'Y-m-d H:i:s');
            } else {
                $date = _('No valid data');
            }
            /**
             * The image type name.
             */
            $imageType = $Image->getImageType()->get('name');
            /**
             * The image partition type name.
             */
            $imagePartitionType = $Image->getImagePartitionType()->get('name');
            /**
             * The path.
             */
            $path = $Image->get('path');
            $serverSize = 0;
            /**
             * If size on server we get our function.
             */
            if ($SizeServer) {
                $serverSize = self::formatByteSize($Image->get('srvsize'));
            }
            /**
             * If the image is not protected show
             * the unlocked symbol and title of not protected
             * otherwise set as is protected.
             */
            if ($Image->get('protected') < 1) {
                $protected = sprintf(
                    '<i class="fa fa-unlock fa-1x icon hand" title="%s"></i>',
                    _('Not protected')
                );
            } else {
                $protected = sprintf(
                    '<i class="fa fa-lock fa-1x icon hand" title="%s"></i>',
                    _('Protected')
                );
            }
            /**
             * If the image format not one, we must
             * be using partclone otherwise partimage.
             */
            switch ($Image->get('format')) {
            case 0:
                $type = _('Partclone Compressed');
                break;
            case 1:
                $type = _('Partimage');
                break;
            case 2:
                $type = _('Partclone Compressed 200MiB split');
                break;
            case 3:
                $type = _('Partclone Uncompressed');
                break;
            case 4:
                $type = _('Partclone Uncompressed 200MiB split');
                break;
            case 5:
                $type = _('ZSTD Compressed');
                break;
            case 6:
                $type = _('ZSTD Compressed 200MiB split');
                break;
            }
            /**
             * Store the data.
             */
            $this->data[] = array(
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'storageGroup' => $storageGroup,
                'os' => $os,
                'deployed' => $date,
                'size' => $imageSize,
                'serv_size' => $serverSize,
                'image_type' => $imageType,
                'image_partition_type' => $imagePartitionType,
                'protected' => $protected,
                'type' => $type
            );
            /**
             * Cleanup.
             */
            unset(
                $id,
                $name,
                $description,
                $storageGroup,
                $os,
                $date,
                $imageSize,
                $serverSize,
                $imageType,
                $imagePartitionType,
                $protected,
                $type,
                $Image
            );
        };
    }
    /**
     * The form to display when adding a new image
     * definition.
     *
     * @return void
     */
    public function add()
    {
        /**
         * Title of initial/general element.
         */
        $this->title = _('New Image');
        /**
         * The table attributes.
         */
        $this->attributes = array(
            array(),
            array(),
        );
        /**
         * The table template.
         */
        $this->templates = array(
            '${field}',
            '${input}',
        );
        /**
         * Set the storage group to pre-select.
         */
        if (isset($_REQUEST['storagegroup'])
            && is_numeric($_REQUEST['storagegroup'])
            && $_REQUEST['storagegroup'] > 0
        ) {
            $sgID = $_REQUEST['storagegroup'];
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
            ->buildSelectBox($_REQUEST['os']);
        $itID = 1;
        if (isset($_REQUEST['imagetype'])
            && is_numeric($_REQUEST['imagetype'])
            && $_REQUEST['imagetype'] > 0
        ) {
            $itID = $_REQUEST['imagetype'];
        }
        $ImageTypes = self::getClass('ImageTypeManager')
            ->buildSelectBox(
                $itID,
                '',
                'id'
            );
        $iptID = 1;
        if (isset($_REQUEST['imagepartitiontype'])
            && is_numeric($_REQUEST['imagepartitiontype'])
            && $_REQUEST['imagepartitiontype'] > 0
        ) {
            $iptID = $_REQUEST['imagepartitiontype'];
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
        if ($_REQUEST['compress']
            && is_numeric($_REQUEST['compress'])
            && $_REQUEST['compress'] > -1
            && $_REQUEST['compress'] < 10
        ) {
            $compression = $_REQUEST['compress'];
        }
        if (!isset($_REQUEST['imagemanage'])) {
            $_REQUEST['imagemanage']
                = self::getSetting('FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT');
        }
        $format = sprintf(
            '<select name="imagemanage">'
            . '<option value="0"%s>%s</option>'
            . '<option value="1"%s>%s</option>'
            . '<option value="2"%s>%s</option>'
            . '<option value="3"%s>%s</option>'
            . '<option value="4"%s>%s</option>'
            . '<option value="5"%s>%s</option>'
            . '<option value="6"%s>%s</option>'
            . '</select>',
            (
                !$_REQUEST['imagemanage'] || $_REQUEST['imagemanage'] == 0 ?
                ' selected' :
                ''
            ),
            _('Partclone Gzip'),
            (
                $_REQUEST['imagemanage'] == 1 ?
                ' selected' :
                ''
            ),
            _('Partimage'),
            (
                $_REQUEST['imagemanage'] == 2 ?
                ' selected' :
                ''
            ),
            _('Partclone Gzip Split 200MiB'),
            (
                $_REQUEST['imagemanage'] == 3 ?
                ' selected' :
                ''
            ),
            _('Partclone Uncompressed'),
            (
                $_REQUEST['imagemanage'] == 4 ?
                ' selected' :
                ''
            ),
            _('Partclone Uncompressed Split 200MiB'),
            (
                $_REQUEST['imagemanage'] == 5 ?
                ' selected' :
                ''
            ),
            _('Partclone Zstd'),
            (
                $_REQUEST['imagemanage'] == 6 ?
                ' selected' :
                ''
            ),
            _('Partclone Zstd Split 200MiB')
        );
        $fields = array(
            _('Image Name') => sprintf(
                '<input type="text" name="name" id="iName" value="%s"/>',
                $_REQUEST['name']
            ),
            _('Image Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $_REQUEST['description']
            ),
            _('Storage Group') => $StorageGroups,
            _('Operating System') => $OSs,
            _('Image Path') => sprintf(
                '%s/&nbsp;<input type="text" name="file" id="iFile" value="%s"/>',
                $StorageNode->get('path'),
                $_REQUEST['file']
            ),
            _('Image Type') => $ImageTypes,
            _('Partition') => $ImagePartitionTypes,
            _('Image Enabled') => '<input type="checkbox" '
            . 'name="isEnabled" value="1" id="isEnabled" checked/>'
            . '<label for="isEnabled"></label>',
            _('Replicate?') => '<input type="checkbox" '
            . 'name="toReplicate" value="1" id="toRep" checked/>'
            . '<label for="toRep"></label>',
            _('Compression') => sprintf(
                '<div class="rangegen pigz"></div>'
                . '<input type="text" readonly="true" name="compress" '
                . 'class="showVal pigz" maxsize="2"'
                . ' value="%s"/>',
                $compression
            ),
            _('Image Manager') => $format,
            '&nbsp;' => sprintf(
                '<input type="submit" name="add" value="%s"/>',
                _('Add')
            )
        );
        printf('<h2>%s</h2>', _('Add new image definition'));
        self::$HookManager
            ->processEvent(
                'IMAGE_FIELDS',
                array(
                    'fields' => &$fields,
                    'Image' => self::getClass('Image')
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'IMAGE_ADD',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        echo '</form>';
    }
    /**
     * Actually submit the creation of the image.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('IMAGE_ADD_POST');
        try {
            $_REQUEST['file'] = trim($_REQUEST['file']);
            $name = trim($_REQUEST['name']);
            if (!$name) {
                throw new Exception(_('An image name is required!'));
            }
            if (self::getClass('ImageManager')->exists($name)) {
                throw new Exception(_('An image already exists with this name!'));
            }
            if (empty($_REQUEST['file'])) {
                throw new Exception(_('An image file name is required!'));
            }
            if ($_REQUEST['file'] == 'postdownloadscripts'
                || $_REQUEST['file'] == 'dev'
            ) {
                throw new Exception(
                    sprintf(
                        '%s, %s.',
                        _('Please choose a different name'),
                        _('this one is reserved for FOG')
                    )
                );
            }
            if (self::getClass('ImageManager')->exists($_REQUEST['file'], 'path')) {
                throw new Exception(
                    sprintf(
                        '%s, %s.',
                        _('Please choose a different path'),
                        _('this one is already in use by another image')
                    )
                );
            }
            if (empty($_REQUEST['storagegroup'])) {
                throw new Exception(_('A Storage Group is required!'));
            }
            if (empty($_REQUEST['os'])) {
                throw new Exception(_('An Operating System is required!'));
            }
            if (empty($_REQUEST['imagetype'])
                || !is_numeric($_REQUEST['imagetype'])
            ) {
                throw new Exception(_('An image type is required!'));
            }
            if (empty($_REQUEST['imagepartitiontype'])
                || !is_numeric($_REQUEST['imagepartitiontype'])
            ) {
                throw new Exception(_('An image partition type is required!'));
            }
            $Image = self::getClass('Image')
                ->set('name', $_REQUEST['name'])
                ->set('description', $_REQUEST['description'])
                ->set('osID', $_REQUEST['os'])
                ->set('path', $_REQUEST['file'])
                ->set('imageTypeID', $_REQUEST['imagetype'])
                ->set('imagePartitionTypeID', $_REQUEST['imagepartitiontype'])
                ->set('compress', $_REQUEST['compress'])
                ->set('isEnabled', (string)intval(isset($_REQUEST['isEnabled'])))
                ->set('format', $_REQUEST['imagemanage'])
                ->set('toReplicate', (string)intval(isset($_REQUEST['toReplicate'])))
                ->addGroup($_REQUEST['storagegroup']);
            if (!$Image->save()) {
                throw new Exception(_('Database update failed'));
            }
            /**
             * During image creation we only allow a single group anyway.
             * This will set it to be the primary master.
             */
            $Image->setPrimaryGroup($_REQUEST['storagegroup']);
            $hook = 'IMAGE_ADD_SUCCESS';
            $msg = _('Image created');
            $url = sprintf(
                '?node=%s&sub=edit&id=%s',
                $this->node,
                $Image->get('id')
            );
        } catch (Exception $e) {
            $hook = 'IMAGE_ADD_FAIL';
            $msg = $e->getMessage();
            $url = $this->formAction;
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Image' => &$Image)
            );
        unset($Image);
        self::setMessage($msg);
        self::redirect($url);
    }
    /**
     * Edit this image
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        echo '<div id="tab-container">';
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $StorageNode = $this
            ->obj
            ->getStorageGroup()
            ->getMasterStorageNode();
        $osID = $this->obj->get('osID');
        if ($_REQUEST['os']
            && is_numeric($_REQUEST['os'])
        ) {
            $osID = $_REQUEST['os'];
        }
        $OSs = self::getClass('OSManager')
            ->buildSelectBox(
                $osID,
                '',
                'id'
            );
        $itID = $this->obj->get('imageTypeID');
        if ($_REQUEST['imagetype']
            && is_numeric($_REQUEST['imagetype'])
        ) {
            $itID = $_REQUEST['imagetype'];
        }
        $ImageTypes = self::getClass('ImageTypeManager')
            ->buildSelectBox(
                $itID,
                '',
                'id'
            );
        $iptID = $this->obj->get('imagePartitionTypeID');
        if ($_REQUEST['imagepartitiontype']
            && is_numeric($_REQUEST['imagepartitiontype'])
        ) {
            $iptID = $_REQUEST['imagepartitiontype'];
        }
        $ImagePartitionTypes = self::getClass('ImagePartitionTypeManager')
            ->buildSelectBox(
                $iptID,
                '',
                'id'
            );
        $compression = $this->obj->get('compress');
        if ($_REQUEST['compress']
            && is_numeric($_REQUEST['compress'])
            && $_REQUEST['compress'] > -1
            && $_REQUEST['compress'] < 10
        ) {
            $compression = $_REQUEST['compress'];
        }
        $format = sprintf(
            '<select name="imagemanage">'
            . '<option value="0"%s>%s</option>'
            . '<option value="1"%s>%s</option>'
            . '<option value="2"%s>%s</option>'
            . '<option value="3"%s>%s</option>'
            . '<option value="4"%s>%s</option>'
            . '<option value="5"%s>%s</option>'
            . '<option value="6"%s>%s</option>'
            . '</select>',
            (
                !$this->obj->get('format') || $this->obj->get('format') == 0 ?
                ' selected' :
                ''
            ),
            _('Partclone Gzip'),
            (
                $this->obj->get('format') == 1 ?
                ' selected' :
                ''
            ),
            _('Partimage'),
            (
                $this->obj->get('format') == 2 ?
                ' selected' :
                ''
            ),
            _('Partclone Gzip Split 200MiB'),
            (
                $this->obj->get('format') == 3 ?
                ' selected' :
                ''
            ),
            _('Partclone Uncompressed'),
            (
                $this->obj->get('format') == 4 ?
                ' selected' :
                ''
            ),
            _('Partclone Uncompressed Split 200MiB'),
            (
                $this->obj->get('format') == 5 ?
                ' selected' :
                ''
            ),
            _('Partclone Zstd'),
            (
                $this->obj->get('format') == 6 ?
                ' selected' :
                ''
            ),
            _('Partclone Zstd Split 200MiB')
        );
        $fields = array(
            _('Image Name') => sprintf(
                '<input type="text" name="name" id="iName" value="%s"/>',
                (
                    isset($_REQUEST['name'])
                    && $_REQUEST['name'] != $this->obj->get('name') ?
                    $_REQUEST['name'] :
                    $this->obj->get('name')
                )
            ),
            _('Image Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                (
                    isset($_REQUEST['description'])
                    && $_REQUEST['description'] != $this->obj->get('description') ?
                    $_REQUEST['description'] :
                    $this->obj->get('description')
                )
            ),
            _('Operating System') => $OSs,
            _('Image Path') => sprintf(
                '%s/&nbsp;<input type="text" name="file" id="iFile" value="%s"/>',
                $StorageNode->get('path'),
                (
                    isset($_REQUEST['file'])
                    && $_REQUEST['file'] != $this->obj->get('path') ?
                    $_REQUEST['file'] :
                    $this->obj->get('path')
                )
            ),
            _('Image Type') => $ImageTypes,
            _('Partition') => $ImagePartitionTypes,
            _('Compression') => sprintf(
                '<div class="rangegen pigz"></div>'
                . '<input type="text" readonly="true" name="compress" '
                . 'class="imagespage showVal pigz" maxsize="2" '
                . 'value="%s"/>',
                $compression
            ),
            _('Protected') => sprintf(
                '<input type="checkbox" name="protected_image" id="'
                . 'protectimage" %s/>'
                . '<label for="protectimage"></label>',
                (
                    $this->obj->get('protected') ?
                    ' checked' :
                    ''
                )
            ),
            _('Image Enabled') => sprintf(
                '<input type="checkbox" name="isEnabled" value="1" id="'
                . 'isEn" %s/><label for="isEn"></label>',
                (
                    $this->obj->get('isEnabled') ?
                    ' checked' :
                    ''
                )
            ),
            _('Replicate?') => sprintf(
                '<input type="checkbox" name="toReplicate" value="1" id="'
                . 'toRep" %s/><label for="toRep"></label>',
                (
                    $this->obj->get('toReplicate') ?
                    ' checked' :
                    ''
                )
            ),
            _('Image Manager') => $format,
            '&nbsp;' => sprintf(
                '<input type="submit" name="update" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager
            ->processEvent(
                'IMAGE_FIELDS',
                array(
                    'fields' => &$fields,
                    'Image' => &$this->obj
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'IMAGE_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<!-- General --><div id="image-gen"><h2>%s</h2><form method="post" '
            . 'action="%s&tab=image-gen">',
            _('Edit image definition'),
            $this->formAction
        );
        $this->render();
        unset($this->data);
        echo '</form></div><!-- Storage Groups --><div id="image-storage">';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxgroup1" '
            . 'class="toggle-checkbox1" id="toggler2"/>'
            . '<label for="toggler2"></label>',
            _('Storage Group Name'),
        );
        $this->templates = array(
            '<input type="checkbox" name="storagegroup[]" '
            . 'value="${storageGroup_id}" class="toggle-group" id="'
            . 'sg-${storageGroup_id}"/><label for="sg-${storageGroup_id}">'
            . '</label>',
            '${storageGroup_name}',
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array(),
        );
        foreach ((array)self::getClass('StorageGroupManager')
            ->find(
                array(
                    'id' => $this->obj->get('storagegroupsnotinme')
                )
            ) as &$Group
        ) {
            $this->data[] = array(
                'storageGroup_id' => $Group->get('id'),
                'storageGroup_name' => $Group->get('name'),
            );
            unset($Group);
        }
        $GroupDataExists = false;
        if (count($this->data) > 0) {
            $GroupDataExists = true;
            self::$HookManager
                ->processEvent(
                    'IMAGE_GROUP_ASSOC',
                    array(
                        'headerData' => &$this->headerData,
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            printf(
                '<p class="c">'
                . '<input type="checkbox" name="groupMeShow" id="groupMeShow"/>'
                . '<label for="groupMeShow">%s&nbsp;&nbsp;</label>',
                _('Check here to see groups not assigned this image')
            );
            printf(
                '<form method="post" action="%s&tab=image-storage">'
                . '<div class="c" id="groupNotInMe"><h2>%s %s</h2>'
                . '<p>%s %s</p>',
                $this->formAction,
                _('Modify group association for'),
                $this->obj->get('name'),
                _('Add image to groups'),
                $this->obj->get('name')
            );
            $this->render();
            echo '</div>';
        }
        unset($this->data);
        if ($GroupDataExists) {
            printf(
                '<br/><p class="c"><input type="submit" value="%s"/></p></form></p>',
                _('Add Image to Group(s)')
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler3"/>'
            . '<label for="toggler3"></label>',
            '',
            _('Storage Group Name'),
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'l filter-false'
            ),
            array(
                'width' => 22,
                'class' => 'l filter-false'
            ),
            array(
                'class' => 'r'
            ),
        );
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" '
            . 'name="storagegroup-rm[]" value="${storageGroup_id}" id="'
            . 'sg1-${storageGroup_id}"/><label for="sg1-${storageGroup_id}">'
            . '</label>',
            sprintf(
                '<input type="radio" class="primary" name="primary" '
                . 'id="group${storageGroup_id}" value="${storageGroup_id}"'
                . '${is_primary}/><label for="group${storageGroup_id}" '
                . 'class="icon icon-hand" title="%s">&nbsp;</label>',
                _('Primary Group Selector')
            ),
            '${storageGroup_name}',
        );
        foreach ((array)self::getClass('StorageGroupManager')
            ->find(
                array(
                    'id' => $this->obj->get('storagegroups')
                )
            ) as &$Group
        ) {
            $this->data[] = array(
                'storageGroup_id' => $Group->get('id'),
                'storageGroup_name' => $Group->get('name'),
                'is_primary' => (
                    $this->obj->getPrimaryGroup($Group->get('id')) ?
                    ' checked' :
                    ''
                ),
            );
            unset($Group);
        }
        self::$HookManager
            ->processEvent(
                'IMAGE_EDIT_GROUP',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<form method="post" action="%s&tab=image-storage">',
            $this->formAction
        );
        $this->render();
        if (count($this->data) > 0) {
            printf(
                '<p class="c"><input name="update" type="submit" value="%s"/>'
                . '&nbsp;<input name="deleteGroup" type="submit" value="%s"/></p>',
                _('Update Primary Group'),
                _('Delete Selected Group associations')
            );
        }
        echo '</form></div></div>';
    }
    /**
     * Submit save/update the image.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'IMAGE_EDIT_POST',
                array(
                    'Image' => &$this->obj
                )
            );
        global $tab;
        try {
            switch ($tab) {
            case 'image-gen':
                $name = trim($_REQUEST['name']);
                if (!$name) {
                    throw new Exception(_('An image name is required!'));
                }
                if ($this->obj->get('name') != $_REQUEST['name']
                    && self::getClass('ImageManager')->exists(
                        $name,
                        $this->obj->get('id')
                    )
                ) {
                    throw new Exception(
                        _('An image already exists with this name!')
                    );
                }
                if ($_REQUEST['file'] == 'postdownloadscripts'
                    || $_REQUEST['file'] == 'dev'
                ) {
                    throw new Exception(
                        sprintf(
                            '%s, %s.',
                            _('Please choose a different name'),
                            _('this one is reserved for FOG')
                        )
                    );
                }
                $exists = self::getClass('ImageManager')
                    ->exists(
                        $_REQUEST['file'],
                        'path'
                    );
                if ($this->obj->get('path') != $_REQUEST['file']
                    && $exists
                ) {
                    throw new Exception(
                        sprintf(
                            '%s, %s.',
                            _('Please choose a different path'),
                            _('this one is already in use by another image')
                        )
                    );
                }
                if (empty($_REQUEST['file'])) {
                    throw new Exception(_('An image file name is required!'));
                }
                if (empty($_REQUEST['os'])) {
                    throw new Exception(_('An Operating System is required!'));
                }
                if (empty($_REQUEST['imagetype']) && $_REQUEST['imagetype'] != 0) {
                    throw new Exception(_('An image type is required!'));
                }
                if (empty($_REQUEST['imagepartitiontype'])
                    && $_REQUEST['imagepartitiontype'] != '0'
                ) {
                    throw new Exception(
                        _('An image partition type is required!')
                    );
                }
                $this
                    ->obj
                    ->set(
                        'name',
                        $_REQUEST['name']
                    )
                    ->set(
                        'description',
                        $_REQUEST['description']
                    )
                    ->set(
                        'osID',
                        $_REQUEST['os']
                    )
                    ->set(
                        'path',
                        $_REQUEST['file']
                    )
                    ->set(
                        'imageTypeID',
                        $_REQUEST['imagetype']
                    )
                    ->set(
                        'imagePartitionTypeID',
                        $_REQUEST['imagepartitiontype']
                    )
                    ->set(
                        'format',
                        (
                            isset($_REQUEST['imagemanage']) ?
                            $_REQUEST['imagemanage'] :
                            $this->obj->get('format')
                        )
                    )
                    ->set(
                        'protected',
                        isset($_REQUEST['protected_image'])
                    )
                    ->set(
                        'compress',
                        $_REQUEST['compress']
                    )
                    ->set(
                        'isEnabled',
                        isset($_REQUEST['isEnabled'])
                    )
                    ->set(
                        'toReplicate',
                        isset($_REQUEST['toReplicate'])
                    );
                break;
            case 'image-storage':
                $this->obj->addGroup($_REQUEST['storagegroup']);
                if (isset($_REQUEST['update'])) {
                    $this->obj->setPrimaryGroup($_REQUEST['primary']);
                } elseif (isset($_REQUEST['deleteGroup'])) {
                    $groupdel = count($_REQUEST['storagegroup-rm']);
                    $ingroups = count($this->obj->get('storagegroups'));
                    if ($groupdel < 1) {
                        throw new Exception(
                            _('No groups selected to be removed')
                        );
                    }
                    if ($ingroups < 2) {
                        throw new Exception(
                            _('You must have at least one group associated')
                        );
                    }
                    $this
                        ->obj
                        ->removeGroup(
                            $_REQUEST['storagegroup-rm']
                        );
                }
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(
                    _('Image update failed')
                );
            }
            $hook = 'IMAGE_EDIT_SUCCESS';
            $msg = _('Image updated');
        } catch (Exception $e) {
            $hook = 'IMAGE_EDIT_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Image' => &$this->obj)
            );
        self::setMessage($msg);
        self::redirect($this->formAction);
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
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Session Name') => sprintf(
                '<input type="text" name="name" id="iName" '
                . 'autocomplete="off" value="%s"/>',
                $_REQUEST['name']
            ),
            _('Client Count') => sprintf(
                '<input type="text" name="count" id="iCount" '
                . 'autocomplete="off" value="%s"/>',
                $_REQUEST['count']
            ),
            sprintf(
                '%s (%s)',
                _('Timeout'),
                _('minutes')
            ) => sprintf(
                '<input type="text" name="timeout" id="iTimeout" '
                . 'autocomplete="off" value="%s"/>',
                $_REQUEST['timeout']
            ),
            _('Select Image') => self::getClass('ImageManager')->buildSelectBox(
                $_REQUEST['image'],
                '',
                'name'
            ),
            '&nbsp;' => sprintf(
                '<input name="start" type="submit" value="%s"/>',
                _('Start')
            ),
        );
        printf(
            '<h2>%s</h2><form method="post" action="%s">',
            _('Start Multicast Session'),
            $this->formAction
        );
        array_walk(
            $fields,
            $this->fieldsToData
        );
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
        $this->render();
        unset($this->data);
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
            array(),
            array('class'=>'r filter-false'),
        );
        $this->templates = array(
            '${mc_name}<br/><small>${image_name}:${os}</small>',
            '${mc_count}',
            '<small>${mc_start}</small>',
            '${mc_percent}',
            '${mc_state}',
            sprintf(
                '<a href="?node=%s&sub=stop&mcid=${mc_id}" '
                . 'title="%s"><i class="fa fa-minus-circle" '
                . 'alt="%s"></i></a>',
                $this->node,
                _('Remove'),
                _('Kill')
            ),
        );
        $find = array(
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array) self::getProgressState()
            )
        );
        foreach ((array)self::getClass('MulticastSessionManager')
            ->find($find) as &$MulticastSession
        ) {
            $Image = $MulticastSession->getImage();
            if (!$Image->isValid()) {
                continue;
            }
            $this->data[] = array(
                'mc_name' => $MulticastSession->get('name'),
                'mc_count' => $MulticastSession->get('sessclients'),
                'image_name' => $Image->get('name'),
                'os' => $Image->getOS()->get('name'),
                'mc_start' => self::formatTime(
                    $MulticastSession->get('starttime'),
                    'Y-m-d H:i:s'
                ),
                'mc_percent' => $MulticastSession->get('percent'),
                'mc_state' => $MulticastSession->getTaskState()->get('name'),
                'mc_id' => $MulticastSession->get('id'),
            );
            unset($MulticastSession);
        }
        self::$HookManager
            ->processEvent(
                'IMAGE_MULTICAST_START',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        echo '</form>';
    }
    /**
     * Submit the mutlicast form.
     *
     * @return void
     */
    public function multicastPost()
    {
        try {
            $name = trim($_REQUEST['name']);
            if (!$name) {
                throw new Exception(_('Please input a session name'));
            }
            if (!$_REQUEST['image']) {
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
            if (is_numeric($_REQUEST['timeout']) && $_REQUEST['timeout'] > 0) {
                self::setSetting('FOG_UDPCAST_MAXWAIT', $_REQUEST['timeout']);
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
            $Image = self::getClass('Image', $_REQUEST['image']);
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
                ->set('sessclients', $_REQUEST['count'])
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
        if ($_REQUEST['mcid'] < 1) {
            self::redirect(sprintf('?node=%s&sub=multicast', $this->node));
        }
        self::getClass('MulticastSessionManager')->cancel($_REQUEST['mcid']);
        self::setMessage(
            sprintf(
                '%s%s',
                _('Cancelled task'),
                (
                    count($_REQUEST['mcid']) !== 1 ?
                    's' :
                    ''
                )
            )
        );
        self::redirect(sprintf('?node=%s&sub=multicast', $this->node));
    }
}
