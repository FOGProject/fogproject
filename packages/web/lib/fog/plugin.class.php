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
     * True only if a REAL bundled plugin of this name exists.
     *
     * Not just is_dir(): syncAssetLinks() puts a symlink at
     * lib/plugins/<name> for every external plugin, is_dir() follows it, and
     * an external plugin would then look bundled to anything asking this
     * question. That made the second upload of a plugin -- the upgrade --
     * refuse itself with "ships with FOG", because its own asset link from
     * the first install was sitting in the bundled root.
     *
     * Matched on the link TARGET, the same test _getDirs() uses, so a symlink
     * an admin made themselves pointing somewhere outside the external root
     * still counts as bundled: _getDirs() would load it from the bundled root
     * and it would genuinely win the collision.
     *
     * @param string $name the plugin's directory name
     *
     * @return bool
     */
    public static function isBundled($name)
    {
        $path = self::bundledRoot() . strtolower((string)$name);
        if (!is_dir($path)) {
            return false;
        }
        if (!is_link($path)) {
            return true;
        }
        $target = @readlink($path);
        $extRoot = defined('FOG_PLUGIN_DIR')
            ? rtrim(FOG_PLUGIN_DIR, DS) . DS
            : null;
        return !(null !== $extRoot
            && is_string($target)
            && strncmp($target, $extRoot, strlen($extRoot)) === 0);
    }
    /**
     * True when a row's plugin code is not on disk.
     *
     * Derived at read time rather than stored in a column. A stored flag has
     * to be cleared by something, and the thing that would clear it --
     * discovery -- only ever walks directories that exist, so a plugin whose
     * code came back would stay flagged until someone thought to re-run it.
     * A stat() is cheaper than that class of bug.
     *
     * Deliberately does NOT change the row. Absence is not reliably
     * permanent: the external root can be an unmounted NFS share, or the web
     * tree can be mid-upgrade while configureHttpd() re-lays it, and either
     * would make every external plugin vanish at once. Deactivating on
     * absence would silently switch them all off; deleting would take their
     * pSchema counts with it and the next install would re-run migrations
     * from step zero against tables that already exist.
     *
     * @param string $location the row's stored location
     *
     * @return bool
     */
    public static function isMissing($location)
    {
        $location = trim((string)$location);
        return '' === $location || !is_dir($location);
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
     * Largest plugin archive accepted, before PHP's own upload limits.
     *
     * A plugin is source code. Sixty-four megabytes is already generous for
     * that and small enough that a refused upload cannot fill the cache
     * partition. post_max_size/upload_max_filesize usually bite first; this is
     * the bound that does not depend on how a distro tuned php.ini.
     */
    const MAX_ARCHIVE_BYTES = 67108864;
    /**
     * Staged uploads older than this are swept, in seconds.
     *
     * An upload that is never confirmed leaves a directory behind. An hour is
     * far longer than the gap between the preview and the admin clicking
     * Install, and short enough that abandoned uploads do not accumulate.
     */
    const STAGING_TTL = 3600;
    /**
     * Where an uploaded plugin waits between preview and confirmation.
     *
     * Under FOG_CACHE_DIR, deliberately NOT under either plugin root: nothing
     * here is on the autoload path, so an archive that fails validation -- or
     * one the admin looks at and rejects -- never becomes loadable code.
     *
     * @return string with a trailing separator
     */
    public static function stagingRoot()
    {
        return rtrim(FOG_CACHE_DIR, DS) . DS . 'plugin-staging' . DS;
    }
    /**
     * Unpacks an uploaded archive into staging and describes what is in it.
     *
     * Two passes, and the order matters. The archive's entry list is checked
     * structurally FIRST -- while it is still a file and nothing has been
     * written -- because that is the only point at which a malicious path can
     * still be refused for free. Only an archive that survives that is
     * extracted, and only then is its manifest read.
     *
     * Reading the manifest means `include`ing PHP out of the archive, which
     * is why extraction lands in staging rather than the plugin root: the code
     * runs either way (the caller holds plugin.install, whose entire meaning
     * is "may introduce executable code"), but a refused install leaves
     * nothing behind where the autoloader would find it.
     *
     * @param string $file path to the uploaded temporary file
     * @param string $name the client-supplied file name, for the error text
     *
     * @return array ['error' => string] or a description of the staged plugin
     */
    public static function stageArchive($file, $name = '')
    {
        self::purgeStaging();
        // $dir is captured by reference so every failure below cleans up the
        // staging directory without each return having to remember to. Before
        // the directory exists it is null and this is just an early return.
        $dir = null;
        $fail = function ($why) use (&$dir) {
            if (null !== $dir) {
                self::rmTree($dir);
            }
            return ['error' => $why];
        };
        if (!is_readable($file)) {
            return $fail(_('The upload did not arrive.'));
        }
        if (filesize($file) > self::MAX_ARCHIVE_BYTES) {
            return $fail(
                sprintf(
                    _('The archive is larger than the %s limit.'),
                    self::MAX_ARCHIVE_BYTES / 1048576 . 'MB'
                )
            );
        }
        // The archive is copied under a .tar.gz name before it is opened.
        // PharData decides the format from the FILE EXTENSION and refuses
        // anything it does not recognise -- passing Phar::TAR|Phar::GZ does not
        // override that -- and PHP's upload temp file is /tmp/phpXXXXXX with no
        // extension at all, so opening it in place always threw "file
        // extension (or combination) not recognised".
        $token = bin2hex(random_bytes(16));
        $made = self::stagingRoot() . $token . DS;
        if (!self::_makeStagingDir($made)) {
            return $fail(_('Could not create the staging directory.'));
        }
        $dir = $made;
        $archive = $dir . 'upload.tar.gz';
        if (!@copy($file, $archive)) {
            return $fail(_('Could not stage the upload.'));
        }
        // PharData reads .tar.gz without shelling out and, crucially, lets the
        // entry list be inspected before anything is extracted.
        try {
            $phar = new \PharData($archive, 0, null, \Phar::TAR | \Phar::GZ);
        } catch (\Exception $e) {
            return $fail(
                sprintf(
                    _('%s is not a readable .tar.gz archive.'),
                    $name ?: basename($file)
                )
            );
        }
        $prefix = 'phar://' . str_replace(DIRECTORY_SEPARATOR, '/', $archive) . '/';
        $tops = [];
        $hasManifest = false;
        $fileCount = 0;
        try {
            $walk = new \RecursiveIteratorIterator(
                $phar,
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($walk as $entry) {
                $rel = str_replace('\\', '/', $entry->getPathname());
                if (strncmp($rel, $prefix, strlen($prefix)) === 0) {
                    $rel = substr($rel, strlen($prefix));
                }
                $rel = ltrim($rel, '/');
                if ('' === $rel
                    || '/' === substr($rel, 0, 1)
                    || preg_match('#(^|/)\.\.(/|$)#', $rel)
                ) {
                    return $fail(
                        sprintf(_('The archive contains an unsafe path: %s'), $rel)
                    );
                }
                $parts = explode('/', $rel);
                if (count($parts) < 2) {
                    // Everything must live under one directory named for the
                    // plugin. A file at the root would extract straight into
                    // the plugin root and could land on a neighbour.
                    if (!$entry->isDir()) {
                        return $fail(
                            sprintf(
                                _('%s is not inside a plugin directory.'),
                                $rel
                            )
                        );
                    }
                }
                if (!$entry->isDir()) {
                    $fileCount++;
                }
                $tops[$parts[0]] = true;
                if ('config/plugin.config.php' === implode('/', array_slice($parts, 1))) {
                    $hasManifest = true;
                }
            }
        } catch (\Exception $e) {
            return $fail(_('The archive could not be read.'));
        }
        if (count($tops) !== 1) {
            return $fail(
                _('The archive must hold exactly one plugin directory.')
            );
        }
        $top = strtolower((string)key($tops));
        if (!preg_match('#^[a-z0-9][a-z0-9_-]*$#', $top)) {
            return $fail(
                sprintf(
                    _('%s is not a usable plugin name. Use lowercase letters, digits, - and _.'),
                    $top
                )
            );
        }
        if (!$hasManifest) {
            return $fail(
                sprintf(
                    _('The archive has no %s.'),
                    $top . '/config/plugin.config.php'
                )
            );
        }
        // A bundled plugin of the same name always wins (_getDirs() refuses
        // the external copy), so an archive that collides with one would
        // install and then never load. Refuse it here where it can be
        // explained rather than leaving the admin to wonder.
        if (self::isBundled($top)) {
            return $fail(
                sprintf(
                    _('%s ships with FOG and cannot be replaced by an upload.'),
                    $top
                )
            );
        }
        try {
            $phar->extractTo($dir, null, true);
        } catch (\Exception $e) {
            return $fail(_('The archive could not be extracted.'));
        }
        $staged = $dir . $top;
        if (!is_dir($staged)) {
            return $fail(_('The archive did not extract as expected.'));
        }
        // Checked on what is actually on disk, not on the archive's entry
        // list. PharData reports isLink() false for a tar symlink and writes
        // it out as an empty regular file, so an entry-level check would have
        // looked right and caught nothing. This asserts the property that
        // matters -- a link is how an install writes outside its own
        // directory -- against the extracted tree, whatever produced it.
        if (self::_hasLink($staged)) {
            return $fail(_('The archive contains a link.'));
        }
        $manifest = self::readManifest($staged);
        if ($manifest['name'] !== $top) {
            return $fail(
                sprintf(
                    _("The manifest calls this plugin '%s' but the directory is '%s'. They must match."),
                    $manifest['name'],
                    $top
                )
            );
        }
        $compat = self::compatError($manifest);
        if ('' !== $compat) {
            return $fail(sprintf('%s %s', $top, $compat));
        }

        // The copy is only needed to give PharData a recognisable extension.
        // Removed once extraction is done so commitStaged() moves a directory
        // holding the plugin and nothing else. The handle goes first: it is
        // still open on the file being deleted.
        unset($phar, $walk);
        @unlink($archive);

        return [
            // notifyFromAPI() shows 'Bad Response' for a payload carrying
            // none of msg/error/info/warning, and every apiCall() response
            // goes through it. 'info' rather than 'msg' because nothing has
            // been installed yet -- this is a preview, not a success.
            'info' => sprintf(
                _('Check %s over before installing it.'),
                $top
            ),
            'title' => _('Plugin Archive Read'),
            'token' => $token,
            'name' => $top,
            'manifest' => $manifest,
            // The checksum of what was uploaded, so an admin can compare it
            // with whatever the plugin's author published.
            'sha256' => hash_file('sha256', $file),
            'files' => $fileCount,
            // Installing over an existing external plugin is an upgrade. It
            // is allowed, but the admin is told, because it replaces files.
            'upgrade' => is_dir(rtrim(FOG_PLUGIN_DIR, DS) . DS . $top),
        ];
    }
    /**
     * Moves a staged plugin into the external root.
     *
     * The checks from stageArchive() are repeated rather than trusted: the
     * preview and the confirmation are two requests, and between them the
     * server could have been upgraded out of the plugin's range or a bundled
     * plugin of the same name could have appeared.
     *
     * @param string $token the staging token from stageArchive()
     *
     * @return array ['error' => string] or ['name' => string]
     */
    public static function commitStaged($token)
    {
        // Hex only. The token names a directory, so anything else is a path
        // traversal attempt rather than a typo.
        if (!preg_match('#^[a-f0-9]{32}$#', (string)$token)) {
            return ['error' => _('That upload is no longer available.')];
        }
        $dir = self::stagingRoot() . $token . DS;
        $dirs = (array)glob($dir . '*', GLOB_ONLYDIR);
        if (count($dirs) !== 1) {
            return ['error' => _('That upload is no longer available.')];
        }
        $staged = $dirs[0];
        $name = strtolower(basename($staged));
        $manifest = self::readManifest($staged);
        $compat = self::compatError($manifest);
        if ($manifest['name'] !== $name || '' !== $compat) {
            self::rmTree($dir);
            return [
                'error' => $compat
                    ? sprintf('%s %s', $name, $compat)
                    : _('The staged plugin no longer matches its manifest.')
            ];
        }
        if (self::isBundled($name)) {
            self::rmTree($dir);
            return [
                'error' => sprintf(
                    _('%s ships with FOG and cannot be replaced by an upload.'),
                    $name
                )
            ];
        }
        if (!defined('FOG_PLUGIN_DIR') || !is_dir(FOG_PLUGIN_DIR)) {
            return ['error' => _('The plugin directory does not exist.')];
        }
        $target = rtrim(FOG_PLUGIN_DIR, DS) . DS . $name;
        // An upgrade replaces files, so the old copy is moved aside first and
        // only deleted once the new one is in place. A failed rename then
        // leaves the plugin as it was rather than gone.
        $backup = null;
        if (is_dir($target)) {
            $backup = $dir . '.replaced-' . $name;
            if (!@rename($target, $backup)) {
                return [
                    'error' => sprintf(
                        _('Could not replace the existing %s. Is %s writable?'),
                        $name,
                        FOG_PLUGIN_DIR
                    )
                ];
            }
        }
        if (!@rename($staged, $target)) {
            if (null !== $backup) {
                @rename($backup, $target);
            }
            self::rmTree($dir);
            return [
                'error' => sprintf(
                    _('Could not write to %s. The UI installer needs that directory to be writable by the web server.'),
                    FOG_PLUGIN_DIR
                )
            ];
        }
        self::rmTree($dir);
        // Code has just appeared under a scanned root while the server is
        // running. The autoloader's file list is TTL-cached, so without this
        // the new plugin stays invisible for up to five minutes: its manager,
        // page and hook classes do not resolve, installing it applies no
        // schema and registers no hooks, and it all reports success.
        Initiator::forgetClassFileList();

        return ['name' => $name, 'upgrade' => null !== $backup];
    }
    /**
     * Removes staged uploads nobody confirmed.
     *
     * @return void
     */
    public static function purgeStaging()
    {
        $cutoff = time() - self::STAGING_TTL;
        foreach ((array)glob(self::stagingRoot() . '*', GLOB_ONLYDIR) as $dir) {
            if (@filemtime($dir) < $cutoff) {
                self::rmTree($dir);
            }
        }
    }
    /**
     * Deletes a directory tree, refusing to follow links out of it.
     *
     * @param string $dir the directory to remove
     *
     * @return void
     */
    public static function rmTree($dir)
    {
        $dir = rtrim((string)$dir, DS);
        // Only ever used on staging, and only ever called with a path this
        // class built. The guard is here so a future caller cannot turn a
        // typo into a recursive delete of something that matters.
        $root = rtrim(self::stagingRoot(), DS);
        if ('' === $dir || strncmp($dir, $root . DS, strlen($root) + 1) !== 0) {
            return;
        }
        foreach ((array)@scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $dir . DS . $entry;
            if (is_link($path) || !is_dir($path)) {
                @unlink($path);
                continue;
            }
            self::rmTree($path);
        }
        @rmdir($dir);
    }
    /**
     * True if anything in this tree is a symlink.
     *
     * @param string $dir the directory to walk
     *
     * @return bool
     */
    private static function _hasLink($dir)
    {
        foreach ((array)@scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = rtrim($dir, DS) . DS . $entry;
            if (is_link($path)) {
                return true;
            }
            if (is_dir($path) && self::_hasLink($path)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Creates the staging directory, and the root above it if needed.
     *
     * 0700: staging holds code that has been neither validated nor accepted,
     * and FOG_CACHE_DIR around it is world-writable. Only the web user that
     * wrote it needs to read it back.
     *
     * @param string $dir the directory to create
     *
     * @return bool
     */
    private static function _makeStagingDir($dir)
    {
        $root = rtrim(self::stagingRoot(), DS);
        if (!is_dir($root) && !@mkdir($root, 0700, true) && !is_dir($root)) {
            return false;
        }
        return (bool)@mkdir($dir, 0700, true);
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
        // inputoverride = true: this needs every row, both to find the
        // selected ones and to build the active set a dependency is checked
        // against. Paginating it would under-report both. Safe today only
        // because the POST body carries plugins[] and no DataTables length --
        // which is not a property worth relying on. See getPlugins().
        $rows = Route::getList('plugin');
        $batch = [];
        $active = [];
        foreach ($rows as $row) {
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
            // Checked before the manifest, because there is no manifest to
            // read: readManifest() on a missing directory returns empty
            // fog_min/fog_max, compatError() then finds nothing wrong, and a
            // row with no code behind it activated and installed with a
            // success message.
            if (self::isMissing((string)$row->location)) {
                $blockers[$name] = _('has no code on disk');
                continue;
            }
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
        $rows = Route::getList('plugin');
        foreach ($rows as $row) {
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
        // getManager() falls back to PluginManager when the plugin's own
        // manager class will not load, and PluginManager has neither schema()
        // nor install() -- so the fallback below returned true and the install
        // reported success having done nothing. A plugin that ships a manager
        // file we cannot load is a broken install, not a plugin without a
        // manager, and the two have to be told apart: a hooks-only plugin
        // genuinely has no manager and must still install cleanly.
        $wanted = $this->get('name') . 'Manager';
        $managerFile = rtrim($this->get('location'), DS) . DS . 'class'
            . DS . strtolower($wanted) . '.class.php';
        // Asked of $manager rather than class_exists($wanted): getManager()
        // has already resolved it, and asking again would autoload the same
        // file a second time.
        // Short name: $wanted is built from the plugins.pName database value,
        // so comparing an FQCN against it would fail every plugin install.
        if (file_exists($managerFile)
            && strcasecmp(self::shortName($manager), $wanted) !== 0
        ) {
            throw new \Exception(
                sprintf(
                    _('%s could not be loaded. Check that %s declares a class of that exact name.'),
                    $wanted,
                    'class' . DS . strtolower($wanted) . '.class.php'
                )
            );
        }
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
