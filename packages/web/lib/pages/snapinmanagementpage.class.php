<?php
class SnapinManagementPage extends FOGPage {
    public $node = 'snapin';
    public function __construct($name = '') {
        $this->name = 'Snapin Management';
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
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes,'object'=>&$this->obj,'linkformat'=>&$this->linkformat,'delformat'=>&$this->delformat,'membership'=>&$this->membership));
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Snapin Name'),
            _('Storage Group'),
            '',
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit')),
            '${storage_group}',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><i class="icon fa fa-pencil"></i></a> <a href="?node=%s&sub=delete&%s=${id}" title="%s"><i class="icon fa fa-minus-circle"></i></a>', $this->node, $this->id, _('Edit'), $this->node, $this->id, _('Delete'))
        );
        $this->attributes = array(
            array('class'=>'l filter-false','width'=>16),
            array(),
            array('class'=>'c','width'=>50),
            array('class'=>'r filter-false'),
        );
    }
    public function index() {
        $this->title = _('All Snap-ins');
        if ($this->getSetting('FOG_DATA_RETURNED') > 0 && $this->getClass('SnapinManager')->count() > $this->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        foreach ((array)$this->getClass('SnapinManager')->find() AS $i => &$Snapin) {
            if (!$Snapin->isValid()) continue;
            $this->data[] = array(
                'id'=>$Snapin->get('id'),
                'name'=>$Snapin->get('name'),
                'storage_group'=>$Snapin->getStorageGroup()->get('name'),
                'description'=>$Snapin->get('description'),
                'file'=>$Snapin->get('file')
            );
            unset($Snapin);
        }
        $this->HookManager->processEvent('SNAPIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        foreach ((array)$this->getClass('SnapinManager')->search('',true) AS $i => &$Snapin) {
            if (!$Snapin->isValid()) continue;
            $this->data[] = array(
                'id'=>$Snapin->get('id'),
                'name'=>$Snapin->get('name'),
                'storage_group'=>$Snapin->getStorageGroup()->get('name'),
                'description'=>$Snapin->get('description'),
                'file'=>$Snapin->get('file'),
            );
            unset($Snapin);
        }
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
        $this->templates = array(
            '${field}',
            '${input}',
        );
        foreach ((array)$this->getClass('StorageNodeManager')->find(array('isMaster'=>1,'isEnabled'=>1)) AS $i => $StorageNode) {
            if (!$StorageNode->isValid()) continue;
            $this->FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!$this->FOGFTP->connect()) continue;
            $filelist = $this->FOGFTP->nlist($StorageNode->get('snapinpath'));
            foreach ((array)$filelist AS $i => &$file) {
                if ($this->FOGFTP->chdir($file)) continue;
                $files[] = basename($file);
                unset($file);
            }
            $this->FOGFTP->close();
            unset($StorageNode,$filelist);
        }
        $files = array_filter(array_unique((array)$files));
        sort($files);
        ob_start();
        foreach ((array)$files AS $i => &$file) {
            printf('<option value="%s"%s>%s</option>',
                basename($file),
                (basename(htmlentities($_REQUEST['snapinfileexist'],ENT_QUOTES,'utf-8')) == basename($file) ? ' selected' : ''),
                basename($file)
            );
            unset($file);
        }
        $selectFiles = sprintf('<select class="cmdlet3" name="snapinfileexist"><span class="lightColor"><option value="">- %s -</option>%s</select>',_('Please select an option'),ob_get_clean());
        $fields = array(
            _('Snapin Name') => sprintf('<input type="text" name="name" value="%s"/>',$_REQUEST['name']),
            _('Snapin Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$_REQUEST['description']),
            _('Snapin Storage Group') => $this->getClass('StorageGroupManager')->buildSelectBox($_REQUEST['storagegroup']),
            _('Snapin Run With') => sprintf('<input class="cmdlet1" type="text" name="rw" value="%s"/>',$_REQUEST['rw']),
            _('Snapin Run With Argument') => sprintf('<input class="cmdlet2" type="text" name="rwa" value="%s"/>',$_REQUEST['rwa']),
            sprintf('%s <span class="lightColor">%s:%s</span>',_('Snapin File'),_('Max Size'),ini_get('post_max_size')) => sprintf('<input class="cmdlet3" name="snapin" value="%s" type="file"/>',$_FILES['snapin']),
            (count($files) > 0 ? _('Snapin File (exists)') : '') => (count($files) > 0 ? $selectFiles : ''),
            _('Snapin Arguments') => sprintf('<input class="cmdlet4" type="text" name="args" value="%s"/>',$_REQUEST['args']),
            _('Snapin Enabled') => '<input type="checkbox" name="isEnabled" value="1"checked/>',
            _('Replicate?') => '<input type="checkbox" name="toReplicate" value="1" checked/>',
            _('Reboot after install') => '<input type="checkbox" name="reboot"/>',
            _('Snapin Command') => '<textarea class="snapincmd" disabled></textarea>',
            '&nbsp;' => sprintf('<input name="add" type="submit" value="%s"/>',_('Add'))
        );
        unset($files,$selectFiles);
        printf('<form method="post" action"%s" enctype="multipart/form-data">',$this->formAction);
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        $this->HookManager->processEvent('SNAPIN_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
        unset($this->data,$this->templates,$this->attributes,$this->headerData);
    }
    public function add_post() {
        $this->HookManager->processEvent('SNAPIN_ADD_POST');
        try {
            $SnapinManager = $this->getClass('SnapinManager');
            $snapinName = $_REQUEST['name'];
            if (!$snapinName) throw new Exception(_('Please enter a name to give this Snapin'));
            if ($SnapinManager->exists($snapinName)) throw new Exception(_('Snapin already exists'));
            if (!$_REQUEST['storagegroup']) throw new Exception(_('Please select a storage group for this snapin to reside in'));
            if (!$_REQUEST['snapinfileexist'] && !$_FILES['snapin']) throw new Exception(_('Missing snapin file information'));
            if (!$_REQUEST['storagegroup']) throw new Exception(_('Must have snapin associated to a group'));
            $StorageNode = $this->getClass('StorageGroup',$_REQUEST['storagegroup'])->getMasterStorageNode();
            $src = $_FILES['snapin']['tmp_name'];
            $dest = sprintf('/%s/%s',trim($StorageNode->get('snapinpath'),'/'),$_FILES['snapin']['name']);
            echo $dest;
            $this->FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!$this->FOGFTP->connect()) throw new Exception(sprintf('%s: %s %s',_('Storage Node'),$StorageNode->get('ip'),_('FTP Connection has failed')));
            if (!$this->FOGFTP->chdir($StorageNode->get('snapinpath'))) {
                if (!$this->FOGFTP->mkdir($StorageNode->get('snapinpath'))) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
            }
            if ( $_FILES['snapin']['name'] != "" ) {
                $this->FOGFTP->delete($dest);
            }
            if (!$this->FOGFTP->put($dest,$src)) throw new Exception(_('Failed to add snapin'));
            $this->FOGFTP->close();
            $Snapin = $this->getClass('Snapin')
                ->set('name',$snapinName)
                ->set('description',$_REQUEST['description'])
                ->set('file',$_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : $_FILES['snapin']['name'])
                ->set('args',$_REQUEST['args'])
                ->set('reboot',(int)isset($_REQUEST['reboot']))
                ->set('runWith',$_REQUEST['rw'])
                ->set('runWithArgs',$_REQUEST['rwa'])
                ->set('isEnabled',(int) isset($_REQUEST['isEnabled']))
                ->set('toReplicate',(int) isset($_REQUEST['toReplicate']))
                ->addGroup($_REQUEST['storagegroup']);
            if (!$Snapin->save()) throw new Exception(_('Add snapin failed!'));
            $this->HookManager->processEvent('SNAPIN_ADD_SUCCESS',array('Snapin'=>&$Snapin));
            $this->setMessage(_('Snapin added, Editing now!'));
            $this->redirect(sprintf('?node=%s&sub=edit&%s=%s', $_REQUEST['node'],$this->id,$Snapin->get('id')));
        } catch (Exception $e) {
            $this->HookManager->processEvent('SNAPIN_ADD_FAIL',array('Snapin'=>&$Snapin));
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        $this->title = sprintf('%s: %s',_('Edit'),$this->obj->get('name'));
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        foreach ((array)$this->getClass('StorageNodeManager')->find(array('storageGroupID'=>$this->obj->get('storageGroups'),'isEnabled'=>1,'isMaster'=>1)) AS $i => &$StorageNode) {
            if (!$StorageNode->isValid()) continue;
            $this->FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!$this->FOGFTP->connect()) continue;
            $filelist = $this->FOGFTP->nlist($StorageNode->get('snapinpath'));
            foreach ((array)$filelist AS $i => &$file) {
                if ($this->FOGFTP->chdir($file)) continue;
                $files[] = basename($file);
                unset($file);
            }
            $this->FOGFTP->close();
            unset($StorageNode,$filelist);
        }
        $files = array_filter(array_unique((array)$files));
        sort($files);
        ob_start();
        foreach ((array)$files AS $i => &$file) {
            printf('<option value="%s"%s>%s</option>',
                basename($file),
                (basename($this->obj->get('file')) == basename($file) ? ' selected' : ''),
                basename($file)
            );
            unset($file);
        }
        $selectFiles = sprintf('<select class="cmdlet3" name="snapinfileexist"><span class="lightColor"><option value="">- %s -</option>%s</select>',_('Please select an option'),ob_get_clean());
        $fields = array(
            _('Snapin Name') => sprintf('<input type="text" name="name" value="%s"/>',$this->obj->get('name')),
            _('Snapin Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            _('Snapin Run With') => sprintf('<input class="cmdlet1" type="text" name="rw" value="%s"/>',$this->obj->get('runWith')),
            _('Snapin Run With Argument') => sprintf('<input class="cmdlet2" type="text" name="rwa" value="%s"/>',$this->obj->get('runWithArgs')),
            sprintf('%s <span class="lightColor">%s:%s</span>',_('Snapin File'),_('Max Size'),ini_get('post_max_size')) => sprintf('<label id="uploader" for="snapin-uploader">%s<a href="#" id="snapin-upload"> <i class="fa fa-arrow-up noBorder"></i></a></label>',basename($this->obj->get('file'))),
            (count($files) > 0 ? _('Snapin File (exists)') : '') => (count($files) > 0 ? $selectFiles : ''),
            _('Snapin Arguments') => sprintf('<input class="cmdlet4" type="text" name="args" value="%s"/>',$this->obj->get('args')),
            _('Protected') => sprintf('<input type="checkbox" name="protected_snapin" value="1"%s/>',$this->obj->get('protected') ? ' checked' : ''),
            _('Reboot after install') => sprintf('<input type="checkbox" name="reboot"%s/>',($this->obj->get('reboot') ? ' checked' : '')),
            _('Snapin Enabled') => sprintf('<input type="checkbox" name="isEnabled" value="1"%s/>',$this->obj->get('isEnabled') ? ' checked' : ''),
            _('Replicate?') => sprintf('<input type="checkbox" name="toReplicate" value="1"%s/>',$this->obj->get('toReplicate') ? ' checked' : ''),
            _('Snapin Command') => '<textarea class="snapincmd" disabled></textarea>',
            '' => sprintf('<input name="update" type="submit" value="%s"/>',_('Update')),
        );
        echo '<div id="tab-container"><!-- General --><div id="snap-gen">';
        echo '<form method="post" action="'.$this->formAction.'&tab=snap-gen" enctype="multipart/form-data">';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        unset($input);
        $this->HookManager->processEvent('SNAPIN_EDIT',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s&tab=snap-gen" enctype="multipart/form-data">',$this->formAction);
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
            '<input type="checkbox" name="storagegroup[]" value="${storageGroup_id}" class="toggle-snapin${check_num}"/>',
            '${storageGroup_name}',
        );
        $this->attributes = array(
            array('class'=>'l filter-false','width'=>16),
            array(),
        );
        foreach ((array)$this->getClass('StorageGroupManager')->find(array('id'=>$this->obj->get('storageGroupsnotinme'))) AS $i => &$StorageGroup) {
            if (!$StorageGroup->isValid()) continue;
            $this->data[] = array(
                'storageGroup_id' => $StorageGroup->get('id'),
                'storageGroup_name' => $StorageGroup->get('name'),
            );
            unset($StorageGroup);
        }
        if (count($this->data) > 0) {
            $this->HookManager->processEvent('SNAPIN_GROUP_ASSOC',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
            printf('<p class="c"><label for="groupMeShow">%s&nbsp;&nbsp;<input type="checkbox" name="groupMeShow" id="groupMeShow"/></label><div id="groupNotInMe"><form method="post" action="%s&tab=snap-storage"><h2>%s %s</h2><p class="c">%s</p>',
                _('Check here to see groups not assigned with this snapin'),
                $this->formAction,
                _('Modify group association for'),
                $this->obj->get('name'),
                _('Add snapin to groups')
            );
            $this->render();
            printf('<br/><input type="submit" value="%s"/></form></div></p>',_('Add Snapin to Group(s)'));
        }
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            '',
            _('Storage Group Name'),
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>22,'class'=>'l filter-false'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" name="storagegroup-rm[]" value="${storageGroup_id}"/>',
            sprintf('<input class="primary" type="radio" name="primary" id="group${storageGroup_id}" value="${storageGroup_id}"${is_primary}/><label for="group${storageGroup_id}" class="icon icon-hand" title="%s">&nbsp;</label>',_('Primary Group Selector')),
            '${storageGroup_name}',
        );
        $GroupIDs = $this->obj->get('storageGroups');
        foreach ((array)$this->getClass('StorageGroupManager')->find(array('id'=>$this->obj->get('storageGroups'))) AS $i => &$StorageGroup) {
            if (!$StorageGroup->isValid()) continue;
            $this->data[] = array(
                'storageGroup_id' => $StorageGroup->get('id'),
                'storageGroup_name' => $StorageGroup->get('name'),
                'is_primary' => ($this->obj->getPrimaryGroup($StorageGroup->get('id')) ? ' checked' : ''),
            );
            unset($StorageGroup);
        }
        $this->HookManager->processEvent('SNAPIN_EDIT_GROUP',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s&tab=snap-storage">',$this->formAction);
        $this->render();
        if (count($this->data) > 0) printf('<p class="c"><input name="update" type="submit" value="%s"/>&nbsp;<input name="deleteGroup" type="submit" value="%s"/></p>',_('Update Primary Group'),_('Deleted selected group associations'));
        echo '</form></div></div>';
    }
    public function edit_post() {
        $this->HookManager->processEvent('SNAPIN_EDIT_POST',array('Snapin'=>&$this->obj));
        try {
            switch ($_REQUEST['tab']) {
            case 'snap-gen':
                if (!$_REQUEST['snapinfileexist'] && !$_FILES['snapin']) throw new Exception(_('Missing snapin file information'));
                if (!$this->obj->getStorageGroup()) throw new Exception(_('Must have snapin associated to a group'));
                if (!$_REQUEST['name']) throw new Exception(_('Snapin name must not be empty'));
                if ($_REQUEST['name'] != $this->obj->get('name') && $this->obj->getManager()->exists($_REQUEST['name'], $this->obj->get('id'))) throw new Exception(_('Snapin already exists'));
                $StorageNode = $this->obj->getStorageGroup()->getMasterStorageNode();
                $snapinfile = $_REQUEST['snapinfileexist'];
                if ($_FILES['snapin']['name']) {
                    $src = $_FILES['snapin']['tmp_name'];
                    $dest = sprintf('/%s/%s',trim($StorageNode->get('snapinpath'),'/'),$_FILES['snapin']['name']);
                    $this->FOGFTP
                        ->set('host',$StorageNode->get('ip'))
                        ->set('username',$StorageNode->get('user'))
                        ->set('password',$StorageNode->get('pass'));
                    if (!$this->FOGFTP->connect()) throw new Exception(sprintf('%s: %s: %s %s: %s %s',_('Storage Node'),$StorageNode->get('ip'),_('FTP connection has failed!')));
                    if (!$this->FOGFTP->chdir($StorageNode->get('snapinpath'))) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                    $this->FOGFTP->delete($dest);
                    if (!$this->FOGFTP->put($dest,$src)) throw new Exception(_('Failed to add snapin'));
                    $this->FOGFTP->close();
                    $snapinfile = $_FILES['snapin']['name'];
                }
                $this->obj
                    ->set('name',$_REQUEST['name'])
                    ->set('description',$_REQUEST['description'])
                    ->set('file',$snapinfile ? $snapinfile : $this->obj->get('file'))
                    ->set('args',$_REQUEST['args'])
                    ->set('reboot',(int)isset($_REQUEST['reboot']))
                    ->set('runWith',$_REQUEST['rw'])
                    ->set('runWithArgs',$_REQUEST['rwa'])
                    ->set('protected',$_REQUEST['protected_snapin'])
                    ->set('isEnabled',(int) isset($_REQUEST['isEnabled']))
                    ->set('toReplicate',(int) isset($_REQUEST['toReplicate']));
                break;
            case 'snap-storage':
                $this->obj->addGroup($_REQUEST['storagegroup']);
                if (isset($_REQUEST['update'])) $this->obj->setPrimaryGroup($_REQUEST['primary']);
                if (isset($_REQUEST['deleteGroup'])) {
                    if (count($this->obj->get('storageGroups')) < 2) throw new Exception(_('Snapin must be assigned to one Storage Group'));
                    $this->obj->removeGroup($_REQUEST['storagegroup-rm']);
                }
                break;
            }
            if (!$this->obj->save()) throw new Exception(_('Snapin update failed'));
            $this->HookManager->processEvent('SNAPIN_UPDATE_SUCCESS',array('Snapin'=>&$this->obj));
            $this->setMessage(_('Snapin updated'));
            $this->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s',$this->node, $this->id, $this->obj->get('id'),$_REQUEST['tab']));
        } catch (Exception $e) {
            $this->HookManager->processEvent('SNAPIN_UPDATE_FAIL',array('Snapin'=>&$this->obj));
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
