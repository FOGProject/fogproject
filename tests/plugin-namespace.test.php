<?php
/**
 * Plugins are namespaced, and an unnamespaced one still loads.
 *
 * Core finished its own migration first: every class under packages/web/src/
 * declares FOG\<Bucket>\<Class> and answers to nothing else. Plugins were
 * left in the global namespace, which cost three things -- two plugins
 * shipping class/settings.class.php shared one keyspace and one silently
 * shadowed the other; a plugin that wanted a namespace had to class_alias()
 * itself back, and getting that wrong took the whole admin UI down; and
 * plugin authors had to learn a second convention. They declare
 * FOG\Plugins\<Plugin>\<Class> now.
 *
 * Four mechanisms make that work and this test pins each of them, because
 * every one of them fails SILENTLY:
 *
 *  1. Initiator::_scanPluginNamespaces() reads each plugin file's actual
 *     namespace declaration. It does not DERIVE one from the path -- that
 *     distinction is the whole backwards-compatibility story, and it is
 *     invisible in the code. Deriving would hand qualify() a FOG\Plugins\
 *     name for a plugin still written globally, and the class_exists() that
 *     follows would then be false for a class that is sitting right there.
 *  2. Initiator::autoload() answers the FOG\Plugins\ prefix. Nothing else
 *     can: Composer maps FOG\ onto src/ and misses, the bare-name classMap
 *     is keyed on basenames, and _bridgeNamespaced() refuses any name with a
 *     second backslash. Without this arm a namespaced plugin does not
 *     resolve at all.
 *  3. FOGBase::qualify() consults the plugin map, which is what keeps the
 *     ~150 getClass('X') literals inside the plugins, all of page/hook/event/
 *     report/task discovery, FOGController::getManager(), FOGPage::$childClass
 *     and Route::_newEntity() working without editing a single call site.
 *  4. qualify() consults CORE FIRST. That order is the guarantee that a
 *     plugin cannot answer a core name -- and its failure mode is the worst
 *     one in the tree, because Authorization::_scopeClassVars() resolves a
 *     node to its model through qualify(): a wrong answer there is access
 *     control silently testing the wrong table.
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
 * Write one fixture plugin class file.
 *
 * @param string      $tmp    the throwaway root
 * @param string      $plugin the plugin directory name
 * @param string      $dir    the subdirectory (class, pages, hooks, ...)
 * @param string      $file   the file basename, including its suffix
 * @param string|null $ns     the namespace to declare, or null for global
 * @param string      $class  the class name to declare
 * @param string      $body   extra class body
 *
 * @return string the absolute path written
 */
function fixture($tmp, $plugin, $dir, $file, $ns, $class, $body = '')
{
    $path = $tmp . '/extplugins/' . $plugin . '/' . $dir;
    @mkdir($path, 0700, true);
    $path .= '/' . $file;
    $src = "<?php\n/**\n * Fixture.\n */\n\n";
    if ($ns !== null) {
        $src .= "namespace {$ns};\n\n";
    }
    $src .= "/**\n * Fixture.\n */\nclass {$class}\n{\n"
        . "    const MARK = '{$plugin}';\n{$body}\n}\n";
    file_put_contents($path, $src);
    return $path;
}

