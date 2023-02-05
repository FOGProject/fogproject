<?php
/**
 * Plugin class.
 *
 * PHP version 5
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin class.
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Plugin extends FOGController
{
    /**
     * The database table to look at.
     *
     * @var string
     */
    protected $databaseTable = 'plugins';
    /**
     * The common and database fields to use.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'pID',
        'name' => 'pName',
        'state' => 'pState',
        'installed' => 'pInstalled',
        'version' => 'pVersion',
        'icon' => 'pIcon',
        'runfile' => 'pRunfile',
        'location' => 'pLocation',
        'description' => 'pDescription',
        'pAnon5' => 'pAnon5'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Any additional Fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'description'
    ];
    /**
     * Gets the directories of plugins.
     *
     * @return array
     */
    private function _getDirs()
    {
        $dir = trim(self::getSetting('FOG_PLUGINSYS_DIR'));
        if ($dir != '../lib/plugins/') {
            self::setSetting('FOG_PLUGINSYS_DIR', '../lib/plugins/');
            $dir = '../lib/plugins/';
        }
        $patternReplacer = function ($element) {
            return preg_replace('#config/plugin\.config\.php$#i', '', $element[0]);
        };
        $regext = '#^.+/config/plugin\.config\.php$#i';
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        $patternReplacer,
                        self::fileitems(
                            '.config.php',
                            'config',
                            false,
                            false
                        )
                    )
                )
            )
        );
    }
    /**
     * Gets plugins.
     *
     * @return array
     */
    public function getPlugins()
    {
        $Plugins = [];
        foreach ((array) $this->_getDirs() as &$file) {
            Route::ids(
                'plugin',
                ['name' => basename($file)]
            );
            $pluginID = json_decode(Route::getData(), true);
            $pluginID = count($pluginID ?: []) ? @min($pluginID) : 0;
            $configFile = sprintf(
                '%s/config/plugin.config.php',
                rtrim($file, '/')
            );
            include $configFile;
            $runFile = sprintf(
                '%s%s',
                $file,
                $fog_plugin['entrypoint']
            );
            $matchIcon = preg_match(
                '#^fa[\-]?#',
                $fog_plugin['menuicon']
            );
            if (false == $matchIcon) {
                $icon = sprintf(
                    '<img src="%s" width="66" height="66"/>',
                    $fog_plugin['menuicon']
                );
            } else {
                $icon = sprintf(
                    '<i class="%s fa-2x" width="66" height="66"></i>',
                    $fog_plugin['menuicon']
                );
            }
            $plugin = self::getClass('Plugin', $pluginID)
                ->set('name', strtolower(basename($file)))
                ->set('description', $fog_plugin['description'])
                ->set('location', $file)
                ->set('runfile', $runFile)
                ->set('icon', $icon);
            $plugman = self::getClass('PluginManager');
            $nameexists = $plugman->exists(
                $plugin->get('name'),
                '',
                'name'
            );
            $descexists = $plugman->exists(
                $plugin->get('description'),
                '',
                'description'
            );
            $locexists = $plugman->exists(
                $plugin->get('location'),
                '',
                'location'
            );
            $runfileexists = $plugman->exists(
                $plugin->get('runfile'),
                '',
                'runfile'
            );
            $iconexists = $plugman->exists(
                $plugin->get('icon'),
                '',
                'icon'
            );
            if (!$nameexists
                || !$descexists
                || !$locexists
                || !$runfileexists
                || !$iconexists
            ) {
                $plugin->save();
            }
            $Plugins[] = $plugin;
            unset($file);
        }

        return $Plugins;
    }
    /**
     * Get's the plugin manager class or plugin's manager class as needed.
     *
     * @return object
     */
    public function getManager()
    {
        if (!$this->get('name')) {
            return parent::getManager();
        }
        $classManager = sprintf(
            '%sManager',
            $this->get('name')
        );
        if (!class_exists($classManager)) {
            return parent::getManager();
        }

        return new $classManager();
    }
}
