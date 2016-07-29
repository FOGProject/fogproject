<?php
class GroupManagementPage extends FOGPage {
    public $node = 'group';
    public function __construct($name = '') {
        $this->name = 'Group Management';
        parent::__construct($this->name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat#group-general" => self::$foglang['General'],
                "$this->linkformat#group-image" => self::$foglang['ImageAssoc'],
                "$this->linkformat#group-tasks" => self::$foglang['BasicTasks'],
                "$this->linkformat#group-active-directory" => self::$foglang['AD'],
                "$this->linkformat#group-printers" => self::$foglang['Printers'],
                "$this->linkformat#group-snapins" => self::$foglang['Snapins'],
                "$this->linkformat#group-service" => sprintf('%s %s',self::$foglang['Service'],self::$foglang['Settings']),
                "$this->linkformat#group-powermanagement"=>self::$foglang['PowerManagement'],
                "$this->linkformat#group-inventory" => self::$foglang['Inventory'],
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Group'] => $this->obj->get('name'),
                self::$foglang['Members'] => $this->obj->getHostCount(),
            );
        }
        self::$HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes,'object'=>&$this->obj,'linkformat'=>&$this->linkformat,'delformat'=>&$this->delformat,'membership'=>&$this->membership));
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Name'),
            _('Members'),
            _('Tasking'),
        );
        $down = self::getClass('TaskType',1);
        $mc = self::getClass('TaskType',8);
        $this->templates = array(
            '<input type="checkbox" name="group[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=group&sub=edit&%s=${id}" title="Edit">${name}</a>', $this->id),
            '${count}',
            sprintf('<a href="?node=group&sub=deploy&type=1&%s=${id}"><i class="icon fa fa-'.$down->get('icon').'" title="'.$down->get('name').'"></i></a> <a href="?node=group&sub=deploy&type=8&%s=${id}"><i class="icon fa fa-'.$mc->get('icon').'" title="'.$mc->get('name').'"></i></a> <a href="?node=group&sub=edit&%s=${id}#group-tasks"><i class="icon fa fa-arrows-alt" title="Goto Basic Tasks"></i></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array(),
            array('width'=>30,'class'=>'c'),
            array('width'=>90,'class'=>'c filter-false'),
        );
        self::$returnData = function(&$Group) {
            if (!$Group->isValid()) return;
            $this->data[] = array(
                'id' => $Group->get('id'),
                'name' => $Group->get('name'),
                'description' => $Group->get('description'),
                'count' => $Group->getHostCount(),
            );
            unset($Group);
        };
    }
    public function index() {
        $this->title = _('All Groups');
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['GroupCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('GROUP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->search('',true));
        self::$HookManager->processEvent('GROUP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = _('New Group');
        $this->data = array();
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
            _('Group Name') => sprintf('<input type="text" class="groupname-input" name="name" value="%s"/>',$_REQUEST['name']),
            _('Group Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$_REQUEST['description']),
            _('Group Kernel') => sprintf('<input type="text" name="kern" value="%s"/>',$_REQUEST['kern']),
            _('Group Kernel Arguments') => sprintf('<input type="text" name="args" name="%s"/>',$_REQUEST['args']),
            _('Group Primary Disk') => sprintf('<input type="text" name="dev" name="%s"/>',$_REQUEST['dev']),
            '' => sprintf('<input type="submit" value="%s"/>',_('Add')),
        );
        printf('<form method="post" action="%s">',$this->formAction);
        array_walk($fields,function(&$formField,&$field) {
            $this->data[] = array(
                'field' => $field,
                'formField' => $formField,
            );
            unset($formField,$field);
        });
        unset($fields);
        self::$HookManager->processEvent('GROUP_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        self::$HookManager->processEvent('GROUP_ADD_POST');
        try {
            if (empty($_REQUEST['name'])) throw new Exception('Group Name is required');
            if (self::getClass('GroupManager')->exists($_REQUEST['name'])) throw new Exception('Group Name already exists');
            $Group = self::getClass('Group')
                ->set('name',$_REQUEST['name'])
                ->set('description',$_REQUEST['description'])
                ->set('kernel',$_REQUEST['kern'])
                ->set('kernelArgs',$_REQUEST['args'])
                ->set('kernelDevice',$_REQUEST['dev']);
            if (!$Group->save()) throw new Exception(_('Group create failed'));
            self::$HookManager->processEvent('GROUP_ADD_SUCCESS', array('Group' => &$Group));
            $this->setMessage(_('Group added'));
            $url = sprintf('?node=%s&sub=edit&id=%s',$_REQUEST['node'],$Group->get('id'));
        } catch (Exception $e) {
            self::$HookManager->processEvent('GROUP_ADD_FAIL', array('Group' => &$Group));
            $this->setMessage($e->getMessage());
            $url = $this->formAction;
        }
        unset($Group);
        $this->redirect($url);
    }
    public function edit() {
        $HostCount = $this->obj->getHostCount();
        $hostids = $this->obj->get('hosts');
        $Host = self::getClass('Host',current((array)$hostids));
        $imageIDs = self::getSubObjectIDs('Host',array('id'=>$hostids),'imageID','','','','','array_count_values');
        $imageIDs = array_shift($imageIDs);
        $groupKey = self::getSubObjectIDs('Host',array('id'=>$hostids),'productKey','','','','','array_count_values');
        $groupKey = array_shift($groupKey);
        $printerLevel = self::getSubObjectIDs('Host',array('id'=>$hostids),'printerLevel','','','','','array_count_values');
        $printerLevel = array_shift($printerLevel);
        // Collect AD Information
        $aduse = self::getSubObjectIDs('Host',array('id'=>$hostids),'useAD','','','','','array_count_values');
        $aduse = in_array(0,array_keys($aduse)) ? 0 : array_shift($aduse);
        $enforcetest = self::getSubObjectIDs('Host',array('id'=>$hostids),'enforce','','','','','array_count_values');
        $enforcetest = array_shift($enforcetest);
        $adDomain = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADDomain','','','','','array_count_values');
        $adDomain = array_shift($adDomain);
        $adOU = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADOU','','','','','array_count_values');
        $adOU = array_shift($adOU);
        $adUser = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADUser','','','','','array_count_values');
        $adUser = array_shift($adUser);
        $adPass = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADPass','','','','','array_count_values');
        $adPass = array_shift($adPass);
        $adPassLegacy = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADPassLegacy','','','','','array_count_values');
        $adPassLegacy = array_shift($adPassLegacy);
        // Set Field Information
        $printerLevel = ($printerLevel == $HostCount ? $Host->get('printerLevel') : '');
        $imageMatchID = ($imageIDs == $HostCount ? $Host->get('imageID') : '');
        $useAD = ($aduse == $HostCount ? $Host->get('useAD') : '');
        $enforce = ($enforcetest == $HostCount ? $Host->get('enforce') : '');
        $ADDomain = ($adDomain == $HostCount ? $Host->get('ADDomain') : '');
        $ADOU = ($adOU == $HostCount ? $Host->get('ADOU') : '');
        $ADUser = ($adUser == $HostCount ? $Host->get('ADUser') : '');
        $adPass = ($adPass == $HostCount ? $Host->get('ADPass') : '');
        $ADPass = $this->encryptpw($Host->get('ADPass'));
        $ADPassLegacy = ($adPassLegacy == $HostCount ? $Host->get('ADPassLegacy') : '');
        $productKey = ($groupKey == $HostCount ? $Host->get('productKey') : '');
        $groupKeyMatch = $this->encryptpw($productKey);
        unset($productKey, $groupKey);
        $biosExit = array_flip(self::getSubObjectIDs('Host',array('id'=>$hostids),'biosexit','','','','','array_count_values'));
        $efiExit = array_flip(self::getSubObjectIDs('Host',array('id'=>$hostids),'efiexit','','','','','array_count_values'));
        $exitNorm = Service::buildExitSelector('bootTypeExit',(count($biosExit) === 1 && isset($biosExit[1]) ? $Host->get('biosexit') : $_REQUEST['bootTypeExit']),true);
        $exitEfi = Service::buildExitSelector('efiBootTypeExit',(count($efiExit) === 1 && isset($efiExit[1]) ? $Host->get('efiexit') : $_REQUEST['efiBootTypeExit']),true);
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
            _('Group Name') => sprintf('<input type="text" class="groupname-input" name="name" value="%s"/>',$this->obj->get('name')),
            _('Group Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            _('Group Product Key') => sprintf('<input id="productKey" type="text" name="key" value="%s"/>',$this->aesdecrypt($groupKeyMatch)),
            _('Group Kernel') => sprintf('<input type="text" name="kern" value="%s"/>',$this->obj->get('kernel')),
            _('Group Kernel Arguments') => sprintf('<input type="text" name="args" value="%s"/>',$this->obj->get('kernelArgs')),
            _('Group Primary Disk') => sprintf('<input type="text" name="dev" value="%s"/>',$this->obj->get('kernelDev')),
            _('Group Bios Exit Type') => $exitNorm,
            _('Group EFI Exit Type') => $exitEfi,
            '&nbsp;' => sprintf('<input type="submit" name="updategroup" value="%s"/>',_('Update')),
        );
        self::$HookManager->processEvent('GROUP_FIELDS',array('fields'=>&$fields,'Group'=>&$this->obj));
        printf('<form method="post" action="%s&tab=group-general"><div id="tab-container"><!-- General --><div id="group-general"><h2>%s: %s</h2><div id="resetSecDataBox" class="hidden"></div><div class="c"><input type="button" id="resetSecData"/></div><br/>',$this->formAction,_('Modify Group'),$this->obj->get('name'));
        array_walk($fields,function(&$input,&$field) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input,$field);
        });
        unset($fields);
        self::$HookManager->processEvent('GROUP_DATA_GEN',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset ($this->data,$exitNorm,$exitEfi);
        echo '</form></div>';
        unset($this->data);
        $imageSelector = self::getClass('ImageManager')->buildSelectBox($imageMatchID,'image');
        echo '<!-- Image Association --><div id="group-image">';
        printf('<h2>%s: %s</h2><form method="post" action="%s&tab=group-image">',_('Image Association for'),$this->obj->get('name'),$this->formAction);
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
        self::$HookManager->processEvent('GROUP_IMAGE',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form></div>';
        unset($this->data);
        self::$HookManager->processEvent('GROUP_GENERAL_EXTRA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes,'Group'=>&$this->obj,'formAction'=>&$this->formAction,'render'=>&$this));
        unset($this->data);
        $this->basictasksOptions();
        $this->adFieldsToDisplay($useAD,$ADDomain,$ADOU,$ADUser,$ADPass,$ADPassLegacy,$enforce);
        echo '<!-- Printers --><div id="group-printers">';
        printf('<form method="post" action="%s&tab=group-printers"><h2>%s</h2>',
            $this->formAction,
            _('Printer Management Level')
        );
        printf('<p class="l"><span class="icon fa fa-question hand" title="%s"></span>',
            _('This setting turns off all FOG Printer Management.  Although there are multiple levels already between host and global settings, this is just another to ensure safety')
        );
        printf('<input type="radio" name="level" value="0"%s/>%s<br/>',
            $printerLevel == 0 ? ' checked' : '',
            _('No Printer Management')
        );
        printf('<span class="icon fa fa-question hand" title="%s"></span>',
            _('This setting only adds and removes printers that FOG is aware of. Printers that are associated to the host will have those printers added.  Printers that are defined in FOG but not associated to the host will be removed')
        );
        printf('<input type="radio" name="level" value="1"%s/>%s<br/>',
            $printerLevel == 1 ? ' checked' : '',
            _('FOG Managed Printers')
        );
        printf('<span class="icon fa fa-question hand" title="%s"></span>',
            _('This setting only allows the host to have printers associated that are assigned through FOG. Any printer on the host that is not associated to the host through FOG will be removed')
        );
        printf('<input type="radio" name="level" value="2"%s/>%s<br/>',
            $printerLevel == 2 ? ' checked' : '',
            _('Only FOG Printers')
        );
        echo '</p>';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprint"/>',
            '',
            _('Printer Name'),
            _('Configuration'),
        );
        $this->templates = array(
            '<input type="checkbox" name="printers[]" value="${printer_id}" class="toggle-print" />',
            '<input class="default" type="radio" name="default" id="printer${printer_id}" value="${printer_id}" /><label for="printer${printer_id}" class="icon icon-hand" title="'._('Default Printer Selector').'">&nbsp;</label><input type="hidden" name="printerid[]" />',
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>16,'class'=>'l filter-false'),
            array(),
            array('width'=>50,'class'=>'r'),
        );
        array_map(function(&$Printer) {
            if (!$Printer->isValid()) return;
            $this->data[] = array(
                'printer_id'=>$Printer->get('id'),
                'printer_name'=>$Printer->get('name'),
                'printer_type'=>$Printer->get('config'),
            );
            unset($Printer);
        },self::getClass('PrinterManager')->find());
        $inputupdate = '';
        if (count($this->data) > 0) {
            printf('<h2>%s</h2>',_('Printer association(s)'));
            $inputupdate = sprintf('<p class="c"><input type="submit" value="%s" name="add"/>&nbsp<input type="submit" value="%s" name="remove"/><br/><br/><input type="submit" value="%s" name="update"/></p>',self::$foglang['Add'],self::$foglang['Remove'],_('Update'));
        }
        self::$HookManager->processEvent('GROUP_PRINTER',array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes,'inputupdate'=>&$inputupdate));
        $this->render();
        unset($this->data);
        echo "$inputupdate</form></div>";
        echo '<!-- Snapins --><div id="group-snapins">';
        printf('<h2>%s</h2>',_('Snapins'));
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
            array(),
            array('width'=>107,'class'=>'r'),
        );
        $returnSnapins = function(&$Snapin) {
            if (!$Snapin->isValid()) return;
            $this->data[] = array(
                'snapin_id' => $Snapin->get('id'),
                'snapin_name' => $Snapin->get('name'),
                'snapin_created' => $this->formatTime($Snapin->get('createdTime'),'Y-m-d H:i:s'),
            );
            unset($Snapin);
        };
        array_map($returnSnapins,self::getClass('SnapinManager')->find());
        self::$HookManager->processEvent('GROUP_SNAPINS',array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes,'inputupdate'=>&$inputupdate));
        if (count($this->data)) {
            printf('<form method="post" action="%s&tab=group-snapins">',$this->formAction);
            $this->render();
            printf('<p class="c"><input type="submit" value="%s" name="add"/>&nbsp<input type="submit" value="%s" name="remove"/></p></form>',self::$foglang['Add'],self::$foglang['Remove']);
        }
        unset($this->headerData,$this->data);
        echo '</div>';
        echo '<!-- Service Settings --><div id="group-service">';
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
        printf('<h2>%s</h2><form method="post" action="%s&tab=group-service"><fieldset><legend>%s</legend>',_('Service Configuration'),$this->formAction,_('General'));
        $dcnote = _('This module is only used on the old client. The old client is what was distributed with FOG 1.2.0 and earlier. This module did not work past Windows XP due to UAC introduced in Vista and up.');
        $gfnote = _('This module is only used on the old client. The old client is what was distributed with FOG 1.2.0 and earlier. This module has been replaced in the new client and the equivalent module for what Green FOG did is now called Power Management. This is only here to maintain old client operations.');
        $ucnote = _('This module is only used on the old client. The old client is what was distributed with FOG 1.2.0 and earlier. This module did not work past Windows XP due to UAC introduced in Vista and up.');
        $cunote = _('This module is only used (with modules) on the old client.  The new client only uses the module to tell it to allow updating automatically or not.');
        $moduleName = $this->getGlobalModuleStatus();
        $ModuleOn = array_values(self::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->obj->get('hosts')),'moduleID',false,'AND','id',false,''));
        array_map(function(&$Module) use ($moduleName,$ModuleOn,$HostCount,$dcnote,$gfnote,$ucnote,$cunote) {
            if (!$Module->isValid()) return;
            $note = '';
            switch($Module->get('shortName')) {
            case 'dircleanup':
                $note = sprintf('<i class="icon fa fa-exclamation-triangle fa-1x hand" title="%s"></i>',$dcnote);
                break;
            case 'greenfog':
                $note = sprintf('<i class="icon fa fa-exclamation-triangle fa-1x hand" title="%s"></i>',$gfnote);
                break;
            case 'usercleanup':
                $note = sprintf('<i class="icon fa fa-exclamation-triangle fa-1x hand" title="%s"></i>',$ucnote);
                break;
            case 'clientupdater':
                $note = sprintf('<i class="icon fa fa-exclamation-triangle fa-1x hand" title="%s"></i>',$cunote);
                break;
            default:
                $note = '';
                break;
            }
            $this->data[] = array(
                'input' => sprintf('<input %stype="checkbox" name="modules[]" value="%s"%s%s/>',($moduleName[$Module->get('shortName')] || ($moduleName[$Module->get('shortName')] && $Module->get('isDefault')) ? 'class="checkboxes" ': ''), $Module->get('id'),(count(array_keys($ModuleOn,$Module->get('id'))) == $HostCount ? ' checked' : ''),!$moduleName[$Module->get('shortName')] ? ' disabled' : ''),
                'span'=>sprintf('%s<span class="icon fa fa-question fa-1x hand" title="%s"></span>',$note,str_replace('"','\"',$Module->get('description'))),
                'mod_name'=>$Module->get('name'),
            );
            unset($Module);
        },(array)self::getClass('ModuleManager')->find());
        unset($moduleName,$ModuleOn);
        $this->data[] = array(
            'mod_name'=> '',
            'input'=>'',
            'span'=>sprintf('<input type="submit" name="updatestatus" value="%s"/>',_('Update')),
        );
        self::$HookManager->processEvent('GROUP_MODULES',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('</fieldset><fieldset><legend>%s</legend>',_('Group Screen Resolution'));
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
        array_map(function(&$Service) {
            if (!$Service->isValid()) return;
            switch ($Service->get('name')) {
            case 'FOG_CLIENT_DISPLAYMANAGER_X':
                $name = 'x';
                $field = _('Screen Width (in pixels)');
                break;
            case 'FOG_CLIENT_DISPLAYMANAGER_Y':
                $name = 'y';
                $field = _('Screen Height (in pixels)');
                break;
            case 'FOG_CLIENT_DISPLAYMANAGER_R':
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
        },self::getClass('ServiceManager')->find(array('name'=>array('FOG_CLIENT_DISPLAYMANAGER_X','FOG_CLIENT_DISPLAYMANAGER_Y','FOG_CLIENT_DISPLAYMANAGER_R')),'OR','id'));
        $this->data[] = array(
            'field'=>'',
            'input'=>'',
            'span'=>sprintf('<input type="submit" name="updatedisplay" value="%s"/>',_('Update')),
        );
        self::$HookManager->processEvent('GROUP_DISPLAY',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('</fieldset><fieldset><legend>%s</legend>',_('Auto Log Out Settings'));
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
        $Service = self::getClass('Service',@max(self::getSubObjectIDs('Service',array('name'=>'FOG_CLIENT_AUTOLOGOFF_MIN'))));
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
        self::$HookManager->processEvent('GROUP_ALO',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</fieldset></form></div>';
        echo '<!-- Power Management Items --><div id="group-powermanagement"><div id="delAllPMBox"></div><div class="c"><input type="button" id="delAllPM"/></div><br/>';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Schedule Power') => sprintf('<p id="cronOptions"><input type="text" name="scheduleCronMin" id="scheduleCronMin" placeholder="min" autocomplete="off" value="%s"/><input type="text" name="scheduleCronHour" id="scheduleCronHour" placeholder="hour" autocomplete="off" value="%s"/><input type="text" name="scheduleCronDOM" id="scheduleCronDOM" placeholder="dom" autocomplete="off" value="%s"/><input type="text" name="scheduleCronMonth" id="scheduleCronMonth" placeholder="month" autocomplete="off" value="%s"/><input type="text" name="scheduleCronDOW" id="scheduleCronDOW" placeholder="dow" autocomplete="off" value="%s"/></p>',$_REQUEST['scheduleCronMin'],$_REQUEST['scheduleCronHour'],$_REQUEST['scheduleCronDOM'],$_REQUEST['scheduleCronMonth'],$_REQUEST['scheduleCronDOW']),
            _('Perform Immediately?') => sprintf('<input type="checkbox" name="onDemand" id="scheduleOnDemand"%s/>',!is_array($_REQUEST['onDemand']) && isset($_REQUEST['onDemand']) ? ' checked' : ''),
            _('Action') => self::getClass('PowerManagementManager')->getActionSelect($_REQUEST['action']),
        );
        array_walk($fields,function(&$input,&$field) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        });
        printf('<form method="post" action="%s&tab=group-powermanagement" class="deploy-container">',$this->formAction);
        $this->render();
        printf('<center><input type="submit" name="pmsubmit" value="%s"/></center></form></div>',_('Add Option'));
        unset($this->headerData,$this->templates,$this->data,$this->attributes);
        echo '<!-- Inventory -->';
        printf('<div id="group-inventory"><h2>%s %s</h2>',_('Group'),self::$foglang['Inventory']);
        printf(
            $this->reportString,
            sprintf('Group_%s_InventoryReport',$this->obj->get('name')),
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            sprintf('Group_%s_InventoryReport',$this->obj->get('name')),
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $this->ReportMaker = self::getClass('ReportMaker');
        array_walk(self::$inventoryCsvHead,function(&$classGet,&$csvHeader) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet,$csvHeader);
        });
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Host name'),
            _('Memory'),
            _('System Product'),
            _('System Serial'),
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '${memory}',
            '${sysprod}',
            '${sysser}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        $Hosts = self::getClass('HostManager')->find(array('id'=>$this->obj->get('hosts')));
        array_walk($Hosts, function(&$Host,&$index) {
            if (!$Host->isValid()) return;
            if (!$Host->get('inventory')->isValid()) return;
            $Image = $Host->getImage();
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'memory' => $Host->get('inventory')->getMem(),
                'sysprod' => $Host->get('inventory')->get('sysproduct'),
                'sysser' => $Host->get('inventory')->get('sysserial'),
            );
            array_walk(self::$inventoryCsvHead,function(&$classGet,&$csvHead) use ($Host) {
                switch ($csvHead) {
                case _('Host ID'):
                    $this->ReportMaker->addCSVCell($Host->get('id'));
                    break;
                case _('Host name'):
                    $this->ReportMaker->addCSVCell($Host->get('name'));
                    break;
                case _('Host MAC'):
                    $this->ReportMaker->addCSVCell($Host->get('mac'));
                    break;
                case _('Host Desc'):
                    $this->ReportMaker->addCSVCell($Host->get('description'));
                    break;
                case _('Host Memory'):
                    $this->ReportMaker->addCSVCell($Host->get('inventory')->getMem());
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Host->get('inventory')->get($classGet));
                    break;
                }
                unset($classGet,$csvHead);
            });
            $this->ReportMaker->endCSVLine();
            unset($Host,$index);
        });
        unset($Hosts);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
        echo '</div></div>';
        unset($imageID,$imageMatchID,$groupKey,$groupKeyMatch,$aduse,$adDomain,$adOU,$adUser,$adPass,$adPassLegacy,$useAD,$ADOU,$ADDomain,$ADUser,$adPass,$ADPass,$ADPassLegacy,$biosExit,$efiExit,$exitNorm,$exitEfi);
    }
    public function edit_post() {
        self::$HookManager->processEvent('GROUP_EDIT_POST',array('Group'=>&$Group));
        try {
            $hostids = $this->obj->get('hosts');
            switch($_REQUEST['tab']) {
            case 'group-general':
                if (empty($_REQUEST['name'])) throw new Exception(_('Group Name is required'));
                $this->obj->set('name',$_REQUEST['name'])
                    ->set('description',$_REQUEST['description'])
                    ->set('kernel',$_REQUEST['kern'])
                    ->set('kernelArgs',$_REQUEST['args'])
                    ->set('kernelDevice',$_REQUEST['dev']);
                $productKey = preg_replace('/([\w+]{5})/','$1-',str_replace('-','',strtoupper(trim($_REQUEST['key']))));
                $productKey = substr($productKey,0,29);
                self::getClass('HostManager')->update(array('id'=>$hostids),'',array('kernel'=>$_REQUEST['kern'],'kernelArgs'=>$_REQUEST['args'],'kernelDevice'=>$_REQUEST['dev'],'efiexit'=>$_REQUEST['efiBootTypeExit'],'biosexit'=>$_REQUEST['bootTypeExit'],'productKey'=>$this->encryptpw(trim($_REQUEST['key']))));
                break;
            case 'group-image':
                $this->obj->addImage($_REQUEST['image']);
                break;
            case 'group-active-directory':
                $useAD = isset($_REQUEST['domain']);
                $domain = $_REQUEST['domainname'];
                $ou = $_REQUEST['ou'];
                $user = $_REQUEST['domainuser'];
                $pass = $_REQUEST['domainpassword'];
                $legacy = $_REQUEST['domainpasswordlegacy'];
                $enforce = isset($_REQUEST['enforcesel']);
                $this->obj->setAD($useAD,$domain,$ou,$user,$pass,$legacy,$enforce);
                break;
            case 'group-printers':
                if (isset($_REQUEST['add'])) {
                    $this->obj->addPrinter($_REQUEST['printers'],array(),$_REQUEST['level']);
                    if (in_array($_REQUEST['default'],(array)$_REQUEST['printers'])) $this->obj->updateDefault($_REQUEST['default']);
                }
                if (isset($_REQUEST['remove'])) $this->obj->addPrinter(array(),$_REQUEST['printers'],$_REQUEST['level']);
                if (isset($_REQUEST['update'])) {
                    $this->obj->addPrinter(array(),array(),$_REQUEST['level']);
                    $this->obj->addPrinter($_REQUEST['default'],array(),$_REQUEST['level']);
                    $this->obj->updateDefault($_REQUEST['default']);
                }
                break;
            case 'group-snapins':
                if (isset($_REQUEST['add'])) $this->obj->addSnapin($_REQUEST['snapin']);
                if (isset($_REQUEST['remove'])) $this->obj->removeSnapin($_REQUEST['snapin']);
                break;
            case 'group-service':
                list($r,$time,$x,$y,) = self::getSubObjectIDs('Service',array('name'=>array('FOG_CLIENT_DISPLAYMANAGER_R','FOG_CLIENT_AUTOLOGOFF_MIN','FOG_CLIENT_DISPLAYMANAGER_X','FOG_CLIENTDISPLAYMANAGER_Y')),'value');
                $x =(is_numeric($_REQUEST['x']) ? $_REQUEST['x'] : $x);
                $y =(is_numeric($_REQUEST['y']) ? $_REQUEST['y'] : $y);
                $r =(is_numeric($_REQUEST['r']) ? $_REQUEST['r'] : $r);
                $time = (is_numeric($_REQUEST['tme']) ? $_REQUEST['tme'] : $time);
                $modOn = (array)$_REQUEST['modules'];
                $modOff = self::getSubObjectIDs('Module',array('id'=>$modOn),'id',true);
                $this->obj->addModule($modOn)->removeModule($modOff)->setDisp($x,$y,$r)->setAlo($time);
                break;
            case 'group-powermanagement':
                $min = $_REQUEST['scheduleCronMin'];
                $hour = $_REQUEST['scheduleCronHour'];
                $dom = $_REQUEST['scheduleCronDOM'];
                $month = $_REQUEST['scheduleCronMonth'];
                $dow = $_REQUEST['scheduleCronDOW'];
                $onDemand = (string)intval(isset($_REQUEST['onDemand']));
                $action = $_REQUEST['action'];
                if (!$action) throw new Exception(_('You must select an action to perform'));
                $items = array();
                if (isset($_REQUEST['pmsubmit'])) {
                    if ($onDemand && $action === 'wol'){
                        array_map(function(&$Host) {
                            $Host->wakeOnLAN();
                        },(array)self::getClass('HostManager')->find(array('id'=>$this->obj->get('hosts'))));
                        break;
                    }
                    $hostIDs = (array)$this->obj->get('hosts');
                    array_map(function($hostID) use ($min,$hour,$dom,$month,$dow,$onDemand,$action,&$items) {
                        $items[] = array($hostID,$min,$hour,$dom,$month,$dow,$onDemand,$action);
                    },(array)$hostIDs);
                    self::getClass('PowerManagementManager')->insert_batch(array('hostID','min','hour','dom','month','dow','onDemand','action'),$items);
                }
                break;
            }
            if (!$this->obj->save()) throw new Exception(_('Database update failed'));
            self::$HookManager->processEvent('GROUP_EDIT_SUCCESS', array('Group' => &$this->obj));
            $this->setMessage('Group information updated!');
        } catch (Exception $e) {
            self::$HookManager->processEvent('GROUP_EDIT_FAIL', array('Group' => &$this->obj));
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
        $hostids = $this->obj->get('hosts');
        array_map(function(&$Host) {
            if (!$Host->isValid()) return;
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'host_deployed' => $this->formatTime($Host->get('deployed'),'Y-m-d H:i:s'),
            );
            unset($Host);
        }, self::getClass('HostManager')->find(array('id'=>$hostids)));
        printf('<p>%s</p>',_('Confirm you really want to delete the following hosts'));
        printf('<form method="post" action="?node=group&sub=delete&id=%s" class="c">',$this->obj->get('id'));
        self::$HookManager->processEvent('GROUP_DELETE_HOST_FORM',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
        $this->render();
        printf('<input type="submit" name="delHostConfirm" value="%s" /></form>',_('Delete listed hosts'));
    }
}
=======
<?php
class GroupManagementPage extends FOGPage {
    public $node = 'group';
    public function __construct($name = '') {
        $this->name = 'Group Management';
        parent::__construct($this->name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat#group-general" => self::$foglang['General'],
                "$this->linkformat#group-image" => self::$foglang['ImageAssoc'],
                "$this->linkformat#group-tasks" => self::$foglang['BasicTasks'],
                "$this->linkformat#group-active-directory" => self::$foglang['AD'],
                "$this->linkformat#group-printers" => self::$foglang['Printers'],
                "$this->linkformat#group-snapins" => self::$foglang['Snapins'],
                "$this->linkformat#group-service" => sprintf('%s %s',self::$foglang['Service'],self::$foglang['Settings']),
                "$this->linkformat#group-powermanagement"=>self::$foglang['PowerManagement'],
                "$this->linkformat#group-inventory" => self::$foglang['Inventory'],
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Group'] => $this->obj->get('name'),
                self::$foglang['Members'] => $this->obj->getHostCount(),
            );
        }
        self::$HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes,'object'=>&$this->obj,'linkformat'=>&$this->linkformat,'delformat'=>&$this->delformat,'membership'=>&$this->membership));
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Name'),
            _('Members'),
            _('Tasking'),
        );
        $down = self::getClass('TaskType',1);
        $mc = self::getClass('TaskType',8);
        $this->templates = array(
            '<input type="checkbox" name="group[]" value="${id}" class="toggle-action" />',
            sprintf('<a href="?node=group&sub=edit&%s=${id}" title="Edit">${name}</a>', $this->id),
            '${count}',
            sprintf('<a href="?node=group&sub=deploy&type=1&%s=${id}"><i class="icon fa fa-'.$down->get('icon').'" title="'.$down->get('name').'"></i></a> <a href="?node=group&sub=deploy&type=8&%s=${id}"><i class="icon fa fa-'.$mc->get('icon').'" title="'.$mc->get('name').'"></i></a> <a href="?node=group&sub=edit&%s=${id}#group-tasks"><i class="icon fa fa-arrows-alt" title="Goto Basic Tasks"></i></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array(),
            array('width'=>30,'class'=>'c'),
            array('width'=>90,'class'=>'c filter-false'),
        );
        self::$returnData = function(&$Group) {
            if (!$Group->isValid()) return;
            $this->data[] = array(
                'id' => $Group->get('id'),
                'name' => $Group->get('name'),
                'description' => $Group->get('description'),
                'count' => $Group->getHostCount(),
            );
            unset($Group);
        };
    }
    public function index() {
        $this->title = _('All Groups');
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['GroupCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('GROUP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->search('',true));
        self::$HookManager->processEvent('GROUP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = _('New Group');
        $this->data = array();
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
            _('Group Name') => sprintf('<input type="text" class="groupname-input" name="name" value="%s"/>',$_REQUEST['name']),
            _('Group Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$_REQUEST['description']),
            _('Group Kernel') => sprintf('<input type="text" name="kern" value="%s"/>',$_REQUEST['kern']),
            _('Group Kernel Arguments') => sprintf('<input type="text" name="args" name="%s"/>',$_REQUEST['args']),
            _('Group Primary Disk') => sprintf('<input type="text" name="dev" name="%s"/>',$_REQUEST['dev']),
            '' => sprintf('<input type="submit" value="%s"/>',_('Add')),
        );
        printf('<form method="post" action="%s">',$this->formAction);
        array_walk($fields,function(&$formField,&$field) {
            $this->data[] = array(
                'field' => $field,
                'formField' => $formField,
            );
            unset($formField,$field);
        });
        unset($fields);
        self::$HookManager->processEvent('GROUP_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        self::$HookManager->processEvent('GROUP_ADD_POST');
        try {
            if (empty($_REQUEST['name'])) throw new Exception('Group Name is required');
            if (self::getClass('GroupManager')->exists($_REQUEST['name'])) throw new Exception('Group Name already exists');
            $Group = self::getClass('Group')
                ->set('name',$_REQUEST['name'])
                ->set('description',$_REQUEST['description'])
                ->set('kernel',$_REQUEST['kern'])
                ->set('kernelArgs',$_REQUEST['args'])
                ->set('kernelDevice',$_REQUEST['dev']);
            if (!$Group->save()) throw new Exception(_('Group create failed'));
            self::$HookManager->processEvent('GROUP_ADD_SUCCESS', array('Group' => &$Group));
            $this->setMessage(_('Group added'));
            $url = sprintf('?node=%s&sub=edit&id=%s',$_REQUEST['node'],$Group->get('id'));
        } catch (Exception $e) {
            self::$HookManager->processEvent('GROUP_ADD_FAIL', array('Group' => &$Group));
            $this->setMessage($e->getMessage());
            $url = $this->formAction;
        }
        unset($Group);
        $this->redirect($url);
    }
    public function edit() {
        $HostCount = $this->obj->getHostCount();
        $hostids = $this->obj->get('hosts');
        $Host = self::getClass('Host',current((array)$hostids));
        $imageIDs = self::getSubObjectIDs('Host',array('id'=>$hostids),'imageID','','','','','array_count_values');
        $imageIDs = array_shift($imageIDs);
        $groupKey = self::getSubObjectIDs('Host',array('id'=>$hostids),'productKey','','','','','array_count_values');
        $groupKey = array_shift($groupKey);
        $printerLevel = self::getSubObjectIDs('Host',array('id'=>$hostids),'printerLevel','','','','','array_count_values');
        $printerLevel = array_shift($printerLevel);
        // Collect AD Information
        $aduse = self::getSubObjectIDs('Host',array('id'=>$hostids),'useAD','','','','','array_count_values');
        $aduse = in_array(0,array_keys($aduse)) ? 0 : array_shift($aduse);
        $enforcetest = self::getSubObjectIDs('Host',array('id'=>$hostids),'enforce','','','','','array_count_values');
        $enforcetest = array_shift($enforcetest);
        $adDomain = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADDomain','','','','','array_count_values');
        $adDomain = array_shift($adDomain);
        $adOU = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADOU','','','','','array_count_values');
        $adOU = array_shift($adOU);
        $adUser = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADUser','','','','','array_count_values');
        $adUser = array_shift($adUser);
        $adPass = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADPass','','','','','array_count_values');
        $adPass = array_shift($adPass);
        $adPassLegacy = self::getSubObjectIDs('Host',array('id'=>$hostids),'ADPassLegacy','','','','','array_count_values');
        $adPassLegacy = array_shift($adPassLegacy);
        // Set Field Information
        $printerLevel = ($printerLevel == $HostCount ? $Host->get('printerLevel') : '');
        $imageMatchID = ($imageIDs == $HostCount ? $Host->get('imageID') : '');
        $useAD = ($aduse == $HostCount ? $Host->get('useAD') : '');
        $enforce = ($enforcetest == $HostCount ? $Host->get('enforce') : '');
        $ADDomain = ($adDomain == $HostCount ? $Host->get('ADDomain') : '');
        $ADOU = ($adOU == $HostCount ? $Host->get('ADOU') : '');
        $ADUser = ($adUser == $HostCount ? $Host->get('ADUser') : '');
        $adPass = ($adPass == $HostCount ? $Host->get('ADPass') : '');
        $ADPass = $this->encryptpw($Host->get('ADPass'));
        $ADPassLegacy = ($adPassLegacy == $HostCount ? $Host->get('ADPassLegacy') : '');
        $productKey = ($groupKey == $HostCount ? $Host->get('productKey') : '');
        $groupKeyMatch = $this->encryptpw($productKey);
        unset($productKey, $groupKey);
        $biosExit = array_flip(self::getSubObjectIDs('Host',array('id'=>$hostids),'biosexit','','','','','array_count_values'));
        $efiExit = array_flip(self::getSubObjectIDs('Host',array('id'=>$hostids),'efiexit','','','','','array_count_values'));
        $exitNorm = Service::buildExitSelector('bootTypeExit',(count($biosExit) === 1 && isset($biosExit[1]) ? $Host->get('biosexit') : $_REQUEST['bootTypeExit']),true);
        $exitEfi = Service::buildExitSelector('efiBootTypeExit',(count($efiExit) === 1 && isset($efiExit[1]) ? $Host->get('efiexit') : $_REQUEST['efiBootTypeExit']),true);
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
            _('Group Name') => sprintf('<input type="text" class="groupname-input" name="name" value="%s"/>',$this->obj->get('name')),
            _('Group Description') => sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            _('Group Product Key') => sprintf('<input id="productKey" type="text" name="key" value="%s"/>',$this->aesdecrypt($groupKeyMatch)),
            _('Group Kernel') => sprintf('<input type="text" name="kern" value="%s"/>',$this->obj->get('kernel')),
            _('Group Kernel Arguments') => sprintf('<input type="text" name="args" value="%s"/>',$this->obj->get('kernelArgs')),
            _('Group Primary Disk') => sprintf('<input type="text" name="dev" value="%s"/>',$this->obj->get('kernelDev')),
            _('Group Bios Exit Type') => $exitNorm,
            _('Group EFI Exit Type') => $exitEfi,
            '&nbsp;' => sprintf('<input type="submit" name="updategroup" value="%s"/>',_('Update')),
        );
        self::$HookManager->processEvent('GROUP_FIELDS',array('fields'=>&$fields,'Group'=>&$this->obj));
        printf('<form method="post" action="%s&tab=group-general"><div id="tab-container"><!-- General --><div id="group-general"><h2>%s: %s</h2><div id="resetSecDataBox" class="hidden"></div><div class="c"><input type="button" id="resetSecData"/></div><br/>',$this->formAction,_('Modify Group'),$this->obj->get('name'));
        array_walk($fields,function(&$input,&$field) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input,$field);
        });
        unset($fields);
        self::$HookManager->processEvent('GROUP_DATA_GEN',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset ($this->data,$exitNorm,$exitEfi);
        echo '</form></div>';
        unset($this->data);
        $imageSelector = self::getClass('ImageManager')->buildSelectBox($imageMatchID,'image');
        echo '<!-- Image Association --><div id="group-image">';
        printf('<h2>%s: %s</h2><form method="post" action="%s&tab=group-image">',_('Image Association for'),$this->obj->get('name'),$this->formAction);
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
        self::$HookManager->processEvent('GROUP_IMAGE',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form></div>';
        unset($this->data);
        self::$HookManager->processEvent('GROUP_GENERAL_EXTRA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes,'Group'=>&$this->obj,'formAction'=>&$this->formAction,'render'=>&$this));
        unset($this->data);
        $this->basictasksOptions();
        $this->adFieldsToDisplay($useAD,$ADDomain,$ADOU,$ADUser,$ADPass,$ADPassLegacy,$enforce);
        echo '<!-- Printers --><div id="group-printers">';
        printf('<form method="post" action="%s&tab=group-printers"><h2>%s</h2>',
            $this->formAction,
            _('Printer Management Level')
        );
        printf('<p class="l"><span class="icon fa fa-question hand" title="%s"></span>',
            _('This setting turns off all FOG Printer Management.  Although there are multiple levels already between host and global settings, this is just another to ensure safety')
        );
        printf('<input type="radio" name="level" value="0"%s/>%s<br/>',
            $printerLevel == 0 ? ' checked' : '',
            _('No Printer Management')
        );
        printf('<span class="icon fa fa-question hand" title="%s"></span>',
            _('This setting only adds and removes printers that FOG is aware of. Printers that are associated to the host will have those printers added.  Printers that are defined in FOG but not associated to the host will be removed')
        );
        printf('<input type="radio" name="level" value="1"%s/>%s<br/>',
            $printerLevel == 1 ? ' checked' : '',
            _('FOG Managed Printers')
        );
        printf('<span class="icon fa fa-question hand" title="%s"></span>',
            _('This setting only allows the host to have printers associated that are assigned through FOG. Any printer on the host that is not associated to the host through FOG will be removed')
        );
        printf('<input type="radio" name="level" value="2"%s/>%s<br/>',
            $printerLevel == 2 ? ' checked' : '',
            _('Only FOG Printers')
        );
        echo '</p>';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprint"/>',
            '',
            _('Printer Name'),
            _('Configuration'),
        );
        $this->templates = array(
            '<input type="checkbox" name="printers[]" value="${printer_id}" class="toggle-print" />',
            '<input class="default" type="radio" name="default" id="printer${printer_id}" value="${printer_id}" /><label for="printer${printer_id}" class="icon icon-hand" title="'._('Default Printer Selector').'">&nbsp;</label><input type="hidden" name="printerid[]" />',
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>16,'class'=>'l filter-false'),
            array(),
            array('width'=>50,'class'=>'r'),
        );
        array_map(function(&$Printer) {
            if (!$Printer->isValid()) return;
            $this->data[] = array(
                'printer_id'=>$Printer->get('id'),
                'printer_name'=>$Printer->get('name'),
                'printer_type'=>$Printer->get('config'),
            );
            unset($Printer);
        },self::getClass('PrinterManager')->find());
        $inputupdate = '';
        if (count($this->data) > 0) {
            printf('<h2>%s</h2>',_('Printer association(s)'));
            $inputupdate = sprintf('<p class="c"><input type="submit" value="%s" name="add"/>&nbsp<input type="submit" value="%s" name="remove"/><br/><br/><input type="submit" value="%s" name="update"/></p>',self::$foglang['Add'],self::$foglang['Remove'],_('Update'));
        }
        self::$HookManager->processEvent('GROUP_PRINTER',array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes,'inputupdate'=>&$inputupdate));
        $this->render();
        unset($this->data);
        echo "$inputupdate</form></div>";
        echo '<!-- Snapins --><div id="group-snapins">';
        printf('<h2>%s</h2>',_('Snapins'));
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
            array(),
            array('width'=>107,'class'=>'r'),
        );
        $returnSnapins = function(&$Snapin) {
            if (!$Snapin->isValid()) return;
            $this->data[] = array(
                'snapin_id' => $Snapin->get('id'),
                'snapin_name' => $Snapin->get('name'),
                'snapin_created' => $this->formatTime($Snapin->get('createdTime'),'Y-m-d H:i:s'),
            );
            unset($Snapin);
        };
        array_map($returnSnapins,self::getClass('SnapinManager')->find());
        self::$HookManager->processEvent('GROUP_SNAPINS',array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes,'inputupdate'=>&$inputupdate));
        if (count($this->data)) {
            printf('<form method="post" action="%s&tab=group-snapins">',$this->formAction);
            $this->render();
            printf('<p class="c"><input type="submit" value="%s" name="add"/>&nbsp<input type="submit" value="%s" name="remove"/></p></form>',self::$foglang['Add'],self::$foglang['Remove']);
        }
        unset($this->headerData,$this->data);
        echo '</div>';
        echo '<!-- Service Settings --><div id="group-service">';
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
        printf('<h2>%s</h2><form method="post" action="%s&tab=group-service"><fieldset><legend>%s</legend>',_('Service Configuration'),$this->formAction,_('General'));
        $dcnote = _('This module is only used on the old client. The old client is what was distributed with FOG 1.2.0 and earlier. This module did not work past Windows XP due to UAC introduced in Vista and up.');
        $gfnote = _('This module is only used on the old client. The old client is what was distributed with FOG 1.2.0 and earlier. This module has been replaced in the new client and the equivalent module for what Green FOG did is now called Power Management. This is only here to maintain old client operations.');
        $ucnote = _('This module is only used on the old client. The old client is what was distributed with FOG 1.2.0 and earlier. This module did not work past Windows XP due to UAC introduced in Vista and up.');
        $cunote = _('This module is only used (with modules) on the old client.  The new client only uses the module to tell it to allow updating automatically or not.');
        $moduleName = $this->getGlobalModuleStatus();
        $ModuleOn = array_values(self::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->obj->get('hosts')),'moduleID',false,'AND','id',false,''));
        array_map(function(&$Module) use ($moduleName,$ModuleOn,$HostCount,$dcnote,$gfnote,$ucnote,$cunote) {
            if (!$Module->isValid()) return;
            $note = '';
            switch($Module->get('shortName')) {
            case 'dircleanup':
                $note = sprintf('<i class="icon fa fa-exclamation-triangle fa-1x hand" title="%s"></i>',$dcnote);
                break;
            case 'greenfog':
                $note = sprintf('<i class="icon fa fa-exclamation-triangle fa-1x hand" title="%s"></i>',$gfnote);
                break;
            case 'usercleanup':
                $note = sprintf('<i class="icon fa fa-exclamation-triangle fa-1x hand" title="%s"></i>',$ucnote);
                break;
            case 'clientupdater':
                $note = sprintf('<i class="icon fa fa-exclamation-triangle fa-1x hand" title="%s"></i>',$cunote);
                break;
            default:
                $note = '';
                break;
            }
            $this->data[] = array(
                'input' => sprintf('<input %stype="checkbox" name="modules[]" value="%s"%s%s/>',($moduleName[$Module->get('shortName')] || ($moduleName[$Module->get('shortName')] && $Module->get('isDefault')) ? 'class="checkboxes" ': ''), $Module->get('id'),(count(array_keys($ModuleOn,$Module->get('id'))) == $HostCount ? ' checked' : ''),!$moduleName[$Module->get('shortName')] ? ' disabled' : ''),
                'span'=>sprintf('%s<span class="icon fa fa-question fa-1x hand" title="%s"></span>',$note,str_replace('"','\"',$Module->get('description'))),
                'mod_name'=>$Module->get('name'),
            );
            unset($Module);
        },(array)self::getClass('ModuleManager')->find());
        unset($moduleName,$ModuleOn);
        $this->data[] = array(
            'mod_name'=> '',
            'input'=>'',
            'span'=>sprintf('<input type="submit" name="updatestatus" value="%s"/>',_('Update')),
        );
        self::$HookManager->processEvent('GROUP_MODULES',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('</fieldset><fieldset><legend>%s</legend>',_('Group Screen Resolution'));
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
        array_map(function(&$Service) {
            if (!$Service->isValid()) return;
            switch ($Service->get('name')) {
            case 'FOG_CLIENT_DISPLAYMANAGER_X':
                $name = 'x';
                $field = _('Screen Width (in pixels)');
                break;
            case 'FOG_CLIENT_DISPLAYMANAGER_Y':
                $name = 'y';
                $field = _('Screen Height (in pixels)');
                break;
            case 'FOG_CLIENT_DISPLAYMANAGER_R':
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
        },self::getClass('ServiceManager')->find(array('name'=>array('FOG_CLIENT_DISPLAYMANAGER_X','FOG_CLIENT_DISPLAYMANAGER_Y','FOG_CLIENT_DISPLAYMANAGER_R')),'OR','id'));
        $this->data[] = array(
            'field'=>'',
            'input'=>'',
            'span'=>sprintf('<input type="submit" name="updatedisplay" value="%s"/>',_('Update')),
        );
        self::$HookManager->processEvent('GROUP_DISPLAY',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('</fieldset><fieldset><legend>%s</legend>',_('Auto Log Out Settings'));
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
        $Service = self::getClass('Service',@max(self::getSubObjectIDs('Service',array('name'=>'FOG_CLIENT_AUTOLOGOFF_MIN'))));
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
        self::$HookManager->processEvent('GROUP_ALO',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</fieldset></form></div>';
        echo '<!-- Power Management Items --><div id="group-powermanagement"><div id="delAllPMBox"></div><div class="c"><input type="button" id="delAllPM"/></div><br/>';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Schedule Power') => sprintf('<p id="cronOptions"><input type="text" name="scheduleCronMin" id="scheduleCronMin" placeholder="min" autocomplete="off" value="%s"/><input type="text" name="scheduleCronHour" id="scheduleCronHour" placeholder="hour" autocomplete="off" value="%s"/><input type="text" name="scheduleCronDOM" id="scheduleCronDOM" placeholder="dom" autocomplete="off" value="%s"/><input type="text" name="scheduleCronMonth" id="scheduleCronMonth" placeholder="month" autocomplete="off" value="%s"/><input type="text" name="scheduleCronDOW" id="scheduleCronDOW" placeholder="dow" autocomplete="off" value="%s"/></p>',$_REQUEST['scheduleCronMin'],$_REQUEST['scheduleCronHour'],$_REQUEST['scheduleCronDOM'],$_REQUEST['scheduleCronMonth'],$_REQUEST['scheduleCronDOW']),
            _('Perform Immediately?') => sprintf('<input type="checkbox" name="onDemand" id="scheduleOnDemand"%s/>',!is_array($_REQUEST['onDemand']) && isset($_REQUEST['onDemand']) ? ' checked' : ''),
            _('Action') => self::getClass('PowerManagementManager')->getActionSelect($_REQUEST['action']),
        );
        array_walk($fields,function(&$input,&$field) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        });
        printf('<form method="post" action="%s&tab=group-powermanagement" class="deploy-container">',$this->formAction);
        $this->render();
        printf('<center><input type="submit" name="pmsubmit" value="%s"/></center></form></div>',_('Add Option'));
        unset($this->headerData,$this->templates,$this->data,$this->attributes);
        echo '<!-- Inventory -->';
        printf('<div id="group-inventory"><h2>%s %s</h2>',_('Group'),self::$foglang['Inventory']);
        printf(
            $this->reportString,
            sprintf('Group_%s_InventoryReport',$this->obj->get('name')),
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            sprintf('Group_%s_InventoryReport',$this->obj->get('name')),
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $this->ReportMaker = self::getClass('ReportMaker');
        array_walk(self::$inventoryCsvHead,function(&$classGet,&$csvHeader) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet,$csvHeader);
        });
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Host name'),
            _('Memory'),
            _('System Product'),
            _('System Serial'),
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '${memory}',
            '${sysprod}',
            '${sysser}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        $Hosts = self::getClass('HostManager')->find(array('id'=>$this->obj->get('hosts')));
        array_walk($Hosts, function(&$Host,&$index) {
            if (!$Host->isValid()) return;
            if (!$Host->get('inventory')->isValid()) return;
            $Image = $Host->getImage();
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'memory' => $Host->get('inventory')->getMem(),
                'sysprod' => $Host->get('inventory')->get('sysproduct'),
                'sysser' => $Host->get('inventory')->get('sysserial'),
            );
            array_walk(self::$inventoryCsvHead,function(&$classGet,&$csvHead) use ($Host) {
                switch ($csvHead) {
                case _('Host ID'):
                    $this->ReportMaker->addCSVCell($Host->get('id'));
                    break;
                case _('Host name'):
                    $this->ReportMaker->addCSVCell($Host->get('name'));
                    break;
                case _('Host MAC'):
                    $this->ReportMaker->addCSVCell($Host->get('mac'));
                    break;
                case _('Host Desc'):
                    $this->ReportMaker->addCSVCell($Host->get('description'));
                    break;
                case _('Host Memory'):
                    $this->ReportMaker->addCSVCell($Host->get('inventory')->getMem());
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Host->get('inventory')->get($classGet));
                    break;
                }
                unset($classGet,$csvHead);
            });
            $this->ReportMaker->endCSVLine();
            unset($Host,$index);
        });
        unset($Hosts);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
        echo '</div></div>';
        unset($imageID,$imageMatchID,$groupKey,$groupKeyMatch,$aduse,$adDomain,$adOU,$adUser,$adPass,$adPassLegacy,$useAD,$ADOU,$ADDomain,$ADUser,$adPass,$ADPass,$ADPassLegacy,$biosExit,$efiExit,$exitNorm,$exitEfi);
    }
    public function edit_post() {
        self::$HookManager->processEvent('GROUP_EDIT_POST',array('Group'=>&$Group));
        try {
            $hostids = $this->obj->get('hosts');
            switch($_REQUEST['tab']) {
            case 'group-general':
                if (empty($_REQUEST['name'])) throw new Exception(_('Group Name is required'));
                $this->obj->set('name',$_REQUEST['name'])
                    ->set('description',$_REQUEST['description'])
                    ->set('kernel',$_REQUEST['kern'])
                    ->set('kernelArgs',$_REQUEST['args'])
                    ->set('kernelDevice',$_REQUEST['dev']);
                $productKey = preg_replace('/([\w+]{5})/','$1-',str_replace('-','',strtoupper(trim($_REQUEST['key']))));
                $productKey = substr($productKey,0,29);
                self::getClass('HostManager')->update(array('id'=>$hostids),'',array('kernel'=>$_REQUEST['kern'],'kernelArgs'=>$_REQUEST['args'],'kernelDevice'=>$_REQUEST['dev'],'efiexit'=>$_REQUEST['efiBootTypeExit'],'biosexit'=>$_REQUEST['bootTypeExit'],'productKey'=>$this->encryptpw(trim($_REQUEST['key']))));
                break;
            case 'group-image':
                $this->obj->addImage($_REQUEST['image']);
                break;
            case 'group-active-directory':
                $useAD = isset($_REQUEST['domain']);
                $domain = $_REQUEST['domainname'];
                $ou = $_REQUEST['ou'];
                $user = $_REQUEST['domainuser'];
                $pass = $_REQUEST['domainpassword'];
                $legacy = $_REQUEST['domainpasswordlegacy'];
                $enforce = isset($_REQUEST['enforcesel']);
                $this->obj->setAD($useAD,$domain,$ou,$user,$pass,$legacy,$enforce);
                break;
            case 'group-printers':
                if (isset($_REQUEST['add'])) {
                    $this->obj->addPrinter($_REQUEST['printers'],array(),$_REQUEST['level']);
                    if (in_array($_REQUEST['default'],(array)$_REQUEST['printers'])) $this->obj->updateDefault($_REQUEST['default']);
                }
                if (isset($_REQUEST['remove'])) $this->obj->addPrinter(array(),$_REQUEST['printers'],$_REQUEST['level']);
                if (isset($_REQUEST['update'])) {
                    $this->obj->addPrinter(array(),array(),$_REQUEST['level']);
                    $this->obj->addPrinter($_REQUEST['default'],array(),$_REQUEST['level']);
                    $this->obj->updateDefault($_REQUEST['default']);
                }
                break;
            case 'group-snapins':
                if (isset($_REQUEST['add'])) $this->obj->addSnapin($_REQUEST['snapin']);
                if (isset($_REQUEST['remove'])) $this->obj->removeSnapin($_REQUEST['snapin']);
                break;
            case 'group-service':
                list($r,$time,$x,$y,) = self::getSubObjectIDs('Service',array('name'=>array('FOG_CLIENT_DISPLAYMANAGER_R','FOG_CLIENT_AUTOLOGOFF_MIN','FOG_CLIENT_DISPLAYMANAGER_X','FOG_CLIENTDISPLAYMANAGER_Y')),'value');
                $x =(is_numeric($_REQUEST['x']) ? $_REQUEST['x'] : $x);
                $y =(is_numeric($_REQUEST['y']) ? $_REQUEST['y'] : $y);
                $r =(is_numeric($_REQUEST['r']) ? $_REQUEST['r'] : $r);
                $time = (is_numeric($_REQUEST['tme']) ? $_REQUEST['tme'] : $time);
                $modOn = (array)$_REQUEST['modules'];
                $modOff = self::getSubObjectIDs('Module',array('id'=>$modOn),'id',true);
                $this->obj->addModule($modOn)->removeModule($modOff)->setDisp($x,$y,$r)->setAlo($time);
                break;
            case 'group-powermanagement':
                $min = $_REQUEST['scheduleCronMin'];
                $hour = $_REQUEST['scheduleCronHour'];
                $dom = $_REQUEST['scheduleCronDOM'];
                $month = $_REQUEST['scheduleCronMonth'];
                $dow = $_REQUEST['scheduleCronDOW'];
                $onDemand = (string)intval(isset($_REQUEST['onDemand']));
                $action = $_REQUEST['action'];
                if (!$action) throw new Exception(_('You must select an action to perform'));
                $items = array();
                if (isset($_REQUEST['pmsubmit'])) {
                    if ($onDemand && $action === 'wol'){
                        array_map(function(&$Host) {
                            $Host->wakeOnLAN();
                        },(array)self::getClass('HostManager')->find(array('id'=>$this->obj->get('hosts'))));
                        break;
                    }
                    $hostIDs = (array)$this->obj->get('hosts');
                    array_map(function($hostID) use ($min,$hour,$dom,$month,$dow,$onDemand,$action,&$items) {
                        $items[] = array($hostID,$min,$hour,$dom,$month,$dow,$onDemand,$action);
                    },(array)$hostIDs);
                    self::getClass('PowerManagementManager')->insert_batch(array('hostID','min','hour','dom','month','dow','onDemand','action'),$items);
                }
                break;
            }
            if (!$this->obj->save()) throw new Exception(_('Database update failed'));
            self::$HookManager->processEvent('GROUP_EDIT_SUCCESS', array('Group' => &$this->obj));
            $this->setMessage('Group information updated!');
        } catch (Exception $e) {
            self::$HookManager->processEvent('GROUP_EDIT_FAIL', array('Group' => &$this->obj));
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
        $hostids = $this->obj->get('hosts');
        array_map(function(&$Host) {
            if (!$Host->isValid()) return;
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'host_deployed' => $this->formatTime($Host->get('deployed'),'Y-m-d H:i:s'),
            );
            unset($Host);
        }, self::getClass('HostManager')->find(array('id'=>$hostids)));
        printf('<p>%s</p>',_('Confirm you really want to delete the following hosts'));
        printf('<form method="post" action="?node=group&sub=delete&id=%s" class="c">',$this->obj->get('id'));
        self::$HookManager->processEvent('GROUP_DELETE_HOST_FORM',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
        $this->render();
        printf('<input type="submit" name="delHostConfirm" value="%s" /></form>',_('Delete listed hosts'));
    }
}
>>>>>>> 0ab36a764f995b40281bcb0238eb18f44d4f091b
