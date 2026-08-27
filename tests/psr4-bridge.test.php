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
 *   3. A plugin shipping class/probealpha.class.php STILL cannot answer a
 *      bare `ProbeAlpha`. Core is not in the classMap, so that plugin file
 *      is the only candidate for the key and would win outright -- the
 *      autoloader recognises the name as core's and refuses rather than
 *      falling through. Losing that is a privilege escalation with no other
 *      symptom, and note it is now a REFUSAL rather than a preference: the
 *      old ordering guarantee has nothing left to order.
 *   4. A FLAT FOG\<Name> still resolves when a flat FOG\<Name> is what the
 *      file declares. The 46 discovery-named classes under lib/ -- pages,
 *      hooks, reports, the one event -- are `namespace FOG;` and stay there,
 *      because FOGPageManager::loadPageClasses() derives their class name
 *      from basename($file) and PSR-4 does not do discovery. Composer maps
 *      FOG\ onto src/, so `use FOG\ReportManagement;` in a core file is
 *      answered by Initiator::_bridgeNamespaced() and by nothing else. The
 *      plan said to delete that bridge alongside the aliases; doing so breaks
 *      every core file that imports one of the 46.
 *
 * Runs against a MINIATURE tree in the system temp directory -- a copy of
 * commons/init.php with its own src/, lib/pages/ and lib/plugins/ -- rather
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

foreach (['commons', 'src/Items', 'src/Base', 'lib/pages',
          'lib/plugins/probeplug/class', 'cache', 'log', 'extplugins'] as $d) {
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
    "<?php\nnamespace FOG\\Items;\n"
    . "class ProbeAlpha { public static function who() { return 'core'; } }\n"
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
 * A second core class, in a DIFFERENT bucket. srcClassMap() derives the
 * namespace from the parent directory name, so one bucket cannot prove it.
 */
file_put_contents(
    $tmp . '/src/Base/ProbeBeta.php',
    "<?php\nnamespace FOG\\Base;\nclass ProbeBeta { }\n"
);
/*
 * A discovery-named page, standing in for the 46 under lib/. Flat
 * `namespace FOG;` plus its own class_alias back to the global name -- which
 * is NOT the retired kind. FOGPageManager::loadPageClasses() looks the class
 * up by basename, so these files keep theirs.
 */
file_put_contents(
    $tmp . '/lib/pages/probediscovered.page.php',
    "<?php\nnamespace FOG;\nclass ProbeDiscovered { }\n"
    . "class_alias(__NAMESPACE__ . '\\ProbeDiscovered', 'ProbeDiscovered');\n"
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
// answer the bare name either -- the refusal in check 2 has to be a refusal,
// not a fall-through to lib/plugins.
check(
    'the plugin file did not answer bare ProbeAlpha',
    null === $fileOf('ProbeAlpha'),
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

// 6. A FLAT FOG\<Name> that a lib/ file actually DECLARES does resolve.
// This is the arm the plan said to delete; `use FOG\ReportManagement;` in
// core has no other answer. See the header.
check(
    'FOG\ProbeDiscovered resolves through the bridge',
    class_exists('FOG\ProbeDiscovered'),
    $failures,
    $checks
);
check(
    'and it is the lib/pages file that declared it',
    $fileOf('FOG\ProbeDiscovered') === $tmp . '/lib/pages/probediscovered.page.php',
    $failures,
    $checks
);
check(
    'and its own class_alias still exports the bare name, which is what '
    . 'FOGPageManager::loadPageClasses() looks up',
    class_exists('ProbeDiscovered'),
    $failures,
    $checks
);

// 7. The arm falls through: a plugin-only class still resolves via classMap.
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
