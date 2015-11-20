<?php
class ServiceConfigurationPage extends FOGPage {
    public $node = 'service';
    public function __construct($name = '') {
        $this->name = 'Service Configuration';
        parent::__construct($name);
        $servicelink = "?node=$this->node&sub=edit";
        $this->menu = array(
            "?node=$this->node#home" => $this->foglang['Home'],
            "$servicelink#autologout" => "{$this->foglang['Auto']} {$this->foglang['Home']}",
            "$servicelink#clientupdater" => $this->foglang['ClientUpdater'],
            "$servicelink#dircleanup" => $this->foglang['DirectoryCleaner'],
            "$servicelink#displaymanager" => sprintf($this->foglang['SelManager'],$this->foglang['Display']),
            "$servicelink#greenfog" => $this->foglang['GreenFOG'],
            "$servicelink#hostregister" => $this->foglang['HostRegistration'],
            "$servicelink#hostnamechanger" => $this->foglang['HostnameChanger'],
            "$servicelink#printermanager" => sprintf($this->foglang['SelManager'],$this->foglang[Printer]),
            "$servicelink#snapinclient" => $this->foglang['SnapinClient'],
            "$servicelink#taskreboot" => $this->foglang['TaskReboot'],
            "$servicelink#usercleanup" => $this->foglang['UserCleanup'],
            "$servicelink#usertracker" => $this->foglang['UserTracker'],
        );
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        $this->headerData = array(
            _('Username'),
            _('Edit'),
        );
        $this->templates = array(
            sprintf('<a href="?node=%s&sub=edit">${name}</a>', $this->node),
            sprintf('<a href="?node=%s&sub=edit"><i class="icon fa fa-pencil"></i></a>', $this->node)
        );
        $this->attributes = array(
            array(),
            array('class'=>'c filter-false','width'=>55),
        );
    }
    public function home() {
        $this->index();
    }
    public function index() {
        printf('<h2>%s</h2><p>%s</p><a href="?node=client">%s</a><h2>%s</h2><p>%s</p>',
            _('FOG Client Download'),
            _('Use the following link to go to the client page. There you can download utilities such as FOG Prep, FOG Crypt, and both the legacy and new FOG Clients.'),
            _('Click Here'),
            _('FOG Service Configuration Information'),
            _('This will allow you to configure how services function on client computers.  The settings tend to be global settings which affect all hosts. If you are looking to configure settings for a specific host, please see the Hosts Service Settings section. To get started please select an item from the left hand menu.')
        );
    }
    public function edit() {
        echo '<div id="tab-container"><div id="home">';
        $this->index();
        echo '</div>';
        $moduleName = $this->getGlobalModuleStatus();
        $modNames = $this->getGlobalModuleStatus(true);
        foreach ((array)$this->getClass('ModuleManager')->find() AS $i => &$Module) {
            if (!$Module->isValid()) continue;
            unset($this->data,$this->headerData,$this->attributes,$this->templates);
            $this->attributes = array(
                array('width'=>270,'class'=>'l'),
                array('class'=>'c'),
                array('class'=>'r'),
            );
            $this->templates = array(
                '${field}',
                '${input}',
                '${span}',
            );
            $fields = array(
                sprintf('%s %s?',$Module->get('name'),_('Enabled')) => sprintf('<input type="checkbox" name="en"%s/>',$moduleName[$Module->get('shortName')] ? ' checked' : ''),
                sprintf('%s',($moduleName[$Module->get('shortName')] ? sprintf('%s %s?',$Module->get('name'),_('Enabled as default')) : '')) => sprintf('%s',($moduleName[$Module->get('shortName')] ? sprintf('<input type="checkbox" name="defen"%s/>',$Module->get('isDefault') ? ' checked' : '') : '')),
            );
            $fields = array_filter($fields);
            foreach((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    'field'=>$field,
                    'input'=>$input,
                    'span'=>sprintf('<i class="icon fa fa-question hand" title="%s"></i>',$Module->get('description')),
                );
                unset($input);
            }
            $this->data[] = array(
                'field'=>sprintf('<input type="hidden" name="name" value="%s"/>',$modNames[$Module->get('shortName')]),
                'input'=>'',
                'span'=>sprintf('<input type="submit" name="updatestatus" value="%s"/>',_('Update')),
            );
            printf('<!-- %s --><div id="%s"><h2>%s</h2><form method="post" action="?node=service&sub=edit&tab=%s"><p>%s</p><h2>%s</h2>',
                $Module->get('name'),
                $Module->get('shortName'),
                $Module->get('name'),
                $Module->get('shortName'),
                $Module->get('description'),
                _('Service Status')
            );
            $this->render();
            echo '</form>';
            switch ($Module->get('shortName')) {
            case 'autologout':
                printf('<h2>%s</h2><form method="post" action="?node=service&sub=edit&tab=%s"><p>%s: <input type="text" name="tme" value="%s"/></p><p><input type="hidden" name="name" value="FOG_SERVICE_AUTOLOGOFF_MIN"/><input name="updatedefaults" type="submit" value="%s"/></p></form>',
                    _('Default Setting'),
                    $Module->get('shortName'),
                    _('Default log out time (in minutes)'),
                    $this->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN'),
                    _('Update Defaults')
                );
                break;
            case 'clientupdater':
                unset($this->data,$this->headerData,$this->attributes,$this->templates);
                $this->getClass('FOGConfigurationPage')->client_updater();
                break;
            case 'dircleanup':
                unset($this->data,$this->headerData,$this->attributes,$this->templates);
                $this->headerData = array(
                    _('Path'),
                    _('Remove'),
                );
                $this->attributes = array(
                    array('class'=>'l'),
                    array('class'=>'filter-false'),
                );
                $this->templates = array(
                    '${dir_path}',
                    sprintf('<input type"checkbox" id="rmdir${dir_id}" class="delid" name="delid" onclick="this.form.submit()" value="${dir_id}"/><label for="rmdir${dir_id}" class="icon fa fa-minus-circle hand" title="%s">&nbsp;</label>',_('Delete')),
                );
                printf('<h2>%s</h2><form method="post" action="%s&tab=%s"><p>%s: <input type="text" name="adddir"/></p><p><input type="hidden" name="name" value="%s"/><input type="submit" value="%s"/></p><h2>%s</h2>',
                    _('Add Directory'),
                    $this->formAction,
                    $Module->get('shortName'),
                    _('Directory Path'),
                    $modNames[$Module->get('shortName')],
                    _('Add Directory'),
                    _('Directories Cleaned')
                );
                foreach ((array)$this->getClass('DirCleanerManager')->find() AS $i => &$DirCleaner) {
                    if (!$DirCleaner->isValid()) continue;
                    $this->data[] = array(
                        'dir_path'=>$DirCleaner->get('path'),
                        'dir_id'=>$DirCleaner->get('id'),
                    );
                }
                $this->render();
                echo '</form>';
                break;
            case 'displaymanager':
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
                    _('Default Width') => sprintf('<input type="text" name="width" value="%s"/>',$this->getSetting('FOG_SERVICE_DISPLAYMANAGER_X')),
                    _('Default Height') => sprintf('<input type="text" name="height" value="%s"/>',$this->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y')),
                    _('Default Refresh Rate') => sprintf('<input type="text" name="refresh" value="%s"/>',$this->getSetting('FOG_SERVICE_DISPLAYMANAGER_R')),
                    sprintf('<input type="hidden" name="name" value="%s"/>',$modNames[$Module->get('shortName')]) => sprintf('<input name="updatedefaults" type="submit" value="%s"/>',_('Update Defaults')),
                );
                printf('<h2>%s</h2><form method="post" action="%s&tab=%s">',
                    _('Default Setting'),
                    $this->formAction,
                    $Module->get('shortName')
                );
                foreach((array)$fields AS $field => &$input) {
                    $this->data[] = array(
                        'field'=>$field,
                        'input'=>$input,
                    );
                    unset($input);
                }
                $this->render();
                echo '</form>';
                break;
            case 'greenfog':
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
                    sprintf('<input type="checkbox" id="gfrem${gf_id}" class="delid" name="delid" onclick="this.form.submit()" value="${gf_id}"/><label for="gfrem${gf_id}" class="icon fa fa-minus-circle hand" title="%s">&nbsp;</label>',_('Delete')),
                );
                printf('<h2>%s</h2><form method="post" action="%s&tab=%s"><p>%s <input class="short" type="text" name="h" maxlength="2" value="HH" onFocus="$(this).val(\'\');"/>:<input class="short" type="text" name="m" maxlength="2" value="MM" onFocus="$(this).val(\'\');"/><select name="style" size="1"><option value="">- %s -</option><option value="s">%s</option><option value="r">%s</option></select></p><p><input type="hidden" name="name" value="%s"/><input type="submit" name="addevent" value="%s"/></p>',
                    _('Shutdown/Reboot Schedule'),
                    $this->formAction,
                    $Module->get('shortName'),
                    _('Add Event (24 Hour Format)'),
                    _('Please select an option'),
                    _('Shutdown'),
                    _('Reboot'),
                    $modNames[$Module->get('shortName')],
                    _('Add Event')
                );
                foreach ((array)$this->getClass('GreenFogManager')->find() AS $i => &$GreenFog) {
                    if (!$GreenFog->isValid()) continue;
                    $gftime = $this->nice_date($GreenFog->get('hour').':'.$GreenFog->get('min'))->format('H:i');
                    $this->data[] = array(
                        'gf_time'=>$this->nice_date(sprintf('%s:%s',$GreenFog->get('hour'),$GreenFog->get('min')))->format('H:i'),
                        'gf_action'=>($GreenFog->get('action') == 'r' ? 'Reboot' : ($GreenFog->get('action') == 's' ? _('Shutdown') : _('N/A'))),
                        'gf_id'=>$GreenFog->get('id'),
                    );
                    unset($GreenFog);
                }
                $this->render();
                echo '</form>';
                break;
            case 'usercleanup':
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
                    _('Username') => '<input type="text" name="usr"/>',
                    sprintf('<input type="hidden" name="name" value="%s"/>',$modNames[$Module->get('shortName')]) => sprintf('<input type="submit" name="adduser" value="%s"/>',_('Add User')),
                );
                printf('<h2>%s</h2><form method="post" action="%s&tab=%s">',
                    _('Add Protected User'),
                    $this->formAction,
                    $Module->get('shortName')
                );
                foreach((array)$fields AS $field => &$input) {
                    $this->data[] = array(
                        'field'=>$field,
                        'input'=>$input,
                    );
                    unset($input);
                }
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
                $UCs = $this->getClass('UserCleanupManager')->find();
                foreach ((array)$UCs AS $i => &$UserCleanup) {
                    $this->data[] = array(
                        'user_name'=>$UserCleanup->get('name'),
                        'input'=>$UserCleanup->get('id') < 7 ? null : '<input type="checkbox" id="rmuser${user_id}" class="delid" name="delid" onclick="this.form.submit()" value="${user_id}" /><label for="rmuser${user_id}" class="icon fa fa-minus-circle hand" title="'._('Delete').'">&nbsp;</label>',
                        'user_id'=>$UserCleanup->get('id'),
                    );
                }
                unset($UserCleanup);
                $this->render();
                echo '</form>';
                break;
            }
            echo '</div>';
            unset($Module);
        }
        echo '</div>';
    }
    public function edit_post() {
        $Service = $this->getClass('ServiceManager')->find(array('name'=>$_REQUEST['name']));
        $Service = @array_shift($Service);
        $Module = $this->getClass('ModuleManager')->find(array('shortName'=>$_REQUEST['tab']));
        $Module = @array_shift($Module);
        $this->HookManager->processEvent('SERVICE_EDIT_POST',array('Service'=>&$Service));
        $onoff = (int)isset($_REQUEST['en']);
        $defen = (int)isset($_REQUEST['defen']);
        try {
            if (isset($_REQUEST['updatestatus'])) {
                if ($Service) $Service->set('value',$onoff)->save();
                if ($Module) $Module->set('isDefault',$defen)->save();
            }
            switch ($_REQUEST['tab']) {
                case 'autologout';
                if ($_REQUEST['updatedefaults'] == 1 && is_numeric($_REQUEST['tme']))
                    $Service->set('value',$_REQUEST['tme']);
                break;
                case 'dircleanup';
                if(trim($_REQUEST['adddir'])) $Service->addDir($_REQUEST['adddir']);
                if(isset($_REQUEST['delid'])) $Service->remDir($_REQUEST['delid']);
                break;
                case 'displaymanager';
                if($_REQUEST['updatedefaults'] == 1 && (is_numeric($_REQUEST['height']) && is_numeric($_REQUEST['width']) && is_numeric($_REQUEST['refresh']))) $Service->setDisplay($_REQUEST['width'],$_REQUEST['height'],$_REQUEST['refresh']);
                break;
                case 'greenfog';
                if(isset($_REQUEST['addevent'])) {
                    if((is_numeric($_REQUEST['h']) && is_numeric($_REQUEST['m'])) && ($_REQUEST['h'] >= 0 && $_REQUEST['h'] <= 23) && ($_REQUEST['m'] >= 0 && $_REQUEST['m'] <= 59) && ($_REQUEST['style'] == 'r' || $_REQUEST['style'] == 's'))
                        $Service->setGreenFog($_REQUEST['h'],$_REQUEST['m'],$_REQUEST['style']);
                }
                if(isset($_REQUEST['delid'])) $Service->remGF($_REQUEST['delid']);
                break;
                case 'usercleanup';
                $addUser = trim($_REQUEST['usr']);
                if(!empty($addUser)) $Service->addUser($addUser);
                if(isset($_REQUEST['delid'])) $Service->remUser($_REQUEST['delid']);
                break;
                case 'clientupdater';
                $this->getClass('FOGConfigurationPage')->client_updater_post();
                break;
            }
            if (!$Service->save()) throw new Exception(_('Service update failed'));
            $this->HookManager->processEvent('SERVICE_EDIT_SUCCESS',array('Service'=>&$Service));
            $this->setMessage('Service Updated!');
        } catch (Exception $e) {
            $this->HookManager->processEvent(SERVICE_EDIT_FAIL,array(Service=>&$Service));
            $this->setMessage($e->getMessage());
        }
        $this->redirect(sprintf('%s#%s',$this->formAction,$_REQUEST['tab']));
    }
    public function search() {
        $this->index();
    }
}
