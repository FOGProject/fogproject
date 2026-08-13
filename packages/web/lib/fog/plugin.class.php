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
        'schema' => 'pSchema',
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
     * The roots a plugin may live under, in precedence order.
     *
     * Bundled plugins ship inside the web tree and are re-laid from the
     * tarball on every upgrade. Third-party plugins live under
     * FOG_PLUGIN_DIR, outside $webdirdest, because configureHttpd() does
     * `rm -rf $webdirdest` and would otherwise delete them (ADR 0009).
     *
     * Bundled is listed first and wins a name collision -- see _getDirs().
     *
     * @return array absolute paths, each with a trailing separator
     */
    public static function pluginRoots()
    {
        $roots = [rtrim(BASEPATH, DS) . DS . 'lib' . DS . 'plugins' . DS];
        if (defined('FOG_PLUGIN_DIR') && is_dir(FOG_PLUGIN_DIR)) {
            $roots[] = rtrim(FOG_PLUGIN_DIR, DS) . DS;
        }
        return $roots;
    }
    /**
     * Gets the directories of plugins.
     *
     * Globs each root one level deep for <root>/<name>/config/
     * plugin.config.php. This used to filter self::fileitems(), which since
     * 698b6dc6c ("cache the BASEPATH class-file scan") filters
     * Initiator::classFileList() -- a list built from a regex matching only
     * *.class.php, *.page.php, *.hook.php, *.event.php and *.report.php.
     * plugin.config.php matches none of those, so the filter could never
     * return anything and discovery had been silently finding zero plugins:
     * a fresh install offered none to install at all, and a newly added
     * directory was never picked up. Existing installs kept working only
     * because the `plugins` rows written before that commit still described
     * them, which also left those rows frozen at whatever they said then.
     *
     * A targeted glob is also cheaper than what it replaced -- two shallow
     * globs instead of a filter over a recursive walk of the whole tree.
     *
     * @return array
     */
    private function _getDirs()
    {
        $dirs = [];
        $seen = [];
        foreach (self::pluginRoots() as $root) {
            foreach ((array)glob($root . '*' . DS . 'config' . DS . 'plugin.config.php') as $config) {
                $dir = dirname(dirname($config)) . DS;
                $name = strtolower(basename($dir));
                // A plugin in a later root sharing a name with an earlier one
                // is REFUSED, not merged and not shadowed. Silently letting an
                // external directory take over a bundled plugin's node is a
                // supply-chain trick, and the reverse -- an upgrade quietly
                // disabling an admin's plugin -- is just as surprising. Name
                // both paths so whichever it is can be resolved by hand.
                if (isset($seen[$name])) {
                    error_log(
                        sprintf(
                            '%s: %s (%s, %s). %s',
                            _('Duplicate plugin name; the later one is ignored'),
                            $name,
                            $seen[$name],
                            $dir,
                            _('Rename or remove one of them.')
                        )
                    );
                    continue;
                }
                $seen[$name] = $dir;
                $dirs[] = $dir;
            }
        }
        return $dirs;
    }
    /**
     * Gets plugins.
     *
     * Reads every existing `plugins` row up front, in ONE query, and keys it
     * by name. What this replaced did seven queries per plugin -- an id
     * lookup, a load, and five separate exists() calls, one per field it
     * wanted to compare -- and getActivePlugins() calls this on every boot.
     * At fifteen bundled plugins that is over a hundred queries on every page
     * load. The cost never showed up only because discovery was returning
     * nothing at all (see _getDirs()), so fixing that without this would have
     * traded a silent breakage for a loud slowdown.
     *
     * Comparing the row in PHP is also a truer test than exists() was.
     * exists() asks "does ANY row hold this value in this column", so two
     * plugins sharing a description each looked unchanged even when one of
     * them genuinely had drifted.
     *
     * @return array
     */
    public function getPlugins()
    {
        $Plugins = [];
        $existing = [];
        // inputoverride = true. Without it listem() parses php://input for
        // DataTables `start`/`length` and paginates THIS query with whatever
        // grid the current request happens to be drawing. Discovery runs on
        // every boot, including the boot inside a grid's own AJAX POST, so a
        // page size below the plugin count returned a short list -- and every
        // plugin past it looked new (see the id lookup below for what that
        // then did). Discovery wants every row, always.
        Route::listem('plugin', false, true);
        $rows = json_decode(Route::getData());
        foreach ((array)($rows->data ?? []) as $row) {
            $existing[strtolower((string)$row->name)] = $row;
        }
        foreach ((array) $this->_getDirs() as $file) {
            $name = strtolower(basename($file));
            $row = $existing[$name] ?? null;
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
            $fields = [
                'name' => $name,
                'description' => $fog_plugin['description'],
                'location' => $file,
                'runfile' => $runFile,
                'icon' => $icon,
            ];
            // Some plugins wrap their description in _(), so the value
            // compared here is locale-dependent and the stored one is
            // whatever locale last ran discovery. That settles after a single
            // write per locale change rather than rewriting every boot, which
            // is why it is left alone: excluding description from the compare
            // would mean an edited description never reached the row at all.
            $changed = null === $row;
            foreach ($fields as $field => $value) {
                if (!$changed && (string)($row->{$field} ?? '') !== (string)$value) {
                    $changed = true;
                }
            }
            $id = (int)($row->id ?? 0);
            if ($changed && !$id) {
                // Never insert on the strength of the row being absent from
                // $existing. plugins.pName is UNIQUE and save() issues
                // INSERT ... ON DUPLICATE KEY UPDATE, so an "insert" for a
                // name that already exists silently OVERWRITES that row --
                // blanking state, installed and pSchema, which presents to
                // the admin as plugins uninstalling themselves. A single
                // short list is all it takes, so confirm the row is really
                // absent rather than trusting the list to be complete. One
                // query, and only for a plugin that looks new.
                $found = Route::getIds('plugin', ['name' => $name], 'id');
                $id = (int)(reset($found) ?: 0);
            }
            if ($changed) {
                // Constructed WITH the id, so the row is loaded before the
                // save. save() writes every databaseField, so saving an
                // unloaded object would blank the columns discovery does not
                // set -- state, installed, version, pSchema -- silently
                // uninstalling every plugin. The load costs a query, which is
                // why it is on this branch and not the common one.
                $plugin = self::getClass('Plugin', $id);
                foreach ($fields as $field => $value) {
                    $plugin->set($field, $value);
                }
                $plugin->save();
            } else {
                // Nothing to write, so skip the load entirely and hydrate
                // from the row already in hand. Keeps steady-state discovery
                // at the single listem() above.
                $plugin = self::getClass('Plugin');
                $plugin->set('id', $id);
                foreach ($fields as $field => $value) {
                    $plugin->set($field, $value);
                }
            }
            $Plugins[] = $plugin;
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
    /**
     * Installs / upgrades this plugin's database non-destructively.
     *
     * Plugins that adopt the schema() contract (an ordered, append-only
     * list of migration steps) get their pending steps applied and the
     * applied count tracked in `pSchema`, so a re-install or a FOG upgrade
     * only adds what is missing and never drops existing data.
     *
     * Plugins not yet migrated to schema() fall back to the legacy
     * (destructive) install() so existing behavior is preserved until they
     * are converted.
     *
     * @return bool True on success, false if a step failed.
     */
    public function installdb()
    {
        $manager = $this->getManager();
        if (!method_exists($manager, 'schema')) {
            return method_exists($manager, 'install')
                ? (bool)$manager->install()
                : true;
        }
        $applied = (int)$this->get('schema');
        $res = Schema::applyUpdates($manager->schema(), $applied);
        if ($res['applied'] !== $applied) {
            $this->set('schema', $res['applied'])->save();
        }
        return $res['error'] === null;
    }
    /**
     * Whether this installed plugin has pending schema migrations.
     *
     * Self-contained and independent of FOG_SCHEMA: it simply compares the
     * applied step count (pSchema) against the number of steps the plugin's
     * code currently defines in schema(). True means installdb() has work to
     * do. Only meaningful for converted (schema()-aware), installed plugins.
     *
     * @return bool
     */
    public function needsSchemaUpdate()
    {
        if ((int)$this->get('installed') < 1) {
            return false;
        }
        $manager = $this->getManager();
        if (!method_exists($manager, 'schema')) {
            return false;
        }
        return (int)$this->get('schema') < count((array)$manager->schema());
    }
}