// A converted plugin: namespace derived from its directory name.
$widgetA = fixture(
    $tmp,
    'alphaplugin',
    'class',
    'widget.class.php',
    'FOG\\Plugins\\Alphaplugin',
    'Widget'
);
// Its manager, so the model -> manager derivation can be exercised. Both
// live in ONE flat namespace per plugin, which is exactly why: getManager()
// is qualify(shortName($this) . 'Manager'), so a Model\ / Manager\ split
// would not resolve.
$widgetManagerA = fixture(
    $tmp,
    'alphaplugin',
    'class',
    'widgetmanager.class.php',
    'FOG\\Plugins\\Alphaplugin',
    'WidgetManager'
);
// A page, to prove the discovery suffixes still work unchanged -- this is
// namespacing, not a PSR-4 move.
$pageA = fixture(
    $tmp,
    'alphaplugin',
    'pages',
    'alphamanagement.page.php',
    'FOG\\Plugins\\Alphaplugin',
    'AlphaManagement'
);
// A SECOND plugin shipping a class of the same short name. Under the global
// namespace exactly one of these could exist; both must now.
$widgetB = fixture(
    $tmp,
    'betaplugin',
    'class',
    'widget.class.php',
    'FOG\\Plugins\\Betaplugin',
    'Widget'
);
// An unconverted third-party plugin. ADR 0009 makes plugins installable
// artifacts FOG does not control, so this one must keep working untouched.
$legacy = fixture(
    $tmp,
    'legacyplugin',
    'class',
    'legacything.class.php',
    null,
    'LegacyThing'
);
// A manager named after the PLUGIN, not after a model. Plugin::getManager()
// builds plugins.pName . 'Manager', so this is the name that call site looks
// for. Deliberately a plain class: getManager() only instantiates it, and a
// real FOGManagerController wants a database this test does not have.
fixture(
    $tmp,
    'alphaplugin',
    'class',
    'alphapluginmanager.class.php',
    'FOG\\Plugins\\Alphaplugin',
    'AlphapluginManager'
);
// A plugin task. PluginRunner derives its class name from the FILENAME, so
// this is the shape that broke: see section 9.
$task = fixture(
    $tmp,
    'alphaplugin',
    'tasks',
    'alphaheartbeat.task.php',
    'FOG\\Plugins\\Alphaplugin',
    'AlphaHeartbeat extends \\FOG\\Base\\PluginTask',
    "    public function run()\n    {\n    }\n"
);
// A plugin trying to claim a CORE name from inside its own namespace. It is
// entitled to the class; it is not entitled to the bare spelling.
$shadow = fixture(
    $tmp,
    'shadowplugin',
    'class',
    'host.class.php',
    'FOG\\Plugins\\Shadowplugin',
    'Host'
);
// A plugin whose declared namespace disagrees with its directory. Resolves
// as declared -- refusing it would stop a working plugin loading over a
// naming opinion -- but forfeits the uniqueness the directory guarantees.
$offRule = fixture(
    $tmp,
    'oddplugin',
    'class',
    'oddthing.class.php',
    'FOG\\Plugins\\SomethingElse',
    'OddThing'
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

// Divert error_log() so the diagnostics themselves can be asserted on.
// Three of them are load-bearing: two must fire, and one must NOT, and the
// one that must not is a line that used to fire for a configuration that is
// now legal.
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
 * 1. _pluginOf() -- the derivation the whole scheme rests on. The plugin's
 *    directory name is its machine name: plugin.config.php's 'name' must
 *    equal it, ?node= addresses it, plugins.pName holds it. Deriving the
 *    namespace segment from anything inside the file instead would let two
 *    plugins claim one namespace and put us back where we started.
 */
$m = new \ReflectionMethod('Initiator', '_pluginOf');
$m->setAccessible(true);
$pluginOf = function ($path) use ($m) {
    return $m->invoke(null, $path);
};

$base = rtrim(BASEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$ext = rtrim(FOG_PLUGIN_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$derivations = [
    // Bundled root.
    $base . 'lib/plugins/ldap/class/ldapmanager.class.php' => 'ldap',
    $base . 'lib/plugins/capone/reg-task/caponetasking.class.php' => 'capone',
    $base . 'lib/plugins/ou/pages/oumanagement.page.php' => 'ou',
    // External root.
    $ext . 'alphaplugin/class/widget.class.php' => 'alphaplugin',
    // Not plugin code at all.
    $base . 'src/Items/Host.php' => '',
];
foreach ($derivations as $path => $expect) {
    check(
        sprintf('_pluginOf(%s) should be "%s"', $path, $expect),
        $pluginOf($path) === $expect,
        $failures,
        $checks
    );
}

/*
 * 2. The map holds what the FILES declare, and nothing else.
 *
 *    The legacy plugin is the load-bearing case. It must produce NO entry:
 *    an entry would make qualify('LegacyThing') hand back
 *    FOG\Plugins\Legacyplugin\LegacyThing, which nothing declares, and every
 *    third-party plugin in the field would stop resolving.
 */
$fqcnMap = Initiator::pluginClassMap();
$shortMap = Initiator::pluginShortMap();

check(
    'alphaplugin Widget is in the FQCN map',
    ($fqcnMap['fog\\plugins\\alphaplugin\\widget'] ?? null) === $widgetA,
    $failures,
    $checks
);
check(
    'betaplugin Widget is in the FQCN map too -- no shadowing',
    ($fqcnMap['fog\\plugins\\betaplugin\\widget'] ?? null) === $widgetB,
    $failures,
    $checks
);
check(
    'a discovery-suffixed page file is mapped like any other class',
    ($fqcnMap['fog\\plugins\\alphaplugin\\alphamanagement'] ?? null) === $pageA,
    $failures,
    $checks
);
check(
    'a plugin declaring NO namespace produces no FQCN entry',
    !isset($fqcnMap['fog\\plugins\\legacyplugin\\legacything']),
    $failures,
    $checks
);
check(
    'a plugin declaring NO namespace produces no short-name entry',
    !isset($shortMap['legacything']),
    $failures,
    $checks
);
check(
    'a namespace disagreeing with its directory still resolves as declared',
    ($fqcnMap['fog\\plugins\\somethingelse\\oddthing'] ?? null) === $offRule,
    $failures,
    $checks
);

/*
 * 3. autoload() answers the FOG\Plugins\ prefix.
 *
 *    Nothing above it in the chain can. Before this arm existed, a plugin
 *    that declared a namespace was simply unreachable, which is why ADR 0013
 *    told authors to class_alias() themselves back into the global namespace.
 */
check(
    'a namespaced plugin class resolves under its FQCN',
    class_exists('FOG\\Plugins\\Alphaplugin\\Widget'),
    $failures,
    $checks
);
check(
    'the second plugin\'s same-named class resolves independently',
    class_exists('FOG\\Plugins\\Betaplugin\\Widget'),
    $failures,
    $checks
);
// Each fixture carries a MARK naming the plugin whose file declares it, so
// this asserts which FILE each loaded class came from -- the anti-shadowing
// property itself, rather than the map that is supposed to produce it.
check(
    'each same-named class is the one from its OWN plugin\'s file',
    class_exists('FOG\\Plugins\\Alphaplugin\\Widget')
    && class_exists('FOG\\Plugins\\Betaplugin\\Widget')
    && constant('FOG\\Plugins\\Alphaplugin\\Widget::MARK') === 'alphaplugin'
    && constant('FOG\\Plugins\\Betaplugin\\Widget::MARK') === 'betaplugin',
    $failures,
    $checks
);
check(
    'an UNNAMESPACED plugin class still resolves by its bare name',
    class_exists('LegacyThing'),
    $failures,
    $checks
);
check(
    'a plugin FQCN core does not hold resolves to nothing, quietly',
    !class_exists('FOG\\Plugins\\Alphaplugin\\NoSuchClass'),
    $failures,
    $checks
);

/*
 * 4. qualify() -- the seam every string-driven lookup goes through.
 */
$qualify = ['FOG\\Base\\FOGBase', 'qualify'];

check(
    'qualify() maps a bare plugin class to its FQCN',
    call_user_func($qualify, 'Widget') === 'FOG\\Plugins\\Alphaplugin\\Widget'
    || call_user_func($qualify, 'Widget') === 'FOG\\Plugins\\Betaplugin\\Widget',
    $failures,
    $checks
);
check(
    'qualify() is case-insensitive on the bare plugin name',
    strtolower(call_user_func($qualify, 'widgetmanager'))
    === 'fog\\plugins\\alphaplugin\\widgetmanager',
    $failures,
    $checks
);
check(
    'the model -> manager derivation resolves inside one plugin namespace',
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
 * 5. CORE WINS. shadowplugin declares its own Host; the bare name must still
 *    be core's. This is the check that stands between a plugin and
 *    Authorization::_scopeClassVars() resolving 'host' to the wrong table.
 */
check(
    'shadowplugin\'s own Host exists under its own namespace',
    isset($fqcnMap['fog\\plugins\\shadowplugin\\host']),
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
 * 6. An empty result is CACHED. A tree whose plugins are all still global --
 *    which is every server until the converted plugins ship, and every
 *    server that installs none -- legitimately produces no entries.
 *    _readFileListCache() treats [] as a miss, so reusing it here would
 *    re-read every plugin file on every request forever, on exactly the
 *    installs that gain nothing from the scan. _readPluginMapCache() exists
 *    for that one difference and nothing else.
 */
$cacheName = 'pluginmap.' . md5(
    implode(
        '|',
        [
            rtrim(BASEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
            rtrim(FOG_PLUGIN_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
        ]
    )
) . '.json';
check(
    'the plugin map is persisted to its own cache file',
    is_file(FOG_CACHE_DIR . DIRECTORY_SEPARATOR . $cacheName),
    $failures,
    $checks
);

$readCache = new \ReflectionMethod('Initiator', '_readPluginMapCache');
$readCache->setAccessible(true);
$emptyCache = FOG_CACHE_DIR . DIRECTORY_SEPARATOR . 'pluginmap.empty.json';
file_put_contents($emptyCache, '[]');
check(
    'an empty cached map is served, not treated as a miss',
    $readCache->invoke(null, $emptyCache) === [],
    $failures,
    $checks
);
$poisoned = FOG_CACHE_DIR . DIRECTORY_SEPARATOR . 'pluginmap.poisoned.json';
file_put_contents(
    $poisoned,
    json_encode(['FOG\\Plugins\\Evil\\Thing' => '/etc/passwd'])
);
check(
    'a cached map pointing outside the scanned roots is refused',
    $readCache->invoke(null, $poisoned) === null,
    $failures,
    $checks
);

/*
 * 7. Invalidation. The plugin uploader calls forgetClassFileList() when it
 *    writes a plugin into a scanned root while the server is running. If
 *    this map is not dropped with the others the plugin half-loads for up to
 *    FILELIST_TTL: its classes resolve by their bare name and not by their
 *    namespaced one.
 */
Initiator::forgetClassFileList();
check(
    'forgetClassFileList() removes the plugin map cache',
    !is_file(FOG_CACHE_DIR . DIRECTORY_SEPARATOR . $cacheName),
    $failures,
    $checks
);
$newPlugin = fixture(
    $tmp,
    'lateplugin',
    'class',
    'latething.class.php',
    'FOG\\Plugins\\Lateplugin',
    'LateThing'
);
check(
    'a plugin written after the first scan is picked up after the flush',
    (Initiator::pluginClassMap()['fog\\plugins\\lateplugin\\latething'] ?? null)
    === $newPlugin,
    $failures,
    $checks
);

/*
 * 8. The diagnostics. Each of these is the only signal its condition
 *    produces, so an assertion on the CODE without one on the MESSAGE leaves
 *    the operator-facing half untested.
 */
$log = is_file($errlog) ? (string) file_get_contents($errlog) : '';

check(
    'a namespace disagreeing with its directory is reported',
    strpos($log, 'the plugin directory it sits in makes it') !== false
    && strpos($log, 'FOG\Plugins\Oddplugin') !== false,
    $failures,
    $checks
);
check(
    'an ambiguous SHORT name across two plugins is reported',
    strpos($log, 'two plugins declare a class named "widget"') !== false,
    $failures,
    $checks
);
check(
    'but the basename collision is NOT reported as shadowing',
    strpos($log, 'two files claim the class name "widget"') === false,
    $failures,
    $checks
);

/*
 * 9. The two call sites that build a class name and then RESOLVE it as a
 *    string. Both were broken by namespacing the plugins and both failed
 *    without an error:
 *
 *      - PluginRunner::_discoverTasks() takes basename($file, '.task.php')
 *        and hands it to is_subclass_of(). That is false for a namespaced
 *        task, so every plugin task is skipped and the daemon logs
 *        "Skipping, not a PluginTask" -- the identical failure ADR 0013 §2
 *        records for the SECOND argument of the same call.
 *      - Plugin::getManager() builds plugins.pName . 'Manager' and hands it
 *        to class_exists(). That is false too, so it falls back to
 *        PluginManager -- and installdb() then correctly reports a manager
 *        file it cannot load, so EVERY plugin install throws.
 *
 *    The mechanism first, executed rather than read: a name derived from a
 *    filename is bare, and only qualify() turns it into the class.
 */
$bareTask = basename($task, '.task.php');
check(
    'a name derived from a task FILENAME is not a class on its own',
    !is_subclass_of($bareTask, 'FOG\\Base\\PluginTask'),
    $failures,
    $checks
);
check(
    'qualify() turns it into the PluginTask subclass it names',
    is_subclass_of(
        call_user_func($qualify, $bareTask),
        'FOG\\Base\\PluginTask'
    ),
    $failures,
    $checks
);

/*
 *    _discoverTasks() itself needs a database -- it walks Route::getList()
 *    over the installed plugins -- so its call site is anchored rather than
 *    executed, and the whole expression is anchored, not the word qualify:
 *    an anchor on the symbol alone would stay green if the argument moved.
 *    The end-to-end proof was a run of the daemon against a live install.
 */
$runnerSrc = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Service/PluginRunner.php'
);
check(
    'PluginRunner derives a task class through qualify(), not from the '
    . 'bare basename',
    strpos(
        $runnerSrc,
        '$class = self::qualify(basename($file, \'.task.php\'));'
    ) !== false,
    $failures,
    $checks
);

/*
 *    Then the call sites themselves. Plugin::getManager() is reachable
 *    without a database: it reads one field and resolves a name, so the
 *    object is built without its constructor and its data set directly.
 *    This executes the real method -- deleting its qualify() reds this.
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
    $resolvedManager === 'FOG\\Plugins\\Alphaplugin\\AlphapluginManager',
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
