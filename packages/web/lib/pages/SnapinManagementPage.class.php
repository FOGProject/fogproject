<?php
class SnapinManagementPage extends FOGPage {
    public $node = 'snapin';
    public function __construct($name = '') {
        $this->name = 'Snapin Management';
        // Call parent constructor
        parent::__construct($name);
        if ($_REQUEST['id']) {
            $this->obj = $this->getClass('Snapin',$_REQUEST[id]);
            $this->subMenu = array(
                "$this->linkformat#snap-gen" => $this->foglang[General],
                "$this->linkformat#snap-storage" => "{$this->foglang[Storage]} {$this->foglang[Group]}",
                $this->membership => $this->foglang[Membership],
                $this->delformat => $this->foglang[Delete],
            );
            $this->notes = array(
                $this->foglang[Snapin] => $this->obj->get('name'),
                $this->foglang[File] => $this->obj->get('file'),
            );
        }
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu' => &$this->menu,'submenu' => &$this->subMenu,'id' => &$this->id,'notes' => &$this->notes));
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
            _('Snapin Name'),
            _('Storage Group'),
            '',
        );
        // Row templates
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${id}" class="toggle-action" checked/>',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit')),
            '${storage_group}',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><i class="icon fa fa-pencil"></i></a> <a href="?node=%s&sub=delete&%s=${id}" title="%s"><i class="icon fa fa-minus-circle"></i></a>', $this->node, $this->id, _('Edit'), $this->node, $this->id, _('Delete'))
        );
        // Row attributes
        $this->attributes = array(
            array('class' => 'c', 'width' => '16'),
            array(),
            array('class' => 'c', 'width' => '50'),
        );
    }
    // Pages
    public function index()
    {
        // Set title
        $this->title = _('All Snap-ins');
        if ($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && $this->getClass('SnapinManager')->count() > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
            $this->FOGCore->redirect(sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node));
        // Find data
        $Snapins = $this->getClass('SnapinManager')->find();
        // Row data
        foreach ((array)$Snapins AS $Snapin)
        {
            if ($Snapin && $Snapin->isValid())
            {
                $this->data[] = array(
                    'id'		=> $Snapin->get('id'),
                    'name'		=> $Snapin->get('name'),
                    'storage_group' => $Snapin->getStorageGroup() && $Snapin->getStorageGroup()->isValid() ? $Snapin->getStorageGroup()->get('name') : '',
                    'description'	=> $Snapin->get('description'),
                    'file'		=> $Snapin->get('file')
                );
            }
        }
        // Hook
        $this->HookManager->processEvent('SNAPIN_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    public function search_post()
    {
        // Find data -> Push data
        foreach ($this->getClass('SnapinManager')->search() AS $Snapin)
        {
            if ($Snapin && $Snapin->isValid())
            {
                $this->data[] = array(
                    'id'		=> $Snapin->get('id'),
                    'name'		=> $Snapin->get('name'),
                    'storage_group' => $Snapin->getStorageGroup() && $Snapin->getStorageGroup()->isValid() ? $Snapin->getStorageGroup()->get('name') : '',
                    'description'	=> $Snapin->get('description'),
                    'file'		=> $Snapin->get('file')
                );
            }
        }
        // Hook
        $this->HookManager->processEvent('SNAPIN_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    // STORAGE NODE
    public function add()
    {
        // Set title
        $this->title = _('Add New Snapin');
        // Header Data
        unset($this->headerData);
        // Attributes (cell information)
        $this->attributes = array(
            array(),
            array(),
        );
        // Template
        $this->templates = array(
            '${field}',
            '${input}',
        );
        // See's what files are available and sorts them.
        $files = array_diff(preg_grep('#^([^.])#',scandir($_SESSION['FOG_SNAPINDIR'])), array('..', '.'));
        foreach($files AS $file)
        {
            if (!is_dir(rtrim($_SESSION['FOG_SNAPINDIR'],'/').'/'.$file))
                $filelist[] = $file;
        }
        if ($filelist && is_array($filelist))
            sort($filelist);
        foreach((array)$filelist AS $file)
            $filesFound .= '<option value="'.basename($file).'"'.(basename($_REQUEST['snapinfileexist']) == basename($file) ? 'selected="selected"' : '').'>'.basename($file).'</option>';
        // Fields to work from:
        $fields = array(
            _('Snapin Name') => '<input type="text" name="name" value="${snapin_name}" />',
            _('Snapin Description') => '<textarea name="description" rows="8" cols="40" value="${snapin_desc}">${snapin_desc}</textarea>',
            _('Snapin Storage Group') => $this->getClass('StorageGroupManager')->buildSelectBox($_REQUEST['storagegroup']),
            _('Snapin Run With') => '<input type="text" name="rw" value="${snapin_rw}" />',
            _('Snapin Run With Argument') => '<input type="text" name="rwa" value="${snapin_rwa}" />',
            _('Snapin File').' <span class="lightColor">'._('Max Size').':${max_size}</span>' => '<input type="file" name="snapin" value="${snapin_file}" />',
            (count($files) > 0 ?_('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><span class="lightColor"><option value="">- '._('Please select an option').'-</option>${snapin_filesexist}</select>' : null),
            _('Snapin Arguments') => '<input type="text" name="args" value="${snapin_args}" />',
            _('Reboot after install') => '<input type="checkbox" name="reboot" />',
            '<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" />',
        );
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
        foreach ((array)$fields AS $field => $input)
        {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'snapin_name' => $_REQUEST['name'],
                'snapin_desc' => $_REQUEST['description'],
                'snapin_args' => $_REQUEST['args'],
                'snapin_rw' => $_REQUEST['rw'],
                'snapin_rwa' => $_REQUEST['rwa'],
                'max_size' => ini_get('post_max_size'),
                'snapin_file' => $_FILES['snapin'],
                'snapin_filesexist' => $filesFound,
            );
        }
        // Hook
        $this->HookManager->processEvent('SNAPIN_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print '</form>';
    }
    public function add_post()
    {
        // Hook
        $this->HookManager->processEvent('SNAPIN_ADD_POST');
        // POST
        try
        {
            // SnapinManager
            $SnapinManager = $this->getClass('SnapinManager');
            // Error checking
            $snapinName = trim($_REQUEST['name']);
            if (!$snapinName)
                throw new Exception(_('Please enter a name to give this Snapin'));
            if ($SnapinManager->exists($snapinName))
                throw new Exception(_('Snapin already exists'));
            if (!$_REQUEST['storagegroup'])
                throw new Exception(_('Please select a storage group for this snapin to reside in'));
            if ($_REQUEST['snapin'] || $_FILES['snapin']['name'])
            {
                if (!$_REQUEST['storagegroup'])
                {
                    $uploadfile = rtrim($_SESSION['FOG_SNAPINDIR'],'/').'/'.basename($_FILES['snapin']['name']);
                    if(!is_dir($_SESSION['FOG_SNAPINDIR']) && !is_writeable($_SESSION['FOG_SNAPINDIR']))
                        throw new Exception('Failed to add snapin, unable to locate snapin directory.');
                    else if (!is_writeable($_SESSION['FOG_SNAPINDIR']))
                        throw new Exception('Failed to add snapin, snapin directory is not writeable.');
                    else if (file_exists($uploadfile))
                        throw new Exception('Failed to add snapin, file already exists.');
                    else if (!move_uploaded_file($_FILES['snapin']['tmp_name'],$uploadfile))
                        throw new Exception('Failed to add snapin, file upload failed.');
                }
                else
                {
                    // Will fail if the storage group is not assigned or found.
                    $StorageNode = $this->getClass('StorageGroup',$_REQUEST['storagegroup'])->getMasterStorageNode();
                    $src = $_FILES['snapin']['tmp_name'];
                    $dest = rtrim($StorageNode->get('snapinpath'),'/').'/'.$_FILES['snapin']['name'];
                    $this->FOGFTP->set('host',$this->FOGCore->resolveHostname($StorageNode->get('ip')))
                        ->set('username',$StorageNode->get('user'))
                        ->set('password',$StorageNode->get('pass'));
                    if (!$this->FOGFTP->connect())
                        throw new Exception(_('Storage Node: '.$this->FOGCore->resolveHostname($StorageNode->get('ip')).' FTP Connection has failed!'));
                    if (!$this->FOGFTP->chdir($StorageNode->get('snapinpath')))
                    {
                        if (!$this->FOGFTP->mkdir($StorageNode->get('snapinpath')))
                            throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                    }
                    // Try to delete the file.
                    $this->FOGFTP->delete($dest);
                    if (!$this->FOGFTP->put($dest,$src))
                        throw new Exception(_('Failed to add snapin'));
                    $this->FOGFTP->close();
                }
            }
            else if (empty($_REQUEST['snapinfileexist']))
                throw new Exception('Failed to add snapin, no file was uploaded or selected for use');
            // Create new Object
            $Snapin = new Snapin(array(
                'name'			=> $snapinName,
                'description'	=> $_REQUEST['description'],
                'file'			=> ($_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : $_FILES['snapin']['name']),
                'args'			=> $_REQUEST['args'],
                'createdTime'	=> $this->formatTime('now','Y-m-d H:i:s'),
                'createdBy' 	=> $_SESSION['FOG_USERNAME'],
                'reboot'		=> (isset($_REQUEST['reboot']) ? 1 : 0 ),
                'runWith'		=> $_REQUEST['rw'],
                'runWithArgs'	=> $_REQUEST['rwa'],
            ));
            $Snapin->addGroup($_REQUEST['storagegroup']);
            // Save
            if ($Snapin->save())
            {
                // Hook
                $this->HookManager->processEvent('SNAPIN_ADD_SUCCESS', array('Snapin' => &$Snapin));
                // Log History event
                $this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin created'), $Snapin->get('id'), $Snapin->get('name')));
                // Set session message
                $this->FOGCore->setMessage('Snapin added, Editing now!');
                // Redirect to new entry
                $this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $Snapin->get('id')));
            }
            else
                // Database save failed
                throw new Exception('Add snapin failed.');
        }
        catch (Exception $e)
        {
            // Hook
            $this->HookManager->processEvent('SNAPIN_ADD_FAIL', array('Snapin' => &$Snapin));
            // Log History event
            $this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Storage'), $_REQUEST['name'], $e->getMessage()));
            // Set session message
            $this->FOGCore->setMessage($e->getMessage());
            // Redirect to new entry
            $this->FOGCore->redirect($this->formAction);
        }
    }
    public function edit()
    {
        // Find
        $Snapin = $this->obj;
        // Title
        $this->title = sprintf('%s: %s', _('Edit'), $Snapin->get('name'));
        // Header Data
        unset($this->headerData);
        // Attributes (cell information)
        $this->attributes = array(
            array(),
            array(),
        );
        // Template
        $this->templates = array(
            '${field}',
            '${input}',
        );
        // See's what files are available and sorts them.
        if ($Snapin->get('storageGroups'))
        {
            foreach((array)$Snapin->get('storageGroups') AS $StorageGroup)
            {
                $StorageNode = $StorageGroup->getMasterStorageNode();
                if ($StorageNode && $StorageNode->isValid())
                {
                    $this->FOGFTP->set('host',$StorageNode->get('ip'))
                        ->set('username',$StorageNode->get('user'))
                        ->set('password',$StorageNode->get('pass'))
                        ->connect();
                    $filelist = $this->FOGFTP->nlist($StorageNode->get('snapinpath'));
                    foreach($filelist AS $file)
                    {
                        if(!$this->FOGFTP->chdir($file))
                            $files[] = basename($file);
                    }
                }
                $this->FOGFTP->close();
            }
            unset($filelist);
            $filelist = $files;
        }
        else
        {
            // See's what files are available and sorts them.
            $files = array_diff(preg_grep('#^([^.])#',scandir($_SESSION['FOG_SNAPINDIR'])), array('..', '.'));
            foreach($files AS $file)
            {
                if (!is_dir(rtrim($_SESSION['FOG_SNAPINDIR'],'/').'/'.$file))
                    $filelist[] = $file;
            }
        }
        sort($filelist);
        foreach((array)$filelist AS $file)
            $filesFound .= '<option value="'.basename($file).'" '.(basename($file) == basename($Snapin->get('file')) ? 'selected="selected"' : '').'>'. basename($file) .'</option>';
        // Fields to work from:
        $fields = array(
            _('Snapin Name') => '<input type="text" name="name" value="${snapin_name}" />',
            _('Snapin Description') => '<textarea name="description" rows="8" cols="40" value="${snapin_desc}">${snapin_desc}</textarea>',
            _('Snapin Run With') => '<input type="text" name="rw" value="${snapin_rw}" />',
            _('Snapin Run With Argument') => '<input type="text" name="rwa" value="${snapin_rwa}" />',
            _('Snapin File').' <span class="lightColor">'._('Max Size').':${max_size}</span>' => '<span id="uploader">${snapin_file}<a href="#" id="snapin-upload"><i class="fa fa-arrow-up noBorder"></i></a></span>',
            (count($files) > 0 ? _('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><<span class="lightColor"><option value="">- '._('Please select an option').'-</option>${snapin_filesexist}</select>' : null),
            _('Snapin Arguments') => '<input type="text" name="args" value="${snapin_args}" />',
            _('Protected') => '<input type="checkbox" name="protected_snapin" value="1" ${snapin_protected} />',
            _('Reboot after install') => '<input type="checkbox" name="reboot" ${checked} />',
            '<input type="hidden" name="snapinid" value="${snapin_id}" /><input type="hidden" name="update" value="1" />' => '<input type="hidden" name="snapinfile" value="${snapin_file}" /><input type="submit" value="'._('Update').'" />',
        );
        print "\n\t\t\t".'<div id="tab-container">';
        print "\n\t\t\t\t".'<!-- General -->';
        print "\n\t\t\t\t".'<div id="snap-gen">';
        print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&id='.$Snapin->get('id').'&tab=snap-gen" enctype="multipart/form-data">';
        foreach ((array)$fields AS $field => $input)
        {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'snapin_id' => $Snapin->get('id'),
                'snapin_name' => $Snapin->get('name'),
                'snapin_desc' => $Snapin->get('description'),
                'snapin_rw' => $Snapin->get('runWith'),
                'snapin_rwa' => htmlentities($Snapin->get('runWithArgs')),
                'snapin_args' => $Snapin->get('args'),
                'max_size' => ini_get('post_max_size'),
                'snapin_file' => $Snapin->get('file'),
                'snapin_filesexist' => $filesFound,
                'snapin_protected' => $Snapin->get('protected') ? 'checked' : '',
                'checked' => $Snapin->get('reboot') ? 'checked' : '',
            );
        }
        // Hook
        $this->HookManager->processEvent('SNAPIN_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print '</form>';
        print "\n\t\t\t</div>";
        unset($this->data);
        print "\n\t\t\t\t<!-- Storage Groups with Assigned Image -->";
        $SGAMan = new SnapinGroupAssociationManager();
        $SGMan = new StorageGroupManager();
        // Get groups with this snapin assigned
        foreach((array)$Snapin->get('storageGroups') AS $Group)
        {
            if ($Group && $Group->isValid())
                $GroupsWithMe[] = $Group->get('id');
        }
        // Get all group IDs with a snapin assigned
        foreach($SGAMan->find() AS $Group)
        {
            if ($Group->getStorageGroup() && $Group->getStorageGroup()->isValid() && $Group->getSnapin()->isValid())
                $GroupWithAnySnapin[] = $Group->getStorageGroup()->get('id');
        }
        // Set the values
        foreach($SGMan->find() AS $Group)
        {
            if ($Group && $Group->isValid())
            {
                if (!in_array($Group->get('id'),$GroupWithAnySnapin))
                    $GroupNotWithSnapin[] = $Group;
                if (!in_array($Group->get('id'),$GroupsWithMe))
                    $GroupNotWithMe[] = $Group;
            }
        }
        print "\n\t\t\t\t".'<div id="snap-storage">';
        // Create the Header Data:
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapin1" class="toggle-checkbox1"/>',
            _('Storage Group Name'),
        );
        // Create the template data:
        $this->templates = array(
            '<input type="checkbox" name="storagegroup[]" value="${storageGroup_id}" class="toggle-snapin${check_num}" />',
            '${storageGroup_name}',
        );
        // Create the attributes data:
        $this->attributes = array(
            array('class' => 'c', 'width' => 16),
            array(),
        );
        // All Groups not with this set as the Snapin
        foreach((array)$GroupNotWithMe AS $Group)
        {
            if ($Group && $Group->isValid())
            {
                $this->data[] = array(
                    'storageGroup_id' => $Group->get('id'),
                    'storageGroup_name' => $Group->get('name'),
                    'check_num' => 1,
                );
            }
        }
        $GroupDataExists = false;
        if (count($this->data) > 0)
        {
            $GroupDataExists = true;
            $this->HookManager->processEvent('SNAPIN_GROUP_ASSOC',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
            print "\n\t\t\t<center>".'<label for="groupMeShow">'._('Check here to see groups not assigned with this snapin').'&nbsp;&nbsp;<input type="checkbox" name="groupMeShow" id="groupMeShow" /></label>';
            print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=snap-storage">';
            print "\n\t\t\t".'<div id="groupNotInMe">';
            print "\n\t\t\t".'<h2>'._('Modify group association for').' '.$Snapin->get('name').'</h2>';
            print "\n\t\t\t".'<p>'._('Add snapin to groups').' '.$Snapin->get('name').'</p>';
            $this->render();
            print '</div>';
        }
        // Reset the data for the next value
        unset($this->data);
        // Create the header data:
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxgroup2" class="toggle-checkbox2"/>',
            _('Storage Group Name'),
        );
        // All groups without a snapin
        foreach((array)$GroupNotWithSnapin AS $Group)
        {
            if ($Group && $Group->isValid())
            {
                $this->data[] = array(
                    'storageGroup_id' => $Group->get('id'),
                    'storageGroup_name' => $Group->get('name'),
                    'check_num' => 2,
                );
            }
        }
        if (count($this->data) > 0)
        {
            $GroupDataExists = true;
            $this->HookManager->processEvent('SNAPIN_GROUP_NOT_WITH_ANY',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
            print "\n\t\t\t".'<label for="groupNoShow">'._('Check here to see groups not with any snapin associated').'&nbsp;&nbsp;<input type="checkbox" name="groupNoShow" id="groupNoShow" /></label';
            print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=snap-storage">';
            print "\n\t\t\t".'<div id="groupNoSnapin">';
            print "\n\t\t\t".'<p>'._('Groups below have no snapin association').'</p>';
            print "\n\t\t\t".'<p>'._('Assign snapin to groups').' '.$Snapin->get('name').'</p>';
            $this->render();
            print "\n\t\t\t</div>";
        }
        if ($GroupDataExists)
        {
            print '<br/><input type="submit" value="'._('Add Snapin to Group(s)').'" />';
            print "\n\t\t\t</form></center>";
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
            _('Storage Group Name'),
        );
        $this->attributes = array(
            array('width' => 16, 'class' => 'c'),
            array('class' => 'r'),
        );
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" name="storagegroup-rm[]" value="${storageGroup_id}" checked/>',
            '${storageGroup_name}',
        );
        unset($this->data);
        foreach((array)$Snapin->get('storageGroups') AS $Group)
        {
            if ($Group && $Group->isValid())
            {
                $this->data[] = array(
                    'storageGroup_id' => $Group->get('id'),
                    'storageGroup_name' => $Group->get('name'),
                );
            }
        }
        // Hook
        $this->HookManager->processEvent('SNAPIN_EDIT_GROUP', array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
        // Output
        print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=snap-storage">';
        $this->render();
        if (count($this->data) > 0)
            print "\n\t\t\t".'<center><input type="submit" value="'._('Delete Selected Group associations').'" name="remstorgroups"/></center>';
        print '</form>';
        print "\n\t\t\t\t</div>";
        print "\n\t\t\t".'</div>';
    }
    public function edit_post()
    {
        // Find
        $Snapin = $this->obj;
        // Hook
        $this->HookManager->processEvent('SNAPIN_EDIT_POST', array('Snapin' => &$Snapin));
        // POST
        try
        {
            switch ($_REQUEST['tab'])
            {
                case 'snap-gen';
                // SnapinManager
                $SnapinManager = $this->getClass('SnapinManager');
                // Error checking
                if ($_REQUEST['snapin'] || $_FILES['snapin']['name'])
                {
                    if (!$Snapin->getStorageGroup())
                    {
                        $uploadfile = rtrim($_SESSION['FOG_SNAPINDIR'],'/').'/'.basename($_FILES['snapin']['name']);
                        if(!is_dir($_SESSION['FOG_SNAPINDIR']) && !is_writeable($_SESSION['FOG_SNAPINDIR']))
                            throw new Exception('Failed to add snapin, unable to locate snapin directory.');
                        else if (!is_writeable($_SESSION['FOG_SNAPINDIR']))
                            throw new Exception('Failed to add snapin, snapin directory is not writeable.');
                        else if (file_exists($uploadfile))
                            throw new Exception('Failed to add snapin, file already exists.');
                        else if (!move_uploaded_file($_FILES['snapin']['tmp_name'],$uploadfile))
                            throw new Exception('Failed to add snapin, file upload failed.');
                    }
                    else
                    {
                        // Will fail if the storage group is not assigned or found.
                        $StorageNode = $Snapin->getStorageGroup()->getMasterStorageNode();
                        $src = $_FILES['snapin']['tmp_name'];
                        $dest = rtrim($StorageNode->get('snapinpath'),'/').'/'.$_FILES['snapin']['name'];
                        $this->FOGFTP->set('host',$this->FOGCore->resolveHostname($StorageNode->get('ip')))
                            ->set('username',$StorageNode->get('user'))
                            ->set('password',$StorageNode->get('pass'));
                        if (!$this->FOGFTP->connect())
                            throw new Exception(_('Storage Node: '.$this->FOGCore->resolveHostname($StorageNode->get('ip')).' FTP Connection has failed!'));
                        if (!$this->FOGFTP->chdir($StorageNode->get('snapinpath')))
                            throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                        // Try to delete the file.
                        $this->FOGFTP->delete($dest);
                        if (!$this->FOGFTP->put($dest,$src))
                            throw new Exception(_('Failed to add snapin'));
                        $this->FOGFTP->close();
                    }
                }
                if ($_REQUEST['name'] != $Snapin->get('name') && $SnapinManager->exists($_REQUEST['name'], $Snapin->get('id')))
                    throw new Exception('Snapin already exists');
                // Update Object
                $Snapin ->set('name',			$_REQUEST['name'])
                    ->set('description',	$_REQUEST['description'])
                    ->set('file',($_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : ($_FILES['snapin']['name'] ? $_FILES['snapin']['name'] : $Snapin->get('file'))))
                    ->set('args',			$_REQUEST['args'])
                    ->set('reboot',			(isset($_REQUEST['reboot']) ? 1 : 0 ))
                    ->set('runWith',		$_REQUEST['rw'])
                    ->set('storageGroupID', $_REQUEST['storagegroup'])
                    ->set('runWithArgs',	$_REQUEST['rwa'])
                    ->set('protected', $_REQUEST['protected_snapin']);
                break;
                case 'snap-storage';
                $Snapin->addGroup($_REQUEST['storagegroup']);
                if (isset($_REQUEST['remstorgroups']))
                    $Snapin->removeGroup($_REQUEST['storagegroup-rm']);
                break;
            }
            // Save
            if ($Snapin->save())
            {
                // Hook
                $this->HookManager->processEvent('SNAPIN_UPDATE_SUCCESS', array('Snapin' => &$Snapin));
                // Log History event
                $this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin updated'), $Snapin->get('id'), $Snapin->get('name')));
                // Set session message
                $this->FOGCore->setMessage(_('Snapin updated'));
                // Redirect to new entry
                $this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Snapin->get('id'),$_REQUEST['tab']));
            }
            else
                throw new Exception('Snapin update failed');
        }
        catch (Exception $e)
        {
            // Hook
            $this->HookManager->processEvent('SNAPIN_UPDATE_FAIL', array('Snapin' => &$Snapin));
            // Log History event
            $this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Snapin'), $_REQUEST['name'], $e->getMessage()));
            // Set session message
            $this->FOGCore->setMessage($e->getMessage());
            // Redirect to new entry
            $this->FOGCore->redirect($this->formAction);
        }
    }
}
