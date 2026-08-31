<?php
/**
 * Plugins are laid out like core, and their classes are DERIVED, not scanned.
 *
 * Core finished its own PSR-4 migration first: every class under
 * packages/web/src/ is src/<Bucket>/<Class>.php declaring FOG\<Bucket>\<Class>
 * and answers to nothing else. Plugins kept a filename-suffix layout
 * (<plugin>/class/<name>.class.php) for one more release, then adopted the
 * same shape: <plugin>/src/<Bucket>/<Class>.php declaring
 * FOG\Plugins\<Segment>\<Bucket>\<Class>, where strtolower(<Segment>) is the
 * plugin's own directory name (ADR 0035).
 *
 * Two rules and four mechanisms, and this test pins each of them because
 * every one fails SILENTLY:
 *
 *  1. The autoloader DERIVES a path from the class name. There is no map to
 *     consult and no file to read: FOG\Plugins\LDAP\Managers\LDAPManager is
 *     <root>/ldap/src/Managers/LDAPManager.php, at most one is_file() per
 *     plugin root. The rule strtolower(<Segment>) === <directory> is what
 *     makes that derivation possible, so a plugin that breaks it is reported
 *     by name rather than being quietly unreachable.
 *  2. Initiator::pluginFileList() is bounded to exactly <plugin>/src/
 *     <Bucket>/*.php. A file one level shallower or deeper has no derivable
 *     name, so listing it would put an entry in the short map that the
 *     autoloader could never find a file for.
 *  3. FOGBase::qualify() consults the plugin short map, which is what keeps
 *     the ~150 getClass('X') literals inside the plugins, FOGController::
 *     getManager(), FOGPage::$childClass and Route::_newEntity() working
 *     without editing a single call site.
 *  4. qualify() consults CORE FIRST. That order is the guarantee that a
 *     plugin cannot answer a core name -- and its failure mode is the worst
 *     one in the tree, because Authorization::_scopeClassVars() resolves a
 *     node to its model through qualify(): a wrong answer there is access
 *     control silently testing the wrong table.
 *
 * A plugin still on the pre-1.6 layout is REFUSED, and refused loudly. That
 * is a deliberate break (ADR 0035): the alternative was two conventions in
 * the tree forever, and the previous behavior -- a plugin whose pages simply
 * never registered, with nothing anywhere saying why -- is the exact defect
 * ADR 0009 records as the reason the old conventions had to go.
 *
 * Everything is exercised against fixture plugins written into a throwaway
 * FOG_PLUGIN_DIR rather than against the bundled tree, so the test says the
 * same thing on a fresh clone (where lib/plugins is empty -- it is gitignored
 * staging, ADR 0009) as it does on a deployed server.
 *
 * DB-free, same shape as autoload-core-wins.test.php: the Initiator
 * constructor only registers the autoloader, so the cache dir is redirected
 * somewhere throwaway and startInit() -- the part that needs MySQL -- is
 * never called.
 *
 * Usage: php tests/plugin-namespace.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web';
$init = $root . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-plugin-ns-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
@mkdir($tmp . '/extplugins', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        if (!is_dir($tmp)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmp);
    }
);

/**
 * Give a fixture plugin the manifest that makes it a plugin.
 *
 * config/plugin.config.php is what Plugin::_getDirs() globs for and what
 * Initiator::_scanPluginSource() requires before it will treat a directory
 * as a plugin at all. Without it a fixture is just a directory -- which is
 * the correct answer for .git and for the bundled root's own bin/, and would
 * be a silently empty test here.
 *
 * @param string $tmp    the throwaway root
 * @param string $plugin the plugin directory name
 *
 * @return void
 */
function manifest($tmp, $plugin)
{
    $dir = $tmp . '/extplugins/' . $plugin . '/config';
    @mkdir($dir, 0700, true);
    file_put_contents(
        $dir . '/plugin.config.php',
        "<?php\n\$fog_plugin = ['name' => '{$plugin}'];\n"
    );
}

