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
            _('Protected'),
            _('Enabled'),
            _('Image Name'),
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
            . '</label>' => '<div class="col-sm-8">'
            //. '<div class="rangegen pigz"></div>'
            . '<input type="text" value="'
            . $compression
            . '" class="slider form-control" '
            . 'data-slider-min="0" data-slider-max="22" data-slider-step="1" '
            . 'data-slider-value="[0,22]" data-slider-orientation="horizontal" '
            . 'data-slider-selection="before" data-slider-tooltip="show" '
            . 'data-slider-id="blue">'
            . '</div>'
            . '<div class="col-sm-2">'
            . '<input type="text" name="compress" class="form-control '
            . 'showVal pigz" maxsize="2" value="'
            . $compression
            . '" id="pigzcomp" readonly/>'
            . '</div>',
            '<label class="col-sm-2 control-label" for="imagemanage">'
            . _('Image Manager')
            . '</label>' => $format
        ];
        self::$HookManager
            ->processEvent(
                'IMAGE_ADD_FIELDS',
                array(
                    'fields' => &$fields,
                    'Image' => self::getClass('Image')
                )
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="image-create">';
        echo '<form id="image-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Image General -->';
        echo $rendered;
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
        self::$HookManager->processEvent('IMAGE_ADD_POST');
        $file = trim(
            filter_input(INPUT_POST, 'file')
        );
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $desc = trim(
            filter_input(INPUT_POST, 'description')
        );
        $storagegroup = (int)filter_input(INPUT_POST, 'storagegroup');
        $os = (int)filter_input(INPUT_POST, 'os');
        $imagetype = (int)filter_input(INPUT_POST, 'imagetype');
        $imagepartitiontype = (int)filter_input(INPUT_POST, 'imagepartitiontype');
        $imagemanage = (int)filter_input(INPUT_POST, 'imagemanage');
        $compress = (int)filter_input(INPUT_POST, 'compress');
        $isenabled = (int)isset($_POST['isEnabled']);
        $torep = (int)isset($_POST['toReplicate']);
        try {
            if (self::getClass('ImageManager')->exists($name)) {
                throw new Exception(_('An image already exists with this name!'));
            }
            if ($file == 'postdownloadscripts'
                || $file == 'dev'
            ) {
                throw new Exception(
                    sprintf(
                        '%s, %s.',
                        _('Please choose a different name'),
                        _('this one is reserved for FOG')
                    )
                );
            }
            if (self::getClass('ImageManager')->exists($file, '', 'path')) {
                throw new Exception(
                    sprintf(
                        '%s, %s.',
                        _('Please choose a different path'),
                        _('this one is already in use by another image')
                    )
                );
            }
            $Image = self::getClass('Image')
                ->set('name', $name)
                ->set('description', $desc)
                ->set('osID', $os)
                ->set('path', $file)
                ->set('imageTypeID', $imagetype)
                ->set('imagePartitionTypeID', $imagepartitiontype)
                ->set('compress', $compress)
                ->set('isEnabled', $isenabled)
                ->set('format', $imagemanage)
                ->set('toReplicate', $torep)
                ->addGroup($storagegroup);
            if (!$Image->save()) {
                throw new Exception(_('Add image failed!'));
            }
            /**
             * During image creation we only allow a single group anyway.
             * This will set it to be the primary master.
             */
            $Image->setPrimaryGroup($storagegroup);
            $hook = 'IMAGE_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Image added!'),
                    'title' => _('Image Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'IMAGE_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Image Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Image' => &$Image)
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
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $StorageNode = $this
            ->obj
            ->getStorageGroup()
            ->getMasterStorageNode();
        $osID = (int)filter_input(INPUT_POST, 'os');
        if ($osID < 1) {
            $osID = $this->obj->get('osID');
        }
        $OSs = self::getClass('OSManager')
            ->buildSelectBox(
                $osID,
                '',
                'id'
            );
        $itID = (int)filter_input(INPUT_POST, 'imagetype');
        if ($itID < 1) {
            $itID = $this->obj->get('imageTypeID');
        }
        $ImageTypes = self::getClass('ImageTypeManager')
            ->buildSelectBox(
                $itID,
                '',
                'id'
            );
        $iptID = (
            filter_input(INPUT_POST, 'imagepartitiontype') ?: $this->obj->get(
                'imagePartitionTypeID'
            )
        );
        $ImagePartitionTypes = self::getClass('ImagePartitionTypeManager')
            ->buildSelectBox(
                $iptID,
                '',
                'id'
            );
        $compression = (
            filter_input(INPUT_POST, 'compress') ?: $this->obj->get('compress')
        );
        $imagemanage = (
            filter_input(INPUT_POST, 'imagemanage') ?: $this->obj->get('format')
        );
        $name = (
            filter_input(INPUT_POST, 'name') ?: $this->obj->get('name')
        );
        $desc = (
            filter_input(INPUT_POST, 'description') ?: $this->obj->get('description')
        );
        $isen = (int)isset($_POST['isEnabled']);
        if (!$isen) {
            $isen = $this->obj->get('isEnabled');
        }
        if ($isen) {
            $isen = ' checked';
        } else {
            $isen = '';
        }
        $torep = (int)isset($_POST['toReplicate']);
        if (!$torep) {
            $torep = $this->obj->get('toReplicate');
        }
        if ($torep) {
            $torep = ' checked';
        } else {
            $torep = '';
        }
        $toprot = (int)isset($_POST['protected_image']);
        if (!$toprot) {
            $toprot = $this->obj->get('protected');
        }
        if ($toprot) {
            $toprot = ' checked';
        } else {
            $toprot = '';
        }
        $file = trim(
            filter_input(INPUT_POST, 'file')
        );
        if (!$file) {
            $file = $this->obj->get('path');
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
        $fields = array(
            '<label for="iName">'
            . _('Image Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control imagename-input" type="text" '
            . 'name="name" id="iName" '
            . 'value="'
            . $name
            . '"/>'
            . '</div>',
            '<label for="description">'
            . _('Image Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" class="form-control imagedesc-input" '
            . 'id="description">'
            . $desc
            . '</textarea>',
            '<label for="os">'
            . _('Operating System')
            . '</label>' => $OSs,
            '<label for="iFile">'
            . _('Image Path')
            . '</label>' => '<div class="input-group">'
            . '<span class="input-group-addon">'
            . $StorageNode->get('path')
            . '/'
            . '</span>'
            . '<input type="text" class="form-control imagefile-input" '
            . 'name="file" id="iFile" '
            . 'value="'
            . $file
            . '" />',
            '<label for="imagetype">'
            . _('Image Type')
            . '</label>' => $ImageTypes,
            '<label for="imagepartitiontype">'
            . _('Partition')
            . '</label>' => $ImagePartitionTypes,
            '<label for="protectimage">'
            . _('Protected')
            . '</label>' => '<input type="checkbox" '
            . 'name="protected_image" id="protectimage"'
            . $toprot
            . '/>',
            '<label for="isEnabled">'
            . _('Image Enabled')
            . '</label>' => '<input type="checkbox" '
            . 'name="isEnabled" id="isEnabled"'
            . $isen
            . '/>',
            '<label for="toRep">'
            . _('Replicate?')
            . '</label>' => '<input type="checkbox" '
            . 'name="toReplicate" id="toRep" '
            . $torep
            . '/>',
            '<label for="pigzcomp">'
            . _('Compression')
            . '</label>' => '<div class="col-xs-8">'
            . '<div class="rangegen pigz"></div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="compress" class="form-control '
            . 'showVal pigz" maxsize="2" value="'
            . $compression
            . '" id="pigzcomp" readonly/>'
            . '</div>'
            . '</div>',
            '<label for="imagemanage">'
            . _('Image Manager')
            . '</label>' => $format,
            '<label for="updategen">'
            . _('Make Changes?')
            . '</label>' => '<button class="btn btn-info btn-block" type="submit" '
            . 'id="updategen" name="update">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'IMAGE_FIELDS',
                array(
                    'fields' => &$fields,
                    'Image' => &$this->obj
                )
            );
        $rendered = self::formFields($fields);
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
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="image-gen">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Image General');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=image-gen">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
    }
    /**
     * Display image storage groups.
     *
     * @return void
     */
    public function imageStoragegroups()
    {
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
        $this->headerData = array(
            '<label for="toggler2">'
            . '<input type="checkbox" name="toggle-checkboxgroup1" '
            . 'class="toggle-checkbox1" id="toggler2"/>'
            . '</label>',
            _('Storage Group Name')
        );
        $this->templates = array(
            '<label for="sg-${storageGroup_id}">'
            . '<input type="checkbox" name="storagegroup[]" class='
            . '"toggle-group" id="sg-${storageGroup_id}" '
            . 'value="${storageGroup_id}"/>'
            . '</label>',
            '<a href="?node=storage&editStorageGroup&id=${storageGroup_id}">'
            . '${storageGroup_name}'
            . '</a>'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
        );
        Route::listem('storagegroup');
        $StorageGroups = json_decode(
            Route::getData()
        );
        $StorageGroups = $StorageGroups->storagegroups;
        foreach ((array)$StorageGroups as &$StorageGroup) {
            $groupinme = in_array(
                $StorageGroup->id,
                $this->obj->get('storagegroups')
            );
            if ($groupinme) {
                continue;
            }
            $this->data[] = array(
                'storageGroup_id' => $StorageGroup->id,
                'storageGroup_name' => $StorageGroup->name,
            );
            unset($StorageGroup);
        }
        self::$HookManager->processEvent(
            'IMAGE_ADD_STORAGE_GROUP',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        echo '<!-- Storage Groups -->';
        echo '<div class="tab-pane fade" id="image-storage">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Image Storage Groups');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=image-storage">';
        if (count($this->data)) {
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="groupMeShow">';
            echo '<input type="checkbox" name="groupMeShow" '
                . 'id="groupMeShow"/>';
            echo _('Check here to see what storage groups can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially groupNotInMe panel panel-info" '
                . 'id="groupNotInMe">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add Storage Groups');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updategroups" class="control-label col-xs-4">';
            echo _('Add selected storage groups');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="updategroups" class='
                . '"btn btn-info btn-block" id="updategroups">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->data,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="toggler3">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler3"/>'
            . '</label>',
            '',
            _('Storage Group Name')
        );
        $this->templates = array(
            '<label for="sg1-${storageGroup_id}">'
            . '<input type="checkbox" name="storagegroup-rm[]" class='
            . '"toggle-group" id="sg1-${storageGroup_id}" '
            . 'value="${storageGroup_id}"/>'
            . '</label>',
            '<div class="radio">'
            . '<input type="radio" class="default" '
            . 'name="primary" id="group${storageGroup_id}" '
            . 'value="${storageGroup_id}" ${is_primary}/>'
            . '<label for="group${storageGroup_id}">'
            . '</label>'
            . '</div>',
            '<a href="?node=storage&editStorageGroup&id=${storageGroup_id}">'
            . '${storageGroup_name}'
            . '</a>'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
        );
        foreach ((array)$StorageGroups as &$StorageGroup) {
            $groupinme = in_array(
                $StorageGroup->id,
                $this->obj->get('storagegroups')
            );
            if (!$groupinme) {
                continue;
            }
            $this->data[] = array(
                'storageGroup_id' => $StorageGroup->id,
                'storageGroup_name' => $StorageGroup->name,
                'is_primary' => (
                    $this->obj->getPrimaryGroup($StorageGroup->id) ?
                    ' checked' :
                    ''
                )
            );
            unset($StorageGroup);
        }
        if (count($this->data) > 0) {
            self::$HookManager->processEvent(
                'IMAGE_EDIT_STORAGE_GROUP',
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
            echo _('Update/Remove Storage Groups');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="primarysel" class="control-label col-xs-4">';
            echo _('Update primary group');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="primarysel" class='
                . '"btn btn-info btn-block" id="primarysel">'
                . _('Update')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label for="groupdel" class="control-label col-xs-4">';
            echo _('Remove selected groups');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="groupdel" class='
                . '"btn btn-danger btn-block" id="groupdel">'
                . _('Remove')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
    }
    /**
     * Edit this image
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->imageGeneral();
        $this->imageStoragegroups();
        echo '</div>';
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
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $file = trim(
            filter_input(INPUT_POST, 'file')
        );
        $desc = trim(
            filter_input(INPUT_POST, 'description')
        );
        $os = (int)filter_input(INPUT_POST, 'os');
        $imagetype = (int)filter_input(INPUT_POST, 'imagetype');
        $imagepartitiontype = (int)filter_input(INPUT_POST, 'imagepartitiontype');
        $imagemanage = (int)filter_input(INPUT_POST, 'imagemanage');
        $protected = (int)isset($_POST['protected_image']);
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $compress = (int)filter_input(INPUT_POST, 'compress');
        $items = filter_input_array(
            INPUT_POST,
            array(
                'storagegroup' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                ),
                'storagegroup-rm' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $storagegroup = $items['storagegroup'];
        $storagegrouprm = $items['storagegroup-rm'];
        $primary = (int)filter_input(
            INPUT_POST,
            'primary'
        );
        try {
            switch ($tab) {
            case 'image-gen':
                if ($this->obj->get('name') != $name
                    && self::getClass('ImageManager')->exists(
                        $name,
                        $this->obj->get('id')
                    )
                ) {
                    throw new Exception(
                        _('An image already exists with this name!')
                    );
                }
                if ($file == 'postdownloadscripts'
                    || $file == 'dev'
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
                        $file,
                        '',
                        'path'
                    );
                if ($this->obj->get('path') != $file
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
                $this
                    ->obj
                    ->set('name', $name)
                    ->set('description', $desc)
                    ->set('osID', $os)
                    ->set('path', $file)
                    ->set('imageTypeID', $imagetype)
                    ->set('imagePartitionTypeID', $imagepartitiontype)
                    ->set('format', $imagemanage)
                    ->set('protected', $protected)
                    ->set('compress', $compress)
                    ->set('isEnabled', $isEnabled)
                    ->set('toReplicate', $toReplicate);
                break;
            case 'image-storage':
                if (isset($_POST['updategroups'])) {
                    $this->obj->addGroup($storagegroup);
                } elseif (isset($_POST['primarysel'])) {
                    $this->obj->setPrimaryGroup($primary);
                } elseif (isset($_POST['groupdel'])) {
                    $groupdel = count($storagegrouprm);
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
                            $storagegrouprm
                        );
                }
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(
                    _('Image update failed!')
                );
            }
            $hook = 'IMAGE_UPDATE_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Image updated!'),
                    'title' => _('Image Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'IMAGE_UPDATE_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Image Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Image' => &$this->obj)
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
}
