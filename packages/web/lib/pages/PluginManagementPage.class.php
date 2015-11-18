<?php
class PluginManagementPage extends FOGPage {
    public $node = 'plugin';
    public function __construct($name = '') {
        $this->name = 'Plugin Management';
        parent::__construct($this->name);
        $this->menu = array(
            'home'=>$this->foglang['Home'],
            'activate'=>$this->foglang['ActivatePlugins'],
            'install'=>$this->foglang['InstallPlugins'],
            'installed'=>$this->foglang['InstalledPlugins'],
        );
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        $this->headerData = array(
            _('Plugin Name'),
            _('Description'),
            _('Location'),
        );
        $this->templates = array(
            '<a href="?node=plugin&sub=${type}&run=${encname}&${type}=${encname}" class="icon" title="Plugin: ${name}"><img alt="${name}" src="${icon}"/><br/><small>${name}</small></a>',
            '${desc}',
            '${location}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
        );
        if (in_array($_REQUEST['sub'],array('installed','install'))) {
            array_push($this->headerData,_('Remove'));
            array_push($this->templates,'<a href="?node=plugin&sub=removeplugin&rmid=${pluginid}"><i class="icon fa fa-minus-circle" title="Remove Plugin"></i></a>');
            array_push($this->attributes,array('class'=>'c filter-false'));
        }
    }
    public function index() {
        $this->title = $this->name;
    }
    public function activate() {
        $this->title = _('Activate Plugins');
        foreach ((array)$this->getClass('Plugin')->getPlugins() AS $i => &$Plugin) {
            if ($Plugin->isActive()) continue;
            $this->data[] = array(
                'name'=>$Plugin->getName(),
                'type'=>'activate',
                'encname'=>trim(md5(trim($Plugin->getName()))),
                'location'=>$Plugin->getPath(),
                'desc'=>$Plugin->getDesc(),
                'icon'=>$Plugin->getIcon(),
            );
            unset($Plugin);
        }
        $this->HookManager->processEvent('PLUGIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if (!empty($_REQUEST['activate']) && $_REQUEST['sub'] == 'activate') {
            $this->getClass('Plugin')->activatePlugin($_REQUEST['activate']);
            $this->setMessage('Successfully added Plugin!');
            $this->redirect('?node=plugin&sub=activate');
        }
    }
    public function install() {
        $this->title = 'Install Plugins';
        foreach ((array)$this->getClass('Plugin')->getPlugins() AS $i => &$Plugin) {
            $PluginMan = $this->getClass('PluginManager')->find(array('name'=>$Plugin->getName()));
            $PluginMan = @array_shift($PluginMan);
            if (!$Plugin->isActive()) continue;
            if ($Plugin->isInstalled()) continue;
            if ($_REQUEST['plug_name']) continue;
            if ($_REQUEST['plug_name'] != $Plugin->getName()) continue;
            $this->data[] = array(
                'name'=>$Plugin->getName(),
                'type'=>'install',
                'encname'=>sprintf('%s&plug_name=%s',trim(md5(trim($Plugin->getName()))),$Plugin->getName()),
                'location'=>$Plugin->getPath(),
                'desc'=>$Plugin->getDesc(),
                'icon'=>$Plugin->getIcon(),
                'pluginid'=>$PluginMan ? $PluginMan->get('id') : '',
            );
        }
        $this->HookManager->processEvent('PLUGIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if ($_REQUEST['run']) {
            $runner = $Plugin->getRunInclude($_REQUEST['run']);
            if (file_exists($runner) && $Plugin->isInstalled()) require($runner);
            else $this->run();
        }
        unset($Plugin);
    }
    public function installed() {
        $this->title = _('Installed Plugins');
        foreach ((array)$this->getClass('Plugin')->getPlugins() AS $i => &$Plugin) {
            $PluginMan = $this->getClass('PluginManager')->find(array('name'=>$Plugin->getName()));
            $PluginMan = @array_shift($PluginMan);
            if (!$Plugin->isActive()) continue;
            if (!$Plugin->isInstalled()) continue;
            $this->data[] = array(
                'name'=>$Plugin->getName(),
                'type'=>'installed',
                'encname'=>trim(md5(trim($Plugin->getName()))),
                'location'=>$Plugin->getPath(),
                'desc'=>$Plugin->getDesc(),
                'icon'=>$Plugin->getIcon(),
                'pluginid'=>$PluginMan ? $PluginMan->get('id') : '',
            );
        }
        $this->HookManager->processEvent('PLUGIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if ($_REQUEST['run']) {
            $runner = $Plugin->getRunInclude($_REQUEST['run']);
            if (file_exists($runner) && $Plugin->isInstalled()) require($runner);
            else $this->run();
        }
    }
    public function run() {
        $plugin = unserialize($_SESSION['fogactiveplugin']);
        try {
            if ($plugin == null) throw new Exception('Unable to determine plugin details.');
            $this->title = sprintf('%s: %s',_('Plugin'),$plugin->getName());
            printf('<p>%s: %s</p>',_('Plugin Description'),$plugin->getDesc());
            switch ($plugin->isInstalled()) {
            case true:
                switch (strtolower($plugin->getName())) {
                case 'capone':
                    $dmiFields = array(
                        'bios-vendor',
                        'bios-version',
                        'bios-release-date',
                        'system-manufacturer',
                        'system-product-name',
                        'system-version',
                        'system-serial-number',
                        'system-uuid',
                        'baseboard-manufacturer',
                        'baseboard-product-name',
                        'baseboard-version',
                        'baseboard-serial-number',
                        'baseboard-asset-tag',
                        'chassis-manufacturer',
                        'chassis-type',
                        'chassis-version',
                        'chassis-serial-number',
                        'chassis-asset-tag',
                        'processor-family',
                        'processor-manufacturer',
                        'processor-version',
                        'processor-frequency',
                    );
                    printf('<p class="titleBottomLeft">%s</p>',_('Settings'));
                    unset($this->headerData,$this->data);
                    $this->templates = array(
                        '${field}',
                        '${input}',
                    );
                    $this->attributes = array(
                        array(),
                        array(),
                    );
                    ob_start();
                    foreach ((array)$dmiFields AS $i => &$dmifield) {
                        $checked = $this->getSetting('FOG_PLUGIN_CAPONE_DMI') == $dmifield ? ' selected' : '';
                        printf('<option value="%s" label="%s"%s>%s</option>',$dmifield,$dmifield,$checked,$dmifield);
                        unset($dmifield);
                    }
                    $dmiOpts = ob_get_clean();
                    $ShutdownFields = array(
                        _('Reboot after deploy'),
                        _('Shutdown after deploy'),
                    );
                    ob_start();
                    printf('<option value="0"%s>%s</option>',(!$this->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN') ? ' selected' : ''),_('Reboot after deploy'));
                    printf('<option value="1"%s>%s</option>',($this->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN') ? ' selected' : ''),_('Shutdown after deploy'));
                    $shutOpts = ob_get_clean();
                    $fields = array(
                        sprintf('%s:',_('DMI Field')) => sprintf('<select name="dmifield" size="1"><option value="">- %s -</option>%s</select>',_('Please select an option'),$dmiOpts),
                        sprintf('%s:',_('Shutdown')) => sprintf('<select name="shutdown" size="1"><option value="">- %s -</option>%s</select>',_('Please select an option'),$shutOpts),
                        '&nbsp;' => sprintf('<input style="margin-top: 7px;" type="submit" name="basics" value="%s"/>',_('Update Settings')),
                    );
                    foreach ((array)$fields AS $field => &$input) {
                        $this->data[] = array(
                            'field'=>$field,
                            'input'=>$input,
                        );
                        unset($input);
                    }
                    printf('<form method="post" action="%s">',$this->formAction);
                    $this->render();
                    echo '</form>';
                    unset($this->headerData,$this->data,$fields);
                    printf('<p class="titleBottomLeft">%s</p>',_('Add Image to DMI Associations'));
                    $fields = array(
                        sprintf('%s:',_('Image Definition')) => $this->getClass('ImageManager')->buildSelectBox(),
                        sprintf('%s:',_('DMI Result')) => '<input type="text" name="key"/>',
                        '&nbps;' => sprintf('<input type="submit" style="margin-top: 7px;" name="addass" value="%s"/>',_('Add Association')),
                    );
                    foreach ((array)$fields AS $field => &$input) {
                        $this->data[] = array(
                            'field' => $field,
                            'input' => $input,
                        );
                        unset($input);
                    }
                    printf('<form method="post" action="%s">',$this->formAction);
                    $this->render();
                    echo '</form>';
                    unset($this->headerData,$this->data,$fields);
                    printf('<p class="titleBottomLeft">%s</p>',_('Current Image to DMI Associations'));
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
                        sprintf('<input type="checkbox" name="kill" value="${capone_id}" class="delid" onclick="this.form.submit()" id="rmcap${capone_id}" /><label for="rmcap${capone_id}"><i class="icon icon-hand fa fa-minus-circle fa-1x" title="%s"></i></label>',_('Delete')),
                    );
                    $this->attributes = array(
                        array(),
                        array(),
                        array(),
                        array('class'=>'filter-false'),
                    );
                    foreach ((array)$this->getClass('CaponeManager')->find() AS $i => &$Capone) {
                        if (!$Capone->isValid()) continue;
                        $Image = $this->getClass('Image',$Capone->get('imageID'));
                        if (!$Image->isValid()) continue;
                        $OS = $Image->getOS();
                        if (!$OS->isValid()) continue;
                        $this->data[] = array(
                            'image_name'=>$Image->get('name'),
                            'os_name'=>$OS->get('name'),
                            'capone_key'=>$Capone->get('key'),
                            'link'=>sprintf('%s&kill=${capone_id}',$this->formAction),
                            'capone_id'=>$Capone->get('id'),
                        );
                        unset($Capone,$Image,$OS);
                    }
                    printf('<form method="post" action="%s">',$this->formAction);
                    $this->render();
                    echo '</form>';
                    unset($this->headerData,$this->data,$fields);
                    break;
                }
                break;
                case false:
                    printf('<p class="titleBottomLeft">%s</p><p>%s</p><div><form method="post" action="%s"><input type="submit" value="Install Plugin" name="install"/></form></div>',_('Plugin Installation'),_('This plugin is currently not installed, would you like to install it now?'),$this->formAction);
                    break;
            }
        } catch (Exception $e) {
            echo $this->setMessage($e->getMessage());
            $this->redirect(sprintf('?node=%s&sub=%s&run=%s',$_REQUEST['node'],$_REQUEST['sub'],$_REQUEST['run']));
        }
    }
    public function install_post() {
        $this->getClass('Plugin')->getRunInclude($_REQUEST['run']);
        $plugin = unserialize($_SESSION['fogactiveplugin']);
        if (isset($_REQUEST['install'])) {
            if($this->getClass(sprintf('%sManager',ucfirst($plugin->getName())))->install($plugin->getName())) {
                $Plugin = $this->getClass('PluginManager')->find(array('name'=>$plugin->getName()));
                foreach ((array)$this->getClass('PluginManager')->find(array('name'=>$plugin->getName())) AS $i => &$Plugin) {
                    if (!$Plugin->isValid()) continue;
                    $Plugin
                        ->set('installed',1)
                        ->set('version',1);
                    if (!$Plugin->save()) {
                        $this->setMessage(_('Plugin Install Failed!'));
                        break;
                    }
                    $this->setMessage(_('Plugin Installed!'));
                }
            }
            if ($_REQUEST['sub'] == 'install') $_REQUEST['sub'] = 'installed';
            $this->redirect(sprintf('?node=%s&sub=%s&run=%s',$_REQUEST['node'],$_REQUEST['sub'],$_REQUEST['run']));
        }
        if (isset($_REQUEST['basics'])) {
            $this->setSetting('FOG_PLUGIN_CAPONE_DMI',$_REQUEST['dmifield']);
            $this->setSetting('FOG_PLUGIN_CAPONE_SHUTDOWN',$_REQUEST['shutdown']);
        } else if (isset($_REQUEST['addass'])) {
            $this->getClass('Capone')
                ->set('imageID',$_REQUEST['image'])
                ->set('osID',$this->getClass('Image',$_REQUEST['image'])->getOS()->get('id'))
                ->set('key',$_REQUEST['key'])
                ->save();
        }
        if ($_REQUEST['kill']) $this->getClass('Capone',$_REQUEST['kill'])->destroy();
        $this->setMessage('Plugin updated!');
        $this->redirect($this->formAction);
    }
    public function removeplugin() {
        if ($_REQUEST['rmid']) $Plugin = $this->getClass('Plugin',$_REQUEST['rmid']);
        $Plugin->getManager()->uninstall();
        if ($Plugin->destroy()) {
            $this->setMessage('Plugin Removed');
            $this->redirect(sprintf('?node=%s&sub=activate',$_REQUEST['node']));
        }
    }
}
