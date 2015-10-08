<?php
class ServiceConfigurationPage extends FOGPage {
    public $node = 'service';
    public function __construct($name = '') {
        $this->name = 'Service Configuration';
        parent::__construct($name);
        $servicelink = "?node=$this->node&sub=edit";
        $this->menu = array(
            "?node=$this->node#home" => $this->foglang[Home],
            "$servicelink#autologout" => "{$this->foglang[Auto]} {$this->foglang[Home]}",
            "$servicelink#clientupdater" => $this->foglang[ClientUpdater],
            "$servicelink#dircleanup" => $this->foglang[DirectoryCleaner],
            "$servicelink#displaymanager" => sprintf($this->foglang[SelManager],$this->foglang[Display]),
            "$servicelink#greenfog" => $this->foglang[GreenFOG],
            "$servicelink#hostregister" => $this->foglang[HostRegistration],
            "$servicelink#hostnamechanger" => $this->foglang[HostnameChanger],
            "$servicelink#printermanager" => sprintf($this->foglang[SelManager],$this->foglang[Printer]),
            "$servicelink#snapinclient" => $this->foglang[SnapinClient],
            "$servicelink#taskreboot" => $this->foglang[TaskReboot],
            "$servicelink#usercleanup" => $this->foglang[UserCleanup],
            "$servicelink#usertracker" => $this->foglang[UserTracker],
        );
        $this->HookManager->processEvent(SUB_MENULINK_DATA,array(menu=>&$this->menu,submenu=>&$this->subMenu,id=>&$this->id,notes=>&$this->notes));
        // Header row
        $this->headerData = array(
            _('Username'),
            _('Edit'),
        );
        // Row templates
        $this->templates = array(
            sprintf('<a href="?node=%s&sub=edit">${name}</a>', $this->node),
            sprintf('<a href="?node=%s&sub=edit"><i class="icon fa fa-pencil"></i></a>', $this->node)
        );
        // Row attributes
        $this->attributes = array(
            array(),
            array('class'=>'c filter-false',width=>55),
        );
    }
    public function home() {$this->index();}
        // Pages
        public function index() {
            echo '<h2>'._('FOG Client Download').'</h2><p>'._('Use the following link to go to the Client page to download the FOG Client, FOG Prep, and FOG Crypt Information.').'</p><a href="?node=client">'._('Click Here').'</a><h2>'._('FOG Service Configuration Information').'</h2><p>'._('This section of the FOG management portal allows you to configure how the FOG service functions on client computers.  The settings in this section tend to be global settings that effect all hosts.  If you are looking to configure settings for a service module that is specific to a host, please see the Servicesection.  To get started editing global settings, please select an item from the left hand menu.').'</p>';
        }
    public function edit() {
        echo '<div id="tab-container"><div id="home">';
        $this->index();
        echo '</div>';
        $moduleName = $this->getGlobalModuleStatus();
        $modNames = $this->getGlobalModuleStatus(true);
        $Modules = $this->getClass(ModuleManager)->find();
        foreach ($Modules AS $i => &$Module) {
            unset($this->data,$this->headerData,$this->attributes,$this->templates);
            $this->attributes = array(
                array(width=>270,'class'=>l),
                array('class'=>c),
                array('class'=>r),
            );
            $this->templates = array(
                '${field}',
                '${input}',
                '${span}',
            );
            $fields = array(
                _($Module->get(name).' Enabled?')=>'<input type="checkbox" name="en" ${checked}/>',
                ($moduleName[$Module->get(shortName)] ? _($Module->get(name).' Enabled as default?') : null) => ($moduleName[$Module->get(shortName)] ? '<input type="checkbox" name="defen" ${is_on}/>' : null),
            );
            $fields = array_filter($fields);
            foreach((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    field=>$field,
                    input=>$input,
                    checked=>($moduleName[$Module->get(shortName)] ? 'checked' : ''),
                    span=>'<i class="icon fa fa-question hand" title="${module_desc}"></i>',
                    module_desc=>$Module->get(description),
                    is_on=>($Module->get(isDefault) ? 'checked' : ''),
                );
            }
            unset($input);
            $this->data[] = array(
                field=>'<input type="hidden" name="name" value="${mod_name}" />',
                input=>'',
                span=>'<input type="submit" name="updatestatus" value="'._('Update').'" />',
                mod_name=>$modNames[$Module->get(shortName)],
            );
            echo '<!-- '._($Module->get(name)).'  --><div id="'.$Module->get(shortName).'"><h2>'._($Module->get(name)).'</h2><form method="post" action="?node=service&sub=edit&tab='.$Module->get(shortName).'"><p>'._($Module->get(description)).'</p><h2>'._('Service Status').'</h2>';
            // Hook
            // Output
            $this->render();
            echo '</form>';
            if ($Module->get(shortName) == 'autologout') {
                echo '<h2>'._('Default Setting').'</h2>';
                echo '<form method="post" action="?node=service&sub=edit&tab='.$Module->get(shortName).'"><p>'._('Default log out time (in minutes): ').'<input type="text" name="tme" value="'.$this->FOGCore->getSetting(FOG_SERVICE_AUTOLOGOFF_MIN).'" /></p><p><input type="hidden" name="name" value="FOG_SERVICE_AUTOLOGOFF_MIN" /><input type="hidden" name="updatedefaults" value="1" /><input type="submit" value="'._('Update Defaults').'" /></p></form>';
            } else if ($Module->get(shortName) == 'clientupdater') {
                unset($this->data,$this->headerData,$this->attributes,$this->templates);
                $this->getClass(FOGConfigurationPage)->client_updater();
            } else if ($Module->get(shortName) == 'dircleanup') {
                unset($this->data,$this->headerData,$this->attributes,$this->templates);
                $this->headerData = array(
                    _('Path'),
                    _('Remove'),
                );
                $this->attributes = array(
                    array('class'=>l),
                    array('class'=>'filter-false'),
                );
                $this->templates = array(
                    '${dir_path}',
                    '<input type="checkbox" id="rmdir${dir_id}" class="delid" name="delid" onclick="this.form.submit()" value="${dir_id}" /><label for="rmdir${dir_id}" class="icon fa fa-minus-circle hand" title="'._('Delete').'">&nbsp;</label>',
                );
                echo '<h2>'._('Add Directory').'</h2><form method="post" action="?node=service&sub=edit&tab='.$Module->get('shortName').'"><p>'._('Directory Path').': <input type="text" name="adddir" /></p><p><input type="hidden" name="name" value="'.$modNames[$Module->get(shortName)].'" /><input type="submit" value="'._('Add Directory').'" /></p><h2>'._('Directories Cleaned').'</h2>';
                $dirs = $this->getClass(DirCleanerManager)->find();
                foreach ((array)$dirs AS $i => &$DirCleaner) {
                    $this->data[] = array(
                        dir_path=>$DirCleaner->get(path),
                        dir_id=>$DirCleaner->get(id),
                    );
                }
                unset($DirCleaner);
                // Hook
                // $this->HookManager->processEvent()
                $this->render();
                echo '</form>';
            } else if ($Module->get(shortName) == 'displaymanager') {
                unset($this->data,$this->headerData,$this->attributes,$this->templates);
                $this->attributes = array(
                    array(),
                    array(),
                );
                $this->templates = array(
                    '${field}',
                    '${input}',
                );
                $fields = array(
                    _('Default Width') => '<input type="text" name="width" value="${width}" />',
                    _('Default Height') => '<input type="text" name="height" value="${height}" />',
                    _('Default Refresh Rate') => '<input type="text" name="refresh" value="${refresh}" />',
                    '<input type="hidden" name="name" value="${mod_name}" /><input type="hidden" name="updatedefaults" value="1" />' => '<input type="submit" value="'._('Update Defaults').'" />',
                );
                echo '<h2>'._('Default Setting').'</h2>';
                echo '<form method="post" action="?node=service&sub=edit&tab='.$Module->get(shortName).'">';
                foreach((array)$fields AS $field => &$input) {
                    $this->data[] = array(
                        field=>$field,
                        input=>$input,
                        width=>$this->FOGCore->getSetting(FOG_SERVICE_DISPLAYMANAGER_X),
                        height=>$this->FOGCore->getSetting(FOG_SERVICE_DISPLAYMANAGER_Y),
                        refresh=>$this->FOGCore->getSetting(FOG_SERVICE_DISPLAYMANAGER_R),
                        mod_name=>$modNames[$Module->get(shortName)],
                    );
                }
                unset($input);
                // Hook
                // $this->HookManager->processEvent()
                $this->render();
                echo '</form>';
            } else if ($Module->get(shortName) == 'greenfog') {
                unset($this->data,$this->headerData,$this->attributes,$this->templates);
                $this->headerData = array(
                    _('Time'),
                    _('Action'),
                    _('Remove'),
                );
                $this->attributes = array(
                    array(),
                    array(),
                    array('class'=>'filter-false'),
                );
                $this->templates = array(
                    '${gf_time}',
                    '${gf_action}',
                    '<input type="checkbox" id="gfrem${gf_id}" class="delid" name="delid" onclick="this.form.submit()" value="${gf_id}" /><label for="gfrem${gf_id}" class="icon fa fa-minus-circle hand" title="'._('Delete').'">&nbsp;</label>',
                );
                echo '<h2>'._('Shutdown/Reboot Schedule').'</h2><form method="post" action="?node=service&sub=edit&tab='.$Module->get(shortName).'"><p>'._('Add Event (24 Hour Format):').'<input class="short" type="text" name="h" maxlength="2" value="HH" onFocus="this.value=\'\'" />:<input class="short" type="text" name="m" maxlength="2" value="MM" onFocus="this.value=\'\'" /><select name="style" size="1"><option value="">'._('Select One').'</option><option value="s">'._('Shut Down').'</option><option value="r">'._('Reboot').'</option></select></p><p><input type="hidden" name="name" value="'.$modNames[$Module->get(shortName)].'" /><input type="submit" name="addevent" value="'._('Add Event').'" /></p>';
                $greenfogs = $this->getClass(GreenFogManager)->find();
                foreach((array)$greenfogs AS $i => &$GreenFog) {
                    if ($GreenFog && $GreenFog->isValid()) {
                        $gftime = $this->nice_date($GreenFog->get(hour).':'.$GreenFog->get('min'))->format('H:i');
                        $this->data[] = array(
                            gf_time=>$gftime,
                            gf_action=>($GreenFog->get(action) == 'r' ? 'Reboot' : ($GreenFog->get(action) == 's' ? _('Shutdown') : _('N/A'))),
                            gf_id=>$GreenFog->get(id),
                        );
                    }
                }
                unset($GreenFog);
                // Hook
                // $this->HookManager->processEvent()
                $this->render();
                echo '</form>';
            } else if ($Module->get(shortName) == 'usercleanup') {
                unset($this->data,$this->headerData,$this->attributes,$this->templates);
                $this->attributes = array(
                    array(),
                    array(),
                );
                $this->templates = array(
                    '${field}',
                    '${input}',
                );
                $fields = array(
                    _('Username') => '<input type="text" name="usr" />',
                    '<input type="hidden" name="name" value="${mod_name}" /><input type="hidden" name="adduser" value="1" />' => '<input type="submit" value="'._('Add User').'" />',
                );
                echo '<h2>'._('Add Protected User').'</h2><form method="post" action="?node=service&sub=edit&tab='.$Module->get(shortName).'">';
                foreach((array)$fields AS $field => &$input) {
                    $this->data[] = array(
                        field=>$field,
                        input=>$input,
                        mod_name=>$modNames[$Module->get(shortName)],
                    );
                }
                unset($input);
                $this->render();
                unset($this->data,$this->headerData,$this->attributes,$this->templates);
                $this->headerData = array(
                    _('User'),
                    _('Remove'),
                );
                $this->attributes = array(
                    array(),
                    array('class'=>'filter-false'),
                );
                $this->templates = array(
                    '${user_name}',
                    '${input}',
                );
                echo '<h2>'._('Current Protected User Accounts').'</h2>';
                $UCs = $this->getClass(UserCleanupManager)->find();
                foreach ((array)$UCs AS $i => &$UserCleanup) {
                    $this->data[] = array(
                        user_name=>$UserCleanup->get(name),
                        input=>$UserCleanup->get(id) < 7 ? null : '<input type="checkbox" id="rmuser${user_id}" class="delid" name="delid" onclick="this.form.submit()" value="${user_id}" /><label for="rmuser${user_id}" class="icon fa fa-minus-circle hand" title="'._('Delete').'">&nbsp;</label>',
                        user_id=>$UserCleanup->get(id),
                    );
                }
                unset($UserCleanup);
                $this->render();
                echo '</form>';
            }
            echo '</div>';
        }
        unset($Module);
        echo '</div>';
    }
    public function edit_post() {
        $Service = $this->getClass(ServiceManager)->find(array(name=>$_REQUEST[name]));
        $Service = @array_shift($Service);
        // Finds the relevant module
        $Module = $this->getClass(ModuleManager)->find(array(shortName=>$_REQUEST[tab]));
        $Module = @array_shift($Module);
        // Hook
        $this->HookManager->processEvent(SERVICE_EDIT_POST,array(Service=>&$Service));
        //Store value of Common Values
        $onoff = (int)isset($_REQUEST[en]);
        //Gets the default enabling status.
        $defen = (int)isset($_REQUEST[defen]);
        // POST
        try {
            if (isset($_REQUEST[updatestatus])) {
                if ($Service) $Service->set(value,$onoff)->save();
                // If the module is found and valid, it saves the default status.
                if ($Module) $Module->set(isDefault,$defen)->save();
            }
            switch ($_REQUEST[tab]) {
                case 'autologout';
                if ($_REQUEST[updatedefaults] == 1 && is_numeric($_REQUEST[tme]))
                    $Service->set(value,$_REQUEST[tme]);
                break;
                case 'dircleanup';
                if(trim($_REQUEST[adddir]) != '') $Service->addDir($_REQUEST[adddir]);
                if(isset($_REQUEST[delid])) $Service->remDir($_REQUEST[delid]);
                break;
                case 'displaymanager';
                if($_REQUEST[updatedefaults] == 1 && (is_numeric($_REQUEST[height]) && is_numeric($_REQUEST[width]) && is_numeric($_REQUEST[refresh]))) $Service->setDisplay($_REQUEST[width],$_REQUEST[height],$_REQUEST[refresh]);
                break;
                case 'greenfog';
                if(isset($_REQUEST[addevent])) {
                    if((is_numeric($_REQUEST[h]) && is_numeric($_REQUEST[m])) && ($_REQUEST[h] >= 0 && $_REQUEST[h] <= 23) && ($_REQUEST[m] >= 0 && $_REQUEST[m] <= 59) && ($_REQUEST[style] == 'r' || $_REQUEST[style] == 's'))
                        $Service->setGreenFog($_REQUEST[h],$_REQUEST[m],$_REQUEST[style]);
                }
                if(isset($_REQUEST[delid])) $Service->remGF($_REQUEST[delid]);
                break;
                case 'usercleanup';
                $addUser = trim($_REQUEST[usr]);
                if(!empty($addUser)) $Service->addUser($addUser);
                if(isset($_REQUEST[delid])) $Service->remUser($_REQUEST[delid]);
                break;
                case 'clientupdater';
                $this->getClass(FOGConfigurationPage)->client_updater_post();
                break;
            }
            // Save to database
            if (!$Service->save()) throw new Exception(_('Service update failed'));
            // Hook
            $this->HookManager->processEvent(SERVICE_EDIT_SUCCESS,array(Service=>&$Service));
            // Set session message
            $this->FOGCore->setMessage('Service Updated!');
            // Redirect to new entry
            $this->FOGCore->redirect(sprintf('?node=%s&sub=edit#%s', $this->request[node],$_REQUEST[tab]));
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent(SERVICE_EDIT_FAIL,array(Service=>&$Service));
            // Set session message
            $this->FOGCore->setMessage($e->getMessage());
            // Redirect
            $this->FOGCore->redirect(sprintf('?node=%s&sub=edit#%s', $_REQUEST[node], $_REQUEST[tab]));
        }
    }
    public function search() {
        $this->index();
    }
}
