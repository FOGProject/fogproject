<?php
/**
 * Bare class names still resolve once core lives under src/, and a plugin
 * still cannot shadow a core class.
 *
 * Commit 1 of docs/composer-psr4-plan.md. Composer's loader claims the FOG\
 * prefix and answers nothing else, so the moment core leaves Initiator's
 * classMap a request for the BARE name `Host` has no answer -- and bare names
 * are how FOGProject/fog-plugins inherits from core 168 times and how all 52
 * entries in Route::$validClasses resolve. autoload() therefore grew a second,
 * opposite arm. This is what holds it.
 *
 * Two properties, and the second is the one that is easy to lose:
 *
 *   1. `Host`, `host` and `HOST` all resolve to the class in src/. The map is
 *      keyed on the lowercased basename because that is the only spelling
 *      every caller agrees on.
 *   2. The src/ arm runs BEFORE the classMap. The classMap's core-wins rule
 *      works by preferring one of two candidates for a key -- and once core
 *      is not in that map, a plugin shipping class/host.class.php is the only
 *      candidate and wins outright. Order is now the whole of that guarantee,
 *      so reordering these two lookups is a privilege escalation with no
 *      other symptom.
 *
 * Runs against a MINIATURE tree in the system temp directory -- a copy of
 * commons/init.php with its own src/ and lib/plugins/ -- rather than against
 * packages/web. Initiator derives BASEPATH from its own location, so a copied
 * init.php gets a BASEPATH of the copy, and the probe classes never touch the
 * repository. That matters more than tidiness here: this test deliberately
 * creates a plugin file NAMED AFTER a core class, and leaving one of those in
 * a real tree is the exact defect under test.
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

foreach (['commons', 'src/Items', 'src/Base', 'lib/plugins/probeplug/class',
          'cache', 'log', 'extplugins'] as $d) {
    if (!@mkdir($tmp . '/' . $d, 0700, true) && !is_dir($tmp . '/' . $d)) {
        fwrite(STDERR, "FAIL: cannot create $tmp/$d\n");
        exit(1);
    }
}

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
    "<?php\nnamespace FOG;\nclass ProbeAlpha { public static function who() { return 'core'; } }\n"
    . "class_alias(__NAMESPACE__ . '\\ProbeAlpha', 'ProbeAlpha');\n"
);
file_put_contents(
    $tmp . '/lib/plugins/probeplug/class/probealpha.class.php',
    "<?php\nclass ProbeAlpha { public static function who() { return 'plugin'; } }\n"
);
/*
 * A plugin class core knows nothing about, proving the src/ arm FALLS
 * THROUGH rather than swallowing every bare name.
 */
file_put_contents(
    $tmp . '/lib/plugins/probeplug/class/probeonlyplugin.class.php',
    "<?php\nclass ProbeOnlyPlugin { }\n"
);
/*
 * A src/ file that forgets its class_alias. ADR 0013 requires one; this is
 * what a reader gets when it is missing.
 */
file_put_contents(
    $tmp . '/src/Base/ProbeNoAlias.php',
    "<?php\nnamespace FOG;\nclass ProbeNoAlias { }\n"
);

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
    $loader->setPsr4('FOG\\', [$tmp . '/src', $tmp . '/src/Items', $tmp . '/src/Base']);
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
check('srcFileList finds ProbeNoAlias', isset($map['probenoalias']), $failures, $checks);
check(
    'srcFileList keys are lowercased',
    $map === array_change_key_case($map, CASE_LOWER),
    $failures,
    $checks
);

// 2. Every spelling of a bare name resolves, and to the SAME type.
foreach (['ProbeAlpha', 'probealpha', 'PROBEALPHA'] as $spelling) {
    check("bare '$spelling' resolves", class_exists($spelling), $failures, $checks);
}
check(
    'all three spellings are one type',
    class_exists('ProbeAlpha')
    && (new \ReflectionClass('probealpha'))->getName()
       === (new \ReflectionClass('PROBEALPHA'))->getName(),
    $failures,
    $checks
);

// 3. THE ONE THAT MATTERS. Core wins over a plugin file of the same name.
check(
    'bare ProbeAlpha resolves to src/, not to the plugin',
    $fileOf('ProbeAlpha') === $tmp . '/src/Items/ProbeAlpha.php',
    $failures,
    $checks
);
check(
    'and it is the core implementation that answers',
    class_exists('ProbeAlpha') && ProbeAlpha::who() === 'core',
    $failures,
    $checks
);

// 4. The namespaced spelling is the same type -- one class, two names.
check(
    'FOG\ProbeAlpha is the same type as bare ProbeAlpha',
    class_exists('FOG\ProbeAlpha')
    && (new \ReflectionClass('FOG\ProbeAlpha'))->getName()
       === (new \ReflectionClass('ProbeAlpha'))->getName(),
    $failures,
    $checks
);

// 5. The arm falls through: a plugin-only class still resolves via classMap.
check(
    'a plugin-only class still resolves',
    class_exists('ProbeOnlyPlugin'),
    $failures,
    $checks
);
check(
    'and it resolves to the plugin file',
    $fileOf('ProbeOnlyPlugin')
    === $tmp . '/lib/plugins/probeplug/class/probeonlyplugin.class.php',
    $failures,
    $checks
);

// 6. A src/ file with no class_alias does not silently half-work.
check(
    'namespaced name resolves without the alias',
    class_exists('FOG\ProbeNoAlias'),
    $failures,
    $checks
);
check(
    'but the bare name does not',
    !class_exists('ProbeNoAlias'),
    $failures,
    $checks
);

// 7. The map is memoised, and forgetting it clears both maps and both caches.
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