/**
 * Write one fixture plugin class file in the bucket layout.
 *
 * @param string      $tmp    the throwaway root
 * @param string      $plugin the plugin directory name
 * @param string      $rel    the path below the plugin, e.g. 'src/Items'
 * @param string      $class  the class name, which is also the file name
 * @param string|null $ns     the namespace to declare, or null for global
 * @param string      $decl   what follows `class`, for `extends`
 * @param string      $body   extra class body
 *
 * @return string the absolute path written
 */
function fixture($tmp, $plugin, $rel, $class, $ns, $decl = '', $body = '')
{
    manifest($tmp, $plugin);
    $path = $tmp . '/extplugins/' . $plugin . '/' . $rel;
    @mkdir($path, 0700, true);
    $path .= '/' . $class . '.php';
    $src = "<?php\n/**\n * Fixture.\n */\n\n";
    if ($ns !== null) {
        $src .= "namespace {$ns};\n\n";
    }
    $src .= "/**\n * Fixture.\n */\nclass {$class}{$decl}\n{\n"
        . "    const MARK = '{$plugin}';\n{$body}\n}\n";
    file_put_contents($path, $src);
    return $path;
}

// A plugin laid out correctly. Note the segment is NOT ucfirst() of the
// directory: "alphaPlugin" is the readable spelling and it lowercases to
// "alphaplugin", which is all the contract asks. LDAP and OIDC are the real
// cases this exists for.
$widgetA = fixture(
    $tmp,
    'alphaplugin',
    'src/Items',
    'Widget',
    'FOG\\Plugins\\alphaPlugin\\Items'
);
// Its manager, in a different bucket. getManager() is
// qualify(shortName($this) . 'Manager'), which is a BARE name -- so this is
// the case that proves the short map spans buckets.
$widgetManagerA = fixture(
    $tmp,
    'alphaplugin',
    'src/Managers',
    'WidgetManager',
    'FOG\\Plugins\\alphaPlugin\\Managers'
);
// A page, to prove discovery reads the bucket rather than a filename suffix.
$pageA = fixture(
    $tmp,
    'alphaplugin',
    'src/Pages',
    'AlphaManagement',
    'FOG\\Plugins\\alphaPlugin\\Pages'
);
// A SECOND plugin shipping a class of the same short name. Under the global
// namespace exactly one of these could exist; both must now.
$widgetB = fixture(
    $tmp,
    'betaplugin',
    'src/Items',
    'Widget',
    'FOG\\Plugins\\Betaplugin\\Items'
);
// A manager named after the PLUGIN, not after a model. Plugin::getManager()
// builds plugins.pName . 'Manager', so this is the name that call site looks
// for. Deliberately a plain class: getManager() only instantiates it, and a
// real FOGManagerController wants a database this test does not have.
fixture(
    $tmp,
    'alphaplugin',
    'src/Managers',
    'AlphapluginManager',
    'FOG\\Plugins\\alphaPlugin\\Managers'
);
// A plugin task, in the bucket PluginRunner walks.
$task = fixture(
    $tmp,
    'alphaplugin',
    'src/Tasks',
    'AlphaHeartbeat',
    'FOG\\Plugins\\alphaPlugin\\Tasks',
    ' extends \\FOG\\Base\\PluginTask',
    "    public function run()\n    {\n    }\n"
);
// A plugin claiming a CORE name from inside its own namespace. It is
// entitled to the class; it is not entitled to the bare spelling.
$shadow = fixture(
    $tmp,
    'shadowplugin',
    'src/Items',
    'Host',
    'FOG\\Plugins\\Shadowplugin\\Items'
);
// Out of bounds, both directions. Neither has a derivable name -- src/Stray
// is FOG\Plugins\Strayplugin\Stray, which the autoloader's three-segment
// derivation refuses, and Deep/Nested is four segments -- so neither may
// appear in the file list.
manifest($tmp, 'strayplugin');
$shallow = $tmp . '/extplugins/strayplugin/src';
@mkdir($shallow, 0700, true);
file_put_contents($shallow . '/Stray.php', "<?php\nnamespace X;\nclass Stray\n{\n}\n");
$deep = $tmp . '/extplugins/strayplugin/src/Items/Deep';
@mkdir($deep, 0700, true);
file_put_contents($deep . '/Nested.php', "<?php\nnamespace X;\nclass Nested\n{\n}\n");
// ... and one legal file in the same plugin, so "strayplugin contributes
// nothing" cannot pass just because the plugin was skipped wholesale.
$strayOk = fixture(
    $tmp,
    'strayplugin',
    'src/Items',
    'StrayOk',
    'FOG\\Plugins\\Strayplugin\\Items'
);
// A plugin whose declared segment does not lowercase to its directory. The
// derivation cannot find its classes, so it is reported by name.
$offRule = fixture(
    $tmp,
    'oddplugin',
    'src/Items',
    'OddThing',
    'FOG\\Plugins\\SomethingElse\\Items'
);
// Not a plugin at all: a directory with no manifest. It is shaped exactly
// like a pre-1.6 plugin -- a .git checkout has a hooks/ directory, and the
// bundled root ships its own bin/ -- so without the manifest requirement it
// would be walked, found wanting, and reported as a broken plugin by name.
@mkdir($tmp . '/extplugins/notaplugin/hooks', 0700, true);
file_put_contents(
    $tmp . '/extplugins/notaplugin/hooks/whatever.hook.php',
    "<?php\nclass Whatever\n{\n}\n"
);
@mkdir($tmp . '/extplugins/notaplugin/src/Items', 0700, true);
file_put_contents(
    $tmp . '/extplugins/notaplugin/src/Items/Whatever.php',
    "<?php\nnamespace FOG\\Plugins\\NotAPlugin\\Items;\nclass Whatever\n{\n}\n"
);

