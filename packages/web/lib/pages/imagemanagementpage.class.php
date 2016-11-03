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
        $this->name = 'Image Management';
        parent::__construct($this->name);
        $this->menu['multicast'] = sprintf(
            '%s %s',
            self::$foglang['Multicast'],
            self::$foglang['Image']
        );
        $SizeServer = $_SESSION['FOG_FTP_IMAGE_SIZE'];
        global $id;
        global $sub;
        if ($id) {
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
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            sprintf(
                '%s<br/>'
                . '<small>%s: %s</small><br/>'
                . '<small>%s</small><br/>'
                . '<small>%s</small>',
                _('Image Name'),
                _('Storage Group'),
                _('OS'),
                _('Image Type'),
                _('Partition')
            ),
            _('Image Size: ON CLIENT'),
        );
        $SizeServer = $_SESSION['FOG_FTP_IMAGE_SIZE'];
        if ($SizeServer) {
            array_push(
                $this->headerData,
                _('Image Size: ON SERVER')
            );
        }
        array_push(
            $this->headerData,
            _('Format'),
            _('Captured')
        );
        $this->templates = array(
            '${protected}',
            '<input type="checkbox" name="image[]" '
            . 'value="${id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${id}" title="%s: '
                . '${name} Last captured: ${deployed}">${name} - '
                . '${id}</a><br/><small>${storageGroup}: ${os}'
                . '</small><br/><small>${image_type}</small>'
                . '<br/><small>${image_partition_type}</small>',
                $this->node,
                _('Edit')
            ),
            '${size}',
        );
        if ($SizeServer) {
            array_push(
                $this->templates,
                '${serv_size}'
            );
        }
        array_push(
            $this->templates,
            '${type}',
            '${deployed}'
        );
        $this->attributes = array(
            array(
                'width' => 5,
                'class' => 'l filter-false'
            ),
            array(
                'width' => 16,
                'class' => 'l filter-false'
            ),
            array(
                'width' => 50,
                'class' => 'l'
            ),
            array(
                'width' => 50,
                'class' => 'c'
            ),
        );
        if ($SizeServer) {
            array_push(
                $this->attributes,
                array(
                    'width' => 50,
                    'class' => 'c'
                )
            );
        }
        array_push(
            $this->attributes,
            array(
                'width' => 50,
                'class' => 'c'
            ),
            array(
                'width' => 50,
                'class' => 'c'
            )
        );
        $servSize = function (&$path, &$StorageNode) {
            return false;
        };
        if ($SizeServer) {
            $servSize = function (&$path, &$StorageNode) {
                return $this->getFTPByteSize(
                    $StorageNode,
                    sprintf(
                        '%s/%s',
                        $StorageNode->get('ftppath'),
                        $path
                    )
                );
            };
        }
        self::$returnData = function (&$Image) use (&$servSize) {
            if (!$Image->isValid()) {
                return;
            }
            $imageSize = $this
                ->formatByteSize(
                    array_sum(
                        explode(
                            ':',
                            $Image->get('size')
                        )
                    )
                );
            $path = $Image->get('path');
            if ($SizeServer) {
                $StorageNode = $Image
                    ->getStorageGroup()
                    ->getMasterStorageNode();
                $serverSize = $servSize(
                    $path,
                    $StorageNode
                );
            }
            $this->data[] = array(
                'id' => $Image->get('id'),
                'name' => $Image->get('name'),
                'description' => $Image->get('description'),
                'storageGroup' => $Image->getStorageGroup()->get('name'),
                'os' => (
                    $Image->getOS()->isValid() ?
                    $Image->getOS()->get('name') :
                    _('Not set')
                ),
                'deployed' => (
                    $this->validDate($Image->get('deployed')) ?
                    $this->formatTime($Image->get('deployed'), 'Y-m-d H:i:s') :
                    _('No Data')
                ),
                'size' => $imageSize,
                'serv_size' => $serverSize,
                'image_type'=>$Image->getImageType()->get('name'),
                'image_partition_type' => $Image->getImagePartitionType()->get(
                    'name'
                ),
                'protected' => sprintf(
                    '<i class="fa fa-%slock fa-1x icon hand" title="%s"></i>',
                    (
                        !$Image->get('protected') ?
                        'un' :
                        ''
                    ),
                    (
                        !$Image->get('protected') ?
                        _('Not Protected') :
                        _('Protected')
                    )
                ),
                'type' => (
                    $Image->get('format') ?
                    _('Partimage') :
                    _('Partclone')
                ),
            );
            unset(
                $Image,
                $imageSize,
                $serverSize
            );
        };
    }
    /**
     * The base element displayed when first going to this page.
     *
     * @return void
     */
    public function index()
    {
        $this->title = _('All Images');
        if ($_SESSION['DataReturn'] > 0
            && $_SESSION['ImageCount'] > $_SESSION['DataReturn']
            && $_REQUEST['sub'] != 'list'
        ) {
            $this->redirect(
                sprintf(
                    '?node=%s&sub=search',
                    $this->node
                )
            );
        }
        $this->data = array();
        $Images = self::getClass('ImageManager')->find();
        array_walk($Images, self::$returnData);
        self::$HookManager
            ->processEvent(
                'IMAGE_DATA',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
    }
    /**
     * How to return searched items.
     *
     * @return void
     */
    public function searchPost()
    {
        $this->data = array();
        $Images = self::getClass('ImageManager')->search('', true);
        array_walk($Images, self::$returnData);
        self::$HookManager
            ->processEvent(
                'IMAGE_DATA',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
    }
    /**
     * The form to display when adding a new image
     * definition.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Image');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if ($_REQUEST['storagegroup']
            && is_numeric($_REQUEST['storagegroup'])
        ) {
            $sgID = $_REQUEST['storagegroup'];
        } else {
            $sgID = @min(self::getSubObjectIDs('StorageGroup'));
        }
        $StorageGroup = new StorageGroup($sgID);
        $StorageNode = $StorageGroup->getMasterStorageNode();
        if (!(($StorageNode instanceof StorageNode)
            && $StorageNode)
        ) {
            die(_('There is no active/enabled Storage nodes on this server.'));
        }
        $StorageGroups = self::getClass('StorageGroupManager')
            ->buildSelectBox(
                $sgID,
                '',
                'id'
            );
        $OSs = self::getClass('OSManager')
            ->buildSelectBox($_REQUEST['os']);
        $itID = 1;
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
        $iptID = 1;
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
        $compression = self::getSetting('FOG_PIGZ_COMP');
        if ($_REQUEST['compress']
            && is_numeric($_REQUEST['compress'])
            && $_REQUEST['compress'] > -1
            && $_REQUEST['compress'] < 10
        ) {
            $compression = $_REQUEST['compress'];
        }
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
            . 'name="isEnabled" value="1"checked/>',
            _('Replicate?') => '<input type="checkbox" '
            . 'name="toReplicate" value="1" checked/>',
            _('Compression') => sprintf(
                '<div id="pigz" style="width: 200px; top: 15px;"></div>'
                . '<input type="text" readonly="true" name="compress" '
                . 'id="showVal" maxsize="1" style="width: 10px; '
                . 'top: -5px; left: 225px; position: relative;" value="%s"/>',
                $compression
            ),
            '&nbsp;' => sprintf(
                '<input type="submit" name="add" value="%s"/>',
                _('Add')
            ),
        );
        printf('<h2>%s</h2>', _('Add new image definition'));
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
            self::$HookManager
                ->processEvent(
                    'IMAGE_ADD_SUCCESS',
                    array(
                        'Image' => &$Image
                    )
                );
            $this->setMessage(_('Image created'));
            $this->redirect(
                sprintf(
                    '?node=%s&sub=edit&id=%s',
                    $_REQUEST['node'],
                    $Image->get('id')
                )
            );
        } catch (Exception $e) {
            self::$HookManager
                ->processEvent(
                    'IMAGE_ADD_FAIL',
                    array(
                        'Image' => &$Image
                    )
                );
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    /**
     * Edit this image
     *
     * @return voi
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
        if ($_SESSION['FOG_FORMAT_FLAG_IN_GUI']) {
            $format = sprintf(
                '<select name="imagemanage"><option value="1"%s>%s</option>'
                . '<option value="0"%s>%s</option></select>',
                (
                    $this->obj->get('format') ?
                    ' selected' :
                    ''
                ),
                _('Partimage'),
                (
                    !$this->obj->get('format') ?
                    ' selected' :
                    ''
                ),
                _('Partclone')
            );
        }
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
                '<div id="pigz" style="width: 200px; top: 15px;"></div>'
                . '<input type="text" readonly="true" name="compress" '
                . 'id="showVal" maxsize="1" style="width: 10px; top: '
                . '-5px; left: 225px; position: relative;" value="%s"/>',
                $compression
            ),
            _('Protected') => sprintf(
                '<input type="checkbox" name="protected_image"%s/>',
                (
                    $this->obj->get('protected') ?
                    ' checked' :
                    ''
                )
            ),
            _('Image Enabled') => sprintf(
                '<input type="checkbox" name="isEnabled" value="1"%s/>',
                (
                    $this->obj->get('isEnabled') ?
                    ' checked' :
                    ''
                )
            ),
            _('Replicate?') => sprintf(
                '<input type="checkbox" name="toReplicate" value="1"%s/>',
                (
                    $this->obj->get('toReplicate') ?
                    ' checked' :
                    ''
                )
            ),
            (
                $_SESSION['FOG_FORMAT_FLAG_IN_GUI'] ?
                _('Image Manager') :
                ''
            ) => (
                $_SESSION['FOG_FORMAT_FLAG_IN_GUI'] ?
                $format :
                ''
            ),
            '&nbsp;' => sprintf(
                '<input type="submit" name="update" value="%s"/>',
                _('Update')
            ),
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
            . 'class="toggle-checkbox1" />',
            _('Storage Group Name'),
        );
        $this->templates = array(
            '<input type="checkbox" name="storagegroup[]" '
            . 'value="${storageGroup_id}" class="toggle-group"/>',
            '${storageGroup_name}',
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array(),
        );
        $StorageGroups = self::getClass('StorageGroupManager')
            ->find(
                array(
                    'id' => $this->obj->get('storagegroupsnotinme')
                )
            );
        foreach ((array)$StorageGroups as &$Group) {
            if (!$Group->isValid()) {
                continue;
            }
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
                '<p class="c"><label for="groupMeShow">%s&nbsp;&nbsp;'
                . '<input type="checkbox" name="groupMeShow" id="groupMeShow"/>'
                . '</label>',
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
            . 'class="toggle-checkboxAction"/>',
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
            . 'name="storagegroup-rm[]" value="${storageGroup_id}"/>',
            sprintf(
                '<input type="radio" class="primary" name="primary" '
                . 'id="group${storageGroup_id}" value="${storageGroup_id}"'
                . '${is_primary}/><label for="group${storageGroup_id}" '
                . 'class="icon icon-hand" title="%s">&nbsp;</label>',
                _('Primary Group Selector')
            ),
            '${storageGroup_name}',
        );
        $StorageGroups = self::getClass('StorageGroupManager')
            ->find(
                array(
                    'id' => $this->obj->get('storagegroups')
                )
            );
        foreach ((array)$StorageGroups as &$Group) {
            if (!$Group->isValid()) {
                continue;
            }
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
        try {
            switch ($_REQUEST['tab']) {
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
                }
                if (isset($_REQUEST['deleteGroup'])) {
                    if (count($this->obj->get('storagegroups')) < 2) {
                        throw new Exception(
                            _('Image must be assigned to one Storage Group')
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
                throw new Exception(_('Database update failed'));
            }
            self::$HookManager
                ->processEvent(
                    'IMAGE_UPDATE_SUCCESS',
                    array(
                        'Image' => &$this->obj
                    )
                );
            $this->setMessage(_('Image updated'));
        } catch (Exception $e) {
            self::$HookManager
                ->processEvent(
                    'IMAGE_UPDATE_FAIL',
                    array(
                        'Image' => &$this->obj
                    )
                );
            $this->setMessage($e->getMessage());
        }
        $this->redirect($this->formAction);
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
        $MCSessions = self::getClass('MulticastSessionsManager')
            ->find(
                array(
                    'stateID' => array_merge(
                        (array)$this->getQueuedStates(),
                        (array)$this->getProgressState()
                    )
                )
            );
        foreach ((array)$MCSessions as &$MulticastSession) {
            if (!$MulticastSession->isValid()) {
                continue;
            }
            $Image = $MulticastSession->getImage();
            if (!$Image->isValid()) {
                continue;
            }
            $this->data[] = array(
                'mc_name' => $MulticastSession->get('name'),
                'mc_count' => $MulticastSession->get('sessclients'),
                'image_name' => $Image->get('name'),
                'os' => $Image->getOS()->get('name'),
                'mc_start' => $this->formatTime(
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
            if (self::getClass('MulticastSessionsManager')->exists($name)) {
                throw new Exception(_('Session with that name already exists'));
            }
            if (self::getClass('HostManager')->exists($name)) {
                throw new Exception(
                    _('Session name cannot be the same as an existing hostname')
                );
            }
            if (is_numeric($_REQUEST['timeout']) && $_REQUEST['timeout'] > 0) {
                $this->setSetting('FOG_UDPCAST_MAXWAIT', $_REQUEST['timeout']);
            }
            $countmc = self::getClass('MulticastSessionsManager')
                ->count(
                    array(
                        'stateID' => array_merge(
                            (array)$this->getQueuedStates(),
                            (array)$this->getProgressState()
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
            $MulticastSession = self::getClass('MulticastSessions')
                ->set('name', $name)
                ->set('port', self::getSetting('FOG_UDPCAST_STARTINGPORT'))
                ->set('image', $Image->get('id'))
                ->set('stateID', 0)
                ->set('sessclients', $_REQUEST['count'])
                ->set('isDD', $Image->get('imageTypeID'))
                ->set('starttime', $this->formatTime('now', 'Y-m-d H:i:s'))
                ->set('interface', $StorageNode->get('interface'))
                ->set('logpath', $Image->get('path'))
                ->set('storagegroupID', $StorageNode->get('id'))
                ->set('clients', -2);
            if (!$MulticastSession->save()) {
                $this->setMessage(_('Failed to create Session'));
            }
            $randomnumber = mt_rand(24576, 32766)*2;
            while ($randomnumber == $MulticastSession->get('port')) {
                $randomnumber = mt_rand(24576, 32766)*2;
            }
            $this->setSetting('FOG_UDPCAST_STARTINGPORT', $randomnumber);
            $this->setMessage(
                sprintf(
                    '%s<br/>%s %s %s',
                    _('Multicast session created'),
                    $MulticastSession->get('name'),
                    _('has been started on port'),
                    $MulticastSession->get('port')
                )
            );
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
        }
        $this->redirect(
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
            $this->redirect(sprintf('?node=%s&sub=multicast', $this->node));
        }
        self::getClass('MulticastSessionsManager')->cancel($_REQUEST['mcid']);
        $this->setMessage(
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
        $this->redirect(sprintf('?node=%s&sub=multicast', $this->node));
    }
}
