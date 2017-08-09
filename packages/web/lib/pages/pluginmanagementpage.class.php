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
     * Stores the type of sub we're working on.
     *
     * @var string
     */
    private static $_plugintype = 'activate';
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
        Route::listem('plugin');
        self::$_plugins = json_decode(
            Route::getData()
        );
        self::$_plugins = self::$_plugins->plugins;
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
            array(
                'width' => 66,
                'height' => 66
            ),
            array('class' => 'col-xs-8'),
            array('class' => 'col-xs-3'),
        );
        global $sub;
        $subs = array('installed', 'install');
        if (in_array($sub, $subs)) {
            array_unshift(
                $this->headerData,
                '<label for="toggler">'
                . '<input type="checkbox" name="toggle-checkbox" '
                . 'class="toggle-checkboxAction" id="toggler"/>'
                . '</label>'
            );
            array_unshift(
                $this->templates,
                '<label for="pluginrm-${name}">'
                . '<input type="checkbox" name="pluginrm[]" '
                . 'value="${id}" class="toggle-action" id="pluginrm-${name}"/>'
                . '</label>'
            );
            array_unshift(
                $this->attributes,
                array(
                    'class' => 'filter-false form-group',
                    'width' => 16
                )
            );
        }
        /**
         * Lambda function to return data for list.
         *
         * @param object $Plugin the plugin to use
         *
         * @return void
         */
        self::$returnData = function (&$Plugin) {
            switch (self::$_plugintype) {
            case 'install':
                if (!$Plugin->state || $Plugin->installed) {
                    return;
                }
                break;
            case 'installed':
                if (!$Plugin->state || !$Plugin->installed) {
                    return;
                }
                break;
            case 'activate':
                if ($Plugin->state || $Plugin->installed) {
                    return;
                }
                break;
            }
            $this->data[] = array(
                'type' => self::$_plugintype,
                'encname' => md5($Plugin->name),
                'id' => $Plugin->id,
                'name' => $Plugin->name,
                'icon' => $Plugin->icon,
                'desc' => $Plugin->description,
                'location' => $Plugin->location
            );
            unset($Plugin);
        };
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
        self::$_plugintype = 'activate';
        array_walk(self::$_plugins, static::$returnData);
        self::$HookManager->processEvent(
            'PLUGIN_DATA',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        echo '<div class="col-xs-9">';
        $this->indexDivDisplay();
        echo '</div>';
        $activate = filter_input(INPUT_GET, 'activate');
        if ($activate) {
            self::getClass('Plugin')->activatePlugin($activate);
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
        $this->title = _('Install Plugins');
        self::$_plugintype = 'install';
        array_walk(self::$_plugins, static::$returnData);
        self::$HookManager->processEvent(
            'PLUGIN_DATA',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $runset = trim(
            filter_input(INPUT_GET, 'run')
        );
        echo '<div class="col-xs-9">';
        if ($runset) {
            $this->indexDivDisplay();
            foreach (self::$_plugins as &$Plugin) {
                $hash = trim($Plugin->hash);
                $name = $Plugin->name;
                if ($hash !== $runset) {
                    continue;
                }
                list(
                    $name,
                    $runner
                ) = $Plugin->runinclude;
                if (!file_exists($runner)) {
                    $this->run($Plugin);
                    echo '</div>';
                    return;
                }
                include $runner;
                break;
                unset($Plugin);
            }
        }
        $this->indexDivDisplay(true, false, true);
        echo '</div>';
    }
    /**
     * The installed portion with the sub.
     *
     * @return void
     */
    public function installed()
    {
        $this->title = _('Installed Plugins');
        self::$_plugintype = 'installed';
        array_walk(self::$_plugins, static::$returnData);
        self::$HookManager->processEvent(
            'PLUGIN_DATA',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $runset = trim(
            filter_input(INPUT_GET, 'run')
        );
        echo '<div class="col-xs-9">';
        if ($runset) {
            $this->indexDivDisplay();
            foreach (self::$_plugins as &$Plugin) {
                $hash = trim($Plugin->hash);
                $name = $Plugin->name;
                if ($hash !== $runset) {
                    continue;
                }
                list(
                    $name,
                    $runner
                ) = $Plugin->runinclude;
                if (!file_exists($runner)) {
                    $this->run($Plugin);
                    echo '</div>';
                    return;
                }
                include $runner;
                break;
                unset($Plugin);
            }
        }
        $this->indexDivDisplay(true, false, true);
        echo '</div>';
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
            if (!$plugin) {
                throw new Exception(_('Unable to determine plugin details.'));
            }
            $this->title = _('Plugin')
                . ' '
                . $plugin->name;
            unset(
                $this->data,
                $this->form,
                $this->headerData,
                $this->templates,
                $this->attributes
            );
            $this->templates = array(
                '${field}',
                '${input}'
            );
            $this->attributes = array(
                array('class' => 'col-xs-4'),
                array('class' => 'col-xs-8 form-group')
            );
            $fields = array(
                _('Plugin Description') => $plugin->description
            );
            if (!$plugin->installed) {
                $fields = self::fastmerge(
                    (array)$fields,
                    array(
                        _('Plugin Installation') => _('This plugin is not installed')
                        . ', '
                        . _('would you ilke to install it now')
                        . '?'
                    ),
                    array(
                        '<label for="installplugin">'
                        . _('Install Plugin')
                        . '</label>' => '<button class="btn btn-info btn-block" '
                        . 'id="installplugin" name="installplugin" type="submit">'
                        . _('Install')
                        . '</button>'
                    )
                );
                array_walk($fields, $this->fieldsToData);
                echo '<form class="form-horizontal" method="post" action="'
                    . $this->formAction
                    . '&run='
                    . $plugin->hash
                    . '">';
                $this->indexDivDisplay(true, false, true);
                echo '</form>';
            } else {
                array_walk($fields, $this->fieldsToData);
                $this->indexDivDisplay(true, false, true);
                $run = filter_input(INPUT_GET, 'run');
                if ('capone' === $plugin->name && $run === $plugin->hash) {
                    echo '<form class="form-horizontal" method="post" action="'
                        . $this->formAction
                        . '&run='
                        . $plugin->hash
                        . '">';
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
                    $actionFields = array(
                        _('Reboot after deploy'),
                        _('Shutdown after deploy'),
                    );
                    unset(
                        $this->data,
                        $this->form,
                        $this->headerData,
                        $this->templates,
                        $this->attributes
                    );
                    $this->title = _('Basic Settings');
                    $this->templates = array(
                        '${field}',
                        '${input}'
                    );
                    $this->attributes = array(
                        array('class' => 'col-xs-4'),
                        array('class' => 'col-xs-8 form-group')
                    );
                    list(
                        $dmifield,
                        $shutdown
                    ) = self::getSubObjectIDs(
                        'Service',
                        array(
                            'name' => array(
                                'FOG_PLUGIN_CAPONE_DMI',
                                'FOG_PLUGIN_CAPONE_SHUTDOWN',
                            )
                        ),
                        'value'
                    );
                    $dmiSel = self::selectForm(
                        'dmifield',
                        $dmiFields,
                        $dmifield
                    );
                    $actionSel = self::selectForm(
                        'shutdown',
                        $actionFields,
                        $shutdown,
                        true
                    );
                    $fields = array(
                        '<label for="dmifield">'
                        . _('DMI Field')
                        . '</label>' => $dmiSel,
                        '<label for="shutdown">'
                        . _('After image Action')
                        . '</label>' => $actionSel,
                        '<label for="basics">'
                        . _('Make Changes?')
                        . '</label>' => '<button class="btn btn-info btn-block" '
                        . 'name="basics" id="basics">'
                        . _('Update')
                        . '</button>'
                    );
                    array_walk($fields, $this->fieldsToData);
                    $this->indexDivDisplay();
                    unset(
                        $fields,
                        $this->data,
                        $this->form,
                        $this->headerData
                    );
                    $images = self::getClass('ImageManager')->buildSelectBox();
                    $this->title = _('Image Associations');
                    $fields = array(
                        '<label for="image">'
                        . _('Image Definition')
                        . '</label>' => $images,
                        '<label for="dmiresult">'
                        . _('DMI Result')
                        . '</label>' => '<div class="input-group">'
                        . '<input class="form-control" '
                        . 'type="text" name="key" id="dmiresult"/>'
                        . '</div>',
                        '<label for="addass">'
                        . _('Make Changes?')
                        . '</label>' => '<button class="btn btn-info btn-block" '
                        . 'name="addass" id="addass">'
                        . _('Update')
                        . '</button>'
                    );
                    array_walk($fields, $this->fieldsToData);
                    $this->indexDivDisplay();
                    unset(
                        $fields,
                        $images,
                        $this->data,
                        $this->form,
                        $this->headerData
                    );
                    $this->title = _('Image to DMI Mappings');
                    $this->headerData = array(
                        '<label for="toggler">'
                        . '<input type="checkbox" name="toggle-checkbox" '
                        . 'id="checkAll"/>'
                        . '</label>',
                        _('Image Name'),
                        _('OS Name'),
                        _('DMI Key')
                    );
                    $this->templates = array(
                        '<label for="kill-${id}">'
                        . '<input type="checkbox" name="kill[]" value="${id}" '
                        . 'id="kill-${id}" class="checkboxes"/>'
                        . '</label>',
                        '<a href="?node=image&sub=edit&id=${image_id}">'
                        . '${image_name}'
                        . '</a>',
                        '${os_name}',
                        '${capone_key}'
                    );
                    $this->attributes = array(
                        array(
                            'width' => 16,
                            'class' => 'filter-false'
                        ),
                        array(),
                        array(),
                        array(),
                    );
                    Route::listem('capone');
                    $Capones = json_decode(
                        Route::getData()
                    );
                    $Capones = $Capones->capones;
                    foreach ($Capones as &$Capone) {
                        $this->data[] = array(
                            'image_name' => $Capone->image->name,
                            'image_id' => $Capone->image->id,
                            'os_name' => $Capone->os->name,
                            'capone_key' => $Capone->key,
                            'id' => $Capone->id
                        );
                        unset($Capone);
                    }
                    echo '<div class="panel panel-info">';
                    echo '<div class="panel-heading text-center">';
                    echo '<h4 class="title">';
                    echo $this->title;
                    echo '</h4>';
                    echo '</div>';
                    echo '<div class="panel-body">';
                    echo '<div class="panel panel-info">';
                    echo '<div class="panel-heading text-center">';
                    echo '<h4 class="title">';
                    echo _('Current Associations');
                    echo '</h4>';
                    echo '</div>';
                    echo '<div class="panel-body">';
                    $this->render(12);
                    echo '</div>';
                    echo '</div>';
                    unset(
                        $this->data,
                        $this->form,
                        $this->headerData,
                        $this->templates,
                        $this->attributes
                    );
                    $this->templates = array(
                        '${field}',
                        '${input}'
                    );
                    $this->attributes = array(
                        array('class' => 'col-xs-4'),
                        array('class' => 'col-xs-8 form-group')
                    );
                    $fields = array(
                        '<label for="delcapone">'
                        . _('Remove Selected?')
                        . '</label>' => '<button class="btn btn-danger btn-block" '
                        . 'type="submit" name="rmAssocs" id="delcapone">'
                        . _('Delete')
                        . '</button>'
                    );
                    array_walk($fields, $this->fieldsToData);
                    echo '<div class="panel panel-warning">';
                    echo '<div class="panel-heading text-center">';
                    echo '<h4 class="title">';
                    echo _('Remove Associations');
                    echo '</h4>';
                    echo '</div>';
                    echo '<div class="panel-body">';
                    $this->render(12);
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</form>';
                }
            }
        } catch (Exception $e) {
            echo self::setMessage($e->getMessage());
            global $sub;
            global $node;
            $run = filter_input(INPUT_GET, 'run');
            $url = sprintf(
                '?node=%s&sub=%s&run=%s',
                $node,
                $sub,
                $run
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
        $run = filter_input(INPUT_GET, 'run');
        $this->formAction .= '&run='
            . $run;
        list(
            $pluginname,
            $entrypoint
        ) = self::getClass('Plugin')->getRunInclude($run);
        $Plugin = self::getClass('Plugin')
            ->set('name', $pluginname)
            ->load('name');
        try {
            if (!$Plugin->isValid()) {
                throw new Exception(_('Invalid Plugin Passed'));
            }
            if (isset($_POST['installplugin'])) {
                if (!$Plugin->getManager()->install($pluginname)) {
                    $msg = sprintf(
                        '%s %s',
                        _('Failed to install plugin'),
                        $pluginname
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
                        $pluginname
                    );
                    throw new Exception($msg);
                }
                $this->formAction = sprintf(
                    '?node=plugin&sub=installed&run=%s',
                    $run
                );
                throw new Exception(_('Plugin Installed!'));
            }
            if (isset($_POST['basics'])) {
                $dmifield = filter_input(INPUT_POST, 'dmifield');
                $shutdown = (int)filter_input(INPUT_POST, 'shutdown');
                self::getClass('Service')
                    ->set('name', 'FOG_PLUGIN_CAPONE_DMI')
                    ->load('name')
                    ->set('value', $dmifield)
                    ->save();
                self::getClass('Service')
                    ->set('name', 'FOG_PLUGIN_CAPONE_SHUTDOWN')
                    ->load('name')
                    ->set('value', $shutdown)
                    ->save();
            }
            if (isset($_POST['addass'])) {
                $key = filter_input(INPUT_POST, 'key');
                $image = (int)filter_input(INPUT_POST, 'image');
                $Image = new Image($image);
                if (!$Image->isValid()) {
                    throw new Exception(_('Must have an image associated'));
                }
                $OS = $Image->getOS();
                $Capone = self::getClass('Capone')
                    ->set('imageID', $image)
                    ->set('osID', $OS->get('id'))
                    ->set('key', $key);
                if (!$Capone->save()) {
                    throw new Exception(_('Failed to save assignment'));
                }
                throw new Exception(_('Assignment saved successfully'));
            }
            if (isset($_POST['rmAssocs'])) {
                $kill = filter_input_array(
                    INPUT_POST,
                    array(
                        'kill' => array(
                            'flags' => FILTER_REQUIRE_ARRAY
                        )
                    )
                );
                $kill = $kill['kill'];
                self::getClass('CaponeManager')
                    ->destroy(array('id' => $kill));
                if (count($kill) !== 1) {
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
}