// A plugin still on the pre-1.6 layout. Refused, and reported by name.
manifest($tmp, 'legacyplugin');
@mkdir($tmp . '/extplugins/legacyplugin/class', 0700, true);
file_put_contents(
    $tmp . '/extplugins/legacyplugin/class/legacything.class.php',
    "<?php\n/**\n * Fixture.\n */\nclass LegacyThing\n{\n}\n"
);

if (!defined('FOG_CACHE_DIR')) {
    define('FOG_CACHE_DIR', $tmp . '/cache');
}
if (!defined('FOG_LOG_DIR')) {
    define('FOG_LOG_DIR', $tmp . '/log');
}
if (!defined('FOG_PLUGIN_DIR')) {
    define('FOG_PLUGIN_DIR', $tmp . '/extplugins');
}
// FOGBase::info() is on the path of every get(), and its file-logging gate is
// `self::$mySchema >= FOG_SCHEMA` -- a constant System.php defines, and this
// test does not boot System.php. Any value above $mySchema (0 when no database
// was read) keeps the gate shut, which is the state this test wants: the
// alternative branch calls getSetting() and dies on a null $DB.
if (!defined('FOG_SCHEMA')) {
    define('FOG_SCHEMA', 1);
}

// Divert error_log() so the diagnostics themselves can be asserted on. Three
// are load-bearing: two must fire, and one must NOT.
$errlog = $tmp . '/php-error.log';
ini_set('log_errors', '1');
ini_set('error_log', $errlog);

require_once $init;
new Initiator();

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

/*
 * 1. pluginOf() -- the derivation the whole scheme rests on. The plugin's
 *    directory name is its machine name: plugin.config.php's 'name' must
 *    equal it, ?node= addresses it, plugins.pName holds it. Deriving the
 *    namespace segment from anything inside the file instead would let two
 *    plugins claim one namespace and put us back where we started.
 */
