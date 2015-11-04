<?php
class HostManagementPage extends FOGPage {
    public $node = 'host';
    public function __construct($name = '') {
        $this->name = 'Host Management';
        parent::__construct($this->name);
        if ($_SESSION['Pending-Hosts']) $this->menu['pending'] = $this->foglang['PendingHosts'];
        if ($_REQUEST['id']) {
            $this->obj = $this->getClass('Host',$_REQUEST['id']);
            if ($this->obj->isValid()) {
                $this->subMenu = array(
                    "$this->linkformat#host-general"=>$this->foglang['General'],
                );
                if (!$this->obj->get('pending')) $this->subMenu = array_merge($this->subMenu,array("$this->linkformat#host-tasks"=>$this->foglang['BasicTasks']));
                $this->subMenu = array_merge($this->subMenu,array(
                    "$this->linkformat#host-active-directory"=>$this->foglang['AD'],
                    "$this->linkformat#host-printers"=>$this->foglang['Printers'],
                    "$this->linkformat#host-snapins"=>$this->foglang['Snapins'],
                    "$this->linkformat#host-service"=>"{$this->foglang['Service']} {$this->foglang['Settings']}",
                    "$this->linkformat#host-hardware-inventory"=>$this->foglang['Inventory'],
                    "$this->linkformat#host-virus-history"=>$this->foglang['VirusHistory'],
                    "$this->linkformat#host-login-history"=>$this->foglang['LoginHistory'],
                    "$this->linkformat#host-image-history"=>$this->foglang['ImageHistory'],
                    "$this->linkformat#host-snapin-history"=>$this->foglang['SnapinHistory'],
                    $this->membership=>$this->foglang['Membership'],
                    $this->delformat=>$this->foglang['Delete'],
                ));
                $this->notes = array(
                    $this->foglang['Host']=>$this->obj->get('name'),
                    $this->foglang['MAC']=>$this->obj->get('mac'),
                    $this->foglang['Image']=>$this->obj->getImageName(),
                    $this->foglang['LastDeployed']=>$this->obj->get('deployed'),
                );
                $Groups = $this->getClass('GroupManager')->find(array('id'=>$this->obj->get('groups')));
                foreach ((array)$Groups AS $i => &$Group) {
                    if (!$Group->isValid()) continue;
                    $this->notes[$this->foglang['PrimaryGroup']] = $Group->get('name');
                    unset($Group);
                    break;
                }
                unset($Groups);
            }
        }
        $this->exitNorm = Service::buildExitSelector('bootTypeExit',($this->obj && $this->obj->isValid() ? $this->obj->get('biosexit') : $_REQUEST['bootTypeExit']),true);
        $this->exitEfi = Service::buildExitSelector('efiBootTypeExit',($this->obj && $this->obj->isValid() ? $this->obj->get('efiexit') : $_REQUEST['efiBootTypeExit']),true);
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes,'biosexit'=>&$this->exitNorm,'efiexit'=>&$this->exitEfi));
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
        );
        $_SESSION['FOGPingActive'] ? array_push($this->headerData,'') : null;
        array_push($this->headerData,
            _('Host'),
            _('Imaged'),
            _('Task'),
            '',
            _('Image')
        );
        $this->templates = array(
            '<span class="icon fa fa-question hand" title="${host_desc}"></span>',
            '<input type="checkbox" name="host[]" value="${host_id}" class="toggle-action" />',
        );
        $_SESSION['FOGPingActive'] ? array_push($this->templates,'${pingstatus}') : null;
        $up = $this->getClass('TaskType',2);
        $down = $this->getClass('TaskType',1);
        $mc = $this->getClass('TaskType',8);
        array_push($this->templates,
            '<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name}" id="host-${host_name}">${host_name}</a><br /><small>${host_mac}</small>',
            '<small>${deployed}</small>',
            '<a href="?node=host&sub=deploy&sub=deploy&type=1&id=${host_id}"><i class="icon fa fa-'.$down->get('icon').'" title="'.$down->get('name').'"></i></a> <a href="?node=host&sub=deploy&sub=deploy&type=2&id=${host_id}"><i class="icon fa fa-'.$up->get('icon').'" title="'.$up->get('name').'"></i></a> <a href="?node=host&sub=deploy&type=8&id=${host_id}"><i class="icon fa fa-'.$mc->get('icon').'" title="'.$mc->get('name').'"></i></a> <a href="?node=host&sub=edit&id=${host_id}#host-tasks"><i class="icon fa fa-arrows-alt" title="Goto Task List"></i></a>',
            '<a href="?node=host&sub=edit&id=${host_id}"><i class="icon fa fa-pencil" title="Edit"></i></a> <a href="?node=host&sub=delete&id=${host_id}"><i class="icon fa fa-minus-circle" title="Delete"></i></a>',
            '${image_name}'
        );
        $this->attributes = array(
            array('width'=>16,'id'=>'host-${host_name}','class'=>'filter-false'),
            array('class'=>'filter-false','width'=>16),
        );
        $_SESSION['FOGPingActive'] ? array_push($this->attributes,array('width'=>16,'class'=>'filter-false')) : null;
        array_push($this->attributes,
            array('width'=>50),
            array('width'=>145),
            array('width'=>80,'class'=>'r filter-false'),
            array('width'=>40,'class'=>'r filter-false'),
            array('width'=>50,'class'=>'r'),
            array('width'=>20,'class'=>'r')
        );
    }
    public function index() {
        $this->title = $this->foglang['AllHosts'];
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['HostCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $Hosts = $this->getClass('HostManager')->find(array('pending'=>array('',0,null)));
        foreach ($Hosts AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $deployed = $this->formatTime($Host->get('deployed'));
            $id = $Host->get('id');
            $name = $Host->get('name');
            $mac = $Host->get('mac');
            $desc = $Host->get('description');
            $image = $Host->getImageName();
            $ping = $Host->getPingCodeStr();
            $this->data[] = array(
                'host_id'=>$id,
                'deployed'=>$deployed,
                'host_name'=>$name,
                'host_mac'=>$mac,
                'host_desc'=>$desc,
                'image_name'=>$image,
                'pingstatus'=>$ping,
            );
            unset($Host,$deployed,$name,$mac,$desc,$image,$ping);
        }
        unset($id);
        $this->HookManager->processEvent('HOST_DATA',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->HookManager->processEvent('HOST_HEADER_DATA',array('headerData'=>&$this->headerData,'title'=>&$this->title));
        $this->render();
    }
    public function search_post() {
        $Hosts = $this->getClass('HostManager')->search('',true);
        foreach ((array)$Hosts AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $id = $Host->get('id');
            $deployed = $this->formatTime($Host->get('deployed'));
            $name = $Host->get('name');
            $mac = $Host->get('mac')->__toString();
            $desc = $Host->get('description');
            $image = $Host->getImageName();
            $ping = $Host->getPingCodeStr();
            $this->data[] = array(
                'host_id'=>$id,
                'deployed'=>$deployed,
                'host_name'=>$name,
                'host_mac'=>$mac,
                'host_desc'=>$desc,
                'image_name'=>$image,
                'pingstatus'=>$ping,
            );
            unset($Host,$deployed,$name,$mac,$desc,$image,$ping,$id);
        }
        unset($Hosts);
        $this->HookManager->processEvent('HOST_DATA',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->HookManager->processEvent('HOST_HEADER_DATA',array('headerData'=>&$this->headerData));
        $this->render();
    }
    public function pending() {
        $this->title = _('Pending Host List');
        echo '<form method="post" action="'.$this->formAction.'">';
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            ($_SESSION['FOGPingActive'] ? '' : null),
            _('Host Name'),
            _('Edit/Remove'),
        );
        $this->templates = array(
            '<i class="icon fa fa-question hand" title="${host_desc}"></i>',
            '<input type="checkbox" name="host[]" value="${host_id}" class="toggle-host" />',
            ($_SESSION['FOGPingActive'] ? '<span class="icon ping"></span>' : ''),
            '<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
            '<a href="?node=host&sub=edit&id=${host_id}"><i class="icon fa fa-pencil" title="Edit"></i></a> <a href="?node=host&sub=delete&id=${host_id}"><i class="icon fa fa-minus-circle" title="Delete"></i></a>',
        );
        $this->attributes = array(
            array('width'=>22,'id'=>'host-${host_name}','class'=>'filter-false'),
            array('class' =>'l filter-false','width'=>16),
            ($_SESSION['FOGPingActive'] ? array('width'=>20,'class'=>'filter-false') : ''),
            array(),
            array('width'=>80,'class'=>'c'),
            array('width'=>50,'class'=>'r filter-false'),
        );
        $Hosts = $this->getClass('HostManager')->find(array('pending'=>1));
        foreach ((array)$Hosts AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $id = $Host->get('id');
            $name = $Host->get('name');
            $mac = $Host->get('mac');
            $desc = $Host->get('description');
            unset($Host);
            $this->data[] = array(
                'host_id'=>$id,
                'host_name'=>$name,
                'host_mac'=>$mac,
                'host_desc'=>$desc,
            );
            unset($id,$name,$mac,$desc);
        }
        unset($Hosts);
        $this->HookManager->processEvent('HOST_DATA',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->HookManager->processEvent('HOST_HEADER_DATA',array('headerData'=>&$this->headerData));
        $this->render();
        if (count($this->data) > 0) echo '<center><input name="approvependhost" type="submit" value="'._('Approve selected Hosts').'"/>&nbsp;&nbsp;<input name="delpendhost" type="submit" value="'._('Delete selected Hosts').'"/></center>';
        echo '</form>';
    }
    public function pending_post() {
        if (isset($_REQUEST['approvependhost'])) $this->getClass('HostManager')->update(array('id'=>$_REQUEST['host']),'',array('pending'=>0));
        if (isset($_REQUEST['delpendhost'])) $this->getClass('HostManager')->destroy(array('id'=>$_REQUEST['host']));
        $appdel = (isset($_REQUEST['approvependhost']) ? 'approved' : 'deleted');
        $this->setMessage(_("All hosts $appdel successfully"));
        $this->redirect('?node='.$_REQUEST['node']);
    }
    public function add() {
        $this->title = _('New Host');
        unset($this->data);
        $this->headerData = '';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Host Name') => '<input type="text" name="host" value="'.$_REQUEST['host'].'" maxlength="15" class="hostname-input" />*',
            _('Primary MAC') => '<input type="text" id="mac" name="mac" value="'.$_REQUEST['mac'].'" />*<span id="priMaker"></span><span class="mac-manufactor"></span><i class="icon add-mac fa fa-plus-circle hand" title="'._('Add MAC').'"></i>',
            _('Host Description') => '<textarea name="description" rows="8" cols="40">'.$_REQUEST['description'].'</textarea>',
            _('Host Product Key') => '<input id="productKey" type="text" name="key" value="'.$_REQUEST['key'].'" />',
            _('Host Image') => $this->getClass('ImageManager')->buildSelectBox($_REQUEST['image'],'','id'),
            _('Host Kernel') => '<input type="text" name="kern" value="'.$_REQUEST['kern'].'" />',
            _('Host Kernel Arguments') => '<input type="text" name="args" value="'.$_REQUEST['args'].'" />',
            _('Host Primary Disk') => '<input type="text" name="dev" value="'.$_REQUEST['dev'].'" />',
            _('Host Bios Exit Type') => $this->exitNorm,
            _('Host EFI Exit Type') => $this->exitEfi,
        );
        echo '<h2>'._('Add new host definition').'</h2>';
        echo '<form method="post" action="'.$this->formAction.'">';
        $this->HookManager->processEvent('HOST_FIELDS',array('fields'=>&$fields));
        foreach ($fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        unset($input);
        $this->HookManager->processEvent('HOST_ADD_GEN',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes,'fields'=>&$fields));
        $this->render();
        echo $this->adFieldsToDisplay();
        echo '</form>';
    }
    public function add_post() {
        $this->HookManager->processEvent('HOST_ADD_POST');
        try {
            $hostName = trim($_REQUEST['host']);
            if (empty($hostName)) throw new Exception('Please enter a hostname');
            if (!$this->getClass('Host')->isHostnameSafe($hostName)) throw new Exception(_('Please enter a valid hostname'));
            if ($this->getClass('HostManager')->exists($hostName)) throw new Exception(_('Hostname Exists already'));
            if (empty($_REQUEST['mac'])) throw new Exception(_('MAC Address is required'));
            $MAC = $this->getClass('MACAddress',$_REQUEST['mac']);
            if (!$MAC->isValid()) throw new Exception(_('MAC Format is invalid'));
            $Host = $this->getClass('HostManager')->getHostByMacAddresses($MAC);
            if ($Host && $Host->isValid()) throw new Exception(_('A host with this MAC already exists with Hostname: ').$Host->get('name'));
            $ModuleIDs = $this->getSubObjectIDs('Module');
            $password = $_REQUEST['domainpassword'];
            if ($_REQUEST['domainpassword']) $password = $this->encryptpw($_REQUEST['domainpassword']);
            $useAD = (int)isset($_REQUEST['domain']);
            $domain = trim($_REQUEST['domainname']);
            $ou = trim($_REQUEST['ou']);
            $user = trim($_REQUEST['domainuser']);
            $pass = $password;
            $passlegacy = trim($_REQUEST['domainpasswordlegacy']);
            $Host = $this->getClass('Host')
                ->set('name',$hostName)
                ->set('description',$_REQUEST['description'])
                ->set('imageID',$_REQUEST['image'])
                ->set('kernel',$_REQUEST['kern'])
                ->set('kernelArgs',$_REQUEST['args'])
                ->set('kernelDevice',$_REQUEST['dev'])
                ->set('productKey',base64_encode($_REQUEST['key']))
                ->set('biosexit',$_REQUEST['bootTypeExit'])
                ->set('efiexit',$_REQUEST['efiBootTypeExit'])
                ->addModule($ModuleIDs)
                ->addPriMAC($MAC)
                ->setAD($useAD,$domain,$ou,$user,$pass,true,true,$passlegacy);
            if (!$Host->save()) throw new Exception(_('Host create failed'));
            $this->HookManager->processEvent('HOST_ADD_SUCCESS',array('Host'=>&$Host));
            $this->setMessage(_('Host added'));
            $url = sprintf('?node=%s&sub=edit&%s=%s',$_REQUEST['node'],$this->id,$Host->get('id'));
        } catch (Exception $e) {
            $this->HookManager->processEvent('HOST_ADD_FAIL',array('Host'=>&$Host));
            $this->setMessage($e->getMessage());
            $url = $this->formAction;
        }
        unset($Host,$passlegacy,$pass,$user,$ou,$domain,$useAD,$password,$ModuleIDs,$MAC,$hostName);
        $this->redirect($url);
    }
    public function edit() {
        $this->title = sprintf('%s: %s', 'Edit', $this->obj->get('name'));
        if ($_REQUEST['approveHost']) {
            $this->obj->set('pending',null);
            if ($this->obj->save()) $this->setMessage(_('Host approved'));
            else $this->setMessage(_('Host approval failed.'));
            $this->redirect('?node='.$_REQUEST['node'].'&sub='.$_REQUEST['sub'].'&id='.$_REQUEST['id']);
        }
        if ($this->obj->get('pending')) echo '<h2><a href="'.$this->formAction.'&approveHost=1">'._('Approve this host?').'</a></h2>';
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if ($_REQUEST['confirmMAC']) {
            try {
                $this->obj->addPendtoAdd($_REQUEST['confirmMAC']);
                if ($this->obj->save()) $this->setMessage('MAC: '.$_REQUEST['confirmMAC'].' Approved!');
            } catch (Exception $e) {
                $this->setMessage($e->getMessage());
            }
            $this->redirect($this->formAction);
        }
        else if ($_REQUEST['approveAll']) {
            $this->getClass('MACAddressAssociationManager')->update(array('hostID'=>$this->obj->get('id')),'',array('pending'=>0));
            $this->setMessage('All Pending MACs approved.');
            $this->redirect($this->formAction);
        }
        foreach ((array)$this->obj->get('additionalMACs') AS $i => &$MAC) {
            if (!$MAC->isValid()) continue;
            $addMACs .= '<div><input class="additionalMAC" type="text" name="additionalMACs[]" value="'.$MAC.'" />&nbsp;&nbsp;<i class="icon fa fa-minus-circle remove-mac hand" title="'._('Remove MAC').'"></i><span class="icon icon-hand" title="'._('Ignore MAC on Client').'"><input type="checkbox" name="igclient[]" value="'.$MAC.'" '.$this->obj->clientMacCheck($MAC).' /></span><span class="icon icon-hand" title="'._('Ignore MAC for imaging').'"><input type="checkbox" name="igimage[]" value="'.$MAC.'" '.$this->obj->imageMacCheck($MAC).'/></span><br/><span class="mac-manufactor"></span></div>';
            unset($MAC);
        }
        foreach ((array)$this->obj->get('pendingMACs') AS $i => &$MAC) {
            if (!$MAC->isValid()) continue;
            $pending .= '<div><input class="pending-mac" type="text" name="pendingMACs[]" value="'.$MAC.'" /><a href="'.$this->formAction.'&confirmMAC='.$MAC.'"><i class="icon fa fa-check-circle"></i></a><span class="mac-manufactor"></span></div>';
            unset($MAC);
        }
        if ($pending != null && $pending != '')
            $pending .= '<div>'._('Approve All MACs?').'<a href="'.$this->formAction.'&approveAll=1"><i class="icon fa fa-check-circle"></i></a></div>';
        $imageSelect = $this->getClass('ImageManager')->buildSelectBox($this->obj->get('imageID'));
        $fields = array(
            _('Host Name') => '<input type="text" name="host" value="'.$this->obj->get('name').'" maxlength="15" class="hostname-input" />*',
            _('Primary MAC') => '<input type="text" name="mac" id="mac" value="'.$this->obj->get('mac').'" />*<span id="priMaker"></span><i class="icon add-mac fa fa-plus-circle hand" title="'._('Add MAC').'"></i><span class="icon icon-hand" title="'._('Ignore MAC on Client').'"><input type="checkbox" name="igclient[]" value="'.$this->obj->get('mac').'" '.$this->obj->clientMacCheck().' /></span><span class="icon icon-hand" title="'._('Ignore MAC for imaging').'"><input type="checkbox" name="igimage[]" value="'.$this->obj->get('mac').'" '.$this->obj->imageMacCheck().'/></span><br/><span class="mac-manufactor"></span>',
            '<div id="additionalMACsRow">'._('Additional MACs').'</div>' => '<div id="additionalMACsCell">'.$addMACs.'</div>',
            ($this->obj->get('pendingMACs') ? _('Pending MACs') : null) => ($this->obj->get('pendingMACs') ? $pending : null),
            _('Host Description') => '<textarea name="description" rows="8" cols="40">'.$this->obj->get('description').'</textarea>',
            _('Host Product Key') => '<input id="productKey" type="text" name="key" value="'.base64_decode($this->obj->get('key')).'" />',
            _('Host Image') => $imageSelect,
            _('Host Kernel') => '<input type="text" name="kern" value="'.$this->obj->get('kernel').'" />',
            _('Host Kernel Arguments') => '<input type="text" name="args" value="'.$this->obj->get('kernelArgs').'" />',
            _('Host Primary Disk') => '<input type="text" name="dev" value="'.$this->obj->get('kernelDevice').'" />',
            _('Host Bios Exit Type') => $this->exitNorm,
            _('Host EFI Exit Type') => $this->exitEfi,
            '&nbsp' => '<input type="submit" value="'._('Update').'" />',
        );
        $this->HookManager->processEvent('HOST_FIELDS', array('fields' => &$fields,'Host' => &$this->obj));
        echo '<div id="tab-container">';
        echo "<!-- General -->";
        echo '<div id="host-general">';
        if ($this->obj->get('pub_key') || $this->obj->get('sec_tok')) $this->form = '<center><div id="resetSecDataBox"></div><input type="button" id="resetSecData" /></center><br/>';
        echo '<form method="post" action="'.$this->formAction.'&tab=host-general">';
        echo '<h2>'._('Edit host definition').'</h2>';
        foreach ($fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($input);
        $this->HookManager->processEvent('HOST_EDIT_GEN',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes,'Host'=>&$this->obj));
        $this->render();
        echo '</form></div>';
        unset($this->data,$this->form);
        unset($this->data,$this->headerData,$this->attributes);
        if (!$this->obj->get('pending')) $this->basictasksOptions();
        $this->adFieldsToDisplay();
        echo "<!-- Printers -->";
        echo '<div id="host-printers" >';
        echo '<form method="post" action="'.$this->formAction.'&tab=host-printers">';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprint" />',
            _('Printer Name'),
            _('Configuration'),
        );
        $this->templates = array(
            '<input type="checkbox" name="printer[]" value="${printer_id}" class="toggle-print" />',
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>50,'class'=>'l'),
            array('width'=>50,'class'=>'r'),
        );
        $Printers = $this->getClass('PrinterManager')->find(array('id'=>$this->obj->get('printersnotinme')));
        foreach ((array)$Printers AS $i => &$Printer) {
            if (!$Printer->isValid()) continue;
            $this->data[] = array(
                'printer_id'=>$Printer->get('id'),
                'printer_name'=>$Printer->get('name'),
                'printer_type'=>$Printer->get('config'),
            );
            unset($Printer);
        }
        unset($Printers);
        $PrintersFound = false;
        if (count($this->data) > 0) {
            $PrintersFound = true;
            echo '<center><label for="hostPrinterShow">'._('Check here to see what printers can be added').'&nbsp;&nbsp;<input type="checkbox" name="hostPrinterShow" id="hostPrinterShow" /></label></center>';
            echo '<div id="printerNotInHost">';
            echo '<h2>'._('Add new printer(s) to this host').'</h2>';
            $this->HookManager->processEvent('HOST_ADD_PRINTER',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
            $this->render();
            echo '</div>';
        }
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Default'),
            _('Printer Alias'),
            _('Printer Type'),
        );
        $this->attributes = array(
            array('class'=>'l filter-false','width'=>16),
            array('class'=>'l filter-false','width'=>22),
            array(),
            array(),
        );
        $this->templates = array(
            '<input type="checkbox" name="printerRemove[]" value="${printer_id}" class="toggle-action" />',
            '<input class="default" type="radio" name="default" id="printer${printer_id}" value="${printer_id}" ${is_default}/><label for="printer${printer_id}" class="icon icon-hand" title="'._('Default Printer Select').'">&nbsp;</label><input type="hidden" name="printerid[]" value="${printer_id}" />',
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}',
        );
        echo '<h2>'._('Host Printer Configuration').'</h2><p>'._('Select Management Level for this Host').'</p><p><span class="icon fa fa-question hand" title="'._('This setting turns off all FOG Printer Management.  Although there are multiple levels already between host and global settings, this is just another to ensure safety').'"></span><input type="radio" name="level" value="0"'.($this->obj->get('printerLevel') == 0 ? 'checked' : '').' />'._('No Printer Management').'<br/><span class="icon fa fa-question hand" title="'._('This setting only adds and removes printers that are managed by FOG.  If the printer exists in printer management but is not assigned to a host, it will remove the printer if it exists on the unsigned host.  It will add printers to the host that are assigned.').'"></span><input type="radio" name="level" value="1"'.($this->obj->get('printerLevel') == 1 ? 'checked' : '').' />'._('FOG Managed Printers').'<br/><span class="icon fa fa-question hand" title="'._('This setting will only allow FOG Assigned printers to be added to the host.  Any printer that is not assigned will be removed including non-FOG managed printers.').'"></span><input type="radio" name="level" value="2"'.($this->obj->get('printerLevel') == 2 ? 'checked' : '').' />'._('Only Assigned Printers').'<br/></p>';
        $Printers = $this->getClass('PrinterManager')->find(array('id'=>$this->obj->get('printers')));
        foreach ((array)$Printers AS $i => &$Printer) {
            if (!$Printer->isValid()) continue;
            $id = $Printer->get('id');
            $this->data[] = array(
                'printer_id'=>$id,
                'is_default'=>($this->obj->getDefault($id) ? 'checked' : ''),
                'printer_name'=>$Printer->get('name'),
                'printer_type'=>$Printer->get('config'),
            );
            unset($Printer);
        }
        unset($Printers);
        $this->HookManager->processEvent('HOST_EDIT_PRINTER',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if ($PrintersFound || count($this->data) > 0) echo '<center><input type="submit" value="'._('Update').'" name="updateprinters"/>';
        if (count($this->data) > 0) echo '&nbsp;&nbsp;<input type="submit" value="'._('Remove selected printers').'" name="printdel"/></center>';
        unset($this->data, $this->headerData);
        echo '</form></div><!-- Snapins --><div id="host-snapins" ><h2>'._('Snapins').'</h2><form method="post" action="'.$this->formAction.'&tab=host-snapins">';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapin" class="toggle-checkboxsnapin" />',
            _('Snapin Name'),
            _('Created'),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${snapin_id}" class="toggle-snapin" />',
            sprintf('<a href="?node=%s&sub=edit&id=${snapin_id}" title="%s">${snapin_name}</a>','snapin',_('Edit')),
            '${snapin_created}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>90,'class'=>'l'),
            array('width'=>20,'class'=>'r'),
        );
        $Snapins = $this->getClass('SnapinManager')->find($this->obj->get('snapinsnotinme'));
        foreach((array)$Snapins AS $i => &$Snapin) {
            if (!$Snapin->isValid()) continue;
            $this->data[] = array(
                'snapin_id'=>$Snapin->get('id'),
                'snapin_name'=>$Snapin->get('name'),
                'snapin_created'=>$Snapin->get('createdTime'),
            );
            unset($Snapin);
        }
        unset($Snapins);
        if (count($this->data) > 0) {
            echo '<center><label for="hostSnapinShow">'._('Check here to see what snapins can be added').'&nbsp;&nbsp;<input type="checkbox" name="hostSnapinShow" id="hostSnapinShow" /></label><div id="snapinNotInHost">';
            $this->HookManager->processEvent('HOST_SNAPIN_JOIN',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
            $this->render();
            echo '<input type="submit" value="'._('Add Snapin(s)').'" /></form></div></center><form method="post" action="'.$this->formAction.'&tab=host-snapins">';
            unset($this->data);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            _('Snapin Name'),
        );
        $this->attributes = array(
            array('class'=>'l disabled filter-false','width'=>16),
            array(),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapinRemove[]" value="${snap_id}" class="toggle-action" />',
            '<a href="?node=snapin&sub=edit&id=${snap_id}">${snap_name}</a>',
        );
        $Snapins = $this->getClass('SnapinManager')->find(array('id'=>$this->obj->get('snapins')));
        foreach ((array)$Snapins AS $i => &$Snapin) {
            if (!$Snapin->isValid()) continue;
            $this->data[] = array(
                'snap_id'=>$Snapin->get('id'),
                'snap_name'=>$Snapin->get('name'),
            );
            unset($Snapin);
        }
        unset($Snapins);
        $this->HookManager->processEvent('HOST_EDIT_SNAPIN',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if (count($this->data)) $inputremove = sprintf('<input type="submit" name="snaprem" value="%s"/>',_('Remove selected snapins'));
        echo "<center>$inputremove</center></form></div>";
        unset($this->data, $this->headerData);
        echo '<!-- Service Configuration -->';
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
            'span'=>''
        );
        echo '<div id="host-service" ><form method="post" action="'.$this->formAction.'&tab=host-service"><h2>'._('Service Configuration').'</h2><fieldset><legend>'._('General').'</legend>';
        $ModOns = $this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->obj->get('id')),'moduleID');
        $moduleName = $this->getGlobalModuleStatus();
        $Modules = $this->getClass('ModuleManager')->find();
        foreach ((array)$Modules AS $i => &$Module) {
            if (!$Module->isValid()) continue;
            $this->data[] = array(
                'input'=>'<input type="checkbox" '.($moduleName[$Module->get('shortName')] || ($moduleName[$Module->get('shortName')] && $Module->get('isDefault')) ? 'class="checkboxes"' : '').' name="modules[]" value="${mod_id}" ${checked} '.(!$moduleName[$Module->get('shortName')] ? 'disabled' : '').' />',
                'span'=>'<span class="icon fa fa-question fa-1x hand" title="${mod_desc}"></span>',
                'checked'=>(in_array($Module->get('id'),$ModOns) ? 'checked' : ''),
                'mod_name'=>$Module->get('name'),
                'mod_shname'=>$Module->get('shortName'),
                'mod_id'=>$Module->get('id'),
                'mod_desc'=>str_replace('"','\"',$Module->get('description')),
            );
            unset($Module);
        }
        unset($ModOns,$Modules);
        $this->data[] = array(
            'mod_name'=>'&nbsp',
            'input'=>'',
            'span'=>'<input type="submit" name="updatestatus" value="'._('Update').'" />',
        );
        $this->HookManager->processEvent('HOST_EDIT_SERVICE',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</fieldset></form>';
        echo '<form method="post" action="'.$this->formAction.'&tab=host-service"><fieldset><legend>'._('Host Screen Resolution').'</legend>';
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
        $Services = $this->getClass('ServiceManager')->find(array('name'=>array('FOG_SERVICE_DISPLAYMANAGER_X','FOG_SERVICE_DISPLAYMANAGER_Y','FOG_SERVICE_DISPLAYMANAGER_R')),'OR');
        foreach ((array)$Services AS $i => &$Service) {
            if (!$Service->isValid()) continue;
            $this->data[] = array(
                'input'=>'<input type="text" name="${type}" value="${disp}" />',
                'span'=>'<span class="icon fa fa-question fa-1x hand" title="${desc}"></span>',
                'field'=>($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? _('Screen Width (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAY_MANAGER_Y' ? _('Screen Height (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? _('Screen Refresh Rate (in Hz)') : ''))),
                'type'=>($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? 'x' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? 'y' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? 'r' : ''))),
                'disp'=>($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? $this->obj->getDispVals('width') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? $this->obj->getDispVals('height') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? $this->obj->getDispVals('refresh') : ''))),
                'desc'=>$Service->get('description'),
            );
            unset($Service);
        }
        unset($Services);
        $this->data[] = array(
            'field'=>'',
            'input'=>'',
            'span'=>'<input type="submit" name="updatedisplay" value="'._('Update').'" />',
        );
        $this->HookManager->processEvent('HOST_EDIT_DISPSERV',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</fieldset></form><form method="post" action="'.$this->formAction.'&tab=host-service"><fieldset><legend>'._('Auto Log Out Settings').'</legend>';
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
        $Service = $this->getClass('Service',@min($this->getSubObjectIDs('Service',array('name'=>'FOG_SERVICE_AUTOLOGOFF_MIN'))));
        if ($Service->isValid()) {
            $this->data[] = array(
                'field'=>_('Auto Log Out Time (in minutes)'),
                'input'=>'<input type="text" name="tme" value="${value}" />',
                'desc'=>'<span class="icon fa fa-question fa-1x hand" title="${serv_desc}"></span>',
                'value'=>$this->obj->getAlo() ? $this->obj->getAlo() : $Service->get('value'),
                'serv_desc'=>$Service->get('description'),
            );
        }
        unset($Service);
        $this->data[] = array(
            'field'=>'',
            'input'=>'',
            'desc'=>'<input type="submit" name="updatealo" value="'._('Update').'" />',
        );
        $this->HookManager->processEvent('HOST_EDIT_ALO',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data,$fields);
        echo '</fieldset></form></div><!-- Inventory -->';
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Primary User') => '<input type="text" value="${inv_user}" name="pu" />',
            _('Other Tag #1') => '<input type="text" value="${inv_oth1}" name="other1" />',
            _('Other Tag #2') => '<input type="text" value="${inv_oth2}" name="other2" />',
            _('System Manufacturer') => '${inv_sysman}',
            _('System Product') => '${inv_sysprod}',
            _('System Version') => '${inv_sysver}',
            _('System Serial Number') => '${inv_sysser}',
            _('System Type') => '${inv_systype}',
            _('BIOS Vendor') => '${bios_ven}',
            _('BIOS Version') => '${bios_ver}',
            _('BIOS Date') => '${bios_date}',
            _('Motherboard Manufacturer') => '${mb_man}',
            _('Motherboard Product Name') => '${mb_name}',
            _('Motherboard Version') => '${mb_ver}',
            _('Motherboard Serial Number') => '${mb_ser}',
            _('Motherboard Asset Tag') => '${mb_asset}',
            _('CPU Manufacturer') => '${cpu_man}',
            _('CPU Version') => '${cpu_ver}',
            _('CPU Normal Speed') => '${cpu_nspeed}',
            _('CPU Max Speed') => '${cpu_mspeed}',
            _('Memory') => '${inv_mem}',
            _('Hard Disk Model') => '${hd_model}',
            _('Hard Disk Firmware') => '${hd_firm}',
            _('Hard Disk Serial Number') => '${hd_ser}',
            _('Chassis Manufacturer') => '${case_man}',
            _('Chassis Version') => '${case_ver}',
            _('Chassis Serial') => '${case_ser}',
            _('Chassis Asset') => '${case_asset}',
            '<input type="hidden" name="update" value="1" />' => '<input type="submit" value="'._('Update').'" />',
        );
        echo '<div id="host-hardware-inventory" ><form method="post" action="'.$this->formAction.'&tab=host-hardware-inventory"><h2>'._('Host Hardware Inventory').'</h2>';
        if ($this->obj->get('inventory')->isValid()) {
            $cpustuff = array('cpuman','cpuversion');
            foreach ((array)$cpustuff AS $i => &$x) {
                $this->obj->get('inventory')->set($x,implode(' ',array_unique(explode(' ',$this->obj->get('inventory')->get($x)))));
                unset($x);
            }
            unset($cpustuff);
            foreach ((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    'field'=>$field,
                    'input'=>$input,
                    'inv_user'=>$this->obj->get('inventory')->get('primaryUser'),
                    'inv_oth1'=>$this->obj->get('inventory')->get('other1'),
                    'inv_oth2'=>$this->obj->get('inventory')->get('other2'),
                    'inv_sysman'=>$this->obj->get('inventory')->get('sysman'),
                    'inv_sysprod'=>$this->obj->get('inventory')->get('sysproduct'),
                    'inv_sysver'=>$this->obj->get('inventory')->get('sysversion'),
                    'inv_sysser'=>$this->obj->get('inventory')->get('sysserial'),
                    'inv_systype'=>$this->obj->get('inventory')->get('systype'),
                    'bios_ven'=>$this->obj->get('inventory')->get('biosvendor'),
                    'bios_ver'=>$this->obj->get('inventory')->get('biosversion'),
                    'bios_date'=>$this->obj->get('inventory')->get('biosdate'),
                    'mb_man'=>$this->obj->get('inventory')->get('mbman'),
                    'mb_name'=>$this->obj->get('inventory')->get('mbproductname'),
                    'mb_ver'=>$this->obj->get('inventory')->get('mbversion'),
                    'mb_ser'=>$this->obj->get('inventory')->get('mbserial'),
                    'mb_asset'=>$this->obj->get('inventory')->get('mbasset'),
                    'cpu_man'=>$this->obj->get('inventory')->get('cpuman'),
                    'cpu_ver'=>$this->obj->get('inventory')->get('cpuversion'),
                    'cpu_nspeed'=>$this->obj->get('inventory')->get('cpucurrent'),
                    'cpu_mspeed'=>$this->obj->get('inventory')->get('cpumax'),
                    'inv_mem'=>$this->obj->get('inventory')->getMem(),
                    'hd_model'=>$this->obj->get('inventory')->get('hdmodel'),
                    'hd_firm'=>$this->obj->get('inventory')->get('hdfirmware'),
                    'hd_ser'=>$this->obj->get('inventory')->get('hdserial'),
                    'case_man'=>$this->obj->get('inventory')->get('caseman'),
                    'case_ver'=>$this->obj->get('inventory')->get('caseversion'),
                    'case_ser'=>$this->obj->get('inventory')->get('caseserial'),
                    'case_asset'=>$this->obj->get('inventory')->get('caseasset'),
                );
            }
            unset($input);
        } else unset($this->data);
        $this->HookManager->processEvent('HOST_INVENTORY',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data,$fields);
        echo '</form></div><!-- Virus -->';
        $this->headerData = array(
            _('Virus Name'),
            _('File'),
            _('Mode'),
            _('Date'),
            _('Clear'),
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
        );
        $this->templates = array(
            '<a href="http://www.google.com/search?q=${virus_name}" target="_blank">${virus_name}</a>',
            '${virus_file}',
            '${virus_mode}',
            '${virus_date}',
            '<input type="checkbox" id="vir_del${virus_id}" class="delvid" name="delvid" onclick="this.form.submit()" value="${virus_id}" /><label for="${virus_id}" class="icon icon-hand" title="'._('Delete').' ${virus_name}"><i class="icon fa fa-minus-circle link"></i>&nbsp;</label>',
        );
        echo '<div id="host-virus-history" ><form method="post" action="'.$this->formAction.'&tab=host-virus-history"><h2>'._('Virus History').'</h2><h2><a href="#"><input type="checkbox" class="delvid" id="all" name="delvid" value="all" onclick="this.form.submit()" /><label for="all">('._('clear all history').')</label></a></h2>';
        $MACs = $this->obj->getMyMacs();
        $Viruses = $this->getClass('VirusManager')->find(array('hostMAC'=>$MACs));
        unset($MACs);
        foreach ((array)$Viruses AS $i => &$Virus) {
            if (!$Virus->isValid()) continue;
            $this->data[] = array(
                'virus_name'=>$Virus->get('name'),
                'virus_file'=>$Virus->get('file'),
                'virus_mode'=>($Virus->get('mode') == 'q' ? _('Quarantine') : ($Virus->get('mode') == 's' ? _('Report') : 'N/A')),
                'virus_date'=>$Virus->get('date'),
                'virus_id'=>$Virus->get('id'),
            );
            unset($Virus);
        }
        unset($Virus);
        $this->HookManager->processEvent('HOST_VIRUS',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data,$this->headerData);
        echo '</form></div><!-- Login History --><div id="host-login-history" ><h2>'._('Host Login History').'</h2><form id="dte" method="post" action="'.$this->formAction.'&tab=host-login-history">';
        $this->headerData = array(
            _('Time'),
            _('Action'),
            _('Username'),
            _('Description')
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        $this->templates = array(
            '${user_time}',
            '${action}',
            '${user_name}',
            '${user_desc}',
        );
        $Dates = $this->getSubObjectIDs('UserTracking',array('id'=>$this->obj->get('users')),'date');
        $Dates = array_unique((array)$Dates);
        if ($Dates) {
            rsort($Dates);
            echo '<p>'._('View History for').'</p>';
            foreach ((array)$Dates AS $i => &$Date) {
                if ($_REQUEST['dte'] == '') $_REQUEST['dte'] = $Date;
                $optionDate[] = '<option value="'.$Date.'" '.($Date == $_REQUEST[dte] ? 'selected="selected"' : '').'>'.$Date.'</option>';
            }
            unset($Date);
            echo '<select name="dte" id="loghist-date" size="1" onchange="document.getElementById(\'dte\').submit()">'.implode($optionDate).'</select><a href="#" onclick="document.getElementByID(\'dte\').submit()"><i class="icon fa fa-play noBorder"></i></a></p>';
            $UserTrackings = $this->getClass('UserTrackingManager')->find(array('id'=>$this->obj->get('users')));
            foreach ((array)$UserTrackings AS $i => &$UserLogin) {
                if (!$UserLogin->isValid()) continue;
                if ($UserLogin->get('date') == $_REQUEST['dte']) {
                    $this->data[] = array(
                        'action'=>($UserLogin->get('action') == 1 ? _('Login') : ($UserLogin->get('action') == 0 ? _('Logout') : '')),
                        'user_name'=>$UserLogin->get('username'),
                        'user_time'=>$UserLogin->get('datetime'),
                        'user_desc'=>$UserLogin->get('description'),
                    );
                }
                unset($UserLogin);
            }
            unset($UserTrackingIDs,$id);
            $this->HookManager->processEvent('HOST_USER_LOGIN',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
            $this->render();
        }
        else echo '<p>'._('No user history data found!').'</p>';
        unset($this->data,$this->headerData);
        echo '<div id="login-history" style="width:575px;height:200px;" /></div></form></div><div id="host-image-history" ><h2>'._('Host Imaging History').'</h2>';
        $this->headerData = array(
            _('Image Name'),
            _('Imaging Type'),
            '<small>'._('Completed').'</small><br />'._('Duration'),
        );
        $this->templates = array(
            '${image_name}',
            '${image_type}',
            '<small>${completed}</small><br />${duration}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
        );
        $ImagingLogs = $this->getClass('ImagingLogManager')->find(array('hostID'=>$this->obj->get('id')));
        foreach ($ImagingLogs AS $i => &$ImageLog) {
            if (!$ImageLog->isValid()) continue;
            $Start = $ImageLog->get('start');
            $End = $ImageLog->get('finish');
            $this->data[] = array(
                'completed'=>$this->formatTime($End),
                'duration'=>$this->diff($Start,$End),
                'image_name'=>$ImageLog->get('image'),
                'image_type'=>$ImageLog->get('type'),
            );
            unset($ImageLog,$Start,$End);
        }
        unset($ImagingLogs);
        $this->HookManager->processEvent('HOST_IMAGE_HIST',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        echo '</div><div id="host-snapin-history">';
        $this->headerData = array(
            _('Snapin Name'),
            _('Start Time'),
            _('Complete'),
            _('Duration'),
            _('Return Code'),
        );
        $this->templates = array(
            '${snapin_name}',
            '${snapin_start}',
            '${snapin_end}',
            '${snapin_duration}',
            '${snapin_return}',
        );
        $SnapinJobIDs = $this->getSubObjectIDs('SnapinJob',array('hostID'=>$this->obj->get('id')));
        $SnapinTasks = $this->getClass('SnapinTaskManager')->find(array('jobID'=>$SnapinJobIDs));
        foreach ((array)$SnapinTasks AS $i => &$SnapinTask) {
            if (!$SnapinTask->isValid()) continue;
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                unset($SnapinTask,$Snapin);
                continue;
            }
            $this->data[] = array(
                'snapin_name' => $Snapin->get('name'),
                'snapin_start' => $this->formatTime($SnapinTask->get('checkin')),
                'snapin_end' => '<span class="icon" title="'.$this->formatTime($SnapinTask->get('complete')).'">'.$this->getClass('TaskState',$SnapinTask->get('stateID')).'</span>',
                'snapin_duration' => $this->diff($SnapinTask->get('checkin'),$SnapinTask->get('complete')),
                'snapin_return'=> $SnapinTask->get('return'),
            );
            unset($Snapin,$SnapinTask);
        }
        unset($SnapinTasks);
        $this->HookManager->processEvent('HOST_SNAPIN_HIST',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</div></div>';
    }
    public function edit_ajax() {
        $this->obj->removeAddMAC($_REQUEST['additionalMACsRM'])->save();
        echo 'Success!';
        exit;
    }
    public function edit_post() {
        $this->HookManager->processEvent('HOST_EDIT_POST',array('Host'=>&$this->obj));
        try {
            switch ($_REQUEST['tab']) {
                case 'host-general';
                $hostName = trim($_REQUEST['host']);
                if (empty($hostName)) throw new Exception('Please enter a hostname');
                if ($this->obj->get('name') != $hostName && !$this->obj->isHostnameSafe($hostName)) throw new Exception(_('Please enter a valid hostname'));
                if ($this->obj->get('name') != $hostName && $this->obj->getManager()->exists($hostName)) throw new Exception('Hostname Exists already');
                if (empty($_REQUEST['mac'])) throw new Exception('MAC Address is required');
                $mac = $this->getClass('MACAddress',$_REQUEST['mac']);
                $Task = $this->obj->get('task');
                if (!$mac->isValid()) throw new Exception(_('MAC Address is not valid'));
                if ((!$_REQUEST['image'] && $Task->isValid()) || ($_REQUEST['image'] && $_REQUEST['image'] != $this->obj->get('imageID') && $Task->isValid())) throw new Exception('Cannot unset image.<br />Host is currently in a tasking.');
                $this->obj
                    ->set('name',$hostName)
                    ->set('description',$_REQUEST['description'])
                    ->set('imageID',$_REQUEST['image'])
                    ->set('kernel',$_REQUEST['kern'])
                    ->set('kernelArgs',$_REQUEST['args'])
                    ->set('kernelDevice',$_REQUEST['dev'])
                    ->set('productKey',base64_encode($_REQUEST['key']))
                    ->set('biosexit',$_REQUEST['bootTypeExit'])
                    ->set('efiexit',$_REQUEST['efiBootTypeExit']);
                if (strtolower($this->obj->get('mac')->__toString()) != strtolower($mac->__toString())) $this->obj->addPriMAC($mac->__toString());
                $this->obj->addAddMAC($_REQUEST['additionalMACs']);
                break;
                case 'host-active-directory';
                $useAD = isset($_REQUEST['domain']);
                $domain = trim($_REQUEST['domainname']);
                $ou = trim($_REQUEST['ou']);
                $user = trim($_REQUEST['domainuser']);
                $pass = trim($_REQUEST['domainpassword']);
                $passlegacy = trim($_REQUEST['domainpasswordlegacy']);
                $this->obj->setAD($useAD,$domain,$ou,$user,$pass,true,false,$passlegacy);
                break;
                case 'host-printers';
                $PrinterManager = $this->getClass('PrinterAssociationManager');
                if (isset($_REQUEST['level'])) $this->obj->set('printerLevel',$_REQUEST['level']);
                if (isset($_REQUEST['updateprinters'])) {
                    if (isset($_REQUEST['printer'])) $this->obj->addPrinter($_REQUEST['printer']);
                    $this->obj->updateDefault($_REQUEST['default'],isset($_REQUEST['default']));
                    unset($printerid);
                }
                if (isset($_REQUEST['printdel'])) $this->obj->removePrinter($_REQUEST['printerRemove']);
                break;
                case 'host-snapins';
                if (!isset($_REQUEST['snapinRemove'])) $this->obj->addSnapin($_REQUEST['snapin']);
                if (isset($_REQUEST['snaprem'])) $this->obj->removeSnapin($_REQUEST['snapinRemove']);
                break;
                case 'host-service';
                $x =(is_numeric($_REQUEST['x']) ? $_REQUEST['x'] : $this->getSetting('FOG_SERVICE_DISPLAYMANAGER_X'));
                $y =(is_numeric($_REQUEST['y']) ? $_REQUEST['y'] : $this->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y'));
                $r =(is_numeric($_REQUEST['r']) ? $_REQUEST['r'] : $this->getSetting('FOG_SERVICE_DISPLAYMANAGER_R'));
                $tme = (is_numeric($_REQUEST['tme']) ? $_REQUEST['tme'] : $this->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN'));
                if (isset($_REQUEST['updatestatus'])) {
                    $modOn = $_REQUEST['modules'];
                    $modOff = $this->getSubObjectIDs('Module',array('id'=>$modOn),'',true);
                    $this->obj->addModule($modOn);
                    $this->obj->removeModule($modOff);
                }
                if (isset($_REQUEST['updatedisplay'])) $this->obj->setDisp($x,$y,$r);
                if (isset($_REQUEST['updatealo'])) $this->obj->setAlo($tme);
                break;
                case 'host-hardware-inventory';
                $pu = trim($_REQUEST['pu']);
                $other1 = trim($_REQUEST['other1']);
                $other2 = trim($_REQUEST['other2']);
                if ($_REQUEST['update'] == 1) {
                    $this->obj
                        ->get('inventory')
                        ->set('primaryUser',$pu)
                        ->set('other1',$other1)
                        ->set('other2',$other2)
                        ->save();
                }
                break;
                case 'host-login-history';
                $this->redirect("?node=host&sub=edit&id=".$this->obj->get('id')."&dte=".$_REQUEST['dte']."#".$_REQUEST['tab']);
                break;
                case 'host-virus-history';
                if (isset($_REQUEST['delvid']) && $_REQUEST['delvid'] == 'all') {
                    $this->obj->clearAVRecordsForHost();
                    $this->redirect('?node=host&sub=edit&id='.$this->obj->get('id').'#'.$_REQUEST['tab']);
                } else if (isset($_REQUEST['delvid'])) $this->getClass('VirusManager')->destroy(array('id' => $_REQUEST['delvid']));
                break;
            }
            if (!$this->obj->save()) throw new Exception(_('Host Update Failed'));
            $this->obj->setAD();
            if ($_REQUEST['tab'] == 'host-general') $this->obj->ignore($_REQUEST['igimage'],$_REQUEST['igclient']);
            $this->HookManager->processEvent('HOST_EDIT_SUCCESS',array('Host'=>&$this->obj));
            $this->setMessage('Host updated!');
        } catch (Exception $e) {
            $this->HookManager->processEvent('HOST_EDIT_FAIL',array('Host'=>&$this->obj));
            $this->setMessage($e->getMessage());
        }
        $this->redirect(sprintf('%s#%s',$this->formAction, $_REQUEST['tab']));
    }
    public function save_group() {
        try {
            if (empty($_REQUEST['hostIDArray'])) throw new Exception(_('No Hosts were selected'));
            if (empty($_REQUEST['group_new']) && empty($_REQUEST['group'])) throw new Exception(_('No Group selected and no new Group name entered'));
            if (!empty($_REQUEST['group_new'])) {
                $Group = $this->getClass('Group')
                    ->set('name',$_REQUEST['group_new']);
                if (!$Group->save()) throw new Exception(_('Failed to create new Group'));
            } else $Group = $this->getClass('Group',$_REQUEST['group']);
            if (!$Group->isValid()) throw new Exception(_('Group is Invalid'));
            $Group->addHost(explode(',',$_REQUEST['hostIDArray']))->save();
            echo '<div class="task-start-ok"><p>'._('Successfully associated Hosts with the Group ').$Group->get('name').'</p></div>';
        } catch (Exception $e) {
            printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>', _('Failed to Associate Hosts with Group'), $e->getMessage());
        }
    }
    public function hostlogins() {
        $MainDate = $this->nice_date($_REQUEST['dte'])->getTimestamp();
        $MainDate_1 = $this->nice_date($_REQUEST['dte'])->modify('+1 day')->getTimestamp();
        $Users = $this->getClass('UserTrackingManager')->find(array('hostID'=>$_REQUEST['id'],'date'=>$_REQUEST['dte'],'action'=>array(null,0,1)),'','date','DESC');
        foreach ($Users AS $i => &$Login) {
            if (!$Login->isValid()) continue;
            if ($Login->get('username') != 'Array') {
                $time = $this->nice_date($Login->get('datetime'))->format('U');
                if (!$Data[$Login->get('username')]) $Data[$Login->get('username')] = array('user'=>$Login->get('username'),'min'=>$MainDate,'max'=>$MainDate_1);
                if ($Login->get('action')) $Data[$Login->get('username')]['login'] = $time;
                if (array_key_exists('login',$Data[$Login->get('username')]) && !$Login->get('action')) $Data[$Login->get('username')]['logout'] = $time;
                if (array_key_exists('login',$Data[$Login->get('username')]) && array_key_exists('logout',$Data[$Login->get('username')])) {
                    $data[] = $Data[$Login->get('username')];
                    unset($Data[$Login->get('username')]);
                }
            }
            unset($Login);
        }
        unset($Users);
        echo json_encode($data);
    }
}
