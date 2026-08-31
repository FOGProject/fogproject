<?php
/**
 * Core resolves by its NAMESPACED name only, and a plugin still cannot shadow
 * a core class.
 *
 * docs/composer-psr4-plan.md. Composer's loader claims the FOG\ prefix and
 * answers nothing else, so once core left Initiator's classMap a request for
 * the BARE name `Host` had no answer. For one release cycle every file under
 * src/ ended in class_alias(__NAMESPACE__ . '\X', 'X') and the bare spelling
 * kept working. Those 202 aliases are now gone (ADR 0013 §2), and this is
 * what holds what replaced them.
 *
 * Four properties. The third is the one that is easy to lose and the fourth
 * is the one the retirement nearly took with it:
 *
 *   1. The namespaced name resolves, through Composer, from the bucket the
 *      file sits in. FOG\Items\ProbeAlpha, not FOG\ProbeAlpha.
 *   2. The bare name does NOT resolve, in any spelling. This is the whole
 *      point of the retirement, and an accidental alias anywhere would make
 *      every other check here pass for the wrong reason.
 *   3. A plugin shipping src/Items/ProbeAlpha.php STILL cannot answer a bare
 *      `ProbeAlpha`. The autoloader recognizes the name as core's and
 *      refuses rather than falling through to the plugin roots. Losing that
 *      is a privilege escalation with no other symptom, and note it is a
 *      REFUSAL rather than a preference: core and the plugin no longer share
 *      a key space to be ordered within.
 *   4. Both classes exist. That is what namespacing bought: the plugin's
 *      FOG\Plugins\ProbePlug\Items\ProbeAlpha resolves to the plugin's own
 *      file at the same time as FOG\Items\ProbeAlpha resolves to core's, and
 *      neither shadows the other. A FLAT FOG\<Name> resolves to neither --
 *      every core class is in a bucket now, so a flat name is a misspelling
 *      and _bridgeNamespaced() answers it with a diagnostic (ADR 0035).
 *
 * Runs against a MINIATURE tree in the system temp directory -- a copy of
 * commons/init.php with its own src/ and lib/plugins/ -- rather
 * than against packages/web. Initiator derives BASEPATH from its own location,
 * so a copied init.php gets a BASEPATH of the copy, and the probe classes
 * never touch the repository. That matters more than tidiness here: this test
 * deliberately creates a plugin file NAMED AFTER a core class, and leaving one
 * of those in a real tree is the exact defect under test.
 *
 * DB-free: Initiator's constructor only registers the autoloader. It is
 * startInit() that reaches MySQL, and it is never called.
 *
 * Usage: php tests/psr4-bridge.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$web = $repo . '/packages/web';
$tmp = sys_get_temp_dir() . '/fog-psr4-bridge-' . getmypid();

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

foreach (['commons', 'src/Items', 'src/Base',
          'lib/plugins/probeplug/src/Items',
          'lib/plugins/probeplug/config', 'cache', 'log',
          'extplugins'] as $d) {
    if (!@mkdir($tmp . '/' . $d, 0700, true) && !is_dir($tmp . '/' . $d)) {
        fwrite(STDERR, "FAIL: cannot create $tmp/$d\n");
        exit(1);
    }
}

// config/plugin.config.php is what makes a directory a plugin: it is what
// Plugin::_getDirs() globs for and what Initiator::_scanPluginSource()
// requires before it will look for a src/ tree at all.
file_put_contents(
    $tmp . '/lib/plugins/probeplug/config/plugin.config.php',
    "<?php\n\$fog_plugin = ['name' => 'probeplug'];\n"
);

if (!@copy($web . '/commons/init.php', $tmp . '/commons/init.php')) {
    fwrite(STDERR, "FAIL: cannot copy commons/init.php\n");
    exit(1);
}

/*
 * The probe classes.
 *
 * ProbeAlpha exists TWICE on purpose: once under src/ as core, once under a
 * bundled plugin claiming the same name. Which one answers is the whole
 * point of check 3.
 */
file_put_contents(
    $tmp . '/src/Items/ProbeAlpha.php',
    "<?php\nnamespace FOG\\Items;\n"
    . "class ProbeAlpha { public static function who() { return 'core'; } }\n"
);
file_put_contents(
    $tmp . '/lib/plugins/probeplug/src/Items/ProbeAlpha.php',
    "<?php\nnamespace FOG\\Plugins\\ProbePlug\\Items;\n"
    . "class ProbeAlpha { public static function who() { return 'plugin'; } }\n"
);
/*
 * A plugin class core knows nothing about, proving the src/ arm FALLS
 * THROUGH rather than swallowing every bare name.
 */
