<?php
class SnapinManagementPage extends FOGPage {
    public $node = 'snapin';
    public function __construct($name = '') {
        $this->name = 'Snapin Management';
        // Call parent constructor
        parent::__construct($name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat#snap-gen" => $this->foglang['General'],
                "$this->linkformat#snap-storage" => "{$this->foglang['Storage']} {$this->foglang['Group']}",
                $this->membership => $this->foglang['Membership'],
                $this->delformat => $this->foglang['Delete'],
            );
            $this->notes = array(
                $this->foglang['Snapin'] => $this->obj->get('name'),
                $this->foglang['File'] => $this->obj->get('file'),
            );
        }
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
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
            array('class'=>'c filter-false','width'=>16),
            array(),
            array('class'=>'c','width'=>50),
            array('class'=>'r filter-false'),
        );
    }
    // Pages
    public function index() {
        $this->title = _('All Snap-ins');
        if ($this->getSetting('FOG_DATA_RETURNED') > 0 && $this->getClass('SnapinManager')->count() > $this->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
            $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $ids = $this->getSubObjectIDs('Snapin');
        foreach ((array)$ids AS $i => &$id) {
            $Snapin = $this->getClass('Snapin',$id);
            if (!$Snapin->isValid()) {
                unset($Snapin);
                continue;
            }
            $this->data[] = array(
                'id'=>$Snapin->get('id'),
                'name'=>$Snapin->get('name'),
                'storage_group'=>$Snapin->getStorageGroup()->get('name'),
                'description'=>$Snapin->get('description'),
                'file'=>$Snapin->get('file')
            );
        }
        unset($Snapin);
        // Hook
        $this->HookManager->processEvent('SNAPIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        // Output
        $this->render();
    }
    public function search_post() {
        $ids = $this->getClass('SnapinManager')->search();
        foreach ((array)$ids AS $i => &$id) {
            $Snapin = $this->getClass('Snapin',$id);
            if (!$Snapin->isValid()) {
                unset($Snapin);
                continue;
            }
            $this->data[] = array(
                'id'=>$Snapin->get('id'),
                'name'=>$Snapin->get('name'),
                'storage_group'=>$Snapin->getStorageGroup()->get('name'),
                'description'=>$Snapin->get('description'),
                'file'=>$Snapin->get('file')
            );
        }
        unset($Snapin);
        $this->HookManager->processEvent('SNAPIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = _('Add New Snapin');
        unset($this->headerData);
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
        foreach($files AS $i => &$file) {
            if (!is_dir(rtrim($_SESSION['FOG_SNAPINDIR'],'/').'/'.$file)) $filelist[] = $file;
        }
        unset($file);
        if ($filelist && is_array($filelist)) sort($filelist);
        foreach((array)$filelist AS $i => &$file) $filesFound .= '<option value="'.basename($file).'"'.(basename($_REQUEST['snapinfileexist']) == basename($file) ? 'selected="selected"' : '').'>'.basename($file).'</option>';
        unset($file);
        // Fields to work from:
        $fields = array(
            _('Snapin Name') => '<input type="text" name="name" value="'.$_REQUEST['name'].'" />',
            _('Snapin Description') => '<textarea name="description" rows="8" cols="40">'.$_REQUEST['description'].'</textarea>',
            _('Snapin Storage Group') => $this->getClass('StorageGroupManager')->buildSelectBox($_REQUEST['storagegroup']),
            _('Snapin Run With') => '<input class="cmdlet1" type="text" name="rw" value="'.$_REQUEST['rw'].'" />',
            _('Snapin Run With Argument') => '<input class="cmdlet2" type="text" name="rwa" value="'.$_REQUEST['rwa'].'" />',
            _('Snapin File').' <span class="lightColor">'._('Max Size').':'.ini_get('post_max_size').'</span>' => '<input class="cmdlet3" type="file" name="snapin" value="'.$_FILES['snapin'].'"/>',
            (count($files) > 0 ?_('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select class="cmdlet3" name="snapinfileexist"><span class="lightColor"><option value="">- '._('Please select an option').'-</option>'.$filesFound.'</select>' : null),
            _('Snapin Arguments') => '<input class="cmdlet4" type="text" name="args" value="'.$_REQUEST['args'].'"/>',
            _('Reboot after install') => '<input type="checkbox" name="reboot" />',
            _('Snapin Command') => '<textarea class="snapincmd" disabled></textarea>',
            '' => '<input name="add" type="submit" value="'._('Add').'" />',
        );
        echo '<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent('SNAPIN_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        // Output
        $this->render();
        echo '</form>';
        unset($this->data,$this->templates,$this->attributes,$this->headerData);
    }
    public function add_post() {
        // Hook
        $this->HookManager->processEvent('SNAPIN_ADD_POST');
        // POST
        try {
            // SnapinManager
            $SnapinManager = $this->getClass('SnapinManager');
            // Error checking
            $snapinName = trim($_REQUEST['name']);
            if (!$snapinName) throw new Exception(_('Please enter a name to give this Snapin'));
            if ($SnapinManager->exists($snapinName)) throw new Exception(_('Snapin already exists'));
            if (!$_REQUEST['storagegroup']) throw new Exception(_('Please select a storage group for this snapin to reside in'));
            if ($_REQUEST['snapin'] || $_FILES['snapin']['name']) {
                if (!$_REQUEST['storagegroup']) throw new Exception(_('Must have snapin associated to a group'));
                $StorageNode = $this->getClass('StorageGroup',$_REQUEST['storagegroup'])->getMasterStorageNode();
                $src = $_FILES['snapin']['tmp_name'];
                $dest = rtrim($StorageNode->get('snapinpath'),'/').'/'.$_FILES['snapin']['name'];
                $this->FOGFTP
                    ->set('host',$StorageNode->get('ip'))
                    ->set('username',$StorageNode->get('user'))
                    ->set('password',$StorageNode->get('pass'));
                if (!$this->FOGFTP->connect()) throw new Exception(_('Storage Node: '.$StorageNode->get('ip').' FTP Connection has failed!'));
                if (!$this->FOGFTP->chdir($StorageNode->get('snapinpath'))) {
                    if (!$this->FOGFTP->mkdir($StorageNode->get('snapinpath'))) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                }
                // Try to delete the file.
                $this->FOGFTP->delete($dest);
                if (!$this->FOGFTP->put($dest,$src)) throw new Exception(_('Failed to add snapin'));
                $this->FOGFTP->close();
            } else if (empty($_REQUEST['snapinfileexist'])) throw new Exception('Failed to add snapin, no file was uploaded or selected for use');
            // Create new Object
            $Snapin = $this->getClass('Snapin')
                ->set('name',$snapinName)
                ->set('description',$_REQUEST['description'])
                ->set('file',$_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : $_FILES['snapin']['name'])
                ->set('args',$_REQUEST['args'])
                ->set('reboot',(int)isset($_REQUEST['reboot']))
                ->set('runWith',$_REQUEST['rw'])
                ->set('runWithArgs',$_REQUEST['rwa'])
                ->addGroup($_REQUEST['storagegroup']);
            // Save
            if (!$Snapin->save()) throw new Exception(_('Add snapin failed!'));
            // Hook
            $this->HookManager->processEvent('SNAPIN_ADD_SUCCESS',array('Snapin'=>&$Snapin));
            // Set session message
            $this->setMessage('Snapin added, Editing now!');
            // Redirect to new entry
            $this->redirect(sprintf('?node=%s&sub=edit&%s=%s', $_REQUEST['node'],$this->id,$Snapin->get('id')));
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent('SNAPIN_ADD_FAIL',array('Snapin'=>&$Snapin));
            // Set session message
            $this->setMessage($e->getMessage());
            // Redirect to new entry
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if ($this->obj->get('storageGroups')) {
            $StorageGroups = $this->getClass('StorageGroup')->getManager()->find(array('id'=>$this->obj->get('storageGroups')));
            foreach($StorageGroups AS $i => &$StorageGroup) {
                $StorageNode = $StorageGroup->getMasterStorageNode();
                if (!$StorageNode->isValid()) continue;
                $this->FOGFTP
                    ->set('host',$StorageNode->get('ip'))
                    ->set('username',$StorageNode->get('user'))
                    ->set('password',$StorageNode->get('pass'))
                    ->connect();
                $filelist = $this->FOGFTP->nlist($StorageNode->get('snapinpath'));
                foreach($filelist AS $i => &$file) if (!$this->FOGFTP->chdir($file)) $files[] = basename($file);
                unset($file);
                $this->FOGFTP->close();
            }
            unset($filelist);
            $filelist = $files;
        } else {
            $files = array_diff(preg_grep('#^([^.])#',scandir($_SESSION['FOG_SNAPINDIR'])), array('..', '.'));
            foreach($files AS $i => &$file) {
                if (!is_dir(rtrim($_SESSION['FOG_SNAPINDIR'],'/').'/'.$file)) $filelist[] = $file;
            }
            unset($file);
        }
        sort($filelist);
        foreach((array)$filelist AS $i => &$file)
            $filesFound .= '<option value="'.basename($file).'" '.(basename($file) == basename($this->obj->get('file')) ? 'selected="selected"' : '').'>'. basename($file) .'</option>';
        unset($file);
        $fields = array(
            _('Snapin Name') => '<input type="text" name="name" value="'.$this->obj->get('name').'" />',
            _('Snapin Description') => '<textarea name="description" rows="8" cols="40">'.$this->obj->get('description').'</textarea>',
            _('Snapin Run With') => '<input class="cmdlet1" type="text" name="rw" value="'.$this->obj->get('runWith').'" />',
            _('Snapin Run With Argument') => '<input class="cmdlet2" type="text" name="rwa" value="'.$this->obj->get('runWithArgs').'" />',
            _('Snapin File').' <span class="lightColor">'._('Max Size').':'.ini_get('post_max_size').'</span>' => '<label id="uploader" for="snapin-uploader">'.$this->obj->get('file').'<a href="#" id="snapin-upload">&nbsp;<i class="fa fa-arrow-up noBorder"></i></a></label>',
            (count($files) > 0 ? _('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select class="cmdlet3" name="snapinfileexist"><span class="lightColor"><option value="">- '._('Please select an option').'-</option>'.$filesFound.'</select>' : null),
            _('Snapin Arguments') => '<input class="cmdlet4" type="text" name="args" value="'.$this->obj->get('args').'" />',
            _('Protected') => '<input type="checkbox" name="protected_snapin" value="1"'.($this->obj->get('protected') ? ' checked' : '').'/>',
            _('Reboot after install') => '<input type="checkbox" name="reboot" '.($this->obj->get('reboot') ? ' checked' : '').'/>',
            _('Snapin Command') => '<textarea class="snapincmd" disabled></textarea>',
            '' => '<input name="update" type="submit" value="'._('Update').'" />',
        );
        echo '<div id="tab-container">';
        echo '<!-- General -->';
        echo '<div id="snap-gen">';
        echo '<form method="post" action="'.$this->formAction.'&tab=snap-gen" enctype="multipart/form-data">';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        unset($input);
        $this->HookManager->processEvent('SNAPIN_EDIT',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form></div>';
        unset($this->data);
        echo "<!-- Snapin Groups -->";
        echo '<div id="snap-storage">';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapin1" class="toggle-checkbox1"/>',
            _('Storage Group Name'),
        );
        $this->templates = array(
            '<input type="checkbox" name="storagegroup[]" value="${storageGroup_id}" class="toggle-snapin${check_num}" />',
            '${storageGroup_name}',
        );
        $this->attributes = array(
            array('class'=>'c filter-false','width'=>16),
            array(),
        );
        $GroupIDs = $this->obj->get('storageGroupsnotinme');
        foreach ((array)$GroupIDs AS $i => &$id) {
            if (!$this->getClass('StorageGroup',$id)->isValid()) continue;
            $Group = $this->getClass('StorageGroup',$id);
            $name = $Group->get('name');
            $this->data[] = array(
                'storageGroup_id' => $id,
                'storageGroup_name' => $name,
            );
            unset($Group,$name);
        }
        unset($id,$GroupIDs);
        if (count($this->data) > 0) {
            $this->HookManager->processEvent('SNAPIN_GROUP_ASSOC',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
            echo '<center><label for="groupMeShow">'._('Check here to see groups not assigned with this snapin').'&nbsp;&nbsp;<input type="checkbox" name="groupMeShow" id="groupMeShow" /></label><div id="groupNotInMe"><form method="post" action="'.$this->formAction.'&tab=snap-storage"><h2>'._('Modify group association for').' '.$this->obj->get('name').'</h2><p>'._('Add snapin to groups').'</p>';
            $this->render();
            echo '<br/><input type="submit" value="'._('Add Snapin to Group(s)').'" /></form></center>';
        }
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            '',
            _('Storage Group Name'),
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>22,'class'=>'l filter-false'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" name="storagegroup-rm[]" value="${storageGroup_id}" />',
            '<input class="primary" type="radio" name="primary" id="group${storageGroup_id}" value="${storageGroup_id}" ${is_primary}/><label for ="group${storageGroup_id}" class="icon icon-hand" title="'._('Primary Group Selector').'">&nbsp;</label>',
            '${storageGroup_name}',
        );
        $GroupIDs = $this->obj->get('storageGroups');
        foreach ((array)$GroupIDs AS $i => &$id) {
            if (!$this->getClass('StorageGroup',$id)->isValid()) continue;
            $Group = $this->getClass('StorageGroup',$id);
            $name = $Group->get('name');
            $is_primary = $this->obj->getPrimaryGroup($id) ? 'checked' : '';
            $this->data[] = array(
                'storageGroup_id' => $id,
                'storageGroup_name' => $name,
                'is_primary' => $is_primary,
            );
            unset($Group,$name,$is_primary);
        }
        unset($id,$GroupIDs);
        $this->HookManager->processEvent('SNAPIN_EDIT_GROUP',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        echo '<form method="post" action="'.$this->formAction.'&tab=snap-storage">';
        $this->render();
        if (count($this->data) > 0) echo '<center><input name="update" type="submit" value="'._('Update Primary Group').'"/>&nbsp;<input name="deleteGroup" type="submit" value="'._('Delete Selected Group associations').'" name="remstorgroups"/></center>';
        echo '</form></div></div>';
    }
    public function edit_post() {
        // Hook
        $this->HookManager->processEvent('SNAPIN_EDIT_POST',array('Snapin'=>&$this->obj));
        try {
            switch ($_REQUEST['tab']) {
                case 'snap-gen':
                // SnapinManager
                $SnapinManager = $this->getClass('SnapinManager');
                // Error checking
                if ($_REQUEST['snapin'] || $_FILES['snapin']['name']) {
                    if (!$this->obj->getStorageGroup()) throw new Exception(_('Must have snapin associated to a group'));
                    $StorageNode = $this->obj->getStorageGroup()->getMasterStorageNode();
                    $src = $_FILES['snapin']['tmp_name'];
                    $dest = rtrim($StorageNode->get('snapinpath'),'/').'/'.$_FILES['snapin']['name'];
                    $this->FOGFTP
                        ->set('host',$StorageNode->get('ip'))
                        ->set('username',$StorageNode->get('user'))
                        ->set('password',$StorageNode->get('pass'));
                    if (!$this->FOGFTP->connect()) throw new Exception(_('Storage Node: '.$StorageNode->get('ip').' FTP Connection has failed!'));
                    if (!$this->FOGFTP->chdir($StorageNode->get('snapinpath'))) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                    // Try to delete the file.
                    $this->FOGFTP->delete($dest);
                    if (!$this->FOGFTP->put($dest,$src)) throw new Exception(_('Failed to add snapin'));
                    $this->FOGFTP->close();
                }
                if ($_REQUEST['name'] != $this->obj->get('name') && $this->obj->getManager()->exists($_REQUEST['name'], $this->obj->get('id'))) throw new Exception('Snapin already exists');
                // Update Object
                $this->obj
                    ->set('name',$_REQUEST['name'])
                    ->set('description',$_REQUEST['description'])
                    ->set('file',($_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : ($_FILES['snapin']['name'] ? $_FILES['snapin']['name'] : $this->obj->get('file'))))
                    ->set('args',$_REQUEST['args'])
                    ->set('reboot',(int)isset($_REQUEST['reboot']))
                    ->set('runWith',$_REQUEST['rw'])
                    ->set('runWithArgs',$_REQUEST['rwa'])
                    ->set('protected',$_REQUEST['protected_snapin']);
                break;
                case 'snap-storage':
                    $this->obj->addGroup($_REQUEST['storagegroup']);
                    if (isset($_REQUEST['update'])) $this->obj->setPrimaryGroup($_REQUEST['primary']);
                    if (isset($_REQUEST['deleteGroup']) && isset($_REQUEST['remstorgroups'])) {
                        if (count($this->obj->get('storageGroups')) < 2) throw new Exception(_('Snapin must be assigned to one Storage Group'));
                        $this->obj->removeGroup($_REQUEST['storagegroup-rm']);
                    }
                    break;
            }
            // Save
            if (!$this->obj->save()) throw new Exception(_('Snapin update failed'));
            // Hook
            $this->HookManager->processEvent('SNAPIN_UPDATE_SUCCESS',array('Snapin'=>&$this->obj));
            // Set session message
            $this->setMessage(_('Snapin updated'));
            // Redirect to new entry
            $this->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s',$this->node, $this->id, $this->obj->get('id'),$_REQUEST['tab']));
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent('SNAPIN_UPDATE_FAIL',array('Snapin'=>&$this->obj));
            // Set session message
            $this->setMessage($e->getMessage());
            // Redirect to new entry
            $this->redirect($this->formAction);
        }
    }
}
