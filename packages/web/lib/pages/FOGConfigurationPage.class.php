<?php
class FOGConfigurationPage extends FOGPage {
    public $node = 'about';
    public function __construct($name = '') {
        $this->name = 'FOG Configuration';
        parent::__construct($this->name);
        $this->menu = array(
            license=>$this->foglang[License],
            'kernel-update'=>$this->foglang[KernelUpdate],
            pxemenu=>$this->foglang[PXEBootMenu],
            'customize-edit'=>$this->foglang[PXEConfiguration],
            'new-menu'=>$this->foglang[NewMenu],
            'client-updater'=>$this->foglang[ClientUpdater],
            'mac-list'=>$this->foglang[MACAddrList],
            settings=>$this->foglang[FOGSettings],
            'log'=>$this->foglang[LogViewer],
            config=>$this->foglang[ConfigSave],
            'http://www.sf.net/projects/freeghost'=>$this->foglang[FOGSFPage],
            'https://fogproject.org'=>$this->foglang[FOGWebPage],
        );
        $this->HookManager->processEvent(SUB_MENULINK_DATA,array(menu=>&$this->menu,submenu=>&$this->subMenu,id=>&$this->id,notes=>&$this->notes));
    }
    /** index()
     * Displays the configuration page.  Right now it redirects to display
     * whether the user is on the current version.
     */
    public function index() {$this->version();}
        /** version() Pulls the current version from the internet. */
        public function version() {
            $URLs = array();
            $Names = array();
            // Set title
            $this->title = _('FOG Version Information');
            print '<p>'._('Version: ').FOG_VERSION.'</p>';
            $URLs[] = 'https://fogproject.org/version/index.php?version='.FOG_VERSION;
            $Names[] = '';
            $Nodes = $this->getClass(StorageNodeManager)->find(array(isEnabled=>1));
            foreach($Nodes AS $i => &$StorageNode) {
                $curroot = trim(trim($StorageNode->get(webroot),'/'));
                $webroot = '/'.(strlen($curroot) > 1 ? $curroot.'/' : '');
                $Names[] = $StorageNode->get(name);
                $URLs[] = "http://{$StorageNode->get(ip)}{$webroot}status/kernelvers.php";
            }
            unset($StorageNode);
            $Responses = $this->FOGURLRequests->process($URLs,'GET');
            asort($Responses);
            foreach($Responses AS $i => &$data) {
                if ($i === 0) {
                    print '<p><div class="sub">'.$Responses[$i].'</div></p>';
                    print '<h1>Kernel Versions</h1>';
                } else {
                    print "<h2>{$Names[$i]}</h2>";
                    print "<pre>$data</pre>";
                }
            }
            unset($data);
        }
    /** license()
     * Displays the GNU License to the user.  Currently Version 3.
     */
    public function license() {
        // Set title
        $this->title = _('FOG License Information');
        if (file_exists('./languages/'.$_SESSION[locale].'/gpl-3.0.txt')) print "<pre>".file_get_contents('./languages/'.$_SESSION[locale].'/gpl-3.0.txt').'</pre>';
        else print "<pre>".file_get_contents('./other/gpl-3.0.txt').'</pre>';
    }
    /** kernel()
     * Redirects as the sub information is currently incorrect.
     * This is because the class files go to post, but it only
     * tries to kernel_post.  The sub is kernel_update though.
     */
    public function kernel() {$this->kernel_update_post();}
        /** kernel_update()
         * Display's the published kernels for update.
         * This information is obtained from the internet.
         * Displays the default of Published kernels.
         */
        public function kernel_update() {
            $this->kernelselForm(pk);
            $htmlData = $this->FOGURLRequests->process('https://fogproject.org/kernels/kernelupdate.php?version='.FOG_VERSION,'GET');
            print $htmlData[0];
        }
    /** kernelselForm($type)
     * Gives the user the option to select between:
     * Published Kernels (from sourceforge)
     * Unofficial Kernels (from mastacontrola.com)
     */
    public function kernelselForm($type) {
        print '<div class="hostgroup">';
        print _('This section allows you to update the Linux kernel which is used to boot the client computers.  In FOG, this kernel holds all the drivers for the client computer, so if you are unable to boot a client you may wish to update to a newer kernel which may have more drivers built in.  This installation process may take a few minutes, as FOG will attempt to go out to the internet to get the requested Kernel, so if it seems like the process is hanging please be patient.');
        print '</div><div><form method="post" action="'.$this->formAction.'"><select name="kernelsel" onchange="this.form.submit()">';
        print '<option value="pk"'.($type == 'pk' ? ' selected="selected"' : '').'>'._('Published Kernels').'</option>';
        print '<option value="ok"'.($type == 'ok' ? ' selected="selected"' : '').'>'._('Old Published Kernels').'</option>';
        print '<option value="uk"'.($type == 'uk' ? ' selected="selected"' : '').'>'._('Unofficial Kernels').'</option></select></form></div>';
    }
    /** kernel_update_post()
     * Displays the kernel based on the list selected.
     * Defaults to published kernels.
     */
    public function kernel_update_post() {
        if ($_REQUEST[sub] == 'kernel-update') {
            switch ($_REQUEST[kernelsel]) {
            case 'pk':
                $this->kernelselForm(pk);
                $htmlData = $this->FOGURLRequests->process("https://fogproject.org/kernels/kernelupdate.php?version=" . FOG_VERSION,'GET');
                print $htmlData[0];
                break;
            case 'uk':
                $this->kernelselForm(uk);
                $htmlData = $this->FOGURLRequests->process("http://mastacontrola.com/fogboot/kernel/index.php?version=" . FOG_VERSION,'GET');
                print $htmlData[0];
                break;
            case 'ok':
                $this->kernelselForm(ok);
                $htmlData = $this->FOGURLRequests->process("http://freeghost.sourceforge.net/kernelupdates/index.php?version=".FOG_VERSION,'GET');
                print $htmlData[0];
                break;
            default:
                $this->kernelselForm(pk);
                $htmlData = $this->FOGURRequests->process('https://fogproject.org/kernels/kernelupdate.php?version='.FOG_VERSION,'GET');
                print $htmlData[0];
                break;
            }
        } else if ($_REQUEST[install]) {
            $_SESSION[allow_ajax_kdl] = true;
            $_SESSION["dest-kernel-file"] = trim($_REQUEST[dstName]);
            $_SESSION["tmp-kernel-file"] = rtrim(sys_get_temp_dir(),'/').'/'.basename($_SESSION["dest-kernel-file"]);
            $_SESSION["dl-kernel-file"] = base64_decode($_REQUEST["file"]);
            if (file_exists($_SESSION["tmp-kernel-file"])) @unlink($_SESSION["tmp-kernel-file"]);
            print '<div id="kdlRes"><p id="currentdlstate">'._('Starting process...').'</p><i id="img" class="fa fa-cog fa-2x fa-spin"></i></div>';
        } else {
            print '<form method="post" action="?node='.$_REQUEST[node].'&sub=kernel&install=1&file='.$_REQUEST['file'].'">';
            print "<p>"._('New Kernel name:').'<input class="smaller" type="text" name="dstName" value="'.($_REQUEST[arch] == 64 || !$_REQUEST[arch] ? 'bzImage' : 'bzImage32').'" /></p>';
            print '<p><input class="smaller" type="submit" value="Next" /></p></form>';
        }
    }
    /** pxemenu()
     * Displays the pxe/ipxe menu selections.
     * Hidden menu requires user login from FOG GUI login.
     * Also, hidden menu enforces a key press to access the menu.
     * If none is selected, defaults to esc key.  Otherwise you
     * need to use the key combination chosen.
     * Also used to setup the default timeout.  This time out is
     * the timeout it uses to boot to the system.  If hidden menu
     * is selected it sets both the hidden menu timeout and the menu,
     * if none is selected, and the menu items.
     */
    public function pxemenu() {
        // Set title
        $this->title = _('FOG PXE Boot Menu Configuration');
        // Headerdata
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $noMenu = $this->FOGCore->getSetting(FOG_NO_MENU) ? 'checked' : '';
        $hidChecked = ($this->FOGCore->getSetting(FOG_PXE_MENU_HIDDEN) ? 'checked' : '');
        $hideTimeout = $this->FOGCore->getSetting(FOG_PXE_HIDDENMENU_TIMEOUT);
        $timeout = $this->FOGCore->getSetting(FOG_PXE_MENU_TIMEOUT);
        $bootKeys = $this->getClass(KeySequenceManager)->buildSelectBox($this->FOGCore->getSetting(FOG_KEY_SEQUENCE));
        $advLogin = ($this->FOGCore->getSetting(FOG_ADVANCED_MENU_LOGIN) ? 'checked' : '');
        $advanced = $this->FOGCore->getSetting(FOG_PXE_ADVANCED);
        $fields = array(
            _('No Menu') => '<input type="checkbox" name="nomenu" value="1" '.$noMenu.'/><i class="icon fa fa-question hand" title="Option sets if there will even be the presence of a menu to the client systems.  If there is not a task set, it boots to the first device, if there is a task, it performs that task."></i>',
            _('Hide Menu') => '<input type="checkbox" name="hidemenu" value="1" '.$hidChecked.'/><i class="icon fa fa-question hand" title="Option below sets the key sequence.  If none is specified, ESC is defaulted. Login with the FOG credentials and you will see the menu.  Otherwise it will just boot like normal."></i>',
            _('Hide Menu Timeout') => '<input type="text" name="hidetimeout" value="'.$hideTimeout.'" /><i class="icon fa fa-question hand" title="Option specifies the timeout value for the hidden menu system."></i>',
            _('Advanced Menu Login') => '<input type="checkbox" name="advmenulogin" value="1" '.$advLogin.'/><i class="icon fa fa-question hand" title="Option below enforces a Login system for the Advanced menu parameters.  If off no login will appear, if on, it will only allow login to the advanced system.."></i>',
            _('Boot Key Sequence') => $bootKeys,
            _('Menu Timeout (in seconds)').':*' => '<input type="text" name="timeout" value="'.$timeout.'" id="timeout" />',
            _('Exit to Hard Drive Type') => '<select name="bootTypeExit"><option value="sanboot" '.($this->FOGCore->getSetting(FOG_BOOT_EXIT_TYPE) == 'sanboot' ? 'selected="selected"' : '').'>Sanboot style</option><option value="exit" '.($this->FOGCore->getSetting(FOG_BOOT_EXIT_TYPE) == 'exit' ? 'selected="selected"' : '').'>Exit style</option><option value="grub" '.($this->FOGCore->getSetting(FOG_BOOT_EXIT_TYPE) == 'grub' ? 'selected="selected"' : '').'>Grub style</option></select>',
            _('Exit to Hard Drive Type(EFI)') => '<select name="efiBootTypeExit"><option value="sanboot" '.($this->FOGCore->getSetting(FOG_EFI_BOOT_EXIT_TYPE) == 'sanboot' ? 'selected="selected"' : '').'>Sanboot style</option><option value="exit" '.($this->FOGCore->getSetting(FOG_EFI_BOOT_EXIT_TYPE) == 'exit' ? 'selected="selected"' : '').'>Exit style</option><option value="grub" '.($this->FOGCore->getSetting(FOG_EFI_BOOT_EXIT_TYPE) == 'grub' ? 'selected="selected"' : '').'>Grub style</option></select>',
            '<a href="#" onload="$(\'#advancedTextArea\').hide();" onclick="$(\'#advancedTextArea\').toggle();" id="pxeAdvancedLink">Advanced Configuration Options</a>' => '<div id="advancedTextArea" class="hidden"><div class="lighterText tabbed">Add any custom text you would like included added as part of your <i>default</i> file.</div><textarea rows="5" cols="40" name="adv">'.$advanced.'</textarea></div>',
            '&nbsp;' => '<input type="submit" value="'._('Save PXE MENU').'" />',
        );
        print '<form method="post" action="'.$this->formAction.'">';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent(PXE_BOOT_MENU,array(data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        print '</form>';
    }
    /** pxemenu_post()
     * Performs the updates for the form sent from pxemenu().
     */
    public function pxemenu_post() {
        try {
            $timeout = trim($_REQUEST[timeout]);
            $timeout = (is_numeric($timeout) || intval($timeout) >= 0 ? true : false);
            if (!$timeout) throw new Exception(_('Invalid Timeout Value'));
            else $timeout = trim($_REQUEST[timeout]);
            $hidetimeout = trim($_REQUEST[hidetimeout]);
            $hidetimeout = (is_numeric($hidetimeout) || intval($hidetimeout) >= 0 ? true : false);
            if (!$hidetimeout) throw new Exception(_('Invalid Timeout Value'));
            else $hidetimeout = trim($_REQUEST[hidetimeout]);
            if (!$this->FOGCore
                ->setSetting(FOG_PXE_MENU_HIDDEN,$_REQUEST[hidemenu])
                ->setSetting(FOG_PXE_MENU_TIMEOUT,$timeout)
                ->setSetting(FOG_PXE_ADVANCED,$_REQUEST[adv])
                ->setSetting(FOG_KEY_SEQUENCE,$_REQUEST[keysequence])
                ->setSetting(FOG_NO_MENU,$_REQUEST[nomenu])
                ->setSetting(FOG_BOOT_EXIT_TYPE,$_REQUEST[bootTypeExit])
                ->setSetting(FOG_EFI_BOOT_EXIT_TYPE,$_REQUEST[efiBootTypeExit])
                ->setSetting(FOG_ADVANCED_MENU_LOGIN,$_REQUEST[advmenulogin])
                ->setSetting(FOG_PXE_HIDDENMENU_TIMEOUT,$hidetimeout)) throw new Exception(_('PXE Menu update failed'));
            throw new Exception(_('PXE Menu has been updated'));
        } catch (Exception $e) {
            $this->FOGCore->setMessage($e->getMessage());
            $this->FOGCore->redirect($this->formAction);
        }
    }
    public function customize_edit() {
        $this->title = $this->foglang[PXEMenuCustomization];
        print '<p>'._('This item allows you to edit all of the PXE Menu items as you see fit.  Mind you, iPXE syntax is very finicky when it comes to edits.  If you need help understanding what items are needed, please see the forums.  You can also look at ipxe.org for syntactic usage and methods.  Some of the items here are bound to limitations.  Documentation will follow when enough time is provided.').'</p>';
        print '<div id="tab-container-1">';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $Menus = $this->getClass(PXEMenuOptionsManager)->find('','','id');
        foreach ($Menus AS $i => &$Menu) {
            $divTab = preg_replace('/[[:space:]]/','_',preg_replace('/\./','_',preg_replace('/:/','_',$Menu->get('name'))));
            print '<a id="'.$divTab.'" style="text-decoration:none;" href="#'.$divTab.'"><h3>'.$Menu->get(name).'</h3></a>';
            print '<div id="'.$divTab.'">';
            print '<form method="post" action="'.$this->formAction.'">';
            $menuid = in_array($Menu->get(id),array(1,2,3,4,5,6,7,8,9,10,11,12,13));
            $menuDefault = $Menu->get('default') ? 'checked' : '';
            $fields = array(
                _('Menu Item:') => '<input type="text" name="menu_item" value="'.$Menu->get(name).'" id="menu_item"/>',
                _('Description:') => '<textarea cols="40" rows="2" name="menu_description">'.$Menu->get(description).'</textarea>',
                _('Parameters:') => '<textarea cols="40" rows="8" name="menu_params">'.$Menu->get(params).'</textarea>',
                _('Boot Options:') => '<input type="text" name="menu_options" id="menu_options" value="'.$Menu->get(args).'" />',
                _('Default Item:') => '<input type="checkbox" name="menu_default" value="1" '.$menuDefault.'/>',
                _('Menu Show with:') => $this->getClass(PXEMenuOptionsManager)->regSelect($Menu->get(regMenu)),
                '<input type="hidden" name="menu_id" value="'.$Menu->get(id).'" />' => '<input type="submit" name="saveform" value="'.$this->foglang[Submit].'" />',
                !$menuid ? '<input type="hidden" name="rmid" value="'.$Menu->get(id).'" />' : '' => !$menuid ? '<input type="submit" name="delform" value="'.$this->foglang['Delete'].' '.$Menu->get(name).'" />' : '',
            );
            foreach($fields AS $field => &$input) {
                $this->data[] = array(
                    field=>$field,
                    input=>$input,
                );
            }
            unset($input);
            // Hook
            $this->HookManager->processEvent('BOOT_ITEMS_'.$divTab,array(data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes,headerData=>&$this->headerData));
            // Output
            $this->render();
            print "</form>";
            print '</div>';
            // Reset for use again.
            unset($this->data);
        }
        unset($Menu);
        print '</div>';
    }
    public function customize_edit_post() {
        if (isset($_REQUEST[saveform]) && $_REQUEST[menu_id]) {
            $this->getClass(PXEMenuOptionsManager)->update('','',array('default'=>0));
            $Menu = $this->getClass(PXEMenuOptions,$_REQUEST[menu_id])
                ->set(name,$_REQUEST[menu_item])
                ->set(description,$_REQUEST[menu_description])
                ->set(params,$_REQUEST[menu_params])
                ->set(regMenu,$_REQUEST[menu_regmenu])
                ->set(args,$_REQUEST[menu_options])
                ->set('default',$_REQUEST[menu_default]);
            if ($Menu->save()) $this->FOGCore->setMessage($Menu->get(name).' '._('successfully updated').'!');
        }
        if (isset($_REQUEST[delform]) && $_REQUEST[rmid]) {
            $Menu = $this->getClass(PXEMenuOptions,$_REQUEST[rmid]);
            $menuname = $Menu->get(name);
            if($Menu->destroy()) $this->FOGCore->setMessage($menuname.' '._('successfully removed').'!');
        }
        // Ensure there's only one default value.
        $countDefault = $this->getClass(PXEMenuOptionsManager)->count(array('default'=>1));
        // If there's no defaults, set the first id (local disk) to default.
        if ($countDefault == 0 || $countDefault > 1) $this->getClass(PXEMenuOptions,1)->set('default',1)->save();
        $this->FOGCore->redirect($this->formAction);
    }
    public function new_menu() {
        $this->title = _('Create New iPXE Menu Entry');
        $this->templates = array(
            '${field}',
            '${input}',
        );
        print '<form method="post" action="'.$this->formAction.'">';
        $menudefault = $_REQUEST[menu_default] ? 'checked' : '';
        $fields = array(
            _('Menu Item:') => '<input type="text" name="menu_item" value="'.$_REQUEST[menu_item].'" id="menu_item" />',
            _('Description:') => '<textarea cols="40" rows="2" name="menu_description">'.$_REQUEST[menu_description].'</textarea>',
            _('Parameters:') => '<textarea cols="40" rows="8" name="menu_params">'.$_REQUEST[menu_params].'</textarea>',
            _('Boot Options:') => '<input type="text" name="menu_options" id="menu_options" value="'.$_REQUEST[menu_options].'" />',
            _('Default Item:') => '<input type="checkbox" name="menu_default" value="1" '.$menudefault.'/>',
            _('Menu Show with:') => $this->getClass(PXEMenuOptionsManager)->regSelect($_REQUEST[menu_regmenu]),
            '&nbsp;' => '<input type="submit" value="'._($this->foglang[Add].' New Menu').'" />',
        );
        foreach($fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent(BOOT_ITEMS_ADD,array(data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes,headerData=>&$this->headerData));
        // Output
        $this->render();
        print "</form>";
    }
    public function new_menu_post() {
        try {
            // Error checking
            // At the least, you should have an item and a description.
            if (!$_REQUEST[menu_item]) throw new Exception(_('Menu Item or title cannot be blank'));
            if (!$_REQUEST[menu_description]) throw new Exception(_('A description needs to be set'));
            // Set all other menus that are default to non-default value.
            if ($_REQUEST[menu_default]) $this->getClass(PXEMenuOptionsManager)->update('','',array('default'=>0));
            $Menu = $this->getClass(PXEMenuOptions)
                ->set(name,$_REQUEST[menu_item])
                ->set(description,$_REQUEST[menu_description])
                ->set(params,$_REQUEST[menu_params])
                ->set(regMenu,$_REQUEST[menu_regmenu])
                ->set(args,$_REQUEST[menu_options])
                ->set('default',$_REQUEST[menu_default]);
            if (!$Menu->save()) throw new Exception(_('Menu create failed'));
            // Ensure there's only one default value.
            $countDefault = $this->getClass(PXEMenuOptionsManager)->count(array('default'=>1));
            // If there's no defaults, set the first id (local disk) to default.
            if ($countDefault == 0 || $countDefault > 1) $this->getClass(PXEMenuOptions,1)->set('default',1)->save();
            // Hook
            $this->HookManager->processEvent(MENU_ADD_SUCCESS,array(Menu=>&$Menu));
            // Log History Event
            $this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s',_('Menu added'),$Menu->get(id),$Menu->get(name)));
            // Set session message
            $this->FOGCore->setMessage(_('Menu Added'));
            // Redirect to edit entry
            $this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s',$this->node,$this->id,$Menu->get(id)));
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent(MENU_ADD_FAIL,array(Menu=>&$Menu));
            // Log History Event
            $this->FOGCore->logHistory(sprintf('%s: %s: %s, %s: %s',_('Menu add failed'),_('Name'),$_REQUEST[name],_('Error'),$e->getMessage()));
            // Set session message
            $this->FOGCore->setMessage($e->getMessage());
            // Redirect to original entry
            $this->FOGCore->redirect($this->formAction);
        }
    }
    /** client_updater()
     * You update the client files through here.
     * This is used for the Host systems with FOG Service installed.
     * Here is where you can update the files an push these files to
     * the client.
     */
    public function client_updater() {
        // Set title
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
            array(),
        );
        print '<div class="hostgroup">'._('This section allows you to update the modules and config files that run on the client computers.  The clients will checkin with the server from time to time to see if a new module is published.  If a new module is published the client will download the module and use it on the next time the service is started.').'</div>';
        $ClientUpdates = $this->getClass(ClientUpdaterManager)->find('','name');
        foreach ($ClientUpdates AS $i => &$ClientUpdate) {
            $this->data[] = array(
                name=>$ClientUpdate->get(name),
                module=>$ClientUpdate->get('md5'),
                type=>$ClientUpdate->get(type),
                client_id=>$ClientUpdate->get(id),
                id=>$ClientUpdate->get(id),
            );
        }
        unset($ClientUpdate);
        // Hook
        $this->HookManager->processEvent(CLIENT_UPDATE,array(data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        print '<form method="post" action="'.$this->formAction.'&tab=clientupdater">';
        $this->render();
        print '</form>';
        // reset for next element
        unset($this->headerData,$this->attributes,$this->templates,$this->data);
        $this->headerData = array(
            _('Upload a new client module/configuration file'),
            '',
        );
        $this->attributes = array(
            array(),
            array(),
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
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent(CLIENT_UPDATE,array(data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        print '<form method="post" action="'.$this->formAction.'&tab=clientupdater" enctype="multipart/form-data"><input type="hidden" name="name" value="FOG_SERVICE_CLIENTUPDATER_ENABLED"/>';
        // Output
        $this->render();
        print '</form>';
    }
    /** client_updater_post()
     * Just updates the values set in client_updater().
     */
    public function client_updater_post() {
        if ($_REQUEST[delcu]) {
            $this->getClass(ClientUpdaterManager)->destroy(array(id=>$_REQUEST[delcu]));
            $this->FOGCore->setMessage(_('Client module update deleted!'));
        }
        if ($_FILES['module']) {
            foreach((array)$_FILES['module']['tmp_name'] AS $index => &$tmp_name) {
                if (file_exists($_FILES['module']['tmp_name'][$index])) {
                    $ClientUpdater = current($this->getClass(ClientUpdaterManager)->find(array(name=>$_FILES[module][name][$index])));
                    if(file_get_contents($_FILES[module][tmp_name][$index])) {
                        if ($ClientUpdater) {
                            $ClientUpdater->set(name,basename($_FILES[module][name][$index]))
                                ->set('md5',md5(file_get_contents($_FILES[module][tmp_name][$index])))
                                ->set(type,($this->FOGCore->endsWith($_FILES[module][name][$index],'.ini')?'txt':'bin'))
                                ->set('file',file_get_contents($_FILES[module][tmp_name][$index]));
                        } else {
                            $ClientUpdater = $this->getClass(ClientUpdater)
                                ->set(name,basename($_FILES[module][name][$index]))
                                ->set('md5',md5(file_get_contents($_FILES[module][tmp_name][$index])))
                                ->set(type,$this->FOGCore->endsWith($_FILES[module][name][$index],'.ini')?'txt':'bin')
                                ->set('file',file_get_contents($_FILES[module][name][$index]));
                        }
                        if ($ClientUpdater->save()) $this->FOGCore->setMessage(_('Modules Added/Updated').'!');
                    }
                }
            }
            unset($tmp_name);
        }
        $this->FOGCore->redirect(sprintf('?node=%s&sub=%s#%s',$_REQUEST[node],edit,$_REQUEST[tab]));
    }
    /** mac_list()
     * This is where you update the mac address listing.
     * If you choose to update, it downloads the latest oui.txt file
     * from http://standards.ieee.org/regauth/oui/oui.txt.
     * Then it updates the database with these values.
     */
    public function mac_list() {
        // Set title
        $this->title = _('MAC Address Manufacturer Listing');
        // Allow the updating and deleting of the mac-lists.
        print '<div class="hostgroup">'._('This section allows you to import known mac address makers into the FOG database for easier identification.').'</div><div><p>'._('Current Records: ').$this->FOGCore->getMACLookupCount().'</p><p><div id="delete"></div><div id="update"></div><input class="macButtons" type="button" title="'._('Delete MACs').'" value="'._('Delete Current Records').'" onclick="clearMacs()" /><input class="macButtons" style="margin-left: 20px" type="button" title="'._('Update MACs').'" value="'._('Update Current Listing').'" onclick="updateMacs()" /></p><p>'._('MAC address listing source: ').'<a href="http://standards.ieee.org/regauth/oui/oui.txt">http://standards.ieee.org/regauth/oui/oui.txt</a></p></div>';
    }
    /** mac_list_post()
     * This just performs the actions when mac_list() is updated.
     */
    public function mac_list_post() {
        if ($_REQUEST[update]) {
            $f = "/tmp/oui.txt";
            exec("rm -rf $f");
            exec("wget -O $f  http://standards.ieee.org/develop/regauth/oui/oui.txt");
            if (file_exists($f)) {
                $handle = fopen($f, "r");
                $start = 18;
                $imported = 0;
                while (!feof($handle)) {
                    $line = trim(fgets($handle));
                    if (preg_match("#^([0-9a-fA-F][0-9a-fA-F][:-]){2}([0-9a-fA-F][0-9a-fA-F]).*$#", $line)) {
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
                $this->FOGCore->setMessage($imported._(' mac addresses updated!'));
            } else print (_('Unable to locate file').': '.$f);
        } else if ($_REQUEST[clear]) $this->FOGCore->clearMACLookupTable();
        $this->resetRequest();
        $this->FOGCore->redirect('?node=about&sub=mac-list');
    }
    /** settings()
     * This is where you set the values for FOG itself.  You can update
     * both the default service information and global information beyond
     * services.  The default kernel, the fog user information, etc...
     * Major things of note is that the system is now more user friendly.
     * Meaning, off/on values are checkboxes, items that are more specific
     * (e.g. image setting, default view,) are now select boxes.  This should
     * help limit typos in the old text based system.
     * Passwords are blocked with the password form field.
     */
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
            'FOG_AES_ENCRYPT',
            'FOG_EMAIL_ACTION',
            'FOG_FTP_IMAGE_SIZE',
            'FOG_KERNEL_DEBUG',
        );
        // Set title
        $this->title = _("FOG System Settings");
        print '<p class="hostgroup">'._('This section allows you to customize or alter the way in which FOG operates.  Please be very careful changing any of the following settings, as they can cause issues that are difficult to troubleshoot.').'</p><form method="post" action="'.$this->formAction.'"><div id="tab-container-1">';
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(width=>270,height=>35),
            array(),
            array('class'=>r),
        );
        // Templates
        $this->templates = array(
            '${service_name}',
            '${input_type}',
            '${span}',
        );
        $ServiceCats = $this->getClass(ServiceManager)->getSettingCats();
        print '<a href="#" class="trigger_expand"><h3>Expand All</h3></a>';
        foreach ((array)$ServiceCats AS $i => &$ServiceCAT) {
            $divTab = preg_replace('/[[:space:]]/','_',preg_replace('/:/','_',$ServiceCAT));
            print '<a id="'.$divTab.'" class="expand_trigger" style="text-decoration:none;" href="#'.$divTab.'"><h3>'.$ServiceCAT.'</h3></a>';
            print '<div id="'.$divTab.'">';
            $ServMan = $this->getClass(ServiceManager)->find(array(category=>$ServiceCAT),'AND','id');
            foreach ((array)$ServMan AS $i => &$Service) {
                if ($Service->get(name) == 'FOG_PIGZ_COMP') $type = '<div id="pigz" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showVal" maxsize="1" style="width: 10px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
                else if ($Service->get(name) == 'FOG_KERNEL_LOGLEVEL') $type = '<div id="loglvl" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showlogVal" maxsize="1" style="width: 10px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
                else if ($Service->get(name) == 'FOG_INACTIVITY_TIMEOUT') $type = '<div id="inact" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showValInAct" maxsize="2" style="width: 15px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
                else if ($Service->get(name) == 'FOG_REGENERATE_TIMEOUT') $type = '<div id="regen" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showValRegen" maxsize="5" style="width: 25px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
                else if (preg_match('#(pass|PASS)#i',$Service->get(name)) && !preg_match('#(VALID|MIN)#i',$Service->get('name'))) {
                    if ($Service->get('name') == 'FOG_AES_ADPASS_ENCRYPT_KEY') {
                        $type = '<input id="'.$Service->get(name).'_text" type="password" name="${service_id}" value="${service_value}" autocomplete="off" readonly="true" maxlength="50" />';
                        $type .= '<br/><small><input type="button" value="Randomize Above Key" id="'.$Service->get(name).'_button" title="You will have to recompile the client if you change this key.'.($Service->get(name) == 'FOG_AES_ADPASS_ENCRYPT_KEY' ? ' You will also o need to reset the password for all hosts and the FOG_AD_DEFAULT_PASSWORD field.' : '').'" /></small>';
                    } else $type = '<input type="password" name="${service_id}" value="${service_value}" autocomplete="off" />';
                } else if ($Service->get(name) == 'FOG_VIEW_DEFAULT_SCREEN') {
                    $screens = array('SEARCH','LIST');
                    foreach ($screens AS $i => &$viewop) $options[] = '<option value="'.strtolower($viewop).'" '.($Service->get(value) == strtolower($viewop) ? 'selected="selected"' : '').'>'.$viewop.'</option>';
                    unset($viewop);
                    $type = '<select name="${service_id}" style="width: 220px" autocomplete="off">'.implode($options).'</select>';
                    unset($options);
                } else if ($Service->get(name) == 'FOG_MULTICAST_DUPLEX') {
                    $duplexTypes = array(
                        'HALF_DUPLEX' => '--half-duplex',
                        'FULL_DUPLEX' => '--full-duplex',
                    );
                    foreach($duplexTypes AS $types => &$val) $options[] = '<option value="'.$val.'" '.($Service->get(value) == $val ? 'selected="selected"' : '').'>'.$types.'</option>';
                    unset($val);
                    $type = '<select name="${service_id}" style="width: 220px" autocomplete="off">'.implode($options).'</select>';
                } else if ($Service->get(name) == 'FOG_BOOT_EXIT_TYPE') {
                    $types = array('sanboot','grub','exit');
                    foreach($types AS $i => &$viewop) $options[] = '<option value="'.$viewop.'" '.($Service->get(value) == $viewop ? 'selected="selected"' : '').'>'.strtoupper($viewop).'</option>';
                    unset($viewop);
                    $type = '<select name="${service_id}" style="width: 220px" autocomplete="off">'.implode($options).'</select>';
                    unset($options);
                } else if ($Service->get(name) == 'FOG_EFI_BOOT_EXIT_TYPE') {
                    $types = array('sanboot','grub','exit');
                    foreach($types AS $i => &$viewop) $options[] = '<option value="'.$viewop.'" '.($Service->get(value) == $viewop ? 'selected="selected"' : '').'>'.strtoupper($viewop).'</option>';
                    unset($viewop);
                    $type = '<select name="${service_id}" style="width: 220px" autocomplete="off">'.implode($options).'</select>';
                    unset($options);
                } else if ($Service->get(name) == 'FOG_DHCP_BOOTFILENAME') {
                    $type = null;
                } else if (in_array($Service->get(name),$ServiceNames)) $type = '<input type="checkbox" name="${service_id}" value="1" '.($Service->get(value) ? 'checked' : '').' />';
                else if ($Service->get(name) == 'FOG_DEFAULT_LOCALE') {
                    foreach((array)$this->foglang[Language] AS $lang => &$humanreadable) $options2[] = '<option value="'.$lang.'" '.($this->FOGCore->getSetting(FOG_DEFAULT_LOCALE) == $lang || $this->FOGCore->getSetting(FOG_DEFAULT_LOCALE) == $this->foglang[Language][$lang] ? 'selected="selected"' : '').'>'.$humanreadable.'</option>';
                    unset($humanreadable);
                    $type = '<select name="${service_id}" autocomplete="off" style="width: 220px">'.implode($options2).'</select>';
                } else if ($Service->get(name) == 'FOG_QUICKREG_IMG_ID') $type = $this->getClass(ImageManager)->buildSelectBox($this->FOGCore->getSetting(FOG_QUICKREG_IMG_ID),$Service->get(id).'" id="${service_name}');
                else if ($Service->get(name) == 'FOG_QUICKREG_GROUP_ASSOC') $type = $this->getClass(GroupManager)->buildSelectBox($this->FOGCore->getSetting(FOG_QUICKREG_GROUP_ASSOC),$Service->get(id));
                else if ($Service->get(name) == 'FOG_KEY_SEQUENCE') $type = $this->getClass(KeySequenceManager)->buildSelectBox($this->FOGCore->getSetting(FOG_KEY_SEQUENCE),$Service->get(id));
                else if ($Service->get(name) == 'FOG_QUICKREG_OS_ID') {
                    if ($this->FOGCore->getSetting(FOG_QUICKREG_IMG_ID) > 0) $Image = $this->getClass(Image,$this->FOGCore->getSetting(FOG_QUICKREG_IMG_ID));
                    $type = '<p id="${service_name}">'.($Image && $Image->isValid() ? $Image->getOS()->get(name) : _('No image specified')).'</p>';
                } else if ($Service->get(name) == 'FOG_TZ_INFO') {
                    $dt = $this->nice_date('now',$utc);
                    $tzIDs = DateTimeZone::listIdentifiers();
                    $type = '<select name="${service_id}">';
                    foreach($tzIDs AS $i => &$tz) {
                        $current_tz = new DateTimeZone($tz);
                        $offset = $current_tz->getOffset($dt);
                        $transition = $current_tz->getTransitions($dt->getTimestamp(),$dt->getTimestamp());
                        $abbr = $transition[0][abbr];
                        $offset = sprintf('%+03d:%02u', floor($offset / 3600), floor(abs($offset) % 3600 / 60));
                        $type .= '<option value="'.$tz.'"'.($Service->get(value) == $tz ? ' selected' : '').'>'.$tz.' ['.$abbr.' '.$offset.']</option>';
                    }
                    unset($current_tz,$offset,$transition,$tz);
                    $type .= '</select>';
                } else if ($Service->get(name) == 'FOG_AD_DEFAULT_OU') {
                    $type = '<textarea rows="5" name="${service_id}">${service_value}</textarea>';
                } else $type = '<input id="${service_name}" type="text" name="${service_id}" value="${service_value}" autocomplete="off" />';
                $this->data[] = array(
                    input_type=>(count(explode(chr(10),$Service->get(value))) <= 1 ? $type : '<textarea rows="5" name="${service_id}">${service_value}</textarea>'),
                    service_name=> $Service->get(name),
                    span=>'<i class="icon fa fa-question hand" title="${service_desc}"></i>',
                    service_id=>$Service->get(id),
                    id=>$Service->get(id),
                    service_value=>$Service->get(value),
                    service_desc=>$Service->get(description),
                );
            }
            unset($Service);
            $this->data[] = array(
                span=>'&nbsp;',
                service_name=>'<input type="hidden" value="1" name="update" />',
                input_type=>'<input type="submit" value="'._('Save Changes').'" />',
            );
            // Hook
            $this->HookManager->processEvent('CLIENT_UPDATE_'.$divTab,array(data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
            // Output
            $this->render();
            print '</div>';
            unset($this->data,$options);
        }
        unset($ServiceCAT);
        print '</div></form>';
    }
    public function getOSID() {
        $osname = $this->getClass(Image,$_REQUEST[image_id])->getOS()->get(name);
        print json_encode($osname ? $osname : _('No Image specified'));
    }
    /** settings_post()
     * Updates the settings set from the fields.
     */
    public function settings_post() {
        $ServiceMan = $this->getClass(ServiceManager)->find();
        foreach ((array)$ServiceMan AS $i => &$Service) {
            $key = $Service->get(id);
            $_REQUEST[$key] = trim($_REQUEST[$key]);
            if ($Service->get(name) == 'FOG_MEMORY_LIMIT' && ($_REQUEST[$key] < 128 || !is_numeric($_REQUEST[$key]))) $Service->set(value,128);
            else if ($Service->get(name) == 'FOG_QUICKREG_IMG_ID' && empty($_REQUEST[$key])) $Service->set(value,-1);
            else if ($Service->get(name) == 'FOG_USER_VALIDPASSCHARS') $Service->set(value,addslashes($_REQUEST[$key]));
            else if ($Service->get(name) == 'FOG_AD_DEFAULT_PASSWORD') $Service->set(value,$this->encryptpw($_REQUEST[$key]));
            else if ($Service->get(name) == 'FOG_MULTICAST_PORT_OVERRIDE') {
                if (is_numeric($_REQUEST[$key]) && $_REQUEST[$key] > 0) {
                    if ($_REQUEST[$key] < 65536)
                        $Service->set(value,$_REQUEST[$key]);
                } else $Service->set(value,0);
            } else if ($Service->get(name) == 'FOG_MULTICAST_ADDRESS') {
                if (filter_var($_REQUEST[$key], FILTER_VALIDATE_IP)) $Service->set(value,$_REQUEST[$key]);
                else $Service->set(value,0);
            } else if ($Service->get(name) == 'FOG_MAX_UPLOADSIZE') {
                $val = $_REQUEST[$key];
                if (!is_numeric($val) || $val < 2) $val = 2;
                $Service->set(value,$val);
            } else if ($Service->get(name) == 'FOG_POST_MAXSIZE') {
                $val = $_REQUEST[$key];
                if ($val < 8 || !is_numeric($val)) $val = 8;
                $Service->set(value,$val);
            } else if ($Service->get(name) == 'FOG_INACTIVITY_TIMEOUT') {
                $val = $_REQUEST[$key];
                if (!is_numeric($val) || $val <= 0 || $val > 24) $val = 1;
                $Service->set(value,$val);
            } else if ($Service->get(name) == 'FOG_REGENERATE_TIMEOUT') {
                $val = $_REQUEST[$key];
                if (!is_numeric($val)) $val = 0;
                $Service->set(value,$val);
            } else $Service->set(value,$_REQUEST[$key]);
            $Service->save();
        }
        unset($Service);
        $this->FOGCore->setMessage('Settings Successfully stored!');
        $this->FOGCore->redirect(sprintf('?node=%s&sub=%s',$_REQUEST[node],$_REQUEST[sub]));
    }
    /** log()
     * Views the log files for the FOG Services on the server (FOGImageReplicator, FOGTaskScheduler, FOGMulticastManager).
     * Just used to view these logs.  Can be used for more than this as well with some tweeking.
     */
    public function log() {
        $StorageGroups = $this->getClass(StorageGroupManager)->find();
        foreach($StorageGroups AS $i => &$StorageGroup) {
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if ($StorageNode->isValid()) {
                $user = $StorageNode->get(user);
                $pass = $StorageNode->get(pass);
                $host = $StorageNode->get(ip);
                $ftpstarter[$StorageNode->get(name)] = "ftp://$user:$pass@$host";
                $ftpstart = $ftpstarter[$StorageNode->get(name)];
                $apacheerrlog = (file_exists("$ftpstart/var/log/httpd/error_log") ? "$ftpstart/var/log/httpd/error_log" : (file_exists("$ftpstart/var/log/apache2/error.log") ? "$ftpstart/var/log/apache2/error.log" : false));
                $apacheacclog = (file_exists("$ftpstart/var/log/httpd/access_log") ? "$ftpstart/var/log/httpd/access_log" : (file_exists("$ftpstart/var/log/apache2/access.log") ? "$ftpstart/var/log/apache2/access.log" : false));
                $multicastlog = (file_exists("$ftpstart/var/log/fog/multicast.log") ? "$ftpstart/var/log/fog/multicast.log" : false);
                $schedulerlog = (file_exists("$ftpstart/var/log/fog/fogscheduler.log") ? "$ftpstart/var/log/fog/fogscheduler.log" : false);
                $imgrepliclog = (file_exists("$ftpstart/var/log/fog/fogreplicator.log") ? "$ftpstart/var/log/fog/fogreplicator.log" : false);
                $snapinreplog = (file_exists("$ftpstart/var/log/fog/fogsnapinrep.log") ? "$ftpstart/var/log/fog/fogsnapinrep.log" : false);
                $files[$StorageNode->get(name)] = array(
                    $multicastlog ? _('Multicast') : null => $multicastlog ? $multicastlog : null,
                    $schedulerlog ? _('Scheduler') : null => $schedulerlog ? $schedulerlog : null,
                    $imgrepliclog ? _('Image Replicator') : null => $imgrepliclog ? $imgrepliclog : null,
                    $snapinreplog ? _('Snapin Replicator') : null => $snapinreplog ? $snapinreplog : null,
                    $apacheerrlog ? _('Apache Error Log') : null  => $apacheerrlog ? $apacheerrlog : null,
                    $apacheacclog ? _('Apache Access Log') : null  => $apacheacclog ? $apacheacclog : null,
                );
                $files[$StorageNode->get(name)] = array_filter((array)$files[$StorageNode->get(name)]);
            }
        }
        unset($StorageGroup);
        $this->HookManager->processEvent(LOG_VIEWER_HOOK,array(files=>&$files,ftpstart=>&$ftpstarter));
        foreach((array)$files AS $nodename => &$filearray) {
            $first = true;
            foreach((array)$filearray AS $value => &$file) {
                if ($first) {
                    $options3[] = '<option disabled="disabled"> ------- '.$nodename.' ------- </option>';
                    $first = false;
                }
                $options3[] = '<option '.($value == $_REQUEST[logtype] ? 'selected="selected"' : '').' value="'.$file.'">'.$value.'</option>';
            }
            unset($file);
        }
        unset($filearray);
        // Set title
        $this->title = _('FOG Log Viewer');
        print '<p><form method="post" action="'.$this->formAction.'"><p>'._('File:');
        print '<select name="logtype" id="logToView">'.implode((array)$options3).'</select>'._('Number of lines:');
        $vals = array(20,50,100,200,400,500,1000);
        foreach ($vals AS $i => &$value) $options4[] = '<option '.($value == $_REQUEST[n] ? 'selected="selected"' : '').' value="'.$value.'">'.$value.'</option>';
        unset($value);
        print '<select name="n" id="linesToView">'.implode((array)$options4).'</select><center><input type="button" id="logpause" /></center></p></form><div id="logsGoHere">&nbsp;</div></p>';
    }
    /** config()
     * This feature is relatively new.  It's a means for the user to save the fog database
     * and/or replace the current one with your own, say if it's a fresh install, but you want
     * the old information restored.
     */
    public function config() {
        $this->HookManager->processEvent(IMPORT);
        $this->title='Configuration Import/Export';
        $report = $this->getClass(ReportMaker);
        $_SESSION[foglastreport]=serialize($report);
        unset($this->data,$this->headerData);
        $this->attributes = array(
            array(),
            array('class'=>r),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->data[0] = array(
            'field' => _('Click the button to export the database.'),
            'input' => '<input type="submit" name="export" value="'._('Export').'" />',
        );
        print '<form method="post" action="export.php?type=sql">';
        $this->render();
        unset($this->data);
        print '</form>';
        $this->data[] = array(
            'field' => _('Import a previous backup file.'),
            'input' => '<span class="lightColor">Max Size: ${size}</span><input type="file" name="dbFile" />',
            'size' => ini_get('post_max_size'),
        );
        $this->data[] = array(
            'field' => null,
            'input' => '<input type="submit" value="'._('Import').'" />',
        );
        print '<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
        $this->render();
        unset($this->data);
        print "</form>";
    }
    /** config_post()
     * Imports the file and installs the file as needed.
     */
    public function config_post() {
        $this->HookManager->processEvent(IMPORT_POST);
        //POST
        try {
            if($_FILES[dbFile] != null) {
                $dbFileName = BASEPATH.'/management/other/'.basename($_FILES[dbFile][name]);
                if(!move_uploaded_file($_FILES[dbFile][tmp_name],$dbFileName)) throw new Exception('Could not upload file!');
                print '<h2>'._('File Import successful!').'</h2>';
                $password = (DATABASE_PASSWORD ? ' -p"'.DATABASE_PASSWORD.'"' : '');
                $command = 'mysql -u ' . DATABASE_USERNAME . $password .' -h '.preg_replace('#p:#','',DATABASE_HOST).' '.DATABASE_NAME.' < "'.$dbFileName.'"';
                exec($command,$output = array(),$worked);
                switch ($worked) {
                case 0:
                    print '<h2>'._('Database Added!').'</h2>';
                    exec('rm -rf "'.$dbFileName.'" > /dev/null 2>/dev/null &');
                    break;
                case 1:
                    print "<h2>"._('Database import failed!').'</h2>';
                    break;
                }
            }
        } catch (Exception $e) {
            $this->FOGCore->setMessage($e->getMessage());
            $this->FOGCore->redirect($this->formAction);
        }
    }
}
