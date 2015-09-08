<?php
class PluginManagementPage extends FOGPage {
    public $node = 'plugin';
    public function __construct($name = '') {
        $this->name = 'Plugin Management';
        // Call parent constructor
        parent::__construct($this->name);
        $this->menu = array(
            home=>$this->foglang[Home],
            activate=>$this->foglang[ActivatePlugins],
            install=>$this->foglang[InstallPlugins],
            installed=>$this->foglang[InstalledPlugins],
        );
        $this->HookManager->processEvent(SUB_MENULINK_DATA,array(menu=>&$this->menu,submenu=>&$this->subMenu,id=>&$this->id,notes=>&$this->notes));
        // Header row
        $this->headerData = array(
            _('Plugin Name'),
            _('Description'),
            _('Location'),
        );
        //Row templates
        $this->templates = array(
            '<a href="?node=plugin&sub=${type}&run=${encname}&${type}=${encname}" title="Plugin: ${name}"><img alt="${name}" src="${icon}"/><br/><small>${name}</small></a>',
            '${desc}',
            '${location}',
        );
        //Row attributes
        $this->attributes = array(
            array(),
            array(),
            array(),
        );
        if (in_array($_REQUEST[sub],array('installed','install'))) {
            array_push($this->headerData,_('Remove'));
            array_push($this->templates,'<a href="?node=plugin&sub=removeplugin&rmid=${pluginid}"><i class="icon fa fa-minus-circle" title="Remove Plugin"></i></a>');
            array_push($this->attributes,array('class'=>'c filter-false'));
        }
    }
    // Pages
    public function index() {
        // Set title
        $this->title = $this->name;
    }
    public function home() {$this->index();}
        public function activate() {
            // Set title
            $this->title = _('Activate Plugins');
            // Find data
            $AllPlugins = $this->getClass(Plugin)->getPlugins();
            foreach ((array)$AllPlugins AS $i => &$Plugin) {
                if(!$Plugin->isActive()) {
                    $this->data[] = array(
                        name=>$Plugin->getName(),
                        type=>'activate',
                        encname=>md5(trim($Plugin->getName())),
                        location=>$Plugin->getPath(),
                        desc=>$Plugin->getDesc(),
                        icon=>$Plugin->getIcon(),
                    );
                }
            }
            //Hook
            $this->HookManager->processEvent(PLUGIN_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
            // Output
            $this->render();
            // Activate plugin if it's not already!
            if (!empty($_REQUEST[activate])&&$_REQUEST[sub] == 'activate') {
                $Plugin->activatePlugin($_REQUEST[activate]);
                $this->FOGCore->setMessage('Successfully added Plugin!');
                $this->FOGCore->redirect('?node=plugin&sub=activate');
            }
        }
    public function install() {
        $this->title = 'Install Plugins';
        $AllPlugins = $this->getClass(Plugin)->getPlugins();
        // Find data
        foreach ((array)$AllPlugins AS $i => &$Plugin) {
            $PluginMan = $this->getClass(PluginManager)->find(array(name=>$Plugin->getName()));
            $PluginMan = @array_shift($PluginMan);
            if (($Plugin->isActive() && !$Plugin->isInstalled() && !$_REQUEST[plug_name]) || ($_REQUEST[plug_name] && $_REQUEST[plug_name] == $Plugin->getName())) {
                $this->data[] = array(
                    name=>$Plugin->getName(),
                    type=>'install',
                    encname=>md5($Plugin->getName()).'&plug_name='.$Plugin->getName(),
                    location=>$Plugin->getPath(),
                    desc=>$Plugin->getDesc(),
                    icon=>$Plugin->getIcon(),
                    pluginid=>$PluginMan ? $PluginMan->get(id) : '',
                );
            }
        }
        //Hook
        $this->HookManager->processEvent(PLUGIN_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        if ($_REQUEST[run]) {
            $runner = $Plugin->getRunInclude($_REQUEST[run]);
            if (file_exists($runner) && $Plugin->isInstalled()) require_once($runner);
            else $this->run();
        }
    }
    public function installed() {
        $this->title = _('Installed Plugins');
        // Find data
        $AllPlugins = $this->getClass(Plugin)->getPlugins();
        foreach ((array)$AllPlugins AS $i => &$Plugin) {
            $PluginMan = $this->getClass(PluginManager)->find(array(name=>$Plugin->getName()));
            $PluginMan = @array_shift($PluginMan);
            if($Plugin->isActive() && $Plugin->isInstalled()) {
                $this->data[] = array(
                    name=>$Plugin->getName(),
                    type=>'installed',
                    encname=>md5($Plugin->getName()),
                    location=>$Plugin->getPath(),
                    desc=>$Plugin->getDesc(),
                    icon=>$Plugin->getIcon(),
                    pluginid=>$PluginMan ? $PluginMan->get(id) : '',
                );
            }
        }
        //Hook
        $this->HookManager->processEvent(PLUGIN_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        if ($_REQUEST[run]) {
            $runner = $Plugin->getRunInclude($_REQUEST[run]);
            if (file_exists($runner) && $Plugin->isInstalled()) require_once($runner);
            else $this->run();
        }
    }
    public function run() {
        $plugin = unserialize($_SESSION[fogactiveplugin]);
        try {
            if ($plugin == null) throw new Exception('Unable to determine plugin details.');
            $this->title = _('Plugin').': '.$plugin->getName();
            print '<p>'._('Plugin Description').': '.$plugin->getDesc().'</p>';
            if ($plugin->isInstalled() && $plugin->getName() == 'capone') {
                $dmiFields = array(
                    "bios-vendor",
                    "bios-version",
                    "bios-release-date",
                    "system-manufacturer",
                    "system-product-name",
                    "system-version",
                    "system-serial-number",
                    "system-uuid",
                    "baseboard-manufacturer",
                    "baseboard-product-name",
                    "baseboard-version",
                    "baseboard-serial-number",
                    "baseboard-asset-tag",
                    "chassis-manufacturer",
                    "chassis-type",
                    "chassis-version",
                    "chassis-serial-number",
                    "chassis-asset-tag",
                    "processor-family",
                    "processor-manufacturer",
                    "processor-version",
                    "processor-frequency",
                );
                print '<p class="titleBottomLeft">'._('Settings').'</p>';
                unset($this->headerData,$this->data);
                $this->templates = array(
                    '${field}',
                    '${input}',
                );
                $this->attributes = array(
                    array(),
                    array(),
                );
                foreach((array)$dmiFields AS $i => &$dmifield) {
                    $checked = $this->FOGCore->getSetting(FOG_PLUGIN_CAPONE_DMI) == $dmifield ? 'selected="selected"' : '';
                    $dmiOpts[] = '<option value="'.$dmifield.'" label="'.$dmifield.'" '.$checked.'>'.$dmifield.'</option>';
                }
                unset($dmifield);
                $ShutdownFields = array(
                    _('Reboot after deploy'),
                    _('Shutdown after deploy'),
                );
                $shutOpts[] = '<option value="0" '.(!$this->FOGCore->getSetting(FOG_PLUGIN_CAPONE_SHUTDOWN) ? 'selected="selected"' : ''). '>'._('Reboot after deploy').'</option>';
                $shutOpts[] = '<option value="1" '.($this->FOGCore->getSetting(FOG_PLUGIN_CAPONE_SHUTDOWN) ? 'selected="selected"' : ''). '>'._('Shutdown after deploy').'</option>';
                $fields = array(
                    _('DMI Field').':' => '<select name="dmifield" size="1"><option value="">- '._('Please select an option').' -</option>'.implode($dmiOpts).'</select>',
                    _('Shutdown').':' => '<select name="shutdown" size="1"><option value="">- '._('Please select an option').' -</option>'.implode($shutOpts).'</select>',
                    '<input type="hidden" name="basics" value="1" />' => '<input style="margin-top: 7px;" type="submit" value="'._('Update Settings').'" />',
                );
                foreach ((array)$fields AS $field => &$input) {
                    $this->data[] = array(
                        field=>$field,
                        input=>$input,
                    );
                }
                unset($input);
                print '<form method="post" action="'.$this->formAction.'">';
                $this->render();
                print '</form>';
                unset($this->headerData,$this->data,$fields);
                print '<p class="titleBottomLeft">'._('Add Image to DMI Associations').'</p>';
                $fields = array(
                    _('Image Definition').':' => $this->getClass(ImageManager)->buildSelectBox(),
                    _('DMI Result').':' => '<input type="text" name="key" />',
                    '<input type="hidden" name="addass" value="1" />' => '<input type="submit" style="margin-top: 7px;" value="'._('Add Association').'" />',
                );
                foreach((array)$fields AS $field => &$input) {
                    $this->data[] = array(
                        'field' => $field,
                        'input' => $input,
                    );
                }
                unset($input);
                print '<form method="post" action="'.$this->formAction.'">';
                $this->render();
                print '</form>';
                unset($this->headerData,$this->data,$fields);
                $Capones = $this->getClass(CaponeManager)->find();
                print '<p class="titleBottomLeft">'._('Current Image to DMI Associations').'</p>';
                $this->headerData = array(
                    _('Image Name'),
                    _('OS Name'),
                    _('DMI Key'),
                    _('Clear'),
                );
                $this->templates = array(
                    '${image_name}',
                    '${os_name}',
                    '${capone_key}',
                    '<input type="checkbox" name="kill" value="${capone_id}" class="delid" onclick="this.form.submit()" id="rmcap${capone_id}" /><label for="rmcap${capone_id}"><i class="icon icon-hand fa fa-minus-circle fa-1x" title="'._('Delete').'"></i></label>',
                );
                $this->attributes = array(
                    array(),
                    array(),
                    array(),
                    array('class'=>'filter-false'),
                );
                foreach((array)$Capones AS $i => &$Capone) {
                    $Image = $this->getClass(Image,$Capone->get(imageID));
                    $OS = $Image->getOS();
                    $this->data[] = array(
                        image_name=>$Image->get(name),
                        os_name=>$OS->get(name),
                        capone_key=>$Capone->get('key'),
                        'link'=>$this->formAction.'&kill=${capone_id}',
                        capone_id=>$Capone->get(id),
                    );
                }
                unset($Capone);
                print '<form method="post" action="'.$this->formAction.'">';
                $this->render();
                print '</form>';
            } else if ($plugin->isInstalled() && !$plugin->getname() == 'capone') $this->FOGCore->setMessage(_('Already installed!'));
            else if (!$plugin->isInstalled()) {
                print '<p class="titleBottomLeft">'._('Plugin Installation').'</p><p>'._('This plugin is currently not installed, would you like to install it now?').'</p><div><form method="post" action="'.$this->formAction.'"><input type="submit" value="Install Plugin" name="install" /></form></div>';
            }
        } catch (Exception $e) {
            print $this->FOGCore->setMessage($e->getMessage());
            $this->FOGCore->redirect('?node='.$_REQUEST[node].'&sub='.$_REQUEST[sub].'&run='.$_REQUEST[run]);
        }
    }
    public function install_post() {
        $this->installed_post();
    }
    public function installed_post() {
        $plugin = unserialize($_SESSION[fogactiveplugin]);
        if (isset($_REQUEST[install])) {
            if($this->getClass(ucfirst($plugin->getName()).'Manager')->install($plugin->getName())) {
                $Plugin = $this->getClass(PluginManager)->find(array(name=>$plugin->getName()));
                $Plugin = @array_shift($Plugin);
                if ($Plugin->isValid()) {
                    $Plugin
                        ->set(installed,1)
                        ->set(version,1);
                    if (!$Plugin->save()) $this->FOGCore->setMessage(_('Plugin Install Failed!'));
                    else $this->FOGCore->setMessage(_('Plugin Installed!'));
                }
            }
            if ($_REQUEST[sub] == 'install') $_REQUEST[sub] = 'installed';
            $this->FOGCore->redirect('?node='.$_REQUEST[node].'&sub='.$_REQUEST[sub].'&run='.$_REQUEST[run]);
        }
        if ($_REQUEST[basics]) {
            $this->FOGCore->setSetting(FOG_PLUGIN_CAPONE_DMI,$_REQUEST[dmifield]);
            $this->FOGCore->setSetting(FOG_PLUGIN_CAPONE_SHUTDOWN,$_REQUEST[shutdown]);
        }
        if($_REQUEST[addass]) {
            $this->getClass(Capone)
                ->set(imageID,$_REQUEST[image])
                ->set(osID,$this->getClass(Image,$_REQUEST[image])->getOS()->get(id))
                ->set('key',$_REQUEST['key'])
                ->save();
        }
        if ($_REQUEST[kill]) $this->getClass(Capone,$_REQUEST[kill])->destroy();
        $this->FOGCore->setMessage('Plugin updated!');
        $this->FOGCore->redirect($this->formAction);
    }
    public function removeplugin() {
        if ($_REQUEST[rmid]) $Plugin = $this->getClass(Plugin,$_REQUEST[rmid]);
        $Plugin->getManager()->uninstall();
        if ($Plugin->destroy()) {
            $this->FOGCore->setMessage('Plugin Removed');
            $this->FOGCore->redirect('?node='.$_REQUEST[node].'&sub=activate');
        }
    }
}
