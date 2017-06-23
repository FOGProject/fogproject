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
     * The plugin name storage.
     *
     * @var string
     */
    private $_strName;
    /**
     * The Plugin entry point/run file.
     *
     * @var string
     */
    private $_strEntryPoint;
    /**
     * The version of the plugin.
     *
     * @var string
     */
    private $_strVersion;
    /**
     * The path to the plugin.
     *
     * @var string
     */
    private $_strPath;
    /**
     * The icon storage.
     *
     * @var string
     */
    private $_strIcon;
    /**
     * The icon hover storage.
     *
     * @var string
     */
    private $_strIconHover;
    /**
     * Is the plugin installed.
     *
     * @var bool
     */
    private $_blIsInstalled = false;
    /**
     * Is the plugin active.
     *
     * @var bool
     */
    private $_blIsActive = false;
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
    protected $databaseFields = array(
        'id' => 'pID',
        'name' => 'pName',
        'state' => 'pState',
        'installed' => 'pInstalled',
        'version' => 'pVersion',
        'pAnon1' => 'pAnon1',
        'pAnon2' => 'pAnon2',
        'pAnon3' => 'pAnon3',
        'pAnon4' => 'pAnon4',
        'pAnon5' => 'pAnon5',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
    /**
     * Any additional Fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'description',
    );
    /**
     * Gets the needed include files to run.
     *
     * @param string $hash the hash to test for
     *
     * @return string
     */
    public function getRunInclude($hash)
    {
        foreach ((array) $this->getPlugins() as &$Plugin) {
            $name = trim($Plugin->get('name'));
            $tmpHash = md5($name);
            $tmpHash = trim($tmpHash);
            if ($tmpHash !== $hash) {
                continue;
            }
            break;
        }

        return array($name, $Plugin->_getEntryPoint());
    }
    /**
     * Sets/gets the active plugins.
     *
     * @return void
     */
    private function _getActivePlugs()
    {
        $this->_blIsActive = (bool) ($this->get('state'));
        $this->_blIsInstalled = (bool) ($this->get('installed'));
    }
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
        $RecursiveDirectoryIterator = new RecursiveDirectoryIterator(
            $dir,
            FileSystemIterator::SKIP_DOTS
        );
        $RecursiveIteratorIterator = new RecursiveIteratorIterator(
            $RecursiveDirectoryIterator
        );
        $RegexIterator = new RegexIterator(
            $RecursiveIteratorIterator,
            $regext,
            RegexIterator::GET_MATCH
        );
        $files = iterator_to_array($RegexIterator, false);
        unset(
            $RecursiveDirectoryIterator,
            $RecursiveIteratorIterator,
            $RegexIterator
        );
        $files = array_map($patternReplacer, (array) $files);
        natcasesort($files);
        $files = array_filter($files);
        $files = array_unique($files);
        $files = array_values($files);

        return $files;
    }
    /**
     * Gets plugins.
     *
     * @return array
     */
    public function getPlugins()
    {
        $Plugins = array();
        foreach ((array) $this->_getDirs() as &$file) {
            $pluginID = self::getSubObjectIDs(
                'Plugin',
                array('name' => basename($file))
            );
            $pluginID = @min($pluginID);
            $configFile = sprintf(
                '%s/config/plugin.config.php',
                rtrim($file, '/')
            );
            include $configFile;
            $Plugin = new self($pluginID);
            $Plugin
                ->set('name', $fog_plugin['name'])
                ->set('description', $fog_plugin['description']);
            $Plugin->_strPath = $file;
            $runFile = sprintf(
                '%s%s',
                $file,
                $fog_plugin['entrypoint']
            );
            $Plugin->_strEntryPoint = $runFile;
            $matchIcon = preg_match(
                '#^fa[-]?#',
                $fog_plugin['menuicon']
            );
            if ($matchIcon != false) {
                $Plugin->_strIcon = sprintf(
                    '<i class="%s" width="%d" height="%d" alt="%s"></i>',
                    $fog_plugin['menuicon'],
                    66,
                    66,
                    $Plugin->get('name')
                );
            } else {
                $Plugin->_strIcon = sprintf(
                    '<img src="%s" width="%d" height="%d" alt="%s"/>',
                    sprintf(
                        '%s%s',
                        $file,
                        $fog_plugin['menuicon']
                    ),
                    66,
                    66,
                    $Plugin->get('name')
                );
            }
            $matchIconHover = preg_match(
                '#^fa[-]?#',
                $fog_plugin['menuicon_hover']
            );
            if ($matchIconHover != false) {
                $Plugin->_strIconHover = sprintf(
                    '<i class="%s" width="%d" height="%d" alt="%s"></i>',
                    $fog_plugin['menuicon_hover'],
                    66,
                    66,
                    $Plugin->get('name')
                );
            } else {
                $Plugin->_strIconHover = sprintf(
                    '<img src="%s" width="%d" height="%d" alt="%s"/>',
                    sprintf(
                        '%s%s',
                        $file,
                        $fog_plugin['menuicon_hover']
                    ),
                    66,
                    66,
                    $Plugin->get('name')
                );
            }
            $Plugins[] = $Plugin;
            unset($file);
        }

        return $Plugins;
    }
    /**
     * Activate the matching hash named plugin.
     *
     * @param string $hash the hash of the name to activate
     *
     * @return object
     */
    public function activatePlugin($hash)
    {
        $hash = trim($hash);
        foreach ((array) $this->getPlugins() as &$Plugin) {
            $name = trim($Plugin->get('name'));
            $tmpHash = md5($name);
            $tmpHash = trim($tmpHash);
            if ($tmpHash !== $hash) {
                continue;
            }
            $Plugin
                ->set('state', 1)
                ->set('installed', 0)
                ->save();
            unset($Plugin);
        }

        return $this;
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
        if (!class_exists($classManager, false)) {
            return parent::getManager();
        }

        return new $classManager();
    }
    /**
     * Get's the plugin path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_strPath;
    }
    /**
     * Get's the plugin run point.
     *
     * @return string
     */
    private function _getEntryPoint()
    {
        return $this->_strEntryPoint;
    }
    /**
     * Get's the plugin's icon.
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->_strIcon;
    }
    /**
     * Get's the installed status of the plugin.
     *
     * @return bool
     */
    public function isInstalled()
    {
        $this->_getActivePlugs();

        return (bool) $this->_blIsInstalled;
    }
    /**
     * Get's the active status of the plugin.
     *
     * @return bool
     */
    public function isActive()
    {
        $this->_getActivePlugs();

        return (bool) $this->_blIsActive;
    }
    /**
     * Get's the plugin's version.
     *
     * @return bool
     */
    public function getVersion()
    {
        return $this->_strVersion;
    }
}
