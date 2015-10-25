<?php
class GroupManagementPage extends FOGPage {
    public $node = 'group';
    public function __construct($name = '') {
        $this->name = 'Group Management';
        parent::__construct($this->name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat#group-general" => $this->foglang['General'],
                "$this->linkformat#group-tasks" => $this->foglang['BasicTasks'],
                "$this->linkformat#group-image" => $this->foglang['ImageAssoc'],
                "$this->linkformat#group-snap-add" => "{$this->foglang['Add']} {$this->foglang['Snapins']}",
                "$this->linkformat#group-snap-del" => "{$this->foglang['Remove']} {$this->foglang['Snapins']}",
                "$this->linkformat#group-service" => "{$this->foglang['Service']} {$this->foglang['Settings']}",
                "$this->linkformat#group-active-directory" => $this->foglang['AD'],
                "$this->linkformat#group-printers" => $this->foglang['Printers'],
                $this->membership => $this->foglang['Membership'],
                $this->delformat => $this->foglang['Delete'],
            );
            $this->obj = $this->getClass('Group',$_REQUEST['id']);
            $this->notes = array(
                $this->foglang['Group'] => $this->obj->get('name'),
                $this->foglang['Members'] => $this->obj->getHostCount(),
            );
        }
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Name'),
            _('Members'),
            _('Tasking'),
            _('Edit/Remove'),
        );
        // Row templates
        $down = $this->getClass('TaskType',1);
        $mc = $this->getClass('TaskType',8);
        $this->templates = array(
            '<input type="checkbox" name="group[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=group&sub=edit&%s=${id}" title="Edit">${name}</a>', $this->id),
            '${count}',
            sprintf('<a href="?node=group&sub=deploy&type=1&%s=${id}"><i class="icon fa fa-'.$down->get('icon').'" title="'.$down->get('name').'"></i></a> <a href="?node=group&sub=deploy&type=8&%s=${id}"><i class="icon fa fa-'.$mc->get('icon').'" title="'.$mc->get('name').'"></i></a> <a href="?node=group&sub=edit&%s=${id}#group-tasks"><i class="icon fa fa-arrows-alt" title="Goto Basic Tasks"></i></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
            sprintf('<a href="?node=group&sub=edit&%s=${id}"><i class="icon fa fa-pencil" title="Edit"></i></a> <a href="?node=group&sub=delete&%s=${id}"><i class="icon fa fa-minus-circle" title="Delete"></i></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
        );
        // Row attributes
        $this->attributes = array(
            array('width'=>16,'class'=>'c filter-false'),
            array(),
            array('width'=>30,'class'=>'c'),
            array('width'=>90,'class'=>'c filter-false'),
            array('width'=>50,'class'=>'c filter-false'),
        );
    }
    public function index() {
        $this->title = _('All Groups');
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['GroupCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $ids = $this->getSubObjectIDs('Group');
        foreach ($ids AS $i => &$id) {
            $Group = $this->getClass('Group',$id);
            if (!$Group->isValid()) {
                unset($Group);
                continue;
            }
            $this->data[] = array(
                'id'=>$Group->get('id'),
                'name'=>$Group->get('name'),
                'description'=>$Group->get('description'),
                'count'=>$Group->getHostCount(),
            );
            unset($Group);
        }
        unset($ids,$id);
        $this->HookManager->processEvent('GROUP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $ids = $this->getClass('GroupManager')->search();
        foreach($ids AS $i => &$id) {
            $Group = $this->getClass('Group',$id);
            if (!$Group->isValid()) {
                unset($Group);
                continue;
            }
            $this->data[] = array(
                'id'=>$Group->get('id'),
                'name'=>$Group->get('name'),
                'description'=>$Group->get('description'),
                'count'=>$Group->getHostCount(),
            );
            unset($Group);
        }
        unset($ids,$id);
        $this->HookManager->processEvent('GROUP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = _('New Group');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${formField}',
        );
        $fields = array(
            _('Group Name') => '<input type="text" name="name" value="'.$_REQUEST['name'].'" />',
            _('Group Description') => '<textarea name="description" rows="8" cols="40">'.$_REQUEST['description'].'</textarea>',
            _('Group Kernel') => '<input type="text" name="kern" value="'.$_REQUEST['kern'].'" />',
            _('Group Kernel Arguments') => '<input type="text" name="args" name="'.$_REQUEST['args'].'" />',
            _('Group Primary Disk') => '<input type="text" name="dev" name="'.$_REQUEST['dev'].'" />',
            '' => '<input type="submit" value="'._('Add').'" />',
        );
        echo '<form method="post" action="'.$this->formAction.'">';
        foreach ((array)$fields AS $field => &$formField) {
            $this->data[] = array(
                'field'=>$field,
                'formField'=>$formField,
            );
        }
        unset($formField,$fields);
        $this->HookManager->processEvent('GROUP_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        $this->HookManager->processEvent('GROUP_ADD_POST');
        try {
            if (empty($_REQUEST['name'])) throw new Exception('Group Name is required');
            if ($this->getClass('GroupManager')->exists($_REQUEST['name'])) throw new Exception('Group Name already exists');
            $Group = $this->getClass('Group')
                ->set('name',$_REQUEST['name'])
                ->set('description',$_REQUEST['description'])
                ->set('kernel',$_REQUEST['kern'])
                ->set('kernelArgs',$_REQUEST['args'])
                ->set('kernelDevice',$_REQUEST['dev']);
            if (!$Group->save()) throw new Exception(_('Group create failed'));
            $this->HookManager->processEvent('GROUP_ADD_SUCCESS', array('Group' => &$Group));
            $this->setMessage(_('Group added'));
            $url = sprintf('?node=%s&sub=edit&%s=%s',$_REQUEST['node'],$this->id,$Group->get('id'));
        } catch (Exception $e) {
            $this->HookManager->processEvent('GROUP_ADD_FAIL', array('Group' => &$Group));
            $this->setMessage($e->getMessage());
            $url = $this->formAction;
        }
        unset($Group);
        $this->redirect($url);
    }
    public function edit() {
        $imageID = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'imageID');
        $imageMatchID = (count($imageID) == 1 ? $imageID[0] : '');
        $groupKey = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'productKey');
        $groupKeyMatch = (count($groupKey) == 1 ? base64_decode($groupKey[0]) : '');
        $aduse = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'useAD');
        $adDomain = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADDomain');
        $adOU = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADOU');
        $adUser = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADUser');
        $adPass = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADPass');
        $adPassLegacy = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADPassLegacy');
        $useAD = (int)(count($aduse) == 1);
        $ADOU = (count($adOU) == 1 ? @array_shift($adOU) : '');
        $ADDomain = (count($adDomain) == 1 ? @array_shift($adDomain) : '');
        $ADUser = (count($adUser) == 1 ? @array_shift($adUser) : '');
        $adPass = (count($adPass) == $this->obj->getHostCount() ? @array_shift($adPass) : '');
        $ADPass = $this->encryptpw($adPass);
        $ADPassLegacy = (count($adPassLegacy) == 1 ? @array_shift($adPassLegacy) : '');
        $biosExit = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'biosexit');
        $efiExit = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'efiexit');
        $exitNorm = Service::buildExitSelector('bootTypeExit',(count($biosExit) == 1 ? @array_shift($biosExit) : $_REQUEST['bootTypeExit']),true);
        $exitEfi = Service::buildExitSelector('efiBootTypeExit',(count($efiExit) == 1 ? @array_shift($efiExit) : $_REQUEST['efiBootTypeExit']),true);
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        unset ($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Group Name') => '<input type="text" name="name" value="'.$this->obj->get('name').'" />',
            _('Group Description') => '<textarea name="description" rows="8" cols="40">'.$this->obj->get('description').'</textarea>',
            _('Group Product Key') => '<input id="productKey" type="text" name="key" value="'.$groupKeyMatch.'" />',
            _('Group Kernel') => '<input type="text" name="kern" value="'.$this->obj->get('kernel').'" />',
            _('Group Kernel Arguments') => '<input type="text" name="args" value="'.$this->obj->get('kernelArgs').'" />',
            _('Group Primary Disk') => '<input type="text" name="dev" value="'.$this->obj->get('kernelDev').'" />',
            _('Group Bios Exit Type') => $exitNorm,
            _('Group EFI Exit Type') => $exitEfi,
            '<input type="hidden" name="updategroup" value="1" />' => '<input type="submit" value="'._('Update').'" />',
        );
        $this->HookManager->processEvent('GROUP_FIELDS',array('fields'=>&$fields,'Group'=>&$this->obj));
        echo '<form method="post" action="'.$this->formAction.'&tab=group-general"><div id="tab-container"><!-- General --><div id="group-general"><h2>'._('Modify Group').': '.$this->obj->get('name').'</h2><center><div id="resetSecDataBox"></div><input type="button" id="resetSecData" /></center><br/>';
        foreach ((array)$fields AS $field => $input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->HookManager->processEvent('GROUP_DATA_GEN',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset ($this->data);
        echo '</form></div>';
        $this->basictasksOptions();
        echo '<!-- Image Association -->';
        $imageSelector = $this->getClass('ImageManager')->buildSelectBox($imageMatchID,'image');
        echo '<div id="group-image">';
        echo '<h2>'._('Image Association for').': '.$this->obj->get('name').'</h2>';
        echo '<form method="post" action="'.$this->formAction.'&tab=group-image">';
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->data[] = array(
            'field'=>$imageSelector,
            'input'=>'<input type="submit" value="'._('Update Images').'" />',
        );
        $this->HookManager->processEvent('GROUP_IMAGE',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</form></div><!-- Add Snap-ins --><div id="group-snap-add"><h2>'._('Add Snapin to all hosts in').': '.$this->obj->get('name').'</h2><form method="post" action="'.$this->formAction.'&tab=group-snap-add">';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapin" class="toggle-checkboxsnapin" />',
            _('Snapin Name'),
            _('Created'),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${snapin_id}" class="toggle-snapin" />',
            '<a href="?node=snapin&sub=edit&id=${snapin_id}" title="'._('Edit').'">${snapin_name}</a>',
            '${snapin_created}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'c filter-false'),
            array('width'=>90,'class'=>'l'),
            array('width'=>20,'class'=>'r'),
        );
        $ids = $this->getSubObjectIDs('Snapin');
        foreach($ids AS $i => &$id) {
            $Snapin = $this->getClass('Snapin',$id);
            if (!$Snapin->isValid()) {
                unset($Snapin);
                continue;
            }
            $this->data[] = array(
                'snapin_id'=>$id,
                'snapin_name'=>$Snapin->get('name'),
                'snapin_created'=>$this->formatTime($Snapin->get('createdTime')),
            );
            unset($Snapin);
        }
        unset($id);
        $this->HookManager->processEvent('GROUP_SNAP_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '<center><input type="submit" value="'._('Add Snapin(s)').'" /></center></form></div><!-- Remove Snap-ins --><div id="group-snap-del"><h2>'._('Remove Snapin to all hosts in').': '.$this->obj->get('name').'</h2><form method="post" action="'.$this->formAction.'&tab=group-snap-del">';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapinrm" class="toggle-checkboxsnapinrm" />',
            _('Snapin Name'),
            _('Created'),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${snapin_id}" class="toggle-snapinrm" />',
            '<a href="?node=snapin&sub=edit&id=${snapin_id}" title="'._('Edit').'">${snapin_name}</a>',
            '${snapin_created}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'c filter-false'),
            array('width'=>90,'class'=>'l'),
            array('width'=>20,'class'=>'r'),
        );
        $ids = $this->getSubObjectIDs('Snapin');
        foreach($ids AS $i => &$id) {
            $Snapin = $this->getClass('Snapin',$id);
            if (!$Snapin->isValid()) {
                unset($Snapin);
                continue;
            }
            $this->data[] = array(
                'snapin_id'=>$Snapin->get('id'),
                'snapin_name'=>$Snapin->get('name'),
                'snapin_created'=>$Snapin->get('createdTime'),
            );
            unset($Snapin);
        }
        unset($id);
        $this->HookManager->processEvent('GROUP_SNAP_DEL',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->headerData,$this->data);
        echo '<center><input type="submit" value="'._('Remove Snapin(s)').'" /></center></form></div><!-- Service Settings -->';
        $this->attributes = array(
            array('width'=>270),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${mod_name}',
            '${input}',
            '${span}',
        );
        $this->data[] = array(
            'mod_name'=>'Select/Deselect All',
            'input'=>'<input type="checkbox" class="checkboxes" id="checkAll" name="checkAll" value="checkAll" />',
            'span'=>'',
        );
        echo '<div id="group-service"><h2>'._('Service Configuration').'</h2><form method="post" action="'.$this->formAction.'&tab=group-service"><fieldset><legend>'._('General').'</legend>';
        $ModOns = array_count_values($this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->obj->get('hosts')),'moduleID'));
        $moduleName = $this->getGlobalModuleStatus();
        $HostCount = $this->obj->getHostCount();
        $Modules = $this->getClass('ModuleManager')->find();
        foreach ($Modules AS $i => &$Module) {
            if (!$Module->isValid()) continue;
            $this->data[] = array(
                'input'=>'<input type="checkbox" '.($moduleName[$Module->get('shortName')] || ($moduleName[$Module->get('shortName')] && $Module->get('isDefault')) ? 'class="checkboxes"' : '').' name="modules[]" value="${mod_id}" ${checked} '.(!$moduleName[$Module->get('shortName')] ? 'disabled' : '').' />',
                'span'=>'<span class="icon fa fa-question fa-1x hand" title="${mod_desc}"></span>',
                'checked'=>$ModOns[$Module->get('id')] ? 'checked' : '',
                'mod_name'=>$Module->get('name'),
                'mod_shname'=>$Module->get('shortName'),
                'mod_id'=>$Module->get('id'),
                'mod_desc'=>str_replace('"','\"',$Module->get('description')),
            );
        }
        unset($ModOns,$Module);
        $this->data[] = array(
            'mod_name'=> '',
            'input'=>'',
            'span'=>'<input type="submit" name="updatestatus" value="'._('Update').'" />',
        );
        $this->HookManager->processEvent('GROUP_MODULES',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        // Output
        $this->render();
        unset($this->data);
        echo '</fieldset></form><form method="post" action="'.$this->formAction.'&tab=group-service"><fieldset><legend>'._('Group Screen Resolution').'</legend>';
        $this->attributes = array(
            array('class'=>'l','style'=>'padding-right: 25px'),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
            '${span}',
        );
        $Services = $this->getClass('ServiceManager')->find(array('name'=>array('FOG_SERVICE_DISPLAYMANAGER_X','FOG_SERVICE_DISPLAYMANAGER_Y','FOG_SERVICE_DISPLAYMANAGER_R')),'OR','id');
        foreach($Services AS $i => &$Service) {
            $this->data[] = array(
                'input'=>'<input type="text" name="${type}" value="${disp}" />',
                'span'=>'<span class="icon fa fa-question fa-1x hand" title="${desc}"></span>',
                'field'=>($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? _('Screen Width (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? _('Screen Height (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? _('Screen Refresh Rate (in Hz)') : null))),
                'type'=>($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? 'x' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? 'y' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? 'r' : null))),
                'disp'=>$Service->get('value'),
                'desc'=>$Service->get('description'),
            );
        }
        unset($Service);
        $this->data[] = array(
            'field'=>'',
            'input'=>'',
            'span'=>'<input type="submit" name="updatedisplay" value="'._('Update').'" />',
        );
        $this->HookManager->processEvent('GROUP_DISPLAY',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</fieldset></form><form method="post" action="'.$this->formAction.'&tab=group-service"><fieldset><legend>'._('Auto Log Out Settings').'</legend>';
        $this->attributes = array(
            array('width'=>270),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
            '${desc}',
        );
        $Service = current($this->getClass('ServiceManager')->find(array('name' => 'FOG_SERVICE_AUTOLOGOFF_MIN')));
        $this->data[] = array(
            'field'=>_('Auto Log Out Time (in minutes)'),
            'input'=>'<input type="text" name="tme" value="${value}" />',
            'desc'=>'<span class="icon fa fa-question fa-1x hand" title="${serv_desc}"></span>',
            'value'=>$Service->get('value'),
            'serv_desc'=>$Service->get('description'),
        );
        $this->data[] = array(
            'field' => '',
            'input' => '',
            'desc' => '<input type="submit" name="updatealo" value="'._('Update').'" />',
        );
        $this->HookManager->processEvent('GROUP_ALO',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</fieldset></form></div>';
        $this->adFieldsToDisplay($useAD,$ADDomain,$ADOU,$ADUser,$ADPass,$ADPassLegacy);
        echo '<!-- Printers --><div id="group-printers"><form method="post" action="'.$this->formAction.'&tab=group-printers"><h2>'._('Select Management Level for all hosts in this group').'</h2><p class="l"><span class="icon fa fa-question hand" title="'._('This setting turns off all FOG Printer Management.  Although there are multiple levels already between host and global settings, this is just another to ensure safety').'"></span><input type="radio" name="level" value="0" />'._('No Printer Management').'<br/><span class="icon fa fa-question hand" title="'._('This setting only adds and removes printers that are managed by FOG.  If the printer exists in printer management but is not assigned to a host, it will remove the printer if it exists on the unsigned host.  It will add printers to the host that are assigned.').'"></span><input type="radio" name="level" value="1" />'._('FOG Managed Printers').'<br/><span class="icon fa fa-question hand" title="'._('This setting will only allow FOG Assigned printers to be added to the host.  Any printer that is assigned will be removed including non-FOG managed printers.').'"></span><input type="radio" name="level" value="2" />'._('Add and Remove').'<br/></p><div class="hostgroup">';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprint" />',
            _('Default'),
            _('Printer Name'),
            _('Configuration'),
        );
        $this->templates = array(
            '<input type="checkbox" name="prntadd[]" value="${printer_id}" class="toggle-print" />',
            '<input class="default" type="radio" name="default" id="printer${printer_id}" value="${printer_id}" /><label for="printer${printer_id}" class="icon icon-hand" title="'._('Default Printer Selector').'">&nbsp;</label><input type="hidden" name="printerid[]" />',
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'c filter-false'),
            array('width'=>20),
            array('width'=>50,'class'=>'l'),
            array('width'=>50,'class'=>'r'),
        );
        $Printers = $this->getClass('PrinterManager')->find();
        foreach($Printers AS $i => &$Printer) {
            $this->data[] = array(
                'printer_id'=>$Printer->get('id'),
                'printer_name'=>addslashes($Printer->get('name')),
                'printer_type'=>$Printer->get('config'),
            );
        }
        unset($Printer);
        if (count($this->data) > 0) {
            echo '<h2>'._('Add new printer(s) to all hosts in this group.').'</h2>';
            $this->HookManager->processEvent('GROUP_ADD_PRINTER',array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes));
            $this->render();
            unset($this->data);
        } else echo '<h2>'._('There are no printers to assign.').'</h2></div><div class="hostgroup">';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprintrm" />',
            _('Printer Name'),
            _('Configuration'),
        );
        $this->templates = array(
            '<input type="checkbox" name="prntdel[]" value="${printer_id}" class="toggle-printrm" />',
            '${printer_name}',
            '${printer_type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'c filter-false'),
            array('width'=>50,'class'=>'l'),
            array('width'=>50,'class'=>'r'),
        );
        $Printers = $this->getClass('PrinterManager')->find();
        foreach($Printers AS $i => &$Printer) {
            $this->data[] = array(
                'printer_id'=>$Printer->get('id'),
                'printer_name'=>addslashes($Printer->get('name')),
                'printer_type'=>$Printer->get('config'),
            );
        }
        unset($Printer);
        if (count($this->data) > 0) {
            echo '<h2>'._('Remove printer from all hosts in this group.').'</h2>';
            $this->HookManager->processEvent('GROUP_REM_PRINTER',array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes));
            $this->render();
            unset($this->data);
        } else echo '<h2>'._('There are no printers to assign.').'</h2>';
        echo '</div><input type="hidden" name="update" value="1" /><input type="submit" value="'._('Update').'" /></form></div></div>';
    }
    public function edit_post() {
        $this->HookManager->processEvent('GROUP_EDIT_POST',array('Group'=>&$Group));
        try {
            switch($_REQUEST['tab']) {
                case 'group-general';
                if (empty($_REQUEST['name'])) throw new Exception('Group Name is required');
                else {
                    $this->obj->set('name',$_REQUEST['name'])
                        ->set('description',$_REQUEST['description'])
                        ->set('kernel',$_REQUEST['kern'])
                        ->set('kernelArgs',$_REQUEST['args'])
                        ->set('kernelDevice',$_REQUEST['dev']);
                    $this->getClass('HostManager')->update(array('id'=>$this->obj->get('hosts')),'',array('kernel'=>$_REQUEST['kern'],'kernelArgs'=>$_REQUEST['args'],'kernelDevice'=>$_REQUEST['dev'],'efiexit'=>$_REQUEST['efiBootTypeExit'],'biosexit'=>$_REQUEST['bootTypeExit']));
                }
                break;
                case 'group-image';
                $this->obj->addImage($_REQUEST['image']);
                break;
                case 'group-snap-add';
                $this->obj->addSnapin($_REQUEST['snapin']);
                break;
                case 'group-snap-del';
                $this->obj->removeSnapin($_REQUEST['snapin']);
                break;
                case 'group-active-directory';
                $useAD = (int)isset($_REQUEST['domain']);
                $domain = $_REQUEST['domainname'];
                $ou = $_REQUEST['ou'];
                $user = $_REQUEST['domainuser'];
                $pass = $_REQUEST['domainpassword'];
                $legacy = $_REQUEST['domainpasswordlegacy'];
                $this->obj->setAD($useAD,$domain,$ou,$user,$pass,$legacy);
                $this->resetRequest();
                break;
                case 'group-printers';
                $this->obj->addPrinter($_REQUEST['prntadd'],$_REQUEST['prntdel'],$_REQUEST['level']);
                $this->obj->updateDefault(isset($_REQUEST['default']) ? $_REQUEST['default'] : 0);
                break;
                case 'group-service';
                $x =(is_numeric($_REQUEST['x']) ? $_REQUEST['x'] : $this->getSetting('FOG_SERVICE_DISPLAYMANAGER_X'));
                $y =(is_numeric($_REQUEST['y']) ? $_REQUEST['y'] : $this->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y'));
                $r =(is_numeric($_REQUEST['r']) ? $_REQUEST['r'] : $this->getSetting('FOG_SERVICE_DISPLAYMANAGER_R'));
                $time = (is_numeric($_REQUEST['tme']) ? $_REQUEST['tme'] : $this->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN'));
                $modOn = $_REQUEST['modules'];
                $modOff = $this->getSubObjectIDs('Module',array('id'=>$modOn),'id',true);
                $this->obj->addModule($modOn)->removeModule($modOff);
                $ids = $this->obj->get('hosts');
                foreach((array)$ids AS $i => &$id) {
                    $Host = $this->getClass('Host',$id);
                    if (!$Host->isValid()) {
                        unset($Host);
                        continue;
                    }
                    if (isset($_REQUEST['updatedisplay'])) $Host->setDisp($x,$y,$r);
                    if (isset($_REQUEST['updatealo'])) $Host->setAlo($time);
                    unset($Host);
                }
                unset($id);
                break;
            }
            if (!$this->obj->save()) throw new Exception(_('Database update failed'));
            $this->HookManager->processEvent('GROUP_EDIT_SUCCESS', array('Group' => &$this->obj));
            $this->setMessage('Group information updated!');
        } catch (Exception $e) {
            $this->HookManager->processEvent('GROUP_EDIT_FAIL', array('Group' => &$this->obj));
            $this->setMessage($e->getMessage());
        }
        $url = sprintf('%s#%s',$this->formAction,$_REQUEST['tab']);
        $this->redirect($url);
    }
    public function delete_hosts() {
        $this->title = _('Delete Hosts');
        unset($this->data);
        $this->headerData = array(
            _('Host Name'),
            _('Last Deployed'),
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '<small>${host_deployed}</small>',
        );
        $ids = $this->obj->get('hosts');
        foreach((array)$ids AS $i => &$id) {
            $Host = $this->getClass('Host',$Host);
            if (!$Host->isValid()) {
                unset($Host);
                continue;
            }
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'host_deployed' => $this->formatTime($Host->get('deployed')),
            );
            unset($Host);
        }
        unset($Host);
        printf('<p>%s</p>',_('Confirm you really want to delete the following hosts'));
        printf('<form method="post" action="?node=group&sub=delete&id=%s" class="c">',$this->obj->get('id'));
        $this->HookManager->processEvent('GROUP_DELETE_HOST_FORM',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
        $this->render();
        printf('<input type="submit" name="delHostConfirm" value="%s" />',_('Delete listed hosts'));
        printf('</form>');
    }
}
