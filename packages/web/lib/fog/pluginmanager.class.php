<?php
/**
 * Plugin manager class.
 *
 * PHP version 5
 *
 * @category PluginManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Plugin manager class.
 *
 * @category PluginManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PluginManager extends FOGManagerController
{
    /**
     * Returns the installed plugins that have pending schema migrations.
     *
     * Independent of FOG_SCHEMA: each plugin self-reports via
     * Plugin::needsSchemaUpdate(). Used to drive the dashboard "needs
     * update" notice and the plugin list badge.
     *
     * @return array Array of Plugin objects needing an upgrade.
     */
    public function getPluginsNeedingUpdate()
    {
        $needing = [];
        // inputoverride = true so the caller's DataTables pagination cannot
        // truncate this list -- see Plugin::getPlugins(). Read-only here, so
        // the worst case was a missing "needs update" badge rather than a
        // damaged row, but it is the same mistake.
        $plugins = Route::getList('plugin', ['installed' => 1]);
        foreach ($plugins as $row) {
            $plugin = self::getClass('Plugin', $row->id);
            if ($plugin->needsSchemaUpdate()) {
                $needing[] = $plugin;
            }
        }
        return $needing;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\PluginManager', 'PluginManager');
