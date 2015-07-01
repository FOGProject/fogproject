<?php
class ImageManagementPage extends FOGPage {
    // Base variables
    public $node = 'image';
    // __construct
    /** __construct($name = '')
     * The basic constructor template for
     * index and search functions.
     */
    public function __construct($name = '') {
        $this->name = 'Image Management';
        // Call parent constructor
        parent::__construct($this->name);
        $this->menu[multicast] = $this->foglang[Multicast].' '.$this->foglang[Image];
        $SizeServer = $_SESSION['FOG_FTP_IMAGE_SIZE'];
        if ($_REQUEST['id']) {
            $this->obj = $this->getClass('Image',$_REQUEST['id']);
            $this->subMenu = array(
                "$this->linkformat#image-gen" => $this->foglang[General],
                "$this->linkformat#image-storage" => $this->foglang[Storage].' '.$this->foglang[Group],
                $this->membership => $this->foglang['Membership'],
                $this->delformat => $this->foglang['Delete'],
            );
            $this->notes = array(
                $this->foglang[Images] => $this->obj->get('name'),
                $this->foglang[LastUploaded] => $this->obj->get('deployed'),
                $this->foglang[DeployMethod] => $this->obj->get('format') ? 'Partimage' : 'Partclone',
                $this->foglang[ImageType] => $this->obj->getImageType() ? $this->obj->getImageType() : $this->foglang[NoAvail],
                _('Primary Storage Group') => $this->obj->getStorageGroup()->get('name'),
            );
        }
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu' => &$this->menu,'submenu' => &$this->subMenu,'id' => &$this->id,'notes' => &$this->notes));
        // Header row
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Image Name') .'<br /><small>'._('Storage Group').': '._('O/S').'</small><br /><small>'._('Image Type').'</small><br /><small>'._('Partition').'</small>',
            _('Image Size: ON CLIENT'),
        );
        $SizeServer ? array_push($this->headerData,_('Image Size: ON SERVER')) : null;
        array_push(
            $this->headerData,
            _('Format'),
            _('Uploaded'),
            _('Edit/Remove')
        );
        // Row templates
        $this->templates = array(
            '${protected}',
            '<input type="checkbox" name="image[]" value="${id}" class="toggle-action" />',
            '<a href="?node='.$this->node.'&sub=edit&'.$this->id.'=${id}" title="'._('Edit').': ${name} Last uploaded: ${deployed}">${name} - ${id}</a><br /><small>${storageGroup}:${os}</small><br /><small>${image_type}</small><br /><small>${image_partition_type}</small>',
            '${size}',
        );
        $SizeServer ? array_push($this->templates,'${serv_size}') : null;
        array_push(
            $this->templates,
            '${type}',
            '${deployed}',
            '<a href="?node='.$this->node.'&sub=edit&'.$this->id.'=${id}" title="'._('Edit').'"><i class="fa fa-pencil"></i></a> <a href="?node='.$this->node.'&sub=delete&'.$this->id.'=${id}" title="'._('Delete').'"><i class="fa fa-minus-circle"></i></a>'
        );
        // Row attributes
        $this->attributes = array(
            array('width' => 5, 'class' => 'c'),
            array('width' => 16, 'class' => 'c'),
            array('width' => 50, 'class' => 'l'),
            array('width' => 50, 'class' => 'c'),
        );
        $SizeServer ? array_push($this->attributes,array('width' => 50, 'class' => 'c')) : null;
        array_push(
            $this->attributes,
            array('width' => 50, 'class' => 'c'),
            array('width' => 50, 'class' => 'c'),
            array('width' => 50, 'class' => 'c')
        );
    }
    // Pages
    /** index()
     * The default page view for Image Management.  If search is default view, this is not displayed.
     */
    public function index() {
        // Set title
        $this->title = _('All Images');
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['ImageCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->FOGCore->redirect(sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node));
        // Find data
        $Images = $this->getClass('ImageManager')->find();
        $SizeServer = $_SESSION['FOG_FTP_IMAGE_SIZE'];
        // Row data
        foreach ($Images AS &$Image) {
            $imageSize = $this->FOGCore->formatByteSize((double)$Image->get('size'));
            $StorageNode = $Image->getStorageGroup()->getMasterStorageNode();
            if ($StorageNode && $StorageNode->isValid() && $SizeServer) $servSize = $this->FOGCore->getFTPByteSize($StorageNode,($StorageNode->isValid() ? $StorageNode->get('path').'/'.$Image->get('path') : null));
            $this->data[] = array(
                'id' => $Image->get('id'),
                'name' => $Image->get('name'),
                'description' => $Image->get('description'),
                'storageGroup' => $Image->getStorageGroup()->get('name'),
                'os' => $Image->getOS() && $Image->getOS()->isValid() ? $Image->getOS()->get('name') : _('Not set'),
                'deployed' => $this->validDate($Image->get('deployed')) ? $this->FOGCore->formatTime($Image->get('deployed')) : 'No Data',
                'size' => $imageSize,
                $SizeServer ? 'serv_size' : null => $SizeServer ? $servSize : null,
                'image_type' => $Image->getImageType()->get('name'),
                'image_partition_type' => $Image->getImagePartitionType()->get('name'),
                'protected' => '<i class="fa fa-'.(!$Image->get('protected') ? 'un' : '').'lock fa-1x icon hand" title="'.(!$Image->get('protected') ? _('Not Protected') : _('Protected')).'"></i>',
                'type' => $Image->get('format') ? 'Partimage' : 'Partclone',
            );
        }
        unset($Image);
        // Hook
        $this->HookManager->processEvent('IMAGE_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    /** search_post()
     * Used from the search field.  If search is default view, this is how the data gets displayed based
     * on what was searched for.
     */
    public function search_post() {
        // Get All images based on the keyword
        $SizeServer = $_SESSION['FOG_FTP_IMAGE_SIZE'];
        // Find data -> Push data
        foreach ($this->getClass('ImageManager')->search() AS &$Image) {
            $imageSize = $this->FOGCore->formatByteSize((double)$Image->get('size'));
            $StorageNode = $Image->getStorageGroup()->getMasterStorageNode();
            if ($StorageNode && $StorageNode->isValid() && $SizeServer) $servSize = $this->FOGCore->getFTPByteSize($StorageNode,($StorageNode->isValid() ? $StorageNode->get('path').'/'.$Image->get('path') : null));
            $this->data[] = array(
                'id' => $Image->get('id'),
                'name' => $Image->get('name'),
                'description' => $Image->get('description'),
                'storageGroup' => $Image->getStorageGroup()->get('name'),
                'os' => $Image->getOS()->get('name'),
                'deployed' => $this->validDate($Image->get('deployed')) ? $this->FOGCore->formatTime($Image->get('deployed')) : 'No Data',
                'size' => $imageSize,
                $SizeServer ? 'serv_size' : null => $SizeServer ? $servSize : null,
                'image_type' => $Image->getImageType()->get('name'),
                'image_partition_type' => $Image->getImagePartitionType()->get('name'),
                'type' => $Image->get('format') ? 'Partimage' : 'Partclone',
                'protected' => '<i class="fa fa-'.(!$Image->get('protected') ? 'un' : '').'lock fa-1x icon hand" title="'.(!$Image->get('protected') ? _('Not Protected') : _('Protected')).'"></i>',
            );
        }
        unset($Image);
        // Hook
        $this->HookManager->processEvent('IMAGE_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    /** add()
     * Displays the form to create a new image object.
     */
    public function add() {
        // Set title
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
        $fields = array(
            _('Image Name') => '<input type="text" name="name" id="iName" onblur="duplicateImageName()" value="${image_name}" />',
            _('Image Description') => '<textarea name="description" rows="8" cols="40">${image_desc}</textarea>',
            _('Storage Group') => '${storage_groups}',
            _('Operating System') => '${operating_systems}',
            _('Image Path') => '${image_path}<input type="text" name="file" id="iFile" value="${image_file}" />',
            _('Image Type') => '${image_types}',
            _('Partition') => '${image_partition_types}',
            _('Compression') => '<div id="pigz" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="compress" id="showVal" maxsize="1" style="width: 10px; top: -5px; left: 225px; position: relative;" value="${image_comp}" />',
            '<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" /><!--<i class="icon fa fa-question" title="TODO!"></i>-->',
        );
        print "\n\t\t\t<h2>"._('Add new image definition').'</h2>';
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
        $StorageNodes = $this->getClass('StorageNodeManager')->find(array('isEnabled' => 1));
        unset($MasterGroupID,$MasterNode,$MastserSet);
        foreach($StorageNodes AS &$Node) {
            if ($Node->isValid() && $Node->get('isMaster')) {
                $MasterGroupID = $Node->get('storageGroupID');
                $MasterNode = $Node;
                break;
            }
        }
        unset($Node);
        if (!isset($MasterGroupID) || is_numeric($MasterGroupID) || $MasterGroupID <= 0) {
            foreach($StorageNodes AS &$Node) {
                if ($Node->isValid()) {
                    $MasterGroupID = $Node->get('storageGroupID');
                    $MasterNode = $Node;
                }
            }
        }
        unset($Node);
        $StorageGroups = $this->getClass('StorageGroupManager')->buildSelectBox($MasterGroupID);
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'image_name' => $_REQUEST['name'],
                'image_desc' => $_REQUEST['description'],
                'storage_groups' => $StorageGroups,
                'operating_systems' => $this->getClass('OSManager')->buildSelectBox($_REQUEST['os']),
                'image_path' => $MasterNode && $MasterNode->isValid() ? $MasterNode->get('path').'/&nbsp;' : _('No Storage Nodes Found'),
                'image_file' => $_REQUEST['file'],
                'image_types' => $this->getClass('ImageTypeManager')->buildSelectBox($_REQUEST['imagetype'],'','id'),
                'image_partition_types' => $this->getClass('ImagePartitionTypeManager')->buildSelectBox($_REQUEST['imagepartitiontype'],'','id'),
                'image_comp' => isset($_REQUEST['compress']) ? intval($_REQUEST['compress']) : $this->FOGCore->getSetting('FOG_PIGZ_COMP'),
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent('IMAGE_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print '</form>';
    }
    /** add_post()
     * Actually creates the new image object.
     */
    public function add_post() {
        // Hook
        $this->HookManager->processEvent('IMAGE_ADD_POST');
        // POST
        try {
            $_REQUEST['file'] = trim($_REQUEST['file']);
            // Error checking
            if (empty($_REQUEST['name'])) throw new Exception('An image name is required!');
            if ($this->getClass('ImageManager')->exists($_REQUEST['name'])) throw new Exception('An image already exists with this name!');
            if (empty($_REQUEST['file'])) throw new Exception('An image file name is required!');
            if ($_REQUEST['file'] == 'postdownloadscripts' && $_REQUEST['file'] == 'dev') throw new Exception('Please choose a different name, this one is reserved for FOG.');
            if (empty($_REQUEST['storagegroup'])) throw new Exception('A Storage Group is required!');
            if (empty($_REQUEST['os'])) throw new Exception('An Operating System is required!');
            if (empty($_REQUEST['imagetype']) || !is_numeric($_REQUEST['imagetype'])) throw new Exception('An image type is required!');
            if (empty($_REQUEST['imagepartitiontype']) || !is_numeric($_REQUEST['imagepartitiontype'])) throw new Exception('An image partition type is required!');
            // Create new Object
            $Image = $this->getClass('Image')
                ->set('name',$_REQUEST['name'])
                ->set('description',$_REQUEST['description'])
                ->set('osID',$_REQUEST['os'])
                ->set('path',$_REQUEST['file'])
                ->set('imageTypeID',$_REQUEST['imagetype'])
                ->set('imagePartitionTypeID',$_REQUEST['imagepartitiontype'])
                ->set('compress',$_REQUEST['compress'])
                ->addGroup($_REQUEST['storagegroup']);
            // Save
            if ($Image->save()) {
                // Hook
                $this->HookManager->processEvent('IMAGE_ADD_SUCCESS', array('Image' => &$Image));
                // Log History event
                $this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Image created'), $Image->get('id'), $Image->get('name')));
                // Set session message
                $this->FOGCore->setMessage(_('Image created'));
                // Redirect to new entry
                $this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $Image->get('id')));
            } else throw new Exception('Database update failed');
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent('IMAGE_ADD_FAIL', array('Image' => &$Image));
            // Log History event
            $this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Image'), $_REQUEST['name'], $e->getMessage()));
            // Set session message
            $this->FOGCore->setMessage($e->getMessage());
            // Redirect to new entry
            $this->FOGCore->redirect($this->formAction);
        }
    }
    /** edit()
     * 	Creates the form and display for editing an existing image object.
     */
    public function edit() {
        // Find
        $Image = $this->obj;
        // Title - set title for page title in window
        $this->title = sprintf('%s: %s', _('Edit'), $Image->get('name'));
        print "\n\t\t\t".'<div id="tab-container">';
        // Unset the headerData
        unset($this->headerData);
        // Set the table row information
        $this->attributes = array(
            array(),
            array(),
        );
        // Set the template information
        $this->templates = array(
            '${field}',
            '${input}',
        );
        // Set the fields and inputs.
        $fields = array(
            _('Image Name') => '<input type="text" name="name" id="iName" onblur="duplicateImageName()" value="${image_name}" />',
            _('Image Description') => '<textarea name="description" rows="8" cols="40">${image_desc}</textarea>',
            _('Operating System') => '${operating_systems}',
            _('Image Path') => '${image_path}<input type="text" name="file" id="iFile" value="${image_file}" />',
            _('Image Type') => '${image_types}',
            _('Partition') => '${image_partition_types}',
            _('Protected') => '<input type="checkbox" name="protected_image" value="1" ${image_protected} />',
            _('Compression') => '<div id="pigz" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="compress" id="showVal" maxsize="1" style="width: 10px; top: -5px; left: 225px; position: relative;" value="${image_comp}" />',
            $_SESSION['FOG_FORMAT_FLAG_IN_GUI'] ? _('Image Manager') : '' => $_SESSION['FOG_FORMAT_FLAG_IN_GUI'] ? '<select name="imagemanage"><option value="1" ${is_legacy}>'._('PartImage').'</option><option value="0" ${is_modern}>'._('PartClone').'</option></select>' : '',
            '<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Update').'" /><!--<i class="icon fa fa-question" title="TODO!"></i>-->',
        );
        $StorageNode = $Image->getStorageGroup()->getMasterStorageNode();
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'image_name' => $Image->get('name'),
                'image_desc' => $Image->get('description'),
                'operating_systems' => $this->getClass('OSManager')->buildSelectBox($Image->get('osID')),
                'image_path' => $StorageNode && $StorageNode->isValid() ? $StorageNode->get('path').'/&nbsp;' : 'No nodes available.',
                'image_file' => $Image->get('path'),
                'image_types' => $this->getClass('ImageTypeManager')->buildSelectBox($Image->get('imageTypeID'),'','id'),
                'image_partition_types' => $this->getClass('ImagePartitionTypeManager')->buildSelectBox($Image->get('imagePartitionTypeID'),'','id'),
                'is_legacy' => $Image->get('format') == 1 ? 'selected="selected"' : '',
                'is_modern' => $Image->get('format') == 0 ? 'selected="selected"' : '',
                'image_protected' => $Image->get('protected') ? 'checked' : '',
                'image_comp' => is_numeric($Image->get('compress')) && $Image->get('compress') > -1 ? $Image->get('compress') : $this->FOGCore->getSetting('FOG_PIGZ_COMP'),
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent('IMAGE_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        print "\n\t\t\t<!-- General -->";
        print "\n\t\t\t".'<div id="image-gen">';
        print "\n\t\t\t<h2>"._('Edit image definition').'</h2>';
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-gen">';
        $this->render();
        print '</form>';
        print "\n\t\t\t\t</div>";
        // Reset for next tab
        unset($this->data);
        print "\n\t\t\t\t<!-- Storage Groups with Assigned Image -->";
        $IAMan = new ImageAssociationManager();
        $SGMan = new StorageGroupManager();
        // Get groups with this image assigned
        foreach($Image->get('storageGroups') AS &$Group) {
            if ($Group->isValid()) $GroupsWithMe[] = $Group->get('id');
        }
        unset($Group);
        // Get all group IDs with an image assigned
        foreach($IAMan->find() AS &$Group) {
            if ($Group->getStorageGroup() && $Group->getStorageGroup()->isValid() && $Group->getImage()->isValid()) $GroupWithAnyImage[] = $Group->getStorageGroup()->get('id');
        }
        unset($Group);
        // Set the values
        foreach($SGMan->find() AS &$Group) {
            if ($Group->isValid()) {
                if (!in_array($Group->get('id'),$GroupWithAnyImage)) $GroupNotWithImage[] = $Group;
                if (!in_array($Group->get('id'),$GroupsWithMe)) $GroupNotWithMe[] = $Group;
            }
        }
        unset($Group);
        print "\n\t\t\t\t".'<div id="image-storage">';
        // Create the header data:
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxgroup1" class="toggle-checkbox1" />',
            _('Storage Group Name'),
        );
        // Create the template data:
        $this->templates = array(
            '<input type="checkbox" name="storagegroup[]" value="${storageGroup_id}" class="toggle-group${check_num}" />',
            '${storageGroup_name}',
        );
        // Create the attributes data:
        $this->attributes = array(
            array('class' => 'c', 'width' => 16),
            array(),
        );
        // All groups not with this set as the image
        foreach((array)$GroupNotWithMe AS &$Group) {
            if ($Group->isValid()) {
                $this->data[] = array(
                    'storageGroup_id' => $Group->get('id'),
                    'storageGroup_name' => $Group->get('name'),
                    'check_num' => 1,
                );
            }
        }
        unset($Group);
        $GroupDataExists = false;
        if (count($this->data) > 0) {
            $GroupDataExists = true;
            $this->HookManager->processEvent('IMAGE_GROUP_ASSOC',array('headerData' => &$this->headerData,'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
            print "\n\t\t\t<center>".'<label for="groupMeShow">'._('Check here to see groups not assigned with this image').'&nbsp;&nbsp;<input type="checkbox" name="groupMeShow" id="groupMeShow" /></label>';
            print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-storage">';
            print "\n\t\t\t".'<div id="groupNotInMe">';
            print "\n\t\t\t".'<h2>'._('Modify group association for').' '.$Image->get('name').'</h2>';
            print "\n\t\t\t".'<p>'._('Add image to groups').' '.$Image->get('name').'</p>';
            $this->render();
            print "</div>";
        }
        // Reset the data for the next value
        unset($this->data);
        // Create the header data:
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxgroup2" class="toggle-checkbox2" />',
            _('Storage Group Name'),
        );
        // All groups without an image
        foreach((array)$GroupNotWithImage AS &$Group) {
            if ($Group->isValid()) {
                $this->data[] = array(
                    'storageGroup_id' => $Group->get('id'),
                    'storageGroup_name' => $Group->get('name'),
                    'check_num' => '2',
                );
            }
        }
        unset($Group);
        if (count($this->data) > 0) {
            $GroupDataExists = true;
            $this->HookManager->processEvent('IMAGE_GROUP_NOT_WITH_ANY',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
            print "\n\t\t\t".'<label for="groupNoShow">'._('Check here to see groups not with any image associated').'&nbsp;&nbsp;<input type="checkbox" name="groupNoShow" id="groupNoShow" /></label>';
            print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-storage">';
            print "\n\t\t\t".'<div id="groupNoImage">';
            print "\n\t\t\t".'<p>'._('Groups below have no image association').'</p>';
            print "\n\t\t\t".'<p>'._('Assign image to groups').' '.$Image->get('name').'</p>';
            $this->render();
            print "\n\t\t\t</div>";
        }
        if ($GroupDataExists) {
            print '<br/><input type="submit" value="'._('Add Image to Group(s)').'" />';
            print "\n\t\t\t</form></center>";
        }
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Storage Group Name'),
        );
        $this->attributes = array(
            array('width' => 16,'class' => 'c'),
            array('class' => 'r'),
        );
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" name="storagegroup-rm[]" value="${storageGroup_id}" />',
            '${storageGroup_name}',
        );
        foreach($Image->get('storageGroups') AS &$Group) {
            if ($Group->isValid()) {
                $this->data[] = array(
                    'storageGroup_id' => $Group->get('id'),
                    'storageGroup_name' => $Group->get('name'),
                );
            }
        }
        unset($Group);
        // Hook
        $this->HookManager->processEvent('IMAGE_EDIT_GROUP', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-storage">';
        $this->render();
        if (count($this->data) > 0) print "\n\t\t\t".'<center><input type="submit" value="'._('Delete Selected Group associations').'" name="remstorgroups"/></center>';
        print '</form>';
        print "\n\t\t\t\t</div>";
        print "\n\t\t\t</div>";
    }
    /** edit_post()
     * Actually updates the image object based on what was filled out in the form.
     */
    public function edit_post() {
        // Find
        $Image = $this->obj;
        // Hook
        $this->HookManager->processEvent('IMAGE_EDIT_POST', array('Image' => &$Image));
        // POST
        try {
            switch ($_REQUEST['tab']) {
                case 'image-gen';
                // Error checking
                if (empty($_REQUEST['name'])) throw new Exception('An image name is required!');
                if ($Image->get('name') != $_REQUEST['name'] && $this->getClass('ImageManager')->exists($_REQUEST['name'], $Image->get('id'))) throw new Exception('An image already exists with this name!');
                if ($_REQUEST['file'] == 'postdownloadscripts' && $_REQUEST['file'] == 'dev') throw new Exception('Please choose a different name, this one is reserved for FOG.');
                if (empty($_REQUEST['file'])) throw new Exception('An image file name is required!');
                if (empty($_REQUEST['os'])) throw new Exception('An Operating System is required!');
                if (empty($_REQUEST['imagetype']) && $_REQUEST['imagetype'] != '0') throw new Exception('An image type is required!');
                if (empty($_REQUEST['imagepartitiontype']) && $_REQUEST['imagepartitiontype'] != '0') throw new Exception('An image partition type is required!');
                // Update Object
                $Image->set('name', $_REQUEST['name'])
                    ->set('description', $_REQUEST['description'])
                    ->set('osID', $_REQUEST['os'])
                    ->set('path', $_REQUEST['file'])
                    ->set('imageTypeID', $_REQUEST['imagetype'])
                    ->set('imagePartitionTypeID', $_REQUEST['imagepartitiontype'])
                    ->set('format',isset($_REQUEST['imagemanage']) ? $_REQUEST['imagemanage'] : $Image->get('format'))
                    ->set('protected', $_REQUEST['protected_image'])
                    ->set('compress', $_REQUEST['compress']);
                break;
                case 'image-storage';
                $Image->addGroup($_REQUEST['storagegroup']);
                if (isset($_REQUEST['remstorgroups'])) {
                    if (count($Image->get('storageGroups')) > 1) $Image->removeGroup($_REQUEST['storagegroup-rm']);
                    else throw new Exception(_('Image must be assigned to one Storage Group'));
                }
                break;
            }
            // Save
            if ($Image->save()) {
                // Hook
                $this->HookManager->processEvent('IMAGE_UPDATE_SUCCESS', array('Image' => &$Image));
                // Log History event
                $this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Image updated'), $Image->get('id'), $Image->get('name')));
                // Set session message
                $this->FOGCore->setMessage(_('Image updated'));
                // Redirect to new entry
                $this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Image->get('id'), $_REQUEST['tab']));
            } else throw new Exception('Database update failed');
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent('IMAGE_UPDATE_FAIL', array('Image' => &$Image));
            // Log History event
            $this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Image'), $_REQUEST['name'], $e->getMessage()));
            // Set session message
            $this->FOGCore->setMessage($e->getMessage());
            // Redirect
            $this->FOGCore->redirect($this->formAction);
        }
    }
    /** multicast()
     * Creates the multicast session.
     */
    public function multicast() {
        // Set title
        $this->title = $this->foglang['Multicast'];
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
            _('Session Name') => '<input type="text" name="name" id="iName" autocomplete="off" value="" />',
            _('Client Count') => '<input type="text" name="count" id="iCount" autocomplete="off" />',
            _('Timeout') .'('._('minutes').')' => '<input type="text" name="timeout" id="iTimeout" autocomplete="off" />',
            _('Select Image') => '${select_image}',
            '<input type="hidden" name="start" value="1" />' => '<input type="submit" value="'._('Start').'" /><!--<i class="icon fa fa-question" title="TODO!"></i>-->',
        );
        print "\n\t\t\t<h2>"._('Start Multicast Session').'</h2>';
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'session_name' => $_REQUEST['name'],
                'client_count' => $_REQUEST['count'],
                'session_timeout' => $_REQUEST['timeout'],
                'select_image' => $this->getClass('ImageManager')->buildSelectBox($_REQUEST['image'],'','name'),
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent('IMAGE_MULTICAST_SESS',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
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
            array('class' => 'r'),
        );
        $this->templates = array(
            '${mc_name}<br/><small>${image_name}:${os}</small>',
            '${mc_count}',
            '<small>${mc_start}</small>',
            '${mc_percent}',
            '${mc_state}',
            '<a href="?node='.$this->node.'&sub=stop&mcid=${mc_id}" title="Remove"><i class="fa fa-minus-circle" alt="'._('Kill').'"></i></a>',
        );
        $MulticastSessions = $this->getClass('MulticastSessionsManager')->find(array('stateID' => array(0,1,2,3)));
        foreach($MulticastSessions AS &$MulticastSession) {
            if ($MulticastSession->isValid()) {
                $Image = new Image($MulticastSession->get('image'));
                $TaskState = new TaskState($MulticastSession->get('stateID'));
                $this->data[] = array(
                    'mc_name' => $MulticastSession->get('name'),
                    'mc_count' => $MulticastSession->get('sessclients'),
                    'image_name' => $Image->get('name'),
                    'os' => $Image->getOS(),
                    'mc_start' => $this->formatTime($MulticastSession->get('starttime'),'Y-m-d H:i:s'),
                    'mc_percent' => $MulticastSession->get('percent'),
                    'mc_state' => $TaskState->get('name'),
                    'mc_id' => $MulticastSession->get('id'),
                );
            }
        }
        unset($MulticastSession);
        // Hook
        $this->HookManager->processEvent('IMAGE_MULTICAST_START',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print '</form>';
    }
    public function multicast_post() {
        try {
            // Error Checking
            if (!trim($_REQUEST['name'])) throw new Exception(_('Please input a session name'));
            if (!$_REQUEST['image']) throw new Exception(_('Please choose an image'));
            if ($this->getClass('MulticastSessionsManager')->exists(trim($_REQUEST['name']))) throw new Exception(_('Session with that name already exists'));
            if ($this->getClass('HostManager')->exists(trim($_REQUEST['name']))) throw new Exception(_('Session name cannot be the same as an existing hostname'));
            if (is_numeric($_REQUEST['timeout']) && $_REQUEST['timeout'] > 0) $this->FOGCore->setSetting('FOG_UDPCAST_MAXWAIT',$_REQUEST['timeout']);
            $countmc = $this->getClass('MulticastSessionsManager')->count(array('stateID' => array(0,1,2,3)));
            $countmctot = $this->FOGCore->getSetting('FOG_MULTICAST_MAX_SESSIONS');
            $Image = $this->getClass('Image',$_REQUEST['image']);
            $StorageGroup = $Image->getStorageGroup();
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if ($countmc >= $countmctot) throw new Exception(_('Please wait until a slot is open<br/>There are currently '.$countmc.' tasks in queue<br/>Your server only allows '.$countmctot));
            $MulticastSession = $this->getClass('MulticastSessions')
                ->set('name',trim($_REQUEST['name']))
                ->set('port',$this->FOGCore->getSetting('FOG_UDPCAST_STARTINGPORT'))
                ->set('image',$Image->get('id'))
                ->set('stateID',0)
                ->set('sessclients',$_REQUEST['count'])
                ->set('isDD',$Image->get('imageTypeID'))
                ->set('starttime',$this->formatTime('now','Y-m-d H:i:s'))
                ->set('interface',$StorageNode->get('interface'))
                ->set('logpath',$Image->get('path'));
            if (!$MulticastSession->save()) $this->FOGCore->setMessage(_('Failed to create Session'));
            // Sets a new port number so you can create multiple Multicast Tasks.
            $randomnumber = mt_rand(24576,32766)*2;
            while ($randomnumber == $MulticastSession->get('port')) $randomnumber = mt_rand(24576,32766)*2;
            $this->FOGCore->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
            $this->FOGCore->setMessage(_('Multicast session created').'<br />'.$MulticastSession->get('name').' has been started on port '.$MulticastSession->get('port'));
        } catch (Exception $e) {
            $this->FOGCore->setMessage($e->getMessage());
        }
        $this->FOGCore->redirect('?node='.$this->node.'&sub=multicast');
    }
    public function stop() {
        if (is_numeric($_REQUEST['mcid']) && $_REQUEST['mcid'] > 0) {
            $MulticastSession = $this->getClass('MulticastSessions',$_REQUEST['mcid']);
            foreach((array)$this->getClass('MulticastSessionsAssociationManager')->find(array('msid' => $MulticastSession->get('id'))) AS &$MulticastAssoc) $this->getClass('Task',$MulticastAssoc->get('taskID'))->cancel();
            unset($MulticastAssoc);
            $MulticastSession->set('name',null)->set('stateID',5)->save();
            $this->FOGCore->setMessage(_('Canceled task'));
            $this->FOGCore->redirect('?node='.$this->node.'&sub=multicast');
        }
    }
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