$base = rtrim(BASEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$ext = rtrim(FOG_PLUGIN_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$derivations = [
    // Bundled root.
    $base . 'lib/plugins/ldap/src/Managers/LDAPManager.php' => 'ldap',
    $base . 'lib/plugins/capone/src/Util/CaponeTasking.php' => 'capone',
    $base . 'lib/plugins/ou/src/Pages/OUManagement.php' => 'ou',
    // External root.
    $ext . 'alphaplugin/src/Items/Widget.php' => 'alphaplugin',
    // Not plugin code at all.
    $base . 'src/Items/Host.php' => '',
];
foreach ($derivations as $path => $expect) {
    check(
        sprintf('pluginOf(%s) should be "%s"', $path, $expect),
        Initiator::pluginOf($path) === $expect,
        $failures,
        $checks
    );
}

/*
 * 2. The segment. Read from the files, once per plugin, and required to
 *    lowercase to the directory -- that equality is what lets the autoloader
 *    turn a class name back into a path without reading anything.
 */
check(
    'the cased segment a plugin declares is what is reported',
    Initiator::pluginSegment('alphaplugin') === 'alphaPlugin',
    $failures,
    $checks
);
check(
    'a plugin whose segment does not lowercase to its directory is not '
    . 'given one',
    Initiator::pluginSegment('oddplugin') === 'Oddplugin',
    $failures,
    $checks
);
check(
    'pluginFqcnFor() derives the FQCN from the path and the segment',
    Initiator::pluginFqcnFor($widgetManagerA)
    === 'FOG\\Plugins\\alphaPlugin\\Managers\\WidgetManager',
    $failures,
    $checks
);

/*
 * 3. The file list is bounded to <plugin>/src/<Bucket>/*.php.
 */
$list = Initiator::pluginFileList();
check(
    'a plugin class file in a bucket is listed',
    in_array($widgetA, $list, true) && in_array($pageA, $list, true),
    $failures,
    $checks
);
check(
    'a file directly under src/ is NOT listed -- it has no bucket',
    !in_array($shallow . '/Stray.php', $list, true),
    $failures,
    $checks
);
check(
    'a file nested BELOW a bucket is not listed either',
    !in_array($deep . '/Nested.php', $list, true),
    $failures,
    $checks
);
check(
    'and the same plugin\'s legal file still is, so the two above are not '
    . 'passing because the plugin was skipped wholesale',
    in_array($strayOk, $list, true),
    $failures,
    $checks
);
check(
    'a plugin on the pre-1.6 layout contributes no files',
    [] === preg_grep('#legacyplugin#', $list),
    $failures,
    $checks
);
check(
    'a directory with no manifest is not a plugin, however it is laid out',
    [] === preg_grep('#notaplugin#', $list),
    $failures,
    $checks
);

/*
 * 4. autoload() answers the FOG\Plugins\ prefix, by derivation.
 */
check(
    'a namespaced plugin class resolves under its FQCN',
    class_exists('FOG\\Plugins\\alphaPlugin\\Items\\Widget'),
    $failures,
    $checks
);
check(
    'the second plugin\'s same-named class resolves independently',
    class_exists('FOG\\Plugins\\Betaplugin\\Items\\Widget'),
    $failures,
    $checks
);
// Each fixture carries a MARK naming the plugin whose file declares it, so
// this asserts which FILE each loaded class came from -- the anti-shadowing
// property itself, rather than the map that is supposed to produce it.
check(
    'each same-named class is the one from its OWN plugin\'s file',
    class_exists('FOG\\Plugins\\alphaPlugin\\Items\\Widget')
    && class_exists('FOG\\Plugins\\Betaplugin\\Items\\Widget')
    && constant('FOG\\Plugins\\alphaPlugin\\Items\\Widget::MARK') === 'alphaplugin'
    && constant('FOG\\Plugins\\Betaplugin\\Items\\Widget::MARK') === 'betaplugin',
    $failures,
    $checks
);
check(
    'the segment is matched case-insensitively, as PHP matches class names',
    class_exists('FOG\\Plugins\\ALPHAPLUGIN\\Items\\Widget'),
    $failures,
    $checks
);
check(
    'a plugin FQCN no file declares resolves to nothing, quietly',
    !class_exists('FOG\\Plugins\\alphaPlugin\\Items\\NoSuchClass'),
    $failures,
    $checks
);
check(
    'a name with no bucket in it is refused rather than guessed at',
    !class_exists('FOG\\Plugins\\alphaPlugin\\Widget'),
    $failures,
    $checks
);
/*
 *    And the property that makes the map machinery deletable: resolution
 *    reads NOTHING. A file written after every list and cache was built
 *    still resolves under its FQCN, with no flush.
 */
$late = fixture(
    $tmp,
    'lateplugin',
    'src/Items',
    'LateThing',
    'FOG\\Plugins\\Lateplugin\\Items'
);
check(
    'a plugin file written after the scan resolves with no cache flush, '
    . 'because the path is derived rather than looked up',
    class_exists('FOG\\Plugins\\Lateplugin\\Items\\LateThing'),
    $failures,
    $checks
);
check(
    'a plugin on the pre-1.6 layout does NOT resolve by its bare name',
    !class_exists('LegacyThing'),
    $failures,
    $checks
);

/*
 * 5. qualify() -- the seam every string-driven lookup goes through.
 */
$qualify = ['FOG\\Base\\FOGBase', 'qualify'];

check(
    'qualify() maps a bare plugin class to its FQCN',
    call_user_func($qualify, 'Widget')
    === 'FOG\\Plugins\\alphaPlugin\\Items\\Widget'
    || call_user_func($qualify, 'Widget')
    === 'FOG\\Plugins\\Betaplugin\\Items\\Widget',
    $failures,
    $checks
);
check(
    'qualify() is case-insensitive on the bare plugin name',
    strtolower(call_user_func($qualify, 'widgetmanager'))
    === 'fog\\plugins\\alphaplugin\\managers\\widgetmanager',
    $failures,
    $checks
);
check(
    'the model -> manager derivation crosses buckets',
    class_exists(call_user_func($qualify, 'WidgetManager')),
    $failures,
    $checks
);
check(
    'qualify() leaves an unknown name alone',
    call_user_func($qualify, 'DateTimeZone') === 'DateTimeZone',
    $failures,
    $checks
);
check(
    'qualify() leaves an already-qualified name alone',
    call_user_func($qualify, 'FOG\\Items\\Host') === 'FOG\\Items\\Host',
    $failures,
    $checks
);

/*
 * 6. CORE WINS. shadowplugin declares its own Host; the bare name must still
 *    be core's. This is the check that stands between a plugin and
 *    Authorization::_scopeClassVars() resolving 'host' to the wrong table.
 */
check(
    'shadowplugin\'s own Host exists under its own namespace',
    class_exists('FOG\\Plugins\\Shadowplugin\\Items\\Host'),
    $failures,
    $checks
);
check(
    'but the BARE name Host still qualifies to core',
    call_user_func($qualify, 'Host') === 'FOG\\Items\\Host',
    $failures,
    $checks
);
check(
    'and a bare core name a plugin does not claim is untouched',
    call_user_func($qualify, 'Image') === 'FOG\\Items\\Image',
    $failures,
    $checks
);

/*
 * 7. Discovery reads the bucket, and only for INSTALLED plugins. A plugin's
 *    directory being present is not consent to run its code: lib/plugins
 *    ships every bundled plugin on every install and Plugin Management is
 *    what decides which are live.
 */
\FOG\Base\FOGBase::$pluginsinstalled = ['alphaplugin', 'betaplugin'];
$pages = \FOG\Base\FOGBase::pluginitems('Pages');
check(
    'pluginitems() returns an installed plugin\'s page',
    in_array($pageA, $pages, true),
    $failures,
    $checks
);
check(
    'pluginitems() returns only the bucket asked for',
    !in_array($widgetA, $pages, true),
    $failures,
    $checks
);
check(
    'pluginitems() drops a plugin that is not installed',
    [] === preg_grep('#shadowplugin#', \FOG\Base\FOGBase::pluginitems('Items')),
    $failures,
    $checks
);
check(
    'classFromDiscoveredFile() derives a plugin FQCN from the path',
    \FOG\Base\FOGBase::classFromDiscoveredFile($pageA)
    === 'FOG\\Plugins\\alphaPlugin\\Pages\\AlphaManagement',
    $failures,
    $checks
);
check(
    'classFromDiscoveredFile() derives a CORE FQCN from the path too',
    \FOG\Base\FOGBase::classFromDiscoveredFile(
        $base . 'src/Pages/HostManagement.php'
    ) === 'FOG\\Pages\\HostManagement',
    $failures,
    $checks
);

/*
 * 8. An empty result is CACHED. A server with no plugins installed
 *    legitimately produces no entries; treating that as a cache miss would
 *    re-walk the plugin roots on every single request, forever, on exactly
 *    the installs that gain nothing from the walk.
 */
$cacheName = 'pluginsrc.' . md5(
    implode(
        '|',
        [
            $base . 'lib' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR,
            $ext,
        ]
    )
) . '.json';
check(
    'the plugin source list is persisted to its own cache file',
    is_file(FOG_CACHE_DIR . DIRECTORY_SEPARATOR . $cacheName),
    $failures,
    $checks
);

$readCache = new \ReflectionMethod('Initiator', '_readPluginFileCache');
$readCache->setAccessible(true);
$emptyCache = FOG_CACHE_DIR . DIRECTORY_SEPARATOR . 'pluginsrc.empty.json';
file_put_contents($emptyCache, '[]');
check(
    'an empty cached list is served, not treated as a miss',
    $readCache->invoke(null, $emptyCache) === [],
    $failures,
    $checks
);
$poisoned = FOG_CACHE_DIR . DIRECTORY_SEPARATOR . 'pluginsrc.poisoned.json';
file_put_contents($poisoned, json_encode(['/etc/passwd']));
check(
    'a cached list pointing outside the plugin roots is refused',
    $readCache->invoke(null, $poisoned) === null,
    $failures,
    $checks
);

/*
 * 9. Invalidation. The plugin uploader calls forgetClassFileList() when it
 *    writes a plugin into a loaded root while the server is running. The
 *    autoloader does not need it -- see section 4 -- but discovery does: a
 *    plugin whose classes resolve while its pages and hooks are invisible is
 *    the half-loaded state that function exists to prevent.
 */
Initiator::forgetClassFileList();
check(
    'forgetClassFileList() removes the plugin source cache',
    !is_file(FOG_CACHE_DIR . DIRECTORY_SEPARATOR . $cacheName),
    $failures,
    $checks
);
check(
    'and the plugin written after the first scan is now discoverable',
    in_array($late, Initiator::pluginFileList(), true),
    $failures,
    $checks
);

/*
 * 10. The diagnostics. Each is the only signal its condition produces, so an
 *     assertion on the CODE without one on the MESSAGE leaves the
 *     operator-facing half untested.
 */
$log = is_file($errlog) ? (string) file_get_contents($errlog) : '';

check(
    'a segment that does not lowercase to its directory is reported',
    strpos($log, 'plugin "oddplugin" declares namespace segment') !== false,
    $failures,
    $checks
);
check(
    'a plugin on the pre-1.6 layout is reported by name, not left silent',
    strpos($log, 'plugin "legacyplugin" uses the pre-1.6 layout') !== false,
    $failures,
    $checks
);
check(
    'but a directory that is not a plugin is not reported as a broken one',
    strpos($log, 'notaplugin') === false,
    $failures,
    $checks
);
check(
    'an ambiguous SHORT name across two plugins is reported',
    strpos($log, 'two plugins declare a class named "widget"') !== false,
    $failures,
    $checks
);

/*
 * 11. The two call sites that build a class name and then RESOLVE it as a
 *     string. Both were broken by namespacing the plugins and both failed
 *     without an error:
 *
 *       - PluginRunner::_discoverTasks() took basename($file, '.task.php')
 *         and handed it to is_subclass_of(). That is false for a namespaced
 *         task, so every plugin task was skipped and the daemon logged
 *         "Skipping, not a PluginTask".
 *       - Plugin::getManager() builds plugins.pName . 'Manager' and hands it
 *         to class_exists(). That is false too, so it falls back to
 *         PluginManager -- and installdb() then correctly reports a manager
 *         file it cannot load, so EVERY plugin install throws.
 *
 *     _discoverTasks() itself needs a database -- it walks Route::getList()
 *     over the installed plugins -- so its bucket and its derivation are
 *     anchored rather than executed, and the whole expression is anchored:
 *     an anchor on a symbol alone stays green when the argument moves. The
 *     end-to-end proof was a run of the daemon against a live install.
 */
$runnerSrc = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Service/PluginRunner.php'
);
check(
    'PluginRunner walks the Tasks bucket',
    strpos(
        $runnerSrc,
        "\$dir = rtrim(\$location, DS) . DS . 'src' . DS . 'Tasks';"
    ) !== false
    && strpos($runnerSrc, "glob(\$dir . DS . '*.php')") !== false,
    $failures,
    $checks
);
check(
    'PluginRunner derives a task class from the path, not from a bare '
    . 'basename',
    strpos(
        $runnerSrc,
        '$class = self::classFromDiscoveredFile($file);'
    ) !== false,
    $failures,
    $checks
);
// The mechanism itself, executed: a bare name is not the class.
check(
    'a bare task name is not a class on its own',
    !is_subclass_of('AlphaHeartbeat', 'FOG\\Base\\PluginTask'),
    $failures,
    $checks
);
check(
    'and the derived name IS the PluginTask subclass it names',
    is_subclass_of(
        \FOG\Base\FOGBase::classFromDiscoveredFile($task),
        'FOG\\Base\\PluginTask'
    ),
    $failures,
    $checks
);

/*
 *     Then Plugin::getManager(), which is reachable without a database: it
 *     reads one field and resolves a name, so the object is built without
 *     its constructor and its data set directly. This executes the real
 *     method -- deleting its qualify() reds this.
 */
$pref = new \ReflectionClass('FOG\Items\Plugin');
$plugin = $pref->newInstanceWithoutConstructor();
$dataProp = new \ReflectionProperty('FOG\Base\FOGController', 'data');
$dataProp->setAccessible(true);
$dataProp->setValue($plugin, ['name' => 'alphaplugin']);
// isLoaded is FOGController's recursion guard for its lazy loader: marking
// the key loaded is what stops get('name') going to the database for a field
// that has just been supplied. Nothing else here touches one.
$loadedProp = new \ReflectionProperty('FOG\Base\FOGController', 'isLoaded');
$loadedProp->setAccessible(true);
$loadedProp->setValue($plugin, ['name' => true]);
$resolvedManager = null;
try {
    $resolvedManager = get_class($plugin->getManager());
} catch (\Throwable $e) {
    $resolvedManager = 'threw ' . get_class($e) . ': ' . $e->getMessage();
}
check(
    'Plugin::getManager() resolves a namespaced plugin manager, not the '
    . 'PluginManager fallback (got ' . $resolvedManager . ')',
    $resolvedManager === 'FOG\\Plugins\\alphaPlugin\\Managers\\AlphapluginManager',
    $failures,
    $checks
);

if ($failures) {
    fwrite(STDERR, "FAIL (" . count($failures) . "/$checks checks)\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
