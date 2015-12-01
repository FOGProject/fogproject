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
            $this->notes = array(
                $this->foglang['Group'] => $this->obj->get('name'),
                $this->foglang['Members'] => $this->obj->getHostCount(),
            );
        }
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes,'object'=>&$this->obj,'linkformat'=>&$this->linkformat,'delformat'=>&$this->delformat,'membership'=>&$this->membership));
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Name'),
            _('Members'),
            _('Tasking'),
            _('Edit/Remove'),
        );
        $down = $this->getClass('TaskType',1);
        $mc = $this->getClass('TaskType',8);
        $this->templates = array(
            '<input type="checkbox" name="group[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=group&sub=edit&%s=${id}" title="Edit">${name}</a>', $this->id),
            '${count}',
            sprintf('<a href="?node=group&sub=deploy&type=1&%s=${id}"><i class="icon fa fa-'.$down->get('icon').'" title="'.$down->get('name').'"></i></a> <a href="?node=group&sub=deploy&type=8&%s=${id}"><i class="icon fa fa-'.$mc->get('icon').'" title="'.$mc->get('name').'"></i></a> <a href="?node=group&sub=edit&%s=${id}#group-tasks"><i class="icon fa fa-arrows-alt" title="Goto Basic Tasks"></i></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
            sprintf('<a href="?node=group&sub=edit&%s=${id}"><i class="icon fa fa-pencil" title="Edit"></i></a> <a href="?node=group&sub=delete&%s=${id}"><i class="icon fa fa-minus-circle" title="Delete"></i></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array(),
            array('width'=>30,'class'=>'c'),
            array('width'=>90,'class'=>'c filter-false'),
            array('width'=>50,'class'=>'c filter-false'),
        );
    }
    public function index() {
        $this->title = _('All Groups');
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['GroupCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        foreach ((array)$this->getClass('GroupManager')->find() AS $i => &$Group) {
            if (!$Group->isValid()) continue;
            $this->data[] = array(
                'id'=>$Group->get('id'),
                'name'=>$Group->get('name'),
                'description'=>$Group->get('description'),
                'count'=>$Group->getHostCount(),
            );
            unset($Group);
        }
        $this->HookManager->processEvent('GROUP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        foreach ((array)$this->getClass('GroupManager')->search('',true) AS $i => &$Group) {
            if (!$Group->isValid()) continue;
            $this->data[] = array(
                'id'=>$Group->get('id'),
                'name'=>$Group->get('name'),
                'description'=>$Group->get('description'),
                'count'=>$Group->getHostCount(),
            );
            unset($Group);
        }
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
            _('Group Name') => sprintf('<input type="text" name="name" value="%s"/>',$_REQUEST['name']),
            _('Group Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$_REQUEST['description']),
            _('Group Kernel') => sprintf('<input type="text" name="kern" value="%s"/>',$_REQUEST['kern']),
            _('Group Kernel Arguments') => sprintf('<input type="text" name="args" name="%s"/>',$_REQUEST['args']),
            _('Group Primary Disk') => sprintf('<input type="text" name="dev" name="%s"/>',$_REQUEST['dev']),
            '' => sprintf('<input type="submit" value="%s"/>',_('Add')),
        );
        printf('<form method="post" action="%s">',$this->formAction);
        foreach ((array)$fields AS $field => &$formField) {
            $this->data[] = array(
                'field'=>$field,
                'formField'=>$formField,
            );
            unset($formField);
        }
        unset($fields);
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
            $url = sprintf('?node=%s&sub=edit&id=%s',$_REQUEST['node'],$Group->get('id'));
        } catch (Exception $e) {
            $this->HookManager->processEvent('GROUP_ADD_FAIL', array('Group' => &$Group));
            $this->setMessage($e->getMessage());
            $url = $this->formAction;
        }
        unset($Group);
        $this->redirect($url);
    }
    public function edit() {
        $HostCount = $this->obj->getHostCount();
        $imageID = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'imageID','','','','','array_count_values');
        $imageMatchID = (count($imageID) == 1 && $imageID[0] == $HostCount ? $this->getClass('Host',current($this->obj->get('hosts')))->get('imageID') : '');
        $groupKey = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'productKey');
        $aduse = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'useAD','','','','','array_count_values');
        $adDomain = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADDomain','','','','','array_count_values');
        $adOU = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADOU','','','','','array_count_values');
        $adUser = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADUser','','','','','array_count_values');
        $adPass = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADPass','','','','','array_count_values');
        $adPassLegacy = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'ADPassLegacy','','','','','array_count_values');
        $Host = $this->getClass('Host',current($this->obj->get('hosts')));
        $useAD = (bool)(count($aduse) == 1 && $aduse[0] == $HostCount);
        unset($aduse);
        $ADDomain = (count($adDomain) == 1 && $adDomain[0] == $HostCount ? $Host->get('ADDomain') : '');
        unset($adDomain);
        $ADOU = (count($adOU) == 1 && $adOU[0] == $HostCount ? $Host->get('ADOU') : '');
        unset($adOU);
        $ADUser = (count($adUser) == 1 && $adUser[0] == $HostCount ? $Host->get('ADUser') : '');
        unset($adUser);
        $adPass = ((count($adPass) == 1 && $adPass[0] == $HostCount) || count($adPass) == $HostCount ? $Host->get('ADPass') : '');
        $ADPass = $this->encryptpw($adPass);
        unset($adPass);
        $ADPassLegacy = (count($adPassLegacy) == 1 && $adPassLegacy[0] == $HostCount ? $Host->get('ADPassLegacy') : '');
        unset($adPassLegacy);
        $productKey = (count($groupKey) == $this->obj->getHostCount() ? @array_shift($groupKey) : '');
        unset($groupKey);
        $productKey = ((count($groupKey) == 1 && $groupKey[0] == $HostCount) || count($groupKey) == $HostCount ? $Host->get('productKey') : '');
        $groupKeyMatch = $this->encryptpw($productKey);
        unset($productKey, $groupKey);
        $biosExit = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'biosexit','','','','','array_count_values');
        $efiExit = $this->getSubObjectIDs('Host',array('id'=>$this->obj->get('hosts')),'efiexit','','','','','array_count_values');
        $exitNorm = Service::buildExitSelector('bootTypeExit',(count($biosExit) == 1 && $biosExit[0] == $HostCount ? $Host->get('biosexit') : $_REQUEST['bootTypeExit']),true);
        $exitEfi = Service::buildExitSelector('efiBootTypeExit',(count($efiExit) == 1 && $efiExit[0] == $HostCount ? $Host->get('efiexit') : $_REQUEST['efiBootTypeExit']),true);
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
            _('Group Name') => sprintf('<input type="text" name="name" value="%s"/>',$this->obj->get('name')),
            _('Group Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            _('Group Product Key') => sprintf('<input id="productKey" type="text" name="key" value="%s"/>',$this->aesdecrypt($groupKeyMatch)),
            _('Group Kernel') => sprintf('<input type="text" name="kern" value="%s"/>',$this->obj->get('kernel')),
            _('Group Kernel Arguments') => sprintf('<input type="text" name="args" value="%s"/>',$this->obj->get('kernelArgs')),
            _('Group Primary Disk') => sprintf('<input type="text" name="dev" value="%s"/>',$this->obj->get('kernelDev')),
            _('Group Bios Exit Type') => $exitNorm,
            _('Group EFI Exit Type') => $exitEfi,
            '&nbsp;' => sprintf('<input type="submit" name="updategroup" value="%s"/>',_('Update')),
        );
        $this->HookManager->processEvent('GROUP_FIELDS',array('fields'=>&$fields,'Group'=>&$this->obj));
        printf('<form method="post" action="%s&tab=group-general"><div id="tab-container"><!-- General --><div id="group-general"><h2>%s: %s</h2><div id="resetSecDataBox" class="c"><input type="button" id="resetSecData"/></div><br/>',$this->formAction,_('Modify Group'),$this->obj->get('name'));
        foreach ((array)$fields AS $field => $input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);
        $this->HookManager->processEvent('GROUP_DATA_GEN',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset ($this->data,$exitNorm,$exitEfi);
        echo '</form></div>';
        $this->basictasksOptions();
        $imageSelector = $this->getClass('ImageManager')->buildSelectBox($imageMatchID,'image');
        printf('<!-- Image Association --><div id="group-image"><h2>%s: %s</h2><form method="post" action="%s&tab=group-image">',_('Image Association for'),$this->obj->get('name'),$this->formAction);
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
            'input'=>sprintf('<input type="submit" value="%s"/>',_('Update Images')),
        );
        $this->HookManager->processEvent('GROUP_IMAGE',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('</form></div><!-- Add Snap-ins --><div id="group-snap-add"><h2>%s: %s</h2><form method="post" action="%s&tab=group-snap-add">',_('Add Snapin to all hosts in'),$this->obj->get('name'),$this->formAction);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapin" class="toggle-checkboxsnapin" />',
            _('Snapin Name'),
            _('Created'),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${snapin_id}" class="toggle-snapin" />',
            sprintf('<a href="?node=snapin&sub=edit&id=${snapin_id}" title="%s">${snapin_name}</a>',_('Edit')),
            '${snapin_created}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>90,'class'=>'l'),
            array('width'=>20,'class'=>'r'),
        );
        foreach ((array)$this->getClass('SnapinManager')->find() AS $i => &$Snapin) {
            if (!$Snapin->isValid()) continue;
            $this->data[] = array(
                'snapin_id'=>$Snapin->get('id'),
                'snapin_name'=>$Snapin->get('name'),
                'snapin_created'=>$this->formatTime($Snapin->get('createdTime')),
            );
            unset($Snapin);
        }
        $this->HookManager->processEvent('GROUP_SNAP_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('<input class="c" type="submit" value="%s"/></form></div><!-- Remove Snapins --><div id="group-snap-del"><h2>%s: %s</h2><form method="post" action="%s&tab=group-snap-del">',_('Add Snapin(s)'),_('Remove Snapin to all hosts in'),$this->obj->get('name'),$this->formAction);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapinrm" class="toggle-checkboxsnapinrm" />',
            _('Snapin Name'),
            _('Created'),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${snapin_id}" class="toggle-snapinrm" />',
            sprintf('<a href="?node=snapin&sub=edit&id=${snapin_id}" title="%s">${snapin_name}</a>',_('Edit')),
            '${snapin_created}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>90,'class'=>'l'),
            array('width'=>20,'class'=>'r'),
        );
        foreach ((array)$this->getClass('SnapinManager')->find() AS $i => &$Snapin) {
            if (!$Snapin->isValid()) continue;
            $this->data[] = array(
                'snapin_id'=>$Snapin->get('id'),
                'snapin_name'=>$Snapin->get('name'),
                'snapin_created'=>$Snapin->get('createdTime'),
            );
            unset($Snapin);
        }
        unset($Snapins);
        $this->HookManager->processEvent('GROUP_SNAP_DEL',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->headerData,$this->data);
        printf('<input class="c" type="submit" value="%s"/></form></div><!-- Service Settings -->',_('Remove Snapin(s)'));
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
            'input'=>'<input type="checkbox" class="checkboxes" id="checkAll" name="checkAll" value="checkAll"/>',
            'span'=>'',
        );
        printf('<div id="group-service"><h2>%s</h2><form method="post" action="%s&tab=group-service"><fieldset><legend>%s</legend>',_('Service Configuration'),$this->formAction,_('General'));
        $moduleName = $this->getGlobalModuleStatus();
        foreach ((array)$this->getClass('ModuleManager')->find() AS $i => &$Module) {
            if (!$Module->isValid()) continue;
            $ModuleOn = $this->getSubObjectIDs('ModuleAssociation',array('moduleID'=>$Module->get('id'),'hostID'=>$this->obj->get('hosts')),'moduleID','','','','','array_count_values');
            $this->data[] = array(
                'input'=>sprintf('<input %stype="checkbox" name="modules[]" value="%s"%s%s/>',($moduleName[$Module->get('shortName')] || ($moduleName[$Module->get('shortName')] && $Module->get('isDefault')) ? 'class="checkboxes" ' : ''), $Module->get('id'), (count($ModuleOn) == 1 && $ModuleOn[0] == $HostCount ? ' checked' : ''), !$moduleName[$Module->get('shortName')] ? ' disabled' : ''),
                'span'=>sprintf('<span class="icon fa fa-question fa-1x hand" title="%s"></span>',str_replace('"','\"',$Module->get('description'))),
                'mod_name'=>$Module->get('name'),
            );
            unset($ModuleOn);
            unset($Module);
        }
        unset($ModOns,$Modules);
        $this->data[] = array(
            'mod_name'=> '',
            'input'=>'',
            'span'=>sprintf('<input type="submit" name="updatestatus" value="%s"/>',_('Update')),
        );
        $this->HookManager->processEvent('GROUP_MODULES',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('</fieldset></form><form method="post" action="%s&tab=group->service"><fieldset><legend>%s</legend>',$this->formAction,_('Group Screen Resolution'));
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
        foreach ((array)$this->getClass('ServiceManager')->find(array('name'=>array('FOG_SERVICE_DISPLAYMANAGER_X','FOG_SERVICE_DISPLAYMANAGER_Y','FOG_SERVICE_DISPLAYMANAGER_R')),'OR','id') AS $i => &$Service) {
            if (!$Service->isValid()) continue;
            switch ($Service->get('name')) {
            case 'FOG_SERVICE_DISPLAYMANAGER_X':
                $name = 'x';
                $field = _('Screen Width (in pixels)');
                break;
            case 'FOG_SERVICE_DISPLAYMANAGER_Y':
                $name = 'y';
                $field = _('Screen Height (in pixels)');
                break;
            case 'FOG_SERVICE_DISPLAYMANAGER_R':
                $name = 'r';
                $field = _('Screen Refresh Rate (in Hz)');
                break;
            }
            $this->data[] = array(
                'input'=>sprintf('<input type="text" name="%s" value="%s"/>',$name,$Service->get('value')),
                'span'=>sprintf('<span class="icon fa fa-question fa-1x hand" title="%s"></span>',$Service->get('description')),
                'field'=>$field,
            );
            unset($name,$field,$Service);
        }
        unset($Services);
        $this->data[] = array(
            'field'=>'',
            'input'=>'',
            'span'=>sprintf('<input type="submit" name="updatedisplay" value="%s"/>',_('Update')),
        );
        $this->HookManager->processEvent('GROUP_DISPLAY',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('</fieldset></form><form method="post" action="%s&tab=group-service"><fieldset><legend>%s</legend>',$this->formAction,_('Auto Log Out Settings'));
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
        $Service = $this->getClass('Service',@max($this->getSubObjectIDs('Service',array('name'=>'FOG_SERVICE_AUTOLOGOFF_MIN'))));
        $this->data[] = array(
            'field'=>_('Auto Log Out Time (in minutes)'),
            'input'=>sprintf('<input type="text" name="tme" value="%s"/>',$Service->get('value')),
            'desc'=>sprintf('<span class="icon fa fa-question fa-1x hand" title="%s"></span>',$Service->get('description')),
        );
        unset($Service);
        $this->data[] = array(
            'field' => '',
            'input' => '',
            'desc' => sprintf('<input type="submit" name="updatealo" value="%s"/>',_('Update')),
        );
        $this->HookManager->processEvent('GROUP_ALO',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</fieldset></form></div>';
        $this->adFieldsToDisplay($useAD,$ADDomain,$ADOU,$ADUser,$ADPass,$ADPassLegacy);
        echo '<!-- Printers --><div id="group-printers">';
        if ($this->getClass('PrinterManager')->count()) {
            printf('<form method="post" action="%s&tab=group-printers"><h2>%s</h2><p class="l"><span class="icon fa fa-question hand" title="%s"></span><input type="radio" name="level" value="0" />%s<br/><span class="icon fa fa-question hand" title="%s"></span><input type="radio" name="level" value="1" />%s<br/><span class="icon fa fa-question hand" title="%s"></span><input type="radio" name="level" value="2" />%s<br/></p><div class="hostgroup">',$this->formAction,_('Select Management Level for all hosts in this group'),_('This setting turns off all FOG Printer Management. Although there are multiple levels already between host and global settings, this is just another to ensure safety'),_('No Printer Management'),_('This setting only adds and removes printers that are managed by FOG. If the printer exists in printer management but is not assigned to a host, it will remove the printer if it exists on the unassigned host. It will add printers to the host that are assigned.'),_('FOG Managed Printers'),_('This setting will only allow FOG Assigned printers to be added to the host.  Any printer that is installed, even printers not within FOG, will be removed'),_('Add and Remove'));
            $this->headerData = array(
                '<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprint"/>',
                '',
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
                array('width'=>16,'class'=>'l filter-false'),
                array('width'=>16,'class'=>'l filter-false'),
                array('width'=>50,'class'=>'l'),
                array('width'=>50,'class'=>'r'),
            );
            foreach ((array)$this->getClass('PrinterManager')->find() AS $i => &$Printer) {
                if (!$Printer->isValid()) continue;
                $this->data[] = array(
                    'printer_id'=>$Printer->get('id'),
                    'printer_name'=>$Printer->get('name'),
                    'printer_type'=>$Printer->get('config'),
                );
                unset($Printer);
            }
            if (count($this->data) > 0) {
                printf('<h2>%s</h2>',_('Add new printer(s) to all hosts in this group.'));
                $this->HookManager->processEvent('GROUP_ADD_PRINTER',array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes));
            }
            $this->render();
            unset($this->data);
            $this->headerData = array(
                '<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprintrm"/>',
                _('Printer Name'),
                _('Configuration'),
            );
            $this->templates = array(
                '<input type="checkbox" name="prntdel[]" value="${printer_id}" class="toggle-printrm" />',
                '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
                '${printer_type}',
            );
            $this->attributes = array(
                array('width'=>16,'class'=>'l filter-false'),
                array('width'=>50,'class'=>'l'),
                array('width'=>50,'class'=>'r'),
            );
            foreach ((array)$this->getClass('PrinterManager')->find() AS $i => &$Printer) {
                if (!$Printer->isValid()) continue;
                $this->data[] = array(
                    'printer_id'=>$Printer->get('id'),
                    'printer_name'=>$Printer->get('name'),
                    'printer_type'=>$Printer->get('config'),
                );
                unset($Printer);
            }
            unset($Printers);
            $inputupdate = '';
            if (count($this->data)) {
                echo '<h2>'._('Remove printer from all hosts in this group.').'</h2>';
                $this->HookManager->processEvent('GROUP_REM_PRINTER',array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes));
                $inputupdate = sprintf('<p class="c"><input type="submit" value="%s" name="update"/></p>',_('Update'));

                $this->render();
                unset($this->data);
            }
        } else echo _('There are no printers defined');
        echo "</div>$inputupdate</form></div></div>";
        unset($imageID,$imageMatchID,$groupKey,$groupKeyMatch,$aduse,$adDomain,$adOU,$adUser,$adPass,$adPassLegacy,$useAD,$ADOU,$ADDomain,$ADUser,$adPass,$ADPass,$ADPassLegacy,$biosExit,$efiExit,$exitNorm,$exitEfi);
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
                    $this->getClass('HostManager')->update(array('id'=>$this->obj->get('hosts')),'',array('kernel'=>$_REQUEST['kern'],'kernelArgs'=>$_REQUEST['args'],'kernelDevice'=>$_REQUEST['dev'],'efiexit'=>$_REQUEST['efiBootTypeExit'],'biosexit'=>$_REQUEST['bootTypeExit'],'productKey'=>$this->encryptpw(trim($_REQUEST['productKey']))));
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
                foreach ((array)$this->getClass('HostManager')->find(array('id'=>$this->obj->get('hosts'))) AS $i => &$Host) {
                    if (!$Host->isValid()) continue;
                    if (isset($_REQUEST['updatedisplay'])) $Host->setDisp($x,$y,$r);
                    if (isset($_REQUEST['updatealo'])) $Host->setAlo($time);
                    unset($Host);
                }
                unset($Hosts);
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
        foreach ((array)$this->getClass('HostManager')->find(array('id'=>$this->obj->get('hosts'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'host_deployed' => $this->formatTime($Host->get('deployed')),
            );
            unset($Host);
        }
        printf('<p>%s</p>',_('Confirm you really want to delete the following hosts'));
        printf('<form method="post" action="?node=group&sub=delete&id=%s" class="c">',$this->obj->get('id'));
        $this->HookManager->processEvent('GROUP_DELETE_HOST_FORM',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
        $this->render();
        printf('<input type="submit" name="delHostConfirm" value="%s" /></form>',_('Delete listed hosts'));
    }
}
