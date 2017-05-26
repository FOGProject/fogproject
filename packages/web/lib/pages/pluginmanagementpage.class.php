<?php
/**
 * Plugin management page
 *
 * PHP version 5
 *
 * @category PluginManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin management page
 *
 * @category PluginManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PluginManagementPage extends FOGPage
{
    /**
     * Stores all the plugins from our caller
     *
     * @var array
     */
    private static $_plugins = array();
    /**
     * The node that uses this item
     *
     * @var string
     */
    public $node = 'plugin';
    /**
     * Initialize the plugin page
     *
     * @param string $name the name of the page.
     *
     * @return false;
     */
    public function __construct($name = '')
    {
        $this->name = 'Plugin Management';
        parent::__construct($this->name);
        self::$_plugins = self::getClass('Plugin')->getPlugins();
        $this->menu = array(
            'home'=>self::$foglang['Home'],
            'activate'=>self::$foglang['ActivatePlugins'],
            'install'=>self::$foglang['InstallPlugins'],
            'installed'=>self::$foglang['InstalledPlugins'],
        );
        self::$HookManager->processEvent(
            'SUB_MENULINK_DATA',
            array(
                'menu' => &$this->menu,
                'submenu' => &$this->subMenu,
                'id' => &$this->id,
                'notes' => &$this->notes
            )
        );
        $this->headerData = array(
            _('Plugin Name'),
            _('Description'),
            _('Location'),
        );
        $this->templates = array(
            '<a href="?node=plugin&sub=${type}&run=${encname}&${type}'
            . '=${encname}" class="icon" title="Plugin: ${name}">${icon}'
            . '<br/><small>${name}</small></a>',
            '${desc}',
            '${location}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
        );
        global $sub;
        $subs = array('installed', 'install');
        if (in_array($sub, $subs)) {
            array_push(
                $this->headerData,
                _('Remove')
            );
            array_push(
                $this->templates,
                '<a href="?node=plugin&sub=removeplugin&rmid='
                . '${pluginid}"><i class="icon fa fa-minus-circle" '
                . 'title="Remove Plugin"></i></a>'
            );
            array_push(
                $this->attributes,
                array(
                    'class' => 'l filter-false'
                )
            );
        }
    }
    /**
     * The basic function / home page of the class if you will
     *
     * @return void
     */
    public function index()
    {
        $this->activate();
    }
    /**
     * The activation sub
     *
     * @return void
     */
    public function activate()
    {
        $this->title = _('Activate Plugins');
        foreach (self::$_plugins as &$Plugin) {
            if ($Plugin->get('state')) {
                continue;
            }
            $name = trim($Plugin->get('name'));
            $hash = md5($name);
            $this->data[] = array(
                'name' => $name,
                'type' => 'activate',
                'encname' => $hash,
                'location' => $Plugin->getPath(),
                'desc' => $Plugin->get('description'),
                'icon' => $Plugin->getIcon()
            );
            unset($Plugin);
        }
        self::$HookManager->processEvent(
            'PLUGIN_DATA',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        if (isset($_REQUEST['activate'])) {
            self::getClass('Plugin')->activatePlugin($_REQUEST['activate']);
            self::setMessage(_('Successfully activated Plugin!'));
            $this->formAction = preg_replace(
                '#&activate=.*&?#',
                '',
                $this->formAction
            );
            self::redirect($this->formAction);
        }
    }
    /**
     * Sub of install
     *
     * @return void
     */
    public function install()
    {
        $this->title = 'Install Plugins';
        foreach (self::$_plugins as &$Plugin) {
            if (!$Plugin->isActive() || $Plugin->isInstalled()) {
                continue;
            }
            if (isset($_REQUEST['plug_name'])) {
                if ($_REQUEST['plug_name'] != $Plugin->get('name')) {
                    continue;
                }
            }
            $name = trim($Plugin->get('name'));
            $hash = md5($name);
            $this->formAction .= sprintf(
                '&run=%s&plug_name=%s',
                $hash,
                $name
            );
            $this->data[] = array(
                'name' => $name,
                'type' => 'install',
                'encname' => sprintf(
                    '%s&plug_name=%s',
                    $hash,
                    $name
                ),
                'location' => $Plugin->getPath(),
                'desc' => $Plugin->get('description'),
                'icon' => $Plugin->getIcon(),
                'pluginid' => $Plugin->get('id'),
            );
            unset($Plugin);
        }
        self::$HookManager->processEvent(
            'PLUGIN_DATA',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        foreach (self::$_plugins as &$Plugin) {
            if (!$_REQUEST['run']) {
                continue;
            }
            $hash = trim(
                basename($_REQUEST['run'])
            );
            $name = trim($Plugin->get('name'));
            $tmpHash = md5($name);
            if ($tmpHash !== $hash) {
                continue;
            }
            list(
                $name,
                $runner
            ) = $Plugin->getRunInclude($hash);
            if (!file_exists($runner)) {
                return $this->run($Plugin);
            }
            include $runner;
            unset($Plugin);
            break;
        }
    }
    /**
     * The installed portion with the sub.
     *
     * @return void
     */
    public function installed()
    {
        $this->title = _('Installed Plugins');
        foreach (self::$_plugins as &$Plugin) {
            if (!$Plugin->isActive() || !$Plugin->isInstalled()) {
                continue;
            }
            $name = trim($Plugin->get('name'));
            $hash = md5($name);
            $this->data[] = array(
                'name' => $name,
                'type' => 'installed',
                'encname' => $hash,
                'location' => $Plugin->getPath(),
                'desc' => $Plugin->get('description'),
                'icon' => $Plugin->getIcon(),
                'pluginid' => $Plugin->get('id'),
            );
            unset($Plugin);
        }
        self::$HookManager->processEvent(
            'PLUGIN_DATA',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        foreach (self::$_plugins as &$Plugin) {
            if (!$_REQUEST['run']) {
                continue;
            }
            $hash = trim(
                basename($_REQUEST['run'])
            );
            $name = trim($Plugin->get('name'));
            $tmpHash = md5($name);
            if ($tmpHash !== $hash) {
                continue;
            }
            list(
                $name,
                $runner
            ) = $Plugin->getRunInclude($hash);
            if (!file_exists($runner)) {
                return $this->run($Plugin);
            }
            include $runner;
            unset($Plugin);
            break;
        }
    }
    /**
     * Perform running actions
     *
     * @param object $plugin the plugin to run
     *
     * @return void
     */
    public function run($plugin)
    {
        try {
            if ($plugin == null) {
                throw new Exception(_('Unable to determine plugin details.'));
            }
            $this->title = sprintf(
                '%s: %s',
                _('Plugin'),
                $plugin->get('name')
            );
            printf(
                '<p>%s: %s</p>',
                _('Plugin Description'),
                $plugin->get('description')
            );
            if (!$plugin->isInstalled()) {
                printf(
                    '<p class="titleBottomLeft">%s</p><p>%s, %s?</p><div>'
                    . '<form method="post" action="%s"><input type="submit" '
                    . 'value="Install Plugin" name="install"/></form></div>',
                    _('Plugin Installation'),
                    _('This plugin is currently not installed'),
                    _('would you like to install it now'),
                    $this->formAction
                );
            } else {
                $name = strtolower($plugin->get('name'));
                $hash = trim($_REQUEST['run']);
                $tmpHash = md5($name);
                if ($name === 'capone' && $hash === $tmpHash) {
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
                    printf(
                        '<p class="titleBottomLeft">%s</p>',
                        _('Settings')
                    );
                    unset($this->headerData, $this->data);
                    $this->templates = array(
                        '${field}',
                        '${input}',
                    );
                    $this->attributes = array(
                        array(),
                        array(),
                    );
                    list($dbField, $dbShutdown) = self::getSubObjectIDs(
                        'Service',
                        array(
                            'name' => array(
                                'FOG_PLUGIN_CAPONE_DMI',
                                'FOG_PLUGIN_CAPONE_SHUTDOWN',
                            )
                        ),
                        'value',
                        false,
                        'AND',
                        'name',
                        false,
                        false
                    );
                    ob_start();
                    foreach ($dmiFields as &$dmifield) {
                        $checked = '';
                        if ($dbField == $dmifield) {
                            $checked = ' selected';
                        }
                        printf(
                            '<option value="%s"%s>%s</option>',
                            $dmifield,
                            $checked,
                            $dmifield
                        );
                        unset($dmifield);
                    }
                    $dmiOpts = ob_get_clean();
                    $actionFields = array(
                        _('Reboot after deploy'),
                        _('Shutdown after deploy'),
                    );
                    ob_start();
                    foreach ($actionFields as $id => &$value) {
                        $checked = '';
                        if ((int)$id === (int)$dbShutdown) {
                            $checked = ' selected';
                        }
                        printf(
                            '<option value="%s"%s>%s</option>',
                            $id,
                            $checked,
                            $value
                        );
                        unset($value);
                    }
                    $shutOpts = ob_get_clean();
                    $fields = array(
                        sprintf(
                            '%s:',
                            _('DMI Field')
                        ) => sprintf(
                            '<select name="dmifield" size="1"><option value="">'
                            . '- %s -</option>%s</select>',
                            _('Please select an option'),
                            $dmiOpts
                        ),
                        sprintf(
                            '%s:',
                            _('Shutdown')
                        ) => sprintf(
                            '<select name="shutdown" size="1"><option value="">'
                            . '- %s -</option>%s</select>',
                            _('Please Select an option'),
                            $shutOpts
                        ),
                        '&nbsp;' => sprintf(
                            '<input type="submit" '
                            . 'name="basics" value="%s"/>',
                            _('Update Settings')
                        ),
                    );
                    array_walk($fields, $this->fieldsToData);
                    printf(
                        '<form method="post" action="%s">',
                        $this->formAction
                    );
                    $this->render();
                    echo '</form>';
                    unset($this->headerData, $this->data, $fields);
                    printf(
                        '<p class="titleBottomLeft">%s</p>',
                        _('Add Image to DMI Associations')
                    );
                    $images = self::getClass('ImageManager')->buildSelectBox();
                    $fields = array(
                        sprintf(
                            '%s:',
                            _('Image Definition')
                        ) => $images,
                        sprintf(
                            '%s:',
                            _('DMI Result')
                        ) => '<input type="text" name="key"/>',
                        '&nbsp;' => sprintf(
                            '<input type="submit" '
                            . 'name="addass" value="%s"/>',
                            _('Add Association')
                        ),
                    );
                    array_walk($fields, $this->fieldsToData);
                    printf(
                        '<form method="post" action="%s">',
                        $this->formAction
                    );
                    $this->render();
                    echo '</form>';
                    unset($this->headerData, $this->data, $fields);
                    printf(
                        '<p class="titleBottomLeft">%s</p>',
                        _('Current Image to DMI Associations')
                    );
                    $this->headerData = array(
                        '<input type="checkbox" id="checkAll" '
                        . 'name="toggle-checkbox"/><label for="checkAll"></label>',
                        _('Image Name'),
                        _('OS Name'),
                        _('DMI Key'),
                    );
                    $this->templates = array(
                        '<input type="checkbox" name="kill[]" value="${id}"'
                        . '${class} id="kill-${id}"/>'
                        . '<label for="kill-${id}"></label>',
                        '${image_name}',
                        '${os_name}',
                        '${capone_key}',
                    );
                    $this->attributes = array(
                        array(
                            'width' => 16,
                            'class' => 'l filter-false'
                        ),
                        array(),
                        array(),
                        array(),
                    );
                    foreach ((array)self::getClass('CaponeManager')
                        ->find() as &$Capone
                    ) {
                        $Image = $Capone->getImage();
                        if (!$Image->isValid()) {
                            continue;
                        }
                        $OS = $Image->getOS();
                        if (!$OS->isValid()) {
                            continue;
                        }
                        $this->data[] = array(
                            'image_name' => $Image->get('name'),
                            'os_name' => $OS->get('name'),
                            'capone_key' => $Capone->get('key'),
                            'id' => $Capone->get('id'),
                            'class' => ' class="checkboxes"',
                        );
                        unset($Capone, $Image, $OS);
                    }
                    printf(
                        '<form method="post" action="%s">',
                        $this->formAction
                    );
                    $this->render();
                    if (count($this->data) > 0) {
                        echo '<p class="c">';
                        printf(
                            '<input type="submit" name="rmAssocs" value="%s"/>',
                            _('Remove selected associations')
                        );
                        echo '</p>';
                    }
                    echo '</form>';
                    unset($this->headerData, $this->data, $fields);
                }
            }
        } catch (Exception $e) {
            echo self::setMessage($e->getMessage());
            $url = sprintf(
                '?node=%s&sub=%s&run=%s',
                $_REQUEST['node'],
                $_REQUEST['sub'],
                $_REQUEST['run']
            );
            self::redirect($url);
        }
    }
    /**
     * Runs the form request for install
     *
     * @return void
     */
    public function installPost()
    {
        $this->installedPost();
    }
    /**
     * Runs the form request for installed
     *
     * @return void
     */
    public function installedPost()
    {
        list(
            $pluginname,
            $entrypoint
        ) = self::getClass('Plugin')->getRunInclude($_REQUEST['run']);
        $Plugin = self::getClass('Plugin')
            ->set('name', $pluginname)
            ->load('name');
        try {
            if (!$Plugin->isValid()) {
                throw new Exception(_('Invalid Plugin Passed'));
            }
            if (isset($_REQUEST['install'])) {
                if (!$Plugin->getManager()->install($Plugin->get('name'))) {
                    $msg = sprintf(
                        '%s %s',
                        _('Failed to install plugin'),
                        $Plugin->get('name')
                    );
                    throw new Exception($msg);
                }
                $Plugin
                    ->set('installed', 1)
                    ->set('version', 1);
                if (!$Plugin->save()) {
                    $msg = sprintf(
                        '%s %s',
                        _('Failed to save plugin'),
                        $Plugin->get('name')
                    );
                    throw new Exception($msg);
                }
                $this->formAction = sprintf(
                    '?node=plugin&sub=installed&run=%s',
                    $_REQUEST['run']
                );
                throw new Exception(_('Plugin Installed!'));
            }
            if (isset($_REQUEST['basics'])) {
                self::getClass('Service')
                    ->set('name', 'FOG_PLUGIN_CAPONE_DMI')
                    ->load('name')
                    ->set('value', $_REQUEST['dmifield'])
                    ->save();
                self::getClass('Service')
                    ->set('name', 'FOG_PLUGIN_CAPONE_SHUTDOWN')
                    ->load('name')
                    ->set('value', $_REQUEST['shutdown'])
                    ->save();
                throw new Exception(_('Settings Updated'));
            }
            if (isset($_REQUEST['addass'])) {
                $Image = new Image($_REQUEST['image']);
                if (!$Image->isValid()) {
                    throw new Exception(_('Must have an image associated'));
                }
                $OS = $Image->getOS();
                $Capone = self::getClass('Capone')
                    ->set('imageID', $_REQUEST['image'])
                    ->set('osID', $OS->get('id'))
                    ->set('key', $_REQUEST['key']);
                if (!$Capone->save()) {
                    throw new Exception(_('Failed to save assignment'));
                }
                throw new Exception(_('Assignment saved successfully'));
            }
            if (isset($_REQUEST['rmAssocs'])) {
                self::getClass('CaponeManager')
                    ->destroy(array('id' => $_REQUEST['kill']));
                if (count($_REQUEST['kill']) !== 1) {
                    throw new Exception(_('Destroyed assignments'));
                } else {
                    throw new Exception(_('Destroyed assignment'));
                }
            }
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
        }
        self::redirect($this->formAction);
    }
    /**
     * Removes a plugin
     *
     * @return void
     */
    public function removeplugin()
    {
        if ($_REQUEST['rmid']) {
            $Plugin = self::getClass('Plugin', $_REQUEST['rmid']);
        }
        $Plugin->getManager()->uninstall();
        if ($Plugin->destroy()) {
            self::setMessage('Plugin Removed');
        }
        $url = sprintf(
            '?node=%s&sub=activate',
            $this->node
        );
        self::redirect($url);
    }
}
