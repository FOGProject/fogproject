<?php
class SnapinManagementPage extends FOGPage {
    private static $argTypes;
    public $node = 'snapin';
    public function __construct($name = '') {
        $this->name = 'Snapin Management';
        parent::__construct($name);
        self::$argTypes = array(
            'MSI' => array('msiexec.exe','/i','/quiet'),
            'Batch Script' => array('cmd.exe','/c'),
            'Bash Script' => array('/bin/bash'),
            'VB Script' => array('cscript.exe'),
            'Powershell' => array('powershell.exe','-ExecutionPolicy Bypass -NoProfile -File'),
            'Mono' => array('mono'),
            'SnapinPack' => array(),
        );
        $this->menu['maker'] = _('SnapinPack Config');
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat#snap-gen" => self::$foglang['General'],
                "$this->linkformat#snap-storage" => sprintf('%s %s',self::$foglang['Storage'],self::$foglang['Group']),
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Snapin'] => $this->obj->get('name'),
                self::$foglang['File'] => $this->obj->get('file'),
            );
        }
        self::$HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes,'object'=>&$this->obj,'linkformat'=>&$this->linkformat,'delformat'=>&$this->delformat,'membership'=>&$this->membership));
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Snapin Name'),
            _('Storage Group'),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit')),
            '${storage_group}',
        );
        $this->attributes = array(
            array('class'=>'l filter-false','width'=>16),
            array(),
            array('class'=>'c','width'=>50),
        );
        self::$returnData = function(&$Snapin) {
            if (!$Snapin->isValid()) return;
            $this->data[] = array(
                'id' => $Snapin->get('id'),
                'name' => $Snapin->get('name'),
                'storage_group' => $Snapin->getStorageGroup()->get('name'),
                'description' => $Snapin->get('description'),
                'file' => $Snapin->get('file'),
            );
            unset($Snapin);
        };
    }
    public function maker() {
        $this->title = _('SnapinPack Configuration Generator');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        printf('<p>%s ',_('The following form helps define how a SnapinPack is run.'));
        printf('%s <a href="https://wiki.fogproject.org/wiki/index.php?title=SnapinPacks">%s</a> %s</p><br/>',_('Please see'),_('here'),_('to learn more.'));
        echo '<div id="snapintemplate">';
        $args = array(
            'MSI'=>array('msiexec.exe','/i &quot;[FOG_SNAPIN_PATH]\MyMSI.msi&quot;'),
            'MSI + MST'=>array('msiexec.exe','/i &quot;[FOG_SNAPIN_PATH]\MyMST.mst&quot;'),
            'Batch Script'=>array('cmd.exe','/c &quot;[FOG_SNAPIN_PATH]\MyScript.bat&quot;'),
            'Bash Script'=>array('/bin/bash','&quot;[FOG_SNAPIN_PATH]/MyScript.sh&quot;'),
            'VB Script'=>array('cscript.exe','&quot;[FOG_SNAPIN_PATH]\MyScript.vbs&quot;'),
            'PowerShell Script'=>array('powershell.exe','-ExecutionPolicy Bypass -File &quot;[FOG_SNAPIN_PATH]\MyScript.ps1&quot;'),
            'EXE'=>array('[FOG_SNAPIN_PATH]\MyFile.exe'),
            'Mono'=>array('mono','&quot;[FOG_SNAPIN_PATH]/MyFile.exe&quot;'),
        );
        ob_start();
        printf('<select id="argTypes"><option value="">- %s -</option>',_('Please select an option'));
        array_walk($args,function(&$cmd,&$type) {
            printf('<option file="%s" args="%s">%s</option>',$cmd[0],isset($cmd[1]) ? $cmd[1] : '',$type);
        });
        echo '</select>';
        $template = ob_get_clean();
        $fields = array(
            _('SnapinPack Template') => $template,
            _('SnapinPack Name') => sprintf('<input type="text" id="snapinpack-name" value="%s" name="snapinpack-name"/>',$_REQUEST['snapinpack-name']),
            _('SnapinPack Version') => sprintf('<input type="text" id="snapinpack-version" value="%s" name="snapinpack-version"/>',$_REQUEST['snapinpack-version']),
            _('SnapinPack File') => sprintf('<input type="text" id="snapinpack-file" value="%s" name="snapinpack-file"/>',$_REQUEST['snapinpack-file']),
            _('SnapinPack Arguments') => sprintf('<input type="text" id="snapinpack-arguments" value="%s" name="snapinpack-arguments"/>',$_REQUEST['snapinpack-arguments']),
            '&nbsp;' => sprintf('<input type="submit" class="snapinpack-generate" value="%s"/>',_('Generate')),
        );
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager->processEvent('SNAPINPACK_GENERATOR',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data,$this->templates,$this->attributes,$this->headerData);
        echo '</div>';
    }
    public function index() {
        $this->title = _('All Snap-ins');
        if (self::getSetting('FOG_DATA_RETURNED') > 0 && self::getClass('SnapinManager')->count() > self::getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $this->data = array();
        array_map(self::$returnData,(array)self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('SNAPIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $this->data = array();
        array_map(self::$returnData,(array)self::getClass($this->childClass)->getManager()->search('',true));
        self::$HookManager->processEvent('SNAPIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
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
        self::$selected = isset($_REQUEST['snapinfileexist']) ? basename($_REQUEST['snapinfileexist']) : '';
        $filelist = array();
        array_map(function(&$StorageNode) use (&$filelist) {
            if (!$StorageNode->isValid()) return;
            if (!$StorageNode->isValid()) return;
            $filelist = array_merge((array)$filelist,(array)$StorageNode->get('snapinfiles'));
            unset($StorageNode);
        },self::getClass('StorageNodeManager')->find(array('isMaster'=>1,'isEnabled'=>1)));
        natcasesort($filelist);
        $filelist = array_values(array_unique(array_filter((array)$filelist)));
        ob_start();
        array_map(self::$buildSelectBox,$filelist);
        $selectFiles = sprintf('<select class="cmdlet3" name="snapinfileexist"><span class="lightColor"><option value="">- %s -</option>%s</select>',_('Please select an option'),ob_get_clean());
        $argTypes = array(
            'MSI' => array('msiexec.exe','/i','/quiet'),
        );
        ob_start();
        printf('<select id="argTypes"><option value="">- %s -</option>',_('Please select an option'));
        array_walk(self::$argTypes,function(&$cmd,&$type) {
            printf('<option value="%s" rwargs="%s" args="%s">%s</option>',$cmd[0],$cmd[1],$cmd[2],$type);
        });
        echo '</select>';
        $template = ob_get_clean();
        $fields = array(
            _('Snapin Name') => sprintf('<input type="text" name="name" value="%s"/>',$_REQUEST['name']),
            _('Snapin Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$_REQUEST['description']),
            _('Snapin Template') => $template,
            _('Snapin Storage Group') => self::getClass('StorageGroupManager')->buildSelectBox($_REQUEST['storagegroup']),
            _('Snapin Run With') => sprintf('<input class="cmdlet1" type="text" name="rw" value="%s"/>',$_REQUEST['rw']),
            _('Snapin Run With Argument') => sprintf('<input class="cmdlet2" type="text" name="rwa" value="%s"/>',$_REQUEST['rwa']),
            sprintf('%s <span class="lightColor">%s:%s</span>',_('Snapin File'),_('Max Size'),ini_get('post_max_size')) => sprintf('<input class="cmdlet3" name="snapin" value="%s" type="file"/>',$_FILES['snapin']['name']),
            (count($filelist) > 0 ? _('Snapin File (exists)') : '') => (count($filelist) > 0 ? $selectFiles : ''),
            _('Snapin Arguments') => sprintf('<input class="cmdlet4" type="text" name="args" value="%s"/>',$_REQUEST['args']),
            _('Snapin Enabled') => '<input type="checkbox" name="isEnabled" value="1"checked/>',
            _('Snapin Arguments Hidden?') => '<input type="checkbox" name="isHidden" value="1"/>',
            _('Snapin Timeout (seconds)') => '<input type="text" name="timeout" value="0"/>',
            _('Replicate?') => '<input type="checkbox" name="toReplicate" value="1" checked/>',
            _('Reboot after install') => '<input class="action" type="radio" name="action" value="reboot"/>',
            _('Shutdown after install') => '<input class="action" type="radio" name="action" value="shutdown"/>',
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
        self::$HookManager->processEvent('SNAPIN_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
        unset($this->data,$this->templates,$this->attributes,$this->headerData);
    }
    public function add_post() {
        self::$HookManager->processEvent('SNAPIN_ADD_POST');
        try {
            $snapinName = trim($_REQUEST['name']);
            if (!$snapinName) throw new Exception(_('Please enter a name to give this Snapin'));
            if (self::getClass('SnapinManager')->exists($snapinName)) throw new Exception(_('Snapin with that name already exists'));
            if (!$_REQUEST['storagegroup']) throw new Exception(_('Please select a storage group for this snapin to reside in'));
            $snapinfile = trim(basename($_REQUEST['snapinfileexist']));
            $uploadfile = trim(basename($_FILES['snapin']['name']));
            if ($uploadfile) $snapinfile = $uploadfile;
            if (!$snapinfile) throw new Exception(_('A file to use for the snapin must be either uploaded or chosen from the already present list'));
            if (!$_REQUEST['storagegroup']) throw new Exception(_('Must have snapin associated to a group'));
            $StorageNode = self::getClass('StorageGroup',$_REQUEST['storagegroup'])->getMasterStorageNode();
            if (!$snapinfile && $_FILES['snapin']['error'] > 0) throw new UploadException($_FILES['snapin']['error']);
            $src = sprintf('%s/%s',dirname($_FILES['snapin']['tmp_name']),basename($_FILES['snapin']['tmp_name']));
            $dest = sprintf('/%s/%s',trim($StorageNode->get('snapinpath'),'/'),$snapinfile);
            if ($uploadfile) {
                self::$FOGFTP
                    ->set('host',$StorageNode->get('ip'))
                    ->set('username',$StorageNode->get('user'))
                    ->set('password',$StorageNode->get('pass'));
                if (!self::$FOGFTP->connect()) throw new Exception(sprintf('%s: %s %s',_('Storage Node'),$StorageNode->get('ip'),_('FTP Connection has failed')));
                if (!self::$FOGFTP->chdir($StorageNode->get('snapinpath'))) {
                    if (!self::$FOGFTP->mkdir($StorageNode->get('snapinpath'))) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                }
                if (is_file($dest)) self::$FOGFTP->delete($dest);
                if (!self::$FOGFTP->put($dest,$src)) throw new Exception(_('Failed to add/update snapin file'));
                self::$FOGFTP->chmod(0755,$dest);
                self::$FOGFTP->close();
            }
            $Snapin = self::getClass('Snapin')
                ->set('name',$snapinName)
                ->set('description',$_REQUEST['description'])
                ->set('file',$snapinfile)
                ->set('args',$_REQUEST['args'])
                ->set('reboot',(isset($_REQUEST['action']) && $_REQUEST['action'] === 'reboot'))
                ->set('shutdown',(string)intval((isset($_REQUEST['action']) && $_REQUEST['action'] === 'shutdown')))
                ->set('runWith',$_REQUEST['rw'])
                ->set('runWithArgs',$_REQUEST['rwa'])
                ->set('isEnabled',(string)intval(isset($_REQUEST['isEnabled'])))
                ->set('toReplicate',(string)intval(isset($_REQUEST['toReplicate'])))
                ->set('hide',(string)intval(isset($_REQUEST['isHidden'])))
                ->set('timeout',$_REQUEST['timeout'])
                ->addGroup($_REQUEST['storagegroup']);
            if (!$Snapin->save()) throw new Exception(_('Add snapin failed!'));
            self::$HookManager->processEvent('SNAPIN_ADD_SUCCESS',array('Snapin'=>&$Snapin));
            $this->setMessage(_('Snapin added, Editing now!'));
            $this->redirect(sprintf('?node=%s&sub=edit&%s=%s', $_REQUEST['node'],$this->id,$Snapin->get('id')));
        } catch (Exception $e) {
            self::$FOGFTP->close();
            self::$HookManager->processEvent('SNAPIN_ADD_FAIL',array('Snapin'=>&$Snapin));
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
        self::$selected = $this->obj->get('file');
        $filelist = array();
        array_map(function(&$StorageNode) use (&$filelist) {
            if (!$StorageNode->isValid()) return;
            $filelist = array_merge((array)$filelist,(array)$StorageNode->get('snapinfiles'));
            unset($StorageNode);
        },self::getClass('StorageNodeManager')->find(array('isMaster'=>1,'isEnabled'=>1)));
        natcasesort($filelist);
        $filelist = array_values(array_filter(array_unique((array)$filelist)));
        ob_start();
        array_map(self::$buildSelectBox,$filelist);
        $selectFiles = sprintf('<select class="cmdlet3" name="snapinfileexist"><span class="lightColor"><option value="">- %s -</option>%s</select>',_('Please select an option'),ob_get_clean());
        ob_start();
        printf('<select id="argTypes"><option>- %s -</option>',_('Please select an option'));
        array_walk(self::$argTypes,function(&$cmd,&$type) {
            printf('<option value="%s" rwargs="%s" args="%s">%s</option>',$cmd[0],$cmd[1],$cmd[2],$type);
        });
        echo '</select>';
        $template = ob_get_clean();
        $fields = array(
            _('Snapin Name') => sprintf('<input type="text" name="name" value="%s"/>',$this->obj->get('name')),
            _('Snapin Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            _('Snapin Run With Template') => $template,
            _('Snapin Run With') => sprintf('<input class="cmdlet1" type="text" name="rw" value="%s"/>',$this->obj->get('runWith')),
            _('Snapin Run With Argument') => sprintf('<input class="cmdlet2" type="text" name="rwa" value="%s"/>',$this->obj->get('runWithArgs')),
            sprintf('%s <span class="lightColor">%s:%s</span>',_('Snapin File'),_('Max Size'),ini_get('post_max_size')) => sprintf('<label id="uploader" for="snapin-uploader">%s<a href="#" id="snapin-upload"> <i class="fa fa-arrow-up noBorder"></i></a></label>',basename($this->obj->get('file'))),
            (count($filelist) > 0 ? _('Snapin File (exists)') : '') => (count($filelist) > 0 ? $selectFiles : ''),
            _('Snapin Arguments') => sprintf('<input class="cmdlet4" type="text" name="args" value="%s"/>',$this->obj->get('args')),
            _('Protected') => sprintf('<input type="checkbox" name="protected_snapin" value="1"%s/>',$this->obj->get('protected') ? ' checked' : ''),
            _('Reboot after install') => sprintf('<input class="action" type="radio" name="action" value="reboot"%s/>',$this->obj->get('reboot') ? ' checked' : ''),
            _('Shutdown after install') => sprintf('<input class="action" type="radio" name="action" value="shutdown"%s/>',$this->obj->get('shutdown') ? ' checked' : ''),
            _('Snapin Enabled') => sprintf('<input type="checkbox" name="isEnabled" value="1"%s/>',$this->obj->get('isEnabled') ? ' checked' : ''),
            _('Replicate?') => sprintf('<input type="checkbox" name="toReplicate" value="1"%s/>',$this->obj->get('toReplicate') ? ' checked' : ''),
            _('Snapin Arguments Hidden?') => sprintf('<input type="checkbox" name="isHidden" value="1"%s/>',$this->obj->get('hide') ? ' checked' : ''),
            _('Snapin Timeout (seconds)') => sprintf('<input type="text" name="timeout" value="%s"/>',$this->obj->get('timeout')),
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
        self::$HookManager->processEvent('SNAPIN_EDIT',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
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
        $storageGroups = function(&$StorageGroup) {
            if (!$StorageGroup->isValid()) return;
            $this->data[] = array(
                'storageGroup_id' => $StorageGroup->get('id'),
                'storageGroup_name' => $StorageGroup->get('name'),
                'is_primary' => ($this->obj->getPrimaryGroup($StorageGroup->get('id')) ? ' checked' : ''),
            );
        };
        array_map($storageGroups,self::getClass('StorageGroupManager')->find(array('id'=>$this->obj->get('storageGroupsnotinme'))));
        if (count($this->data) > 0) {
            self::$HookManager->processEvent('SNAPIN_GROUP_ASSOC',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
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
        array_map($storageGroups,self::getClass('StorageGroupManager')->find(array('id'=>$this->obj->get('storageGroups'))));
        self::$HookManager->processEvent('SNAPIN_EDIT_GROUP',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s&tab=snap-storage">',$this->formAction);
        $this->render();
        if (count($this->data) > 0) printf('<p class="c"><input name="update" type="submit" value="%s"/>&nbsp;<input name="deleteGroup" type="submit" value="%s"/></p>',_('Update Primary Group'),_('Deleted selected group associations'));
        echo '</form></div></div>';
    }
    public function edit_post() {
        self::$HookManager->processEvent('SNAPIN_EDIT_POST',array('Snapin'=>&$this->obj));
        try {
            switch ($_REQUEST['tab']) {
            case 'snap-gen':
                $snapinName = trim($_REQUEST['name']);
                if (!$snapinName) throw new Exception(_('Please enter a name to give this Snapin'));
                if ($snapinName != $this->obj->get('name') && $this->obj->getManager()->exists($snapinName)) throw new Exception(_('Snapin with that name already exists'));
                $snapinfile = trim(basename($_REQUEST['snapinfileexist']));
                $uploadfile = trim(basename($_FILES['snapin']['name']));
                if ($uploadfile) $snapinfile = $uploadfile;
                if (!$snapinfile) throw new Exception(_('A file to use for the snapin must be either uploaded or chosen from the already present list'));
                $StorageNode = $this->obj->getStorageGroup()->getMasterStorageNode();
                if (!$snapinfile && $_FILES['snapin']['error'] > 0) throw new UploadException($_FILES['snapin']['error']);
                $src = sprintf('%s/%s',dirname($_FILES['snapin']['tmp_name']),basename($_FILES['snapin']['tmp_name']));
                $dest = sprintf('/%s/%s',trim($StorageNode->get('snapinpath'),'/'),$snapinfile);
                if ($uploadfile) {
                    self::$FOGFTP
                        ->set('host',$StorageNode->get('ip'))
                        ->set('username',$StorageNode->get('user'))
                        ->set('password',$StorageNode->get('pass'));
                    if (!self::$FOGFTP->connect()) throw new Exception(sprintf('%s: %s: %s %s: %s %s',_('Storage Node'),$StorageNode->get('ip'),_('FTP connection has failed')));
                    if (!self::$FOGFTP->chdir($StorageNode->get('snapinpath'))) {
                        if (!self::$FOGFTP->mkdir($StorageNode->get('snapinpath'))) throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
                    }
                    if (is_file($dest)) self::$FOGFTP->delete($dest);
                    if (!self::$FOGFTP->put($dest,$src)) throw new Exception(_('Failed to add/update snapin file'));
                    self::$FOGFTP->chmod(0755,$dest);
                    self::$FOGFTP->close();
                }
                $this->obj
                    ->set('name',$snapinName)
                    ->set('description',$_REQUEST['description'])
                    ->set('file',$snapinfile)
                    ->set('args',$_REQUEST['args'])
                    ->set('reboot',(isset($_REQUEST['action']) && $_REQUEST['action'] === 'reboot'))
                    ->set('shutdown',(string)intval((isset($_REQUEST['action']) && $_REQUEST['action'] === 'shutdown')))
                    ->set('runWith',$_REQUEST['rw'])
                    ->set('runWithArgs',$_REQUEST['rwa'])
                    ->set('protected',isset($_REQUEST['protected_snapin']))
                    ->set('isEnabled',(string)intval(isset($_REQUEST['isEnabled'])))
                    ->set('toReplicate',(string)intval(isset($_REQUEST['toReplicate'])))
                    ->set('hide',(string)intval(isset($_REQUEST['isHidden'])))
                    ->set('timeout',$_REQUEST['timeout']);
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
            self::$HookManager->processEvent('SNAPIN_UPDATE_SUCCESS',array('Snapin'=>&$this->obj));
            $this->setMessage(_('Snapin updated'));
            $this->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s',$this->node, $this->id, $this->obj->get('id'),$_REQUEST['tab']));
        } catch (Exception $e) {
            self::$FOGFTP->close();
            self::$HookManager->processEvent('SNAPIN_UPDATE_FAIL',array('Snapin'=>&$this->obj));
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