file_put_contents(
    $tmp . '/lib/plugins/probeplug/src/Items/ProbeOnlyPlugin.php',
    "<?php\nnamespace FOG\\Plugins\\ProbePlug\\Items;\n"
    . "class ProbeOnlyPlugin { }\n"
);
/*
 * A third plugin class, used only to prove the plugin arm's prefix match is
 * case-insensitive (strncasecmp). It needs its own file because the check has
 * to be the FIRST request for the class: once any spelling has been loaded,
 * PHP's own class table answers every other casing without consulting an
 * autoloader at all, so asserting against an already-loaded probe passes
 * whatever the autoloader does. That is exactly how the version of this check
 * that used to live in autoload.test.php was a fake gate -- verified by
 * mutating strncasecmp to strncmp, which it did not catch.
 */
file_put_contents(
    $tmp . '/lib/plugins/probeplug/src/Items/ProbeCased.php',
    "<?php\nnamespace FOG\\Plugins\\ProbePlug\\Items;\n"
    . "class ProbeCased { }\n"
);
/*
 * A second core class, in a DIFFERENT bucket. srcClassMap() derives the
 * namespace from the parent directory name, so one bucket cannot prove it.
 */
file_put_contents(
    $tmp . '/src/Base/ProbeBeta.php',
    "<?php\nnamespace FOG\\Base;\nclass ProbeBeta { }\n"
);

// Diverted so the deliberate refusals below can be asserted on, and so a
// passing test leaves no alarming lines in the tester's own PHP log.
ini_set('log_errors', '1');
ini_set('error_log', $tmp . '/php-error.log');

define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/extplugins');

require_once $tmp . '/commons/init.php';
new Initiator();

/*
 * Composer's half. composer.json will carry FOG\ => the taxonomy roots; this
 * registers the same thing against the miniature tree, so the namespaced
 * spelling is exercised by the real ClassLoader rather than by a stand-in.
 */
if (is_readable($web . '/vendor/composer/ClassLoader.php')) {
    require_once $web . '/vendor/composer/ClassLoader.php';
    $loader = new \Composer\Autoload\ClassLoader();
    $loader->setPsr4('FOG\\', [$tmp . '/src']);
    $loader->register(true);
}

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

$fileOf = function ($class) {
    return class_exists($class) ? (new \ReflectionClass($class))->getFileName() : null;
};

// 1. The src/ scan finds the files and keys them lowercased.
$map = Initiator::srcFileList();
check('srcFileList finds ProbeAlpha', isset($map['probealpha']), $failures, $checks);
check('srcFileList finds ProbeBeta', isset($map['probebeta']), $failures, $checks);
check(
    'srcFileList keys are lowercased',
    $map === array_change_key_case($map, CASE_LOWER),
    $failures,
    $checks
);

// 1b. srcClassMap turns those into FQCNs, taking the namespace from the
// bucket directory. FOGBase::qualify() is this map, and every string-driven
// instantiation left in core -- getClass(), Route::_newEntity(),
// FOGPage's $childClass -- resolves through it.
$classMap = Initiator::srcClassMap();
check(
    'srcClassMap qualifies into the Items bucket',
    ($classMap['probealpha'] ?? null) === 'FOG\\Items\\ProbeAlpha',
    $failures,
    $checks
);
check(
    'srcClassMap qualifies into the Base bucket',
    ($classMap['probebeta'] ?? null) === 'FOG\\Base\\ProbeBeta',
    $failures,
    $checks
);

// 2. The bare name does not resolve, in any spelling.
//
// First, because that is the decision. Second, because every check below it
// would pass for the wrong reason if an alias crept back in -- check 3 in
// particular reads "core answered, not the plugin" and an alias makes that
// true without the autoloader having refused anything.
foreach (['ProbeAlpha', 'probealpha', 'PROBEALPHA'] as $spelling) {
    check("bare '$spelling' does NOT resolve", !class_exists($spelling), $failures, $checks);
}

// 3. THE ONE THAT MATTERS. A plugin file named after a core class cannot
// answer the bare name either. Structural now rather than a preference:
// nothing maps a bare name to a file any more, so there is no path by which
// lib/plugins could answer one. The assertion stays because that is a
// property of the whole chain, not of one branch -- a single class_alias()
// anywhere, or a fourth autoloader added later, re-opens it silently.
//
// The live ORDERING guarantee is FOGBase::qualify(), which consults core's
// map before the plugin one, and tests/plugin-namespace.test.php holds it.
check(
    'the plugin file did not answer bare ProbeAlpha',
    null === $fileOf('ProbeAlpha'),
    $failures,
    $checks
);
// And the refusal says which spelling was meant. It is the only thing an
// author gets -- PHP's own "class not found" points at the caller and gives
// no hint that the class exists one segment away.
$log = is_file($tmp . '/php-error.log')
    ? (string)file_get_contents($tmp . '/php-error.log')
    : '';
