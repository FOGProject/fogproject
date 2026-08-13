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
        $roots = [self::bundledRoot()];
        if (defined('FOG_PLUGIN_DIR') && is_dir(FOG_PLUGIN_DIR)) {
            $roots[] = rtrim(FOG_PLUGIN_DIR, DS) . DS;
        }
        return $roots;
    }
    /**
     * The bundled plugin root, inside the web tree.
     *
     * @return string with a trailing separator
     */
    public static function bundledRoot()
    {
        return rtrim(BASEPATH, DS) . DS . 'lib' . DS . 'plugins' . DS;
    }
    /**
     * Points lib/plugins/<name> at each external plugin, so the browser can
     * fetch its js/css.
     *
     * PHP finds an external plugin's classes by scanning FOG_PLUGIN_DIR
     * directly, but the BROWSER cannot: FOG_PLUGIN_DIR is outside the
     * document root by design, so every <script src> a plugin injects would
     * 404. A symlink in the web tree closes that without changing a single
     * asset path -- Hook::injectPluginJS() still emits
     * ../lib/plugins/<node>/js/..., exactly as it does for a bundled plugin.
     *
     * These links are DERIVED state, rebuilt from the external root, never
     * the plugin's home. That is what makes them safe to sit inside
     * $webdirdest, which configureHttpd() deletes wholesale on every upgrade:
     * the next page load puts them back. It is also why this runs from
     * getPlugins() -- on every boot, self-healing -- rather than from the
     * installer, which would only heal on an upgrade and never when an admin
     * drops a plugin in by hand.
     *
     * Only ever touches links pointing INTO the external root. A real
     * directory is left alone (a bundled plugin of the same name wins, and
     * _getDirs() logs the clash), and so is a symlink an admin made pointing
     * somewhere else of their own.
     *
     * Requires FollowSymLinks on Apache; nginx follows unconditionally. A
     * failure here is degraded, not fatal -- the plugin still runs, its
     * assets just 404 -- so it logs and carries on.
     *
     * @return void
     */
    public static function syncAssetLinks()
    {
        if (!defined('FOG_PLUGIN_DIR') || !is_dir(FOG_PLUGIN_DIR)) {
            return;
        }
        $webRoot = self::bundledRoot();
        $extRoot = rtrim(FOG_PLUGIN_DIR, DS) . DS;

        // Sweep links whose external plugin has gone away. Left in place they
        // are litter that a later bundled plugin of the same name would
        // collide with.
        foreach ((array)@scandir($webRoot) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $link = $webRoot . $entry;
            if (!is_link($link)) {
                continue;
            }
            $target = @readlink($link);
            if (!is_string($target)
                || strncmp($target, $extRoot, strlen($extRoot)) !== 0
            ) {
                continue;
            }
            if (!is_dir($target)) {
                @unlink($link);
            }
        }

        foreach ((array)glob($extRoot . '*' . DS . 'config' . DS . 'plugin.config.php') as $config) {
            $dir = dirname(dirname($config));
            $link = $webRoot . strtolower(basename($dir));
            if (is_link($link)) {
                if (@readlink($link) === $dir) {
                    continue;
                }
                @unlink($link);
            } elseif (file_exists($link)) {
                // A real bundled directory of the same name. _getDirs()
                // refuses the external one, so linking over the bundled
                // plugin's assets would serve files for a plugin that is not
                // the one running.
                continue;
            }
            if (!@symlink($dir, $link)) {
                error_log(
                    sprintf(
                        '%s: %s -> %s. %s',
                        _('Could not link plugin assets'),
                        $link,
                        $dir,
                        _('The plugin will run but its JS and CSS will 404.')
                    )
                );
            }
        }
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
                // Skip our own asset links. glob() resolves symlinks, so an
                // external plugin linked into the web tree by
                // syncAssetLinks() matches under BOTH roots -- and the
                // bundled root is scanned first, so without this the plugin
                // would be recorded at its symlink path and the duplicate
                // check would then refuse its real one, logging a clash on
                // every boot. Matched on the link TARGET rather than "is a
                // symlink" so a link an admin made themselves, pointing
                // outside the external root, still works as it always did.
                if (is_link(rtrim($dir, DS))) {
                    $target = @readlink(rtrim($dir, DS));
                    $extRoot = defined('FOG_PLUGIN_DIR')
                        ? rtrim(FOG_PLUGIN_DIR, DS) . DS
                        : null;
                    if (null !== $extRoot
                        && is_string($target)
                        && strncmp($target, $extRoot, strlen($extRoot)) === 0
                    ) {
                        continue;
                    }
                }
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
     * Reads and normalizes a plugin's manifest.
     *
     * Every caller gets the same keys with the same types whatever the config
     * file actually declares, so no consumer has to null-check its way
     * through a third-party author's omissions.
     *
     * $fog_plugin is reset before the include for a real reason: the file
     * assigns into that variable, and getPlugins() reads one config after
     * another in a single request. Without the reset a config that omits a
     * key silently inherited the PREVIOUS plugin's value for it -- so a
     * plugin with no description would show the description of whichever
     * plugin happened to be read before it.
     *
     * 'entrypoint' is deliberately not returned. Every bundled plugin
     * declared 'html/run.php' and not one of them ships that file; nothing
     * has ever loaded it. Routing goes through the node -> page-class map.
     *
     * @param string $dir the plugin directory
     *
     * @return array
     */
    public static function readManifest($dir)
    {
        $file = rtrim($dir, DS) . DS . 'config' . DS . 'plugin.config.php';
        $fog_plugin = [];
        if (is_readable($file)) {
            include $file;
        }
        $manifest = (array)$fog_plugin;
        $string = function ($key, $default = '') use ($manifest) {
            return trim((string)($manifest[$key] ?? $default));
        };
        return [
            'name' => strtolower($string('name', basename(rtrim($dir, DS)))),
            'description' => $string('description'),
            'menuicon' => $string('menuicon', 'fa fa-plug fa-fw'),
            'version' => $string('version'),
            'fog_min' => $string('fog_min'),
            'fog_max' => $string('fog_max'),
            'author' => $string('author'),
            'homepage' => $string('homepage'),
            'requires' => array_values(
                array_filter(
                    array_map(
                        function ($req) {
                            return strtolower(trim((string)$req));
                        },
                        (array)($manifest['requires'] ?? [])
                    )
                )
            ),
        ];
    }
    /**
     * The comparable part of a version string.
     *
     * FOG_VERSION carries a channel suffix -- '1.6.0-beta.3318',
     * '1.6.0-RC-1' -- and version_compare() sorts any such suffix BELOW the
     * bare release, so '1.6.0-beta.3318' < '1.6.0'. Compared raw, a plugin
     * declaring fog_min '1.6.0' would be refused on every single 1.6.0 beta,
     * which is the entire population of this branch. Comparing only the
     * numeric core is what makes a declared minimum mean what an author
     * intends by it.
     *
     * @param string $version
     *
     * @return string
     */
    public static function versionCore($version)
    {
        $version = trim((string)$version);
        $cut = strpos($version, '-');
        return false === $cut ? $version : substr($version, 0, $cut);
    }
    /**
     * Why this plugin cannot run on this FOG, or '' if it can.
     *
     * An unset bound is no bound, so a plugin declaring neither -- which is
     * every plugin written before the manifest existed -- is compatible with
     * everything and keeps working untouched.
     *
     * @param array       $manifest from readManifest()
     * @param string|null $fogVersion defaults to the running FOG_VERSION
     *
     * @return string a human-readable reason, or '' when compatible
     */
    public static function compatError(array $manifest, $fogVersion = null)
    {
        if (null === $fogVersion) {
            $fogVersion = defined('FOG_VERSION') ? FOG_VERSION : '';
        }
        $fog = self::versionCore($fogVersion);
        if ('' === $fog) {
            return '';
        }
        $min = self::versionCore($manifest['fog_min'] ?? '');
        $max = self::versionCore($manifest['fog_max'] ?? '');
        if ('' !== $min && version_compare($fog, $min, '<')) {
            return sprintf(
                _('needs FOG %s or newer; this server is %s'),
                $min,
                $fog
            );
        }
        if ('' !== $max && version_compare($fog, $max, '>')) {
            return sprintf(
                _('supports FOG up to %s; this server is %s'),
                $max,
                $fog
            );
        }
        return '';
    }
    /**
     * Why these plugins cannot be switched on, keyed by plugin name.
     *
     * Both the things that stop a plugin running are checked here rather than
     * at the two call sites: the FOG range it declares, and the other plugins
     * it declares it needs. An empty return means every id given is safe to
     * activate or install.
     *
     * @param array $ids plugin row ids
     *
     * @return array name => human-readable reason
     */
    public static function activationBlockers(array $ids)
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!count($ids)) {
            return [];
        }
        Route::listem('plugin');
        $rows = json_decode(Route::getData());
        $batch = [];
        $active = [];
        foreach ((array)($rows->data ?? []) as $row) {
            $name = strtolower((string)$row->name);
            if (in_array((int)$row->id, $ids, true)) {
                $batch[$name] = $row;
            } elseif ($row->installed && $row->state) {
                $active[] = $name;
            }
        }
        // A plugin in the same batch counts as satisfying a dependency. The
        // whole batch is turned on in one go, so demanding the user submit
        // them in dependency order would be a rule with nothing behind it.
        $available = array_merge($active, array_keys($batch));
        $blockers = [];
        foreach ($batch as $name => $row) {
            $manifest = self::readManifest((string)$row->location);
            $reason = self::compatError($manifest);
            if ('' === $reason) {
                $missing = array_diff($manifest['requires'], $available);
                if (count($missing)) {
                    $reason = sprintf(
                        _('needs these plugins active first: %s'),
                        implode(', ', $missing)
                    );
                }
            }
            if ('' !== $reason) {
                $blockers[$name] = $reason;
            }
        }

        return $blockers;
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
        // Before discovery, so an external plugin added or removed since the
        // last boot has its asset link in step with the row written below.
        self::syncAssetLinks();
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
            $manifest = self::readManifest($file);
            $matchIcon = preg_match(
                '#^fa[\-]?#',
                $manifest['menuicon']
            );
            if (false == $matchIcon) {
                $icon = sprintf(
                    '<img src="%s" width="66" height="66"/>',
                    $manifest['menuicon']
                );
            } else {
                $icon = sprintf(
                    '<i class="%s fa-2x" width="66" height="66"></i>',
                    $manifest['menuicon']
                );
            }
            $fields = [
                'name' => $name,
                'description' => $manifest['description'],
                'location' => $file,
                // pVersion has existed since the table was created and
                // nothing ever wrote it -- every row reads ''. It is the
                // manifest's version now, so "which build of this plugin is
                // installed" finally has an answer.
                'version' => $manifest['version'],
                // runfile always '' now. It held $location . 'html/run.php',
                // a path no plugin has ever shipped and nothing has ever
                // loaded; readManifest() no longer returns an entrypoint to
                // build it from. The column stays -- dropping it is a schema
                // step for no gain -- and empties itself on the next boot.
                'runfile' => '',
                'icon' => $icon,
            ];
            // Upgrading FOG can move the server out of a plugin's declared
            // range while that plugin is switched on, and nothing else would
            // notice: the plugin's hooks just keep firing against a core its
            // author never claimed to support. Discovery turns it off instead.
            // Only `state` is cleared -- `installed` and `pSchema` stay, so
            // the plugin's tables and applied-migration count survive and
            // re-activating once it catches up is a single click.
            $compat = self::compatError($manifest);
            if ('' !== $compat && $row && $row->installed && $row->state) {
                $fields['state'] = 0;
                error_log(
                    sprintf(
                        '%s: %s %s',
                        _('Deactivated an incompatible plugin'),
                        $name,
                        $compat
                    )
                );
            }
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
