<?php
class PluginManagementPage extends FOGPage {
    public $node = 'plugin';
    public function __construct($name = '') {
        $this->name = 'Plugin Management';
        parent::__construct($this->name);
        $this->menu = array(
            'home'=>self::$foglang['Home'],
            'activate'=>self::$foglang['ActivatePlugins'],
            'install'=>self::$foglang['InstallPlugins'],
            'installed'=>self::$foglang['InstalledPlugins'],
        );
        self::$HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        $this->headerData = array(
            _('Plugin Name'),
            _('Description'),
            _('Location'),
        );
        $this->templates = array(
            '<a href="?node=plugin&sub=${type}&run=${encname}&${type}=${encname}" class="icon" title="Plugin: ${name}"><img width="66" height="66" alt="${name}" src="${icon}"/><br/><small>${name}</small></a>',
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
            array_push($this->attributes,array('class'=>'l filter-false'));
        }
    }
    public function index() {
        $this->activate();
    }
    public function activate() {
        $this->title = _('Activate Plugins');
        array_map(function(&$Plugin) {
            if ($Plugin->get('state')) return;
            $this->data[] = array(
                'name'=>$Plugin->get('name'),
                'type'=>'activate',
                'encname'=>trim(md5(trim($Plugin->get('name')))),
                'location'=>$Plugin->getPath(),
                'desc'=>$Plugin->get('description'),
                'icon'=>$Plugin->getIcon(),
            );
            unset($Plugin);
        },self::getClass($this->childClass)->getPlugins());
        self::$HookManager->processEvent('PLUGIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if (!empty($_REQUEST['activate']) && $_REQUEST['sub'] == 'activate') {
            self::getClass($this->childClass)->activatePlugin($_REQUEST['activate']);
            $this->setMessage(_('Successfully activated Plugin!'));
            $this->redirect(preg_replace('#&activate=.*&?#','',$this->formAction));
        }
    }
    public function install() {
        $this->title = 'Install Plugins';
        $P = null;
        array_map(function(&$Plugin) use (&$P) {
            if (!$Plugin->isActive() || $Plugin->isInstalled() || isset($_REQUEST['plug_name']) && $_REQUEST['plug_name'] != $Plugin->get('name')) return;
            $this->data[] = array(
                'name'=>$Plugin->get('name'),
                'type'=>'install',
                'encname'=>sprintf('%s&plug_name=%s',trim(md5(trim($Plugin->get('name')))),$Plugin->get('name')),
                'location'=>$Plugin->getPath(),
                'desc'=>$Plugin->get('description'),
                'icon'=>$Plugin->getIcon(),
                'pluginid'=>$Plugin->get('id') ? $Plugin->get('id') : '',
            );
            $P = $Plugin;
            unset($Plugin);
        },self::getClass($this->childClass)->getPlugins());
        self::$HookManager->processEvent('PLUGIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if ($_REQUEST['run']) {
            $runner = $P->getRunInclude($_REQUEST['run']);
            if (file_exists($runner) && $P->isInstalled()) require($runner);
            else $this->run();
        }
        unset($P);
    }
    public function installed() {
        $this->title = _('Installed Plugins');
        $P = null;
        array_map(function(&$Plugin) use (&$P) {
            if (!$Plugin->isActive() || !$Plugin->isInstalled()) return;
            $this->data[] = array(
                'name'=>$Plugin->get('name'),
                'type'=>'installed',
                'encname'=>trim(md5(trim($Plugin->get('name')))),
                'location'=>$Plugin->getPath(),
                'desc'=>$Plugin->get('description'),
                'icon'=>$Plugin->getIcon(),
                'pluginid'=>$Plugin->get('id') ? $Plugin->get('id') : '',
            );
            $P = $Plugin;
            unset($Plugin);
        },self::getClass($this->childClass)->getPlugins());
        self::$HookManager->processEvent('PLUGIN_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if ($_REQUEST['run']) {
            $runner = $P->getRunInclude($_REQUEST['run']);
            if (file_exists($runner) && $P->isInstalled()) require($runner);
            else $this->run();
        }
        unset($P);
    }
    public function run() {
        $plugin = self::getClass('Plugin',@min($this->getSubObjectIDs('Plugin',array('name'=>$_SESSION['fogactiveplugin']))));
        try {
            if ($plugin == null) throw new Exception(_('Unable to determine plugin details.'));
            $this->title = sprintf('%s: %s',_('Plugin'),$plugin->get('name'));
            printf('<p>%s: %s</p>',_('Plugin Description'),$plugin->get('description'));
            switch ($plugin->isInstalled()) {
            case true:
                switch (strtolower($plugin->get('name'))) {
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
                    array_map(function(&$dmifield) {
                        $checked = $this->getSetting('FOG_PLUGIN_CAPONE_DMI') == $dmifield ? ' selected' : '';
                        printf('<option value="%s" label="%s"%s>%s</option>',$dmifield,$dmifield,$checked,$dmifield);
                        unset($dmifield);
                    },(array)$dmiFields);
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
                    array_walk($fields,$this->fieldsToData);
                    printf('<form method="post" action="%s">',$this->formAction);
                    $this->render();
                    echo '</form>';
                    unset($this->headerData,$this->data,$fields);
                    printf('<p class="titleBottomLeft">%s</p>',_('Add Image to DMI Associations'));
                    $fields = array(
                        sprintf('%s:',_('Image Definition')) => self::getClass('ImageManager')->buildSelectBox(),
                        sprintf('%s:',_('DMI Result')) => '<input type="text" name="key"/>',
                        '' => sprintf('<input type="submit" style="margin-top: 7px;" name="addass" value="%s"/>',_('Add Association')),
                    );
                    array_walk($fields,$this->fieldsToData);
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
                        array('class'=>'l filter-false'),
                    );
                    array_map(function(&$Capone) {
                        if (!$Capone->isValid()) return;
                        $Image = self::getClass('Image',$Capone->get('imageID'));
                        if (!$Image->isValid()) return;
                        $OS = $Image->getOS();
                        if (!$OS->isValid()) return;
                        $this->data[] = array(
                            'image_name'=>$Image->get('name'),
                            'os_name'=>$OS->get('name'),
                            'capone_key'=>$Capone->get('key'),
                            'link'=>sprintf('%s&kill=${capone_id}',$this->formAction),
                            'capone_id'=>$Capone->get('id'),
                        );
                        unset($Capone,$Image,$OS);
                    },self::getClass('CaponeManager')->find());
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
        self::getClass('Plugin')->getRunInclude($_REQUEST['run']);
        $Plugin = self::getClass('Plugin',@min($this->getSubObjectIDs('Plugin',array('name'=>$_SESSION['fogactiveplugin']))));
        try {
            if (!$Plugin->isValid()) throw new Exception(_('Invalid Plugin Passed'));
            if (isset($_REQUEST['install'])) {
                if (!$Plugin->getManager()->install($Plugin->get('name'))) throw new Exception(sprintf('%s %s',_('Failed to install plugin'),$Plugin->get('name')));
                $Plugin
                    ->set('installed',1)
                    ->set('version',1);
                if (!$Plugin->save()) throw new Exception(sprintf('%s %s',_('Failed to save plugin'),$Plugin->get('name')));
                $this->formAction = preg_replace('#sub=install&#','sub=installed&',$this->formAction);
                throw new Exception(_('Plugin Installed!'));
            }
            if (isset($_REQUEST['basics'])) {
                $this->setSetting('FOG_PLUGIN_CAPONE_DMI',$_REQUEST['dmifield']);
                $this->setSetting('FOG_PLUGIN_CAPONE_SHUTDOWN',$_REQUEST['shutdown']);
                throw new Exception(_('Settings Updated'));
            }
            if (isset($_REQUEST['addass'])) {
                $Capone = self::getClass('Capone')
                    ->set('imageID',$_REQUEST['image'])
                    ->set('osID',self::getClass('Image',$_REQUEST['image'])->getOS()->get('id'))
                    ->set('key',$_REQUEST['key']);
                if (!$Capone->save()) throw new Exception(_('Failed to save assignment'));
                throw new Exception(_('Assignment saved successfully'));
            }
            if ($_REQUEST['kill']) {
                self::getClass('Capone',$_REQUEST['kill'])->destroy();
                throw new Exception(_('Destroyed assignment'));
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
        }
        $this->redirect($this->formAction);
    }
    public function removeplugin() {
        if ($_REQUEST['rmid']) $Plugin = self::getClass('Plugin',$_REQUEST['rmid']);
        $Plugin->getManager()->uninstall();
        if ($Plugin->destroy()) {
            $this->setMessage('Plugin Removed');
            $this->redirect(sprintf('?node=%s&sub=activate',$_REQUEST['node']));
        }
    }
}