check(
    'and the refusal named the qualified spelling to use instead',
    strpos($log, '"ProbeAlpha" is a core class') !== false
    && strpos($log, 'FOG\Items\ProbeAlpha') !== false,
    $failures,
    $checks
);

// 4. The namespaced name resolves, from its bucket, to the core file.
check(
    'FOG\Items\ProbeAlpha resolves',
    class_exists('FOG\Items\ProbeAlpha'),
    $failures,
    $checks
);
check(
    'and it is the core implementation that answers',
    class_exists('FOG\Items\ProbeAlpha')
    && \FOG\Items\ProbeAlpha::who() === 'core',
    $failures,
    $checks
);
check(
    'and it is the src/ file, not the plugin one',
    $fileOf('FOG\Items\ProbeAlpha') === $tmp . '/src/Items/ProbeAlpha.php',
    $failures,
    $checks
);

// 5. A FLAT FOG\<Name> for a BUCKETED core class is a wrong spelling, not a
// name to resolve. Refused, for the same shadowing reason as check 3.
check(
    'flat FOG\ProbeAlpha does not resolve',
    !class_exists('FOG\ProbeAlpha'),
    $failures,
    $checks
);

// 6. The plugin's own class exists at the same time, under its own name, and
// is the PLUGIN file. Two classes of one short name, neither shadowing the
// other, is the thing namespacing bought.
check(
    'FOG\Plugins\ProbePlug\Items\ProbeAlpha resolves',
    class_exists('FOG\Plugins\ProbePlug\Items\ProbeAlpha'),
    $failures,
    $checks
);
check(
    'and it is the plugin implementation that answers',
    class_exists('FOG\Plugins\ProbePlug\Items\ProbeAlpha')
    && \FOG\Plugins\ProbePlug\Items\ProbeAlpha::who() === 'plugin',
    $failures,
    $checks
);
check(
    'and it is the plugin file, not the src/ one',
    $fileOf('FOG\Plugins\ProbePlug\Items\ProbeAlpha')
    === $tmp . '/lib/plugins/probeplug/src/Items/ProbeAlpha.php',
    $failures,
    $checks
);
// The plugin arm's prefix match is strncasecmp and PHP class names are
// case-insensitive, so it has to answer a differently-cased prefix too.
// Asserted against ProbeCased, whose FIRST request this is -- see the fixture
// comment for why reusing ProbeAlpha here would prove nothing.
check(
    'the plugin arm is case-insensitive on the FOG\Plugins\ prefix',
    class_exists('fog\plugins\ProbePlug\Items\ProbeCased'),
    $failures,
    $checks
);

// 7. The bare name of a plugin-only class. Nothing autoloads it -- the
// autoloader answers namespaced names only -- so it reaches core through
// pluginShortMap(), which is what FOGBase::qualify() consults and what keeps
// the getClass('X') literals inside the plugins working.
$short = Initiator::pluginShortMap();
check(
    'a plugin-only class is reachable by its bare name through the short map',
    ($short['probeonlyplugin'] ?? null)
    === 'FOG\Plugins\ProbePlug\Items\ProbeOnlyPlugin',
    $failures,
    $checks
);
check(
    'and that name resolves to the plugin file',
    $fileOf($short['probeonlyplugin'] ?? '')
    === $tmp . '/lib/plugins/probeplug/src/Items/ProbeOnlyPlugin.php',
    $failures,
    $checks
);
check(
    'while the BARE spelling still autoloads nothing at all',
    !class_exists('ProbeOnlyPlugin'),
    $failures,
    $checks
);

// 8. The map is memoised, and forgetting it clears both maps and both caches.
$cacheFile = FOG_CACHE_DIR . '/srcmap.' . md5($tmp . '/src') . '.json';
check('the src map is persisted', is_file($cacheFile), $failures, $checks);
check(
    'the persisted map keeps its keys',
    is_file($cacheFile)
    && is_array(json_decode((string) file_get_contents($cacheFile), true))
    && isset(json_decode((string) file_get_contents($cacheFile), true)['probealpha']),
    $failures,
    $checks
);
Initiator::forgetClassFileList();
check(
    'forgetClassFileList removes the src map cache',
    !is_file($cacheFile),
    $failures,
    $checks
);
check(
    'and the map rebuilds itself',
    isset(Initiator::srcFileList()['probealpha']),
    $failures,
    $checks
);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " of $checks check(s)\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
