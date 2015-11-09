<?php
class FOGConfigurationPage extends FOGPage {
    public $node = 'about';
    public function __construct($name = '') {
        $this->name = 'FOG Configuration';
        parent::__construct($this->name);
        $this->menu = array(
            'license'=>$this->foglang['License'],
            'kernel-update'=>$this->foglang['KernelUpdate'],
            'pxemenu'=>$this->foglang['PXEBootMenu'],
            'customize-edit'=>$this->foglang['PXEConfiguration'],
            'new-menu'=>$this->foglang['NewMenu'],
            'client-updater'=>$this->foglang['ClientUpdater'],
            'mac-list'=>$this->foglang['MACAddrList'],
            'settings'=>$this->foglang['FOGSettings'],
            'log'=>$this->foglang['LogViewer'],
            'config'=>$this->foglang['ConfigSave'],
            'http://www.sf.net/projects/freeghost'=>$this->foglang['FOGSFPage'],
            'https://fogproject.org'=>$this->foglang['FOGWebPage'],
        );
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
    }
    public function index() {
        $this->version();
    }
    public function version() {
        $URLs = array();
        $Names = array();
        $this->title = _('FOG Version Information');
        printf('<p>%s: %s</p>',_('Version'),FOG_VERSION);
        $URLs[] = sprintf('https://fogproject.org/version/index.php?version=%s',FOG_VERSION);
        $Nodes = $this->getClass('StorageNodeManager')->find(array('isEnabled'=>1));
        foreach ((array)$Nodes AS $i => &$StorageNode) {
            $curroot = trim(trim($StorageNode->get('webroot'),'/'));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            $URLs[] = "http://{$StorageNode->get(ip)}{$webroot}status/kernelvers.php";
            unset($StorageNode);
        }
        $Responses = $this->FOGURLRequests->process($URLs,'GET');
        array_unshift($Nodes,'');
        foreach ((array)$Responses AS $i => &$data) {
            if ($i === 0) echo "<p><div class=\"sub\">{$Responses[$i]}</div></p><h1>Kernel Versions</h1>";
            else echo "<h2>{$Nodes[$i]->get(name)}</h2><pre>$data</pre>";
            unset($data);
        }
        unset($Responses,$Nodes);
    }
    public function license() {
        $this->title = _('FOG License Information');
        $file = "./languages/{$_SESSION['locale']}/gpl-3.0.txt";
        if ($handle = fopen($file,'rb')) {
            echo '<pre>';
            while (($line = fgets($handle)) !== false) echo $line;
            echo '</pre>';
        }
        fclose($handle);
    }
    public function kernel() {
        $this->kernel_update_post();
    }
    public function kernel_update() {
        $this->kernelselForm('pk');
        $htmlData = $this->FOGURLRequests->process(sprintf('https://fogproject.org/kernels/kernelupdate.php?version=%s',FOG_VERSION),'GET');
        echo $htmlData[0];
    }
    public function kernelselForm($type) {
        printf('<div class="hostgroup">%s</div><div><form method="post" action="%s"><select name="kernelsel" onchange="this.form.submit()"><option value="pk" %s>%s</option><option value="ok" %s>%s</option></select></form></div>',_('This section allows you to update the Linux kernel which is used to boot the client computers.  In FOG, this kernel holds all the drivers for the client computer, so if you are unable to boot a client you may wish to update to a newer kernel which may have more drivers built in.  This installation process may take a few minutes, as FOG will attempt to go out to the internet to get the requested Kernel, so if it seems like the process is hanging please be patient.'),$this->formAction,($type == 'pk' ? 'selected' : ''),_('Published Kernel'),($type == 'ok' ? 'selected' : ''),_('Old Published Kernels'));
    }
    public function kernel_update_post() {
        if (in_array($_REQUEST['sub'],array('kernel-update','kernel_update'))) {
            switch ($_REQUEST['kernelsel']) {
            case 'pk':
                $this->kernelselForm('pk');
                $htmlData = $this->FOGURLRequests->process("https://fogproject.org/kernels/kernelupdate.php?version=" . FOG_VERSION,'GET');
                echo $htmlData[0];
                break;
            case 'ok':
                $this->kernelselForm('ok');
                $htmlData = $this->FOGURLRequests->process("http://freeghost.sourceforge.net/kernelupdates/index.php?version=".FOG_VERSION,'GET');
                echo $htmlData[0];
                break;
            default:
                $this->kernelselForm('pk');
                $htmlData = $this->FOGURRequests->process('https://fogproject.org/kernels/kernelupdate.php?version='.FOG_VERSION,'GET');
                echo $htmlData[0];
                break;
            }
        } else if ($_REQUEST['install']) {
            $_SESSION['allow_ajax_kdl'] = true;
            $_SESSION['dest-kernel-file'] = trim($_REQUEST['dstName']);
            $_SESSION['tmp-kernel-file'] = rtrim(sys_get_temp_dir(),'/').'/'.basename($_SESSION['dest-kernel-file']);
            $_SESSION['dl-kernel-file'] = base64_decode($_REQUEST['file']);
            if (file_exists($_SESSION['tmp-kernel-file'])) @unlink($_SESSION['tmp-kernel-file']);
            echo '<div id="kdlRes"><p id="currentdlstate">'._('Starting process...').'</p><i id="img" class="fa fa-cog fa-2x fa-spin"></i></div>';
        } else {
            echo '<form method="post" action="?node='.$_REQUEST['node'].'&sub=kernel&install=1&file='.$_REQUEST['file'].'">';
            echo "<p>"._('New Kernel name:').'<input class="smaller" type="text" name="dstName" value="'.($_REQUEST['arch'] == 64 || !$_REQUEST['arch'] ? 'bzImage' : 'bzImage32').'" /></p>';
            echo '<p><input class="smaller" type="submit" value="Next" /></p></form>';
        }
    }
    public function pxemenu() {
        $this->title = _('FOG PXE Boot Menu Configuration');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $noMenu = $this->getSetting('FOG_NO_MENU') ? 'checked' : '';
        $hidChecked = ($this->getSetting('FOG_PXE_MENU_HIDDEN') ? 'checked' : '');
        $hideTimeout = $this->getSetting('FOG_PXE_HIDDENMENU_TIMEOUT');
        $timeout = $this->getSetting('FOG_PXE_MENU_TIMEOUT');
        $bootKeys = $this->getClass('KeySequenceManager')->buildSelectBox($this->getSetting('FOG_KEY_SEQUENCE'));
        $advLogin = ($this->getSetting('FOG_ADVANCED_MENU_LOGIN') ? 'checked' : '');
        $advanced = $this->getSetting('FOG_PXE_ADVANCED');
        $exitNorm = Service::buildExitSelector('bootTypeExit',$this->getSetting('FOG_BOOT_EXIT_TYPE'));
        $exitEfi = Service::buildExitSelector('efiBootTypeExit',$this->getSetting('FOG_EFI_BOOT_EXIT_TYPE'));
        $fields = array(
            _('No Menu') => '<input type="checkbox" name="nomenu" value="1" '.$noMenu.'/><i class="icon fa fa-question hand" title="Option sets if there will even be the presence of a menu to the client systems.  If there is not a task set, it boots to the first device, if there is a task, it performs that task."></i>',
            _('Hide Menu') => '<input type="checkbox" name="hidemenu" value="1" '.$hidChecked.'/><i class="icon fa fa-question hand" title="Option below sets the key sequence.  If none is specified, ESC is defaulted. Login with the FOG credentials and you will see the menu.  Otherwise it will just boot like normal."></i>',
            _('Hide Menu Timeout') => '<input type="text" name="hidetimeout" value="'.$hideTimeout.'" /><i class="icon fa fa-question hand" title="Option specifies the timeout value for the hidden menu system."></i>',
            _('Advanced Menu Login') => '<input type="checkbox" name="advmenulogin" value="1" '.$advLogin.'/><i class="icon fa fa-question hand" title="Option below enforces a Login system for the Advanced menu parameters.  If off no login will appear, if on, it will only allow login to the advanced system.."></i>',
            _('Boot Key Sequence') => $bootKeys,
            _('Menu Timeout (in seconds)').':*' => '<input type="text" name="timeout" value="'.$timeout.'" id="timeout" />',
            _('Exit to Hard Drive Type') => $exitNorm,
            _('Exit to Hard Drive Type(EFI)') => $exitEfi,
            '<a href="#" onload="$(\'#advancedTextArea\').hide();" onclick="$(\'#advancedTextArea\').toggle();" id="pxeAdvancedLink">Advanced Configuration Options</a>' => '<div id="advancedTextArea" class="hidden"><div class="lighterText tabbed">Add any custom text you would like included added as part of your <i>default</i> file.</div><textarea rows="5" cols="40" name="adv">'.$advanced.'</textarea></div>',
            '&nbsp;' => '<input type="submit" value="'._('Save PXE MENU').'" />',
        );
        echo '<form method="post" action="'.$this->formAction.'">';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);
        $this->HookManager->processEvent('PXE_BOOT_MENU',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function pxemenu_post() {
        try {
            $timeout = trim($_REQUEST['timeout']);
            $timeout = (is_numeric($timeout) || intval($timeout) >= 0 ? true : false);
            if (!$timeout) throw new Exception(_('Invalid Timeout Value'));
            else $timeout = trim($_REQUEST['timeout']);
            $hidetimeout = trim($_REQUEST['hidetimeout']);
            $hidetimeout = (is_numeric($hidetimeout) || intval($hidetimeout) >= 0 ? true : false);
            if (!$hidetimeout) throw new Exception(_('Invalid Timeout Value'));
            else $hidetimeout = trim($_REQUEST['hidetimeout']);
            if (!$this
                ->setSetting('FOG_PXE_MENU_HIDDEN',$_REQUEST['hidemenu'])
                ->setSetting('FOG_PXE_MENU_TIMEOUT',$timeout)
                ->setSetting('FOG_PXE_ADVANCED',$_REQUEST['adv'])
                ->setSetting('FOG_KEY_SEQUENCE',$_REQUEST['keysequence'])
                ->setSetting('FOG_NO_MENU',$_REQUEST['nomenu'])
                ->setSetting('FOG_BOOT_EXIT_TYPE',$_REQUEST['bootTypeExit'])
                ->setSetting('FOG_EFI_BOOT_EXIT_TYPE',$_REQUEST['efiBootTypeExit'])
                ->setSetting('FOG_ADVANCED_MENU_LOGIN',$_REQUEST['advmenulogin'])
                ->setSetting('FOG_PXE_HIDDENMENU_TIMEOUT',$hidetimeout)) throw new Exception(_('PXE Menu update failed'));
            throw new Exception(_('PXE Menu has been updated'));
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function customize_edit() {
        $this->title = $this->foglang['PXEMenuCustomization'];
        echo '<p>'._('This item allows you to edit all of the PXE Menu items as you see fit.  Mind you, iPXE syntax is very finicky when it comes to edits.  If you need help understanding what items are needed, please see the forums.  You can also look at ipxe.org for syntactic usage and methods.  Some of the items here are bound to limitations.  Documentation will follow when enough time is provided.').'</p>';
        echo '<div id="tab-container-1">';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        foreach ((array)$this->getClass('PXEMenuOptionsManager')->find('','','id') AS $i => &$Menu) {
            if (!$Menu->isValid()) continue;
            $divTab = preg_replace('/[[:space:]]/','_',preg_replace('/\./','_',preg_replace('/:/','_',$Menu->get('name'))));
            echo '<a id="'.$divTab.'" style="text-decoration:none;" href="#'.$divTab.'"><h3>'.$Menu->get('name').'</h3></a>';
            echo '<div id="'.$divTab.'">';
            echo '<form method="post" action="'.$this->formAction.'">';
            $menuid = in_array($Menu->get('id'),range(1,13));
            $menuDefault = $Menu->get('default') ? 'checked' : '';
            $fields = array(
                _('Menu Item:') => '<input type="text" name="menu_item" value="'.$Menu->get('name').'" id="menu_item"/>',
                _('Description:') => '<textarea cols="40" rows="2" name="menu_description">'.$Menu->get('description').'</textarea>',
                _('Parameters:') => '<textarea cols="40" rows="8" name="menu_params">'.$Menu->get('params').'</textarea>',
                _('Boot Options:') => '<input type="text" name="menu_options" id="menu_options" value="'.$Menu->get('args').'" />',
                _('Default Item:') => '<input type="checkbox" name="menu_default" value="1" '.$menuDefault.'/>',
                _('Menu Show with:') => $this->getClass('PXEMenuOptionsManager')->regSelect($Menu->get('regMenu')),
                '<input type="hidden" name="menu_id" value="'.$Menu->get('id').'" />' => '<input type="submit" name="saveform" value="'.$this->foglang['Submit'].'" />',
                !$menuid ? '<input type="hidden" name="rmid" value="'.$Menu->get('id').'" />' : '' => !$menuid ? '<input type="submit" name="delform" value="'.$this->foglang['Delete'].' '.$Menu->get('name').'"/>' : '',
            );
            foreach ((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    'field'=>$field,
                    'input'=>$input,
                );
                unset($input);
            }
            unset($fields);
            $this->HookManager->processEvent('BOOT_ITEMS_'.$divTab,array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes,'headerData'=>&$this->headerData));
            $this->render();
            echo '</form></div>';
            unset($this->data,$Menu);
        }
        echo '</div>';
    }
    public function customize_edit_post() {
        if (isset($_REQUEST['saveform']) && $_REQUEST['menu_id']) {
            $this->getClass('PXEMenuOptionsManager')->update('','',array('default'=>0));
            $Menu = $this->getClass('PXEMenuOptions',$_REQUEST['menu_id'])
                ->set('name',$_REQUEST['menu_item'])
                ->set('description',$_REQUEST['menu_description'])
                ->set('params',$_REQUEST['menu_params'])
                ->set('regMenu',$_REQUEST['menu_regmenu'])
                ->set('args',$_REQUEST['menu_options'])
                ->set('default',$_REQUEST['menu_default']);
            if ($Menu->save()) $this->setMessage($Menu->get('name').' '._('successfully updated').'!');
        }
        if (isset($_REQUEST['delform']) && $_REQUEST['rmid']) {
            $menuname = $this->getClass('PXEMenuOptions',$_REQUEST['rmid'])->get('name');
            if ($this->getClass('PXEMenuOptions',$_REQUEST['rmid'])->destroy()) $this->setMessage($menuname.' '._('successfully removed').'!');
        }
        $countDefault = $this->getClass('PXEMenuOptionsManager')->count(array('default'=>1));
        if ($countDefault == 0 || $countDefault > 1) $this->getClass('PXEMenuOptions',1)->set('default',1)->save();
        $this->redirect($this->formAction);
    }
    public function new_menu() {
        $this->title = _('Create New iPXE Menu Entry');
        $this->templates = array(
            '${field}',
            '${input}',
        );
        echo '<form method="post" action="'.$this->formAction.'">';
        $menudefault = $_REQUEST['menu_default'] ? 'checked' : '';
        $fields = array(
            _('Menu Item:') => '<input type="text" name="menu_item" value="'.$_REQUEST['menu_item'].'" id="menu_item" />',
            _('Description:') => '<textarea cols="40" rows="2" name="menu_description">'.$_REQUEST['menu_description'].'</textarea>',
            _('Parameters:') => '<textarea cols="40" rows="8" name="menu_params">'.$_REQUEST['menu_params'].'</textarea>',
            _('Boot Options:') => '<input type="text" name="menu_options" id="menu_options" value="'.$_REQUEST['menu_options'].'" />',
            _('Default Item:') => '<input type="checkbox" name="menu_default" value="1" '.$menudefault.'/>',
            _('Menu Show with:') => $this->getClass('PXEMenuOptionsManager')->regSelect($_REQUEST['menu_regmenu']),
            '&nbsp;' => '<input type="submit" value="'._($this->foglang['Add'].' New Menu').'" />',
        );
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);
        $this->HookManager->processEvent('BOOT_ITEMS_ADD',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes,'headerData'=>&$this->headerData));
        $this->render();
        echo "</form>";
    }
    public function new_menu_post() {
        try {
            if (!$_REQUEST['menu_item']) throw new Exception(_('Menu Item or title cannot be blank'));
            if (!$_REQUEST['menu_description']) throw new Exception(_('A description needs to be set'));
            if ($_REQUEST['menu_default']) $this->getClass('PXEMenuOptionsManager')->update('','',array('default'=>0));
            $Menu = $this->getClass('PXEMenuOptions')
                ->set('name',$_REQUEST['menu_item'])
                ->set('description',$_REQUEST['menu_description'])
                ->set('params',$_REQUEST['menu_params'])
                ->set('regMenu',$_REQUEST['menu_regmenu'])
                ->set('args',$_REQUEST['menu_options'])
                ->set('default',$_REQUEST['menu_default']);
            if (!$Menu->save()) throw new Exception(_('Menu create failed'));
            $countDefault = $this->getClass('PXEMenuOptionsManager')->count(array('default'=>1));
            if ($countDefault == 0 || $countDefault > 1) $this->getClass('PXEMenuOptions',1)->set('default',1)->save();
            $this->HookManager->processEvent('MENU_ADD_SUCCESS',array('Menu'=>&$Menu));
            $this->setMessage(_('Menu Added'));
            $this->redirect(sprintf('?node=%s&sub=edit&%s=%s',$this->node,$this->id,$Menu->get('id')));
        } catch (Exception $e) {
            $this->HookManager->processEvent('MENU_ADD_FAIL',array('Menu'=>&$Menu));
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function client_updater() {
        $this->title = _("FOG Client Service Updater");
        $this->headerData = array(
            _('Module Name'),
            _('Module MD5'),
            _('Module Type'),
            _('Delete'),
        );
        $this->templates = array(
            '<input type="hidden" name="name" value="FOG_SERVICE_CLIENTUPDATER_ENABLED" />${name}',
            '${module}',
            '${type}',
            '<input type="checkbox" onclick="this.form.submit()" name="delcu" class="delid" id="delcuid${client_id}" value="${client_id}" /><label for="delcuid${client_id}" class="icon fa fa-minus-circle icon-hand" title="'._('Delete').'">&nbsp;</label>',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array('class'=>'filter-false disabled'),
        );
        echo '<div class="hostgroup">'._('This section allows you to update the modules and config files that run on the client computers.  The clients will checkin with the server from time to time to see if a new module is published.  If a new module is published the client will download the module and use it on the next time the service is started.').'</div>';
        foreach ((array)$this->getClass('ClientUpdaterManager')->find() AS $i => &$ClientUpdate) {
            $this->data[] = array(
                'name'=>$ClientUpdate->get('name'),
                'module'=>$ClientUpdate->get('md5'),
                'type'=>$ClientUpdate->get('type'),
                'client_id'=>$ClientUpdate->get('id'),
                'id'=>$ClientUpdate->get('id'),
            );
            unset($ClientUpdate);
        }
        $this->HookManager->processEvent('CLIENT_UPDATE',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        echo '<form method="post" action="'.$this->formAction.'&tab=clientupdater">';
        $this->render();
        echo '</form>';
        unset($this->headerData,$this->attributes,$this->templates,$this->data);
        echo '<p class="header">'._('Upload a new client module/configuration file').'</p>';
        $this->attributes = array(
            array(),
            array('class'=>'filter-false'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<input type="file" name="module[]" value="" multiple/> <span class="lightColor">'._('Max Size:').ini_get('post_max_size').'</span>' => '<input type="submit" value="'._('Upload File').'" />',
        );
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        $this->HookManager->processEvent('CLIENT_UPDATE',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        echo '<form method="post" action="'.$this->formAction.'&tab=clientupdater" enctype="multipart/form-data"><input type="hidden" name="name" value="FOG_SERVICE_CLIENTUPDATER_ENABLED"/>';
        $this->render();
        echo '</form>';
    }
    public function client_updater_post() {
        if ($_REQUEST['delcu']) {
            $this->getClass('ClientUpdaterManager')->destroy(array('id'=>$_REQUEST['delcu']));
            $this->setMessage(_('Client module update deleted!'));
        }
        if ($_FILES['module']) {
            foreach ((array)$_FILES['module']['tmp_name'] AS $index => &$tmp_name) {
                if (!file_exists($tmp_name)) continue;
                if (!($md5 = md5(file_get_contents($tmp_name)))) continue;
                $filename = basename($_FILES['module']['name'][$index]);
                $this->getClass('ClientUpdater',@max($this->getClass('ClientUpdater',array('name'=>$filename),'id')))
                    ->set('name',$filename)
                    ->set('md5',$md5)
                    ->set('type',$this->endsWith($filename,'.ini') ? 'txt' : 'bin')
                    ->set('file',file_get_contents($tmp_name))
                    ->save();
                unset($tmp_name);
            }
        }
        $this->setMessage(_('Modules added/updated'));
        $this->redirect(sprintf('%s#%s',$this->formAction,$_REQUEST['tab']));
    }
    public function mac_list() {
        $this->title = _('MAC Address Manufacturer Listing');
        echo '<div class="hostgroup">'._('This section allows you to import known mac address makers into the FOG database for easier identification.').'</div><div><p>'._('Current Records: ').$this->FOGCore->getMACLookupCount().'</p><p><div id="delete"></div><div id="update"></div><input class="macButtons" type="button" title="'._('Delete MACs').'" value="'._('Delete Current Records').'" onclick="clearMacs()" /><input class="macButtons" style="margin-left: 20px" type="button" title="'._('Update MACs').'" value="'._('Update Current Listing').'" onclick="updateMacs()" /></p><p>'._('MAC address listing source: ').'<a href="http://standards.ieee.org/regauth/oui/oui.txt">http://standards.ieee.org/regauth/oui/oui.txt</a></p></div>';
    }
    public function mac_list_post() {
        if ($_REQUEST['update']) {
            $f = '/tmp/oui.txt';
            exec("rm -rf $f");
            exec("wget -O $f  http://standards.ieee.org/develop/regauth/oui/oui.txt");
            if (false !== ($handle = fopen($f,'rb'))) {
                $start = 18;
                $imported = 0;
                while (!feof($handle)) {
                    $line = trim(fgets($handle));
                    if (preg_match("#^([0-9a-fA-F][0-9a-fA-F][:-]){2}([0-9a-fA-F][0-9a-fA-F]).*$#",$line)) {
                        $macprefix = substr($line,0,8);
                        $maker = substr($line,$start,strlen($line)-$start);
                        if (strlen($macprefix) == 8 && strlen($maker) > 0) {
                            $mac = trim($macprefix);
                            $mak = trim($maker);
                            $macsandmakers[$mac] = $mak;
                            $imported++;
                        }
                    }
                }
                fclose($handle);
                $this->FOGCore->addUpdateMACLookupTable($macsandmakers);
                $this->setMessage($imported._(' mac addresses updated!'));
            } else echo (_('Unable to locate file').': '.$f);
        } else if ($_REQUEST['clear']) $this->FOGCore->clearMACLookupTable();
        $this->resetRequest();
        $this->redirect('?node=about&sub=mac-list');
    }
    public function settings() {
        $ServiceNames = array(
            'FOG_REGISTRATION_ENABLED',
            'FOG_PXE_MENU_HIDDEN',
            'FOG_QUICKREG_AUTOPOP',
            'FOG_SERVICE_AUTOLOGOFF_ENABLED',
            'FOG_SERVICE_CLIENTUPDATER_ENABLED',
            'FOG_SERVICE_DIRECTORYCLEANER_ENABLED',
            'FOG_SERVICE_DISPLAYMANAGER_ENABLED',
            'FOG_SERVICE_GREENFOG_ENABLED',
            'FOG_SERVICE_HOSTREGISTER_ENABLED',
            'FOG_SERVICE_HOSTNAMECHANGER_ENABLED',
            'FOG_SERVICE_PRINTERMANAGER_ENABLED',
            'FOG_SERVICE_SNAPIN_ENABLED',
            'FOG_SERVICE_TASKREBOOT_ENABLED',
            'FOG_SERVICE_USERCLEANUP_ENABLED',
            'FOG_SERVICE_USERTRACKER_ENABLED',
            'FOG_ADVANCED_STATISTICS',
            'FOG_CHANGE_HOSTNAME_EARLY',
            'FOG_DISABLE_CHKDSK',
            'FOG_HOST_LOOKUP',
            'FOG_UPLOADIGNOREPAGEHIBER',
            'FOG_USE_ANIMATION_EFFECTS',
            'FOG_USE_LEGACY_TASKLIST',
            'FOG_USE_SLOPPY_NAME_LOOKUPS',
            'FOG_PLUGINSYS_ENABLED',
            'FOG_FORMAT_FLAG_IN_GUI',
            'FOG_NO_MENU',
            'FOG_MINING_ENABLE',
            'FOG_MINING_FULL_RUN_ON_WEEKEND',
            'FOG_ALWAYS_LOGGED_IN',
            'FOG_ADVANCED_MENU_LOGIN',
            'FOG_TASK_FORCE_REBOOT',
            'FOG_EMAIL_ACTION',
            'FOG_FTP_IMAGE_SIZE',
            'FOG_KERNEL_DEBUG',
        );
        $this->title = _("FOG System Settings");
        echo '<p class="hostgroup">'._('This section allows you to customize or alter the way in which FOG operates.  Please be very careful changing any of the following settings, as they can cause issues that are difficult to troubleshoot.').'</p><form method="post" action="'.$this->formAction.'"><div id="tab-container-1">';
        unset($this->headerData);
        $this->attributes = array(
            array('width'=>270,'height'=>35),
            array(),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${service_name}',
            '${input_type}',
            '${span}',
        );
        echo '<a href="#" class="trigger_expand"><h3>Expand All</h3></a>';
        foreach ((array)$this->getClass('ServiceManager')->getSettingCats() AS $i => &$ServiceCAT) {
            $divTab = preg_replace('/[[:space:]]/','_',preg_replace('/:/','_',$ServiceCAT));
            echo '<a id="'.$divTab.'" class="expand_trigger" style="text-decoration:none;" href="#'.$divTab.'"><h3>'.$ServiceCAT.'</h3></a>';
            echo '<div id="'.$divTab.'">';
            $ServMan = $this->getClass('ServiceManager')->find(array('category'=>$ServiceCAT),'AND','id');
            foreach ((array)$this->getClass('ServiceManager')->find(array('category'=>$ServiceCAT),'AND','id') AS $i => &$Service) {
                if (!$Service->isValid()) continue;
                switch ($Service->get('name')) {
                case 'FOG_PIGZ_COMP':
                    $type = '<div id="pigz" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showVal" maxsize="1" style="width: 10px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
                    break;
                case 'FOG_KERNEL_LOGLEVEL':
                    $type = '<div id="loglvl" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showlogVal" maxsize="1" style="width: 10px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
                    break;
                case 'FOG_INACTIVITY_TIMEOUT':
                    $type = '<div id="inact" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showValInAct" maxsize="2" style="width: 15px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
                    break;
                case 'FOG_REGENERATE_TIMEOUT':
                    $type = '<div id="regen" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showValRegen" maxsize="5" style="width: 25px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
                    break;
                case 'FOG_VIEW_DEFAULT_SCREEN':
                    $screens = array('SEARCH','LIST');
                    foreach ((array)$screens AS $i => &$viewop) $options[] = '<option value="'.strtolower($viewop).'" '.($Service->get('value') == strtolower($viewop) ? 'selected="selected"' : '').'>'.$viewop.'</option>';
                    unset($viewop);
                    $type = '<select name="${service_id}" style="width: 220px" autocomplete="off">'.implode($options).'</select>';
                    unset($options);
                    break;
                case 'FOG_MULTICAST_DUPLEX':
                    $duplexTypes = array(
                        'HALF_DUPLEX' => '--half-duplex',
                        'FULL_DUPLEX' => '--full-duplex',
                    );
                    foreach ((array)$duplexTypes AS $types => &$val) $options[] = '<option value="'.$val.'" '.($Service->get('value') == $val ? 'selected="selected"' : '').'>'.$types.'</option>';
                    unset($val);
                    $type = '<select name="${service_id}" style="width: 220px" autocomplete="off">'.implode($options).'</select>';
                    break;
                case 'FOG_BOOT_EXIT_TYPE':
                case 'FOG_EFI_BOOT_EXIT_TYPE':
                    $type = Service::buildExitSelector($Service->get(id),$Service->get(value));
                    break;
                case 'FOG_DEFAULT_LOCALE':
                    foreach ((array)$this->foglang['Language'] AS $lang => &$humanreadable) $options2[] = '<option value="'.$lang.'" '.($this->getSetting('FOG_DEFAULT_LOCALE') == $lang || $this->getSetting('FOG_DEFAULT_LOCALE') == $this->foglang['Language'][$lang] ? 'selected="selected"' : '').'>'.$humanreadable.'</option>';
                    unset($humanreadable);
                    $type = '<select name="${service_id}" autocomplete="off" style="width: 220px">'.implode($options2).'</select>';
                    break;
                case 'FOG_QUICKREG_IMG_ID':
                    $type = $this->getClass('ImageManager')->buildSelectBox($this->getSetting('FOG_QUICKREG_IMG_ID'),$Service->get('id').'" id="${service_name}');
                    break;
                case 'FOG_QUICKREG_GROUP_ASSOC':
                    $type = $this->getClass('GroupManager')->buildSelectBox($this->getSetting('FOG_QUICKREG_GROUP_ASSOC'),$Service->get('id'));
                    break;
                case 'FOG_KEY_SEQUENCE':
                    $type = $this->getClass('KeySequenceManager')->buildSelectBox($this->getSetting('FOG_KEY_SEQUENCE'),$Service->get('id'));
                    break;
                case 'FOG_QUICKREG_OS_ID':
                    $ImageName = _('No image specified');
                    if ($this->getSetting('FOG_QUICKREG_IMG_ID') > 0) $ImageName = $this->getClass('Image',$this->getSetting('FOG_QUICKREG_IMG_ID'))->get('name');
                    $type = '<p id="${service_name}">'.$ImageName.'</p>';
                    break;
                case 'FOG_TZ_INFO':
                    $dt = $this->nice_date('now',$utc);
                    $tzIDs = DateTimeZone::listIdentifiers();
                    $type = '<select name="${service_id}">';
                    foreach ((array)$tzIDs AS $i => &$tz) {
                        $current_tz = $this->getClass('DateTimeZone',$tz);
                        $offset = $current_tz->getOffset($dt);
                        $transition = $current_tz->getTransitions($dt->getTimestamp(),$dt->getTimestamp());
                        $abbr = $transition[0]['abbr'];
                        $offset = sprintf('%+03d:%02u', floor($offset / 3600), floor(abs($offset) % 3600 / 60));
                        $type .= '<option value="'.$tz.'"'.($Service->get('value') == $tz ? ' selected' : '').'>'.$tz.' ['.$abbr.' '.$offset.']</option>';
                    }
                    unset($current_tz,$offset,$transition,$tzIDs,$dt);
                    $type .= '</select>';
                    break;
                case (preg_match('#pass#i',$Service->get('name')) && !preg_match('#(valid|min)#i',$Service->get('name'))):
                    $type = '<input type="password" name="${service_id}" value="${service_value}" autocomplete="off" />';
                    break;
                case (in_array($Service->get('name'),$ServiceNames)):
                    $type = '<input type="checkbox" name="${service_id}" value="1" '.($Service->get('value') ? 'checked' : '').' />';
                    break;
                case 'FOG_AD_DEFAULT_OU':
                    $type = '<textarea rows="5" name="${service_id}">${service_value}</textarea>';
                    break;
                default:
                    $type = '<input id="${service_name}" type="text" name="${service_id}" value="${service_value}" autocomplete="off" />';
                    break;
                }
                $this->data[] = array(
                    'input_type'=>(count(explode(chr(10),$Service->get('value'))) <= 1 ? $type : '<textarea rows="5" name="${service_id}">${service_value}</textarea>'),
                    'service_name'=> $Service->get('name'),
                    'span'=>'<i class="icon fa fa-question hand" title="${service_desc}"></i>',
                    'service_id'=>$Service->get('id'),
                    'id'=>$Service->get('id'),
                    'service_value'=>$Service->get('value'),
                    'service_desc'=>$Service->get('description'),
                );
                unset($Service);
            }
            unset($ServMan);
            $this->data[] = array(
                'span'=>'&nbsp;',
                'service_name'=>'',
                'input_type'=>'<input name="update" type="submit" value="'._('Save Changes').'" />',
            );
            $this->HookManager->processEvent('CLIENT_UPDATE_'.$divTab,array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
            $this->render();
            echo '</div>';
            unset($this->data,$options,$ServiceCAT);
        }
        unset($ServiceCats);
        echo '</div></form>';
    }
    public function getOSID() {
        $osname = $this->getClass('Image',$_REQUEST['image_id'])->getOS()->get('name');
        echo json_encode($osname ? $osname : _('No Image specified'));
        exit;
    }
    public function settings_post() {
        foreach ((array)$this->getClass('ServiceManager')->find() AS $i => &$Service) {
            $key = $Service->get('id');
            $_REQUEST[$key] = trim($_REQUEST[$key]);
            $Service->set('value',$_REQUEST[$key]);
            switch ($Service->get('name')) {
            case 'FOG_MEMORY_LIMIT':
                if ($_REQUEST[$key] < 128 || !is_numeric($_REQUEST[$key])) $Service->set('value',128);
                break;
            case 'FOG_QUICKREG_IMG_ID':
                if (empty($_REQUEST[$key])) $Service->set('value',-1);
                break;
            case 'FOG_USER_VALIDPASSCHARS':
                $Service->set('value',$_REQUEST[$key]);
                break;
            case 'FOG_AD_DEFAULT_PASSWORD':
                $Service->set('value',$this->encryptpw($_REQUEST[$key]));
                break;
            case 'FOG_MULTICAST_PORT_OVERRIDE':
                if (!is_numeric($_REQUEST[$key]) || $_REQUEST[$key] < 0 || $_REQUEST[$key] > 65536) $Service->set('value',0);
                break;
            case 'FOG_MULTICAST_ADDRESS':
                if (!filter_var($_REQUEST[$key],FILTER_VALIDATE_IP)) $Service->set('value',0);
                break;
            case 'FOG_INACTIVITY_TIMEOUT':
                if (!is_numeric($_REQUEST[$key]) || $_REQUEST[$key] < 1 || $_REQUEST[$key] > 24) $Service->set('value',1);
                break;
            case 'FOG_REGENERATE_TIMEOUT':
                if (!is_numeric($_REQUEST[$key])) $Service->set('value',0);
                break;
            }
            $Service->save();
            unset($Service);
        }
        unset($ServiceMan);
        $this->setMessage('Settings Successfully stored!');
        $this->redirect($this->formAction);
    }
    public function log() {
        $StorageGroups = $this->getClass('StorageGroupManager')->find();
        foreach ((array)$StorageGroups AS $i => &$StorageGroup) {
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode->isValid()) continue;
            if (!$StorageNode->get('isEnabled')) continue;
            $user = $StorageNode->get('user');
            $pass = $StorageNode->get('pass');
            $host = $StorageNode->get('ip');
            $this->FOGFTP
                ->set('host',$host)
                ->set('username',$user)
                ->set('password',$pass);
            if (!$this->FOGFTP->connect()) continue;
            $ftpstart = "ftp://$user:$pass@$host";
            $fogfiles = array();
            $fogfiles = array_merge($this->FOGFTP->nlist('/var/log/httpd/'),$this->FOGFTP->nlist('/var/log/apache2/'),$this->FOGFTP->nlist('/var/log/fog'));
            $this->FOGFTP->close();
            $apacheerrlog = preg_grep('#(error\.log$|.*error_log$)#i',$fogfiles);
            $apacheerrlog = $ftpstart.@array_shift($apacheerrlog);
            $apacheacclog = preg_grep('#(access\.log$|.*access_log$)#i',$fogfiles);
            $apacheacclog = $ftpstart.@array_shift($apacheacclog);
            $multicastlog = preg_grep('#(multicast.log$)#i',$fogfiles);
            $multicastlog = $ftpstart.@array_shift($multicastlog);
            $schedulerlog = preg_grep('#(fogscheduler.log$)#i',$fogfiles);
            $schedulerlog = $ftpstart.@array_shift($schedulerlog);
            $imgrepliclog = preg_grep('#(fogreplicator.log$)#i',$fogfiles);
            $imgrepliclog = $ftpstart.@array_shift($imgrepliclog);
            $snapinreplog = preg_grep('#(fogsnapinrep.log$)#i',$fogfiles);
            $snapinreplog = $ftpstart.@array_shift($snapinreplog);
            $pinghostlog = preg_grep('#(pinghosts.log$)#i',$fogfiles);
            $pinghostlog = $ftpstart.@array_shift($pinghostlog);
            $svcmasterlog = preg_grep('#(servicemaster.log$)#i',$fogfiles);
            $svcmasterlog = $ftpstart.@array_shift($svcmasterlog);
            $files[$StorageNode->get('name')] = array(
                $svcmasterlog ? _('Service Master') : null => $svcmasterlog ? $svcmasterlog : null,
                $multicastlog ? _('Multicast') : null => $multicastlog ? $multicastlog : null,
                $schedulerlog ? _('Scheduler') : null => $schedulerlog ? $schedulerlog : null,
                $imgrepliclog ? _('Image Replicator') : null => $imgrepliclog ? $imgrepliclog : null,
                $snapinreplog ? _('Snapin Replicator') : null => $snapinreplog ? $snapinreplog : null,
                $pinghostlog ? _('Ping Hosts') : null => $pinghostlog ? $pinghostlog : null,
                $apacheerrlog ? _('Apache Error Log') : null  => $apacheerrlog ? $apacheerrlog : null,
                $apacheacclog ? _('Apache Access Log') : null  => $apacheacclog ? $apacheacclog : null,
            );
            $files[$StorageNode->get('name')] = array_filter((array)$files[$StorageNode->get('name')]);
            $this->HookManager->processEvent('LOG_VIEWER_HOOK_'.$StorageGroup->get('name'),array('files'=>&$files,'ftpstart'=>&$ftpstarter));
            unset($StorageGroup);
        }
        unset($StorageGroups);
        foreach ((array)$files AS $nodename => &$filearray) {
            $first = true;
            foreach((array)$filearray AS $value => &$file) {
                if ($first) {
                    $options3[] = '<option disabled="disabled"> ------- '.$nodename.' ------- </option>';
                    $first = false;
                }
                $options3[] = '<option '.($value == $_REQUEST['logtype'] ? 'selected="selected"' : '').' value="'.$file.'">'.$value.'</option>';
                unset($file);
            }
            unset($filearray);
        }
        unset($files);
        $this->title = _('FOG Log Viewer');
        echo '<p><form method="post" action="'.$this->formAction.'"><p>'._('File:');
        echo '<select name="logtype" id="logToView">'.implode((array)$options3).'</select>'._('Number of lines:');
        $vals = array(20,50,100,200,400,500,1000);
        foreach ((array)$vals AS $i => &$value) {
            $options4[] = '<option '.($value == $_REQUEST['n'] ? 'selected="selected"' : '').' value="'.$value.'">'.$value.'</option>';
            unset($value);
        }
        unset($vals);
        echo '<select name="n" id="linesToView">'.implode((array)$options4).'</select><center><input type="button" id="logpause" /></center></p></form><div id="logsGoHere">&nbsp;</div></p>';
    }
    public function config() {
        $this->HookManager->processEvent('IMPORT');
        $this->title='Configuration Import/Export';
        $report = $this->getClass('ReportMaker');
        $_SESSION['foglastreport']=serialize($report);
        unset($this->data,$this->headerData);
        $this->attributes = array(
            array(),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->data[0] = array(
            'field' => _('Click the button to export the database.'),
            'input' => '<input type="submit" name="export" value="'._('Export').'" />',
        );
        echo '<form method="post" action="export.php?type=sql">';
        $this->render();
        unset($this->data);
        echo '</form>';
        $this->data[] = array(
            'field' => _('Import a previous backup file.'),
            'input' => '<span class="lightColor">Max Size: ${size}</span><input type="file" name="dbFile" />',
            'size' => ini_get('post_max_size'),
        );
        $this->data[] = array(
            'field' => null,
            'input' => '<input type="submit" value="'._('Import').'" />',
        );
        echo '<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
        $this->render();
        unset($this->data);
        echo "</form>";
    }
    public function config_post() {
        $this->HookManager->processEvent('IMPORT_POST');
        $Schema = $this->getClass('Schema');
        try {
            if (!$_FILES['dbFile']) throw new Exception(_('No files uploaded'));
            $original = $Schema->export_db();
            $result = $this->getClass('Schema')->import_db($_FILES['dbFile']['tmp_name']);
            if ($result === true) echo '<h2>'._('Database Imported and added successfully').'</h2>';
            else {
                echo '<h2>'._('Errors detected on import').'</h2>';
                $origres = $result;
                $result = $Schema->import_db($original);
                unset($original);
                if ($result === true) echo '<h2>'._('Database changes reverted').'</h2>';
                else _('Errors on revert detected')."<br/><br/><code><pre>$result</pre></code>";
                echo '<h2>'._('There were errors during import')."</h2><code><pre>$origres</pre></code>";
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
