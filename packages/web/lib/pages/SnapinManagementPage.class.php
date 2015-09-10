<?php
class SnapinManagementPage extends FOGPage {
    public $node = 'snapin';
    public function __construct($name = '') {
        $this->name = 'Snapin Management';
        // Call parent constructor
        parent::__construct($name);
        if ($_REQUEST[id]) {
            $this->obj = $this->getClass(Snapin,$_REQUEST[id]);
            $this->subMenu = array(
                "$this->linkformat#snap-gen" => $this->foglang[General],
                "$this->linkformat#snap-storage" => "{$this->foglang[Storage]} {$this->foglang[Group]}",
                $this->membership => $this->foglang[Membership],
                $this->delformat => $this->foglang[Delete],
            );
            $this->notes = array(
                $this->foglang[Snapin] => $this->obj->get(name),
                $this->foglang[File] => $this->obj->get(file),
            );
        }
        $this->HookManager->processEvent(SUB_MENULINK_DATA,array(menu=>&$this->menu,submenu=>&$this->subMenu,id=>&$this->id,notes=>&$this->notes));
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Snapin Name'),
            _('Storage Group'),
            '',
        );
        // Row templates
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit')),
            '${storage_group}',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><i class="icon fa fa-pencil"></i></a> <a href="?node=%s&sub=delete&%s=${id}" title="%s"><i class="icon fa fa-minus-circle"></i></a>', $this->node, $this->id, _('Edit'), $this->node, $this->id, _('Delete'))
        );
        // Row attributes
        $this->attributes = array(
            array('class'=>'c filter-false',width=>16),
            array(),
            array('class'=>c,width=>50),
            array('class'=>'r filter-false'),
        );
    }
    // Pages
    public function index() {
        // Set title
        $this->title = _('All Snap-ins');
        if ($this->FOGCore->getSetting(FOG_DATA_RETURNED) > 0 && $this->getClass(SnapinManager)->count() > $this->FOGCore->getSetting(FOG_DATA_RETURNED) && $_REQUEST[sub] != 'list')
            $this->FOGCore->redirect(sprintf('?node=%s&sub=search',$this->node));
        // Find data
        $Snapins = $this->getClass(SnapinManager)->find();
        // Row data
        foreach ($Snapins AS $i => &$Snapin) {
            $this->data[] = array(
                id=>$Snapin->get(id),
                name=>$Snapin->get(name),
                storage_group=>$Snapin->getStorageGroup()->get(name),
                description=>$Snapin->get(description),
                file=>$Snapin->get(file)
            );
        }
        unset($Snapin);
        // Hook
        $this->HookManager->processEvent(SNAPIN_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function search_post() {
        // Find data -> Push data
        $Snapins = $this->getClass(SnapinManager)->search();
        foreach ($Snapins AS $i => &$Snapin) {
            $this->data[] = array(
                id=>$Snapin->get(id),
                name=>$Snapin->get(name),
                storage_group=>$Snapin->getStorageGroup()->get(name),
                description=>$Snapin->get(description),
                file=>$Snapin->get(file)
            );
        }
        unset($Snapin);
        // Hook
        $this->HookManager->processEvent(SNAPIN_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    // STORAGE NODE
    public function add() {
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
        $files = array_diff(preg_grep('#^([^.])#',scandir($_SESSION[FOG_SNAPINDIR])), array('..', '.'));
        foreach($files AS $i => &$file) {
            if (!is_dir(rtrim($_SESSION[FOG_SNAPINDIR],'/').'/'.$file)) $filelist[] = $file;
        }
        unset($file);
        if ($filelist && is_array($filelist)) sort($filelist);
        foreach((array)$filelist AS $i => &$file) $filesFound .= '<option value="'.basename($file).'"'.(basename($_REQUEST[snapinfileexist]) == basename($file) ? 'selected="selected"' : '').'>'.basename($file).'</option>';
        unset($file);
        // Fields to work from:
        $fields = array(
            _('Snapin Name') => '<input type="text" name="name" value="'.$_REQUEST[name].'" />',
            _('Snapin Description') => '<textarea name="description" rows="8" cols="40">'.$_REQUEST[description].'</textarea>',
            _('Snapin Storage Group') => $this->getClass(StorageGroupManager)->buildSelectBox($_REQUEST[storagegroup]),
            _('Snapin Run With') => '<input type="text" name="rw" value="'.$_REQUEST[rw].'" />',
            _('Snapin Run With Argument') => '<input type="text" name="rwa" value="'.$_REQUEST[rwa].'" />',
            _('Snapin File').' <span class="lightColor">'._('Max Size').':'.ini_get('post_max_size').'</span>' => '<input type="file" name="snapin" value="'.$_FILES[snapin].'"/>',
            (count($files) > 0 ?_('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><span class="lightColor"><option value="">- '._('Please select an option').'-</option>'.$filesFound.'</select>' : null),
            _('Snapin Arguments') => '<input type="text" name="args" value="'.$_REQUEST[args].'"/>',
            _('Reboot after install') => '<input type="checkbox" name="reboot" />',
            '<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" />',
        );
        print '<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent(SNAPIN_ADD,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        print '</form>';
        unset($this->data,$this->templates,$this->attributes,$this->headerData);
        $this->templates = array(
            _('Snapin Command').': ${snapincmd}',
        );
        $this->attributes = array(
            array('class'=>c),
        );
    }
    public function add_post() {
        // Hook
        $this->HookManager->processEvent(SNAPIN_ADD_POST);
        // POST
        try {
            // SnapinManager
            $SnapinManager = $this->getClass(SnapinManager);
            // Error checking
            $snapinName = trim($_REQUEST[name]);
            if (!$snapinName) throw new Exception(_('Please enter a name to give this Snapin'));
            if ($SnapinManager->exists($snapinName)) throw new Exception(_('Snapin already exists'));
            if (!$_REQUEST[storagegroup]) throw new Exception(_('Please select a storage group for this snapin to reside in'));
            if ($_REQUEST[snapin] || $_FILES[snapin][name]) {
                if (!$_REQUEST[storagegroup]) {
                    $uploadfile = rtrim($_SESSION[FOG_SNAPINDIR],'/').'/'.basename($_FILES[snapin][name]);
                    if(!is_dir($_SESSION[FOG_SNAPINDIR]) && !is_writeable($_SESSION[FOG_SNAPINDIR])) throw new Exception('Failed to add snapin, unable to locate snapin directory.');
                    else if (!is_writeable($_SESSION['FOG_SNAPINDIR'])) throw new Exception('Failed to add snapin, snapin directory is not writeable.');
                    else if (file_exists($uploadfile)) throw new Exception('Failed to add snapin, file already exists.');
                    else if (!move_uploaded_file($_FILES[snapin][tmp_name],$uploadfile)) throw new Exception('Failed to add snapin, file upload failed.');
                } else {
                    // Will fail if the storage group is not assigned or found.
                    $StorageNode = $this->getClass(StorageGroup,$_REQUEST[storagegroup])->getMasterStorageNode();
                    $src = $_FILES[snapin][tmp_name];
                    $dest = rtrim($StorageNode->get(snapinpath),'/').'/'.$_FILES[snapin][name];
                    $this->FOGFTP->set(host,$StorageNode->get(ip))
                        ->set(username,$StorageNode->get(user))
                        ->set(password,$StorageNode->get(pass));
                    if (!$this->FOGFTP->connect()) throw new Exception(_('Storage Node: '.$StorageNode->get(ip).' FTP Connection has failed!'));
                    if (!$this->FOGFTP->chdir($StorageNode->get(snapinpath))) {
                        if (!$this->FOGFTP->mkdir($StorageNode->get('snapinpath'))) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                    }
                    // Try to delete the file.
                    $this->FOGFTP->delete($dest);
                    if (!$this->FOGFTP->put($dest,$src)) throw new Exception(_('Failed to add snapin'));
                    $this->FOGFTP->close();
                }
            } else if (empty($_REQUEST[snapinfileexist])) throw new Exception('Failed to add snapin, no file was uploaded or selected for use');
            // Create new Object
            $Snapin = $this->getClass(Snapin)
                ->set(name,$snapinName)
                ->set(description,$_REQUEST[description])
                ->set(file,$_REQUEST[snapinfileexist] ? $_REQUEST[snapinfileexist] : $_FILES[snapin][name])
                ->set(args,$_REQUEST[args])
                ->set(createdTime,$this->formatTime('now','Y-m-d H:i:s'))
                ->set(createdBy,$_SESSION[FOG_USERNAME])
                ->set(reboot,(int)isset($_REQUEST[reboot]))
                ->set(runWith,$_REQUEST[rw])
                ->set(runWithArgs,$_REQUEST[rwa])
                ->addGroup($_REQUEST[storagegroup]);
            // Save
            if (!$Snapin->save()) throw new Exception(_('Add snapin failed!'));
            // Hook
            $this->HookManager->processEvent(SNAPIN_ADD_SUCCESS,array(Snapin=>&$Snapin));
            // Log History event
            $this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin created'),$Snapin->get(id),$Snapin->get(name)));
            // Set session message
            $this->FOGCore->setMessage('Snapin added, Editing now!');
            // Redirect to new entry
            $this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request[node],$this->id,$Snapin->get(id)));
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent(SNAPIN_ADD_FAIL,array(Snapin=>&$Snapin));
            // Log History event
            $this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s',_('Storage'),$_REQUEST[name],$e->getMessage()));
            // Set session message
            $this->FOGCore->setMessage($e->getMessage());
            // Redirect to new entry
            $this->FOGCore->redirect($this->formAction);
        }
    }
    public function edit() {
        // Title
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get(name));
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
        if ($this->obj->get(storageGroups)) {
            $StorageGroups = $this->getClass(StorageGroup)->getManager()->find(array(id=>$this->obj->get(storageGroups)));
            foreach($StorageGroups AS $i => &$StorageGroup) {
                $StorageNode = $StorageGroup->getMasterStorageNode();
                if ($StorageNode->isValid()) {
                    $this->FOGFTP->set(host,$StorageNode->get(ip))
                        ->set(username,$StorageNode->get(user))
                        ->set(password,$StorageNode->get(pass))
                        ->connect();
                    $filelist = $this->FOGFTP->nlist($StorageNode->get(snapinpath));
                    foreach($filelist AS $i => &$file) if (!$this->FOGFTP->chdir($file)) $files[] = basename($file);
                    unset($file);
                }
                $this->FOGFTP->close();
            }
            unset($filelist);
            $filelist = $files;
        } else {
            // See's what files are available and sorts them.
            $files = array_diff(preg_grep('#^([^.])#',scandir($_SESSION[FOG_SNAPINDIR])), array('..', '.'));
            foreach($files AS $i => &$file) {
                if (!is_dir(rtrim($_SESSION[FOG_SNAPINDIR],'/').'/'.$file)) $filelist[] = $file;
            }
            unset($file);
        }
        sort($filelist);
        foreach((array)$filelist AS $i => &$file)
            $filesFound .= '<option value="'.basename($file).'" '.(basename($file) == basename($this->obj->get(file)) ? 'selected="selected"' : '').'>'. basename($file) .'</option>';
        unset($file);
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
        print '<div id="tab-container">';
        print '<!-- General -->';
        print '<div id="snap-gen">';
        print '<form method="post" action="'.$this->formAction.'&tab=snap-gen" enctype="multipart/form-data">';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
                snapin_id=>$this->obj->get(id),
                snapin_name=>$this->obj->get(name),
                snapin_desc=>$this->obj->get(description),
                snapin_rw=>$this->obj->get(runWith),
                snapin_rwa=>htmlentities($this->obj->get(runWithArgs)),
                snapin_args=>$this->obj->get(args),
                max_size=>ini_get(post_max_size),
                snapin_file=>$this->obj->get(file),
                snapin_filesexist=>$filesFound,
                snapin_protected=>$this->obj->get('protected') ? 'checked' : '',
                checked=>$this->obj->get(reboot) ? 'checked' : '',
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent(SNAPIN_EDIT,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        print '</form></div>';
        unset($this->data);
        print "<!-- Storage Groups with Assigned Snapin -->";
        // Get groups with this snapin assigned
        $GroupsWithMe = $this->getClass(StorageGroupManager)->find(array('id' => $this->obj->get(storageGroups)));
        $GroupsNotWithMe = $this->getClass(StorageGroupManager)->find(array('id' => $this->obj->get(storageGroups)),'','','','','',true);
        print '<div id="snap-storage">';
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
            array('class'=>'c filter-false',width=>16),
            array(),
        );
        // All Groups not with this set as the Snapin
        foreach((array)$GroupsNotWithMe AS $i => &$Group) {
            $this->data[] = array(
                storageGroup_id=>$Group->get(id),
                storageGroup_name=>$Group->get(name),
                check_num=>1,
            );
        }
        unset($Group);
        if (count($this->data) > 0) {
            $this->HookManager->processEvent(SNAPIN_GROUP_ASSOC,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
            print '<center><label for="groupMeShow">'._('Check here to see groups not assigned with this snapin').'&nbsp;&nbsp;<input type="checkbox" name="groupMeShow" id="groupMeShow" /></label><div id="groupNotInMe"><form method="post" action="'.$this->formAction.'&tab=snap-storage"><h2>'._('Modify group association for').' '.$this->obj->get(name).'</h2><p>'._('Add snapin to groups').'</p>';
            $this->render();
            print '<br/><input type="submit" value="'._('Add Snapin to Group(s)').'" /></form></center>';
        }
        // Reset the data for the next value
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Storage Group Name'),
        );
        $this->attributes = array(
            array(width=>16,'class'=>'c filter-false'),
            array('class'=>r),
        );
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" name="storagegroup-rm[]" value="${storageGroup_id}" />',
            '${storageGroup_name}',
        );
        foreach($GroupsWithMe AS $i => &$Group) {
            $this->data[] = array(
                storageGroup_id=>$Group->get(id),
                storageGroup_name=>$Group->get(name),
            );
        }
        unset($Group);
        // Hook
        $this->HookManager->processEvent(SNAPIN_EDIT_GROUP,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        print '<form method="post" action="'.$this->formAction.'&tab=snap-storage">';
        $this->render();
        if (count($this->data) > 0) print '<center><input type="submit" value="'._('Delete Selected Group associations').'" name="remstorgroups"/></center>';
        print '</form></div></div>';
    }
    public function edit_post() {
        // Find
        $Snapin = $this->obj;
        // Hook
        $this->HookManager->processEvent(SNAPIN_EDIT_POST,array(Snapin=>&$this->obj));
        // POST
        try {
            switch ($_REQUEST[tab]) {
                case 'snap-gen';
                // SnapinManager
                $SnapinManager = $this->getClass(SnapinManager);
                // Error checking
                if ($_REQUEST[snapin] || $_FILES[snapin][name]) {
                    if (!$this->obj->getStorageGroup()) {
                        $uploadfile = rtrim($_SESSION[FOG_SNAPINDIR],'/').'/'.basename($_FILES[snapin][name]);
                        if(!is_dir($_SESSION[FOG_SNAPINDIR]) && !is_writeable($_SESSION[FOG_SNAPINDIR])) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                        else if (!is_writeable($_SESSION[FOG_SNAPINDIR])) throw new Exception(_('Failed to add snapin, snapin directory is not writeable.'));
                        else if (file_exists($uploadfile)) throw new Exception(_('Failed to add snapin, file already exists.'));
                        else if (!move_uploaded_file($_FILES[snapin][tmp_name],$uploadfile)) throw new Exception(_('Failed to add snapin, file upload failed.'));
                    } else {
                        // Will fail if the storage group is not assigned or found.
                        $StorageNode = $this->obj->getStorageGroup()->getMasterStorageNode();
                        $src = $_FILES[snapin][tmp_name];
                        $dest = rtrim($StorageNode->get(snapinpath),'/').'/'.$_FILES[snapin][name];
                        $this->FOGFTP->set(host,$StorageNode->get(ip))
                            ->set(username,$StorageNode->get(user))
                            ->set(password,$StorageNode->get(pass));
                        if (!$this->FOGFTP->connect()) throw new Exception(_('Storage Node: '.$StorageNode->get(ip).' FTP Connection has failed!'));
                        if (!$this->FOGFTP->chdir($StorageNode->get(snapinpath))) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                        // Try to delete the file.
                        $this->FOGFTP->delete($dest);
                        if (!$this->FOGFTP->put($dest,$src)) throw new Exception(_('Failed to add snapin'));
                        $this->FOGFTP->close();
                    }
                }
                if ($_REQUEST[name] != $this->obj->get(name) && $this->obj->getManager()->exists($_REQUEST[name], $this->obj->get(id))) throw new Exception('Snapin already exists');
                // Update Object
                $this->obj
                    ->set(name,$_REQUEST[name])
                    ->set(description,$_REQUEST[description])
                    ->set('file',($_REQUEST[snapinfileexist] ? $_REQUEST[snapinfileexist] : ($_FILES[snapin][name] ? $_FILES[snapin][name] : $this->obj->get('file'))))
                    ->set(args,$_REQUEST[args])
                    ->set(reboot,(int)isset($_REQUEST[reboot]))
                    ->set(runWith,$_REQUEST[rw])
                    ->set(storageGroupID,$_REQUEST[storagegroup])
                    ->set(runWithArgs,$_REQUEST[rwa])
                    ->set('protected',$_REQUEST[protected_snapin]);
                break;
                case 'snap-storage';
                $this->obj->addGroup($_REQUEST[storagegroup]);
                if (isset($_REQUEST[remstorgroups])) $this->obj->removeGroup($_REQUEST['storagegroup-rm']);
                break;
            }
            // Save
            if (!$this->obj->save()) throw new Exception(_('Snapin update failed'));
            // Hook
            $this->HookManager->processEvent(SNAPIN_UPDATE_SUCCESS,array(Snapin=>&$this->obj));
            // Log History event
            $this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin updated'), $this->obj->get(id), $this->obj->get(name)));
            // Set session message
            $this->FOGCore->setMessage(_('Snapin updated'));
            // Redirect to new entry
            $this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s',$this->node, $this->id, $this->obj->get(id),$_REQUEST[tab]));
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent(SNAPIN_UPDATE_FAIL,array(Snapin=>&$this->obj));
            // Log History event
            $this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Snapin'), $_REQUEST[name], $e->getMessage()));
            // Set session message
            $this->FOGCore->setMessage($e->getMessage());
            // Redirect to new entry
            $this->FOGCore->redirect($this->formAction);
        }
    }
}
