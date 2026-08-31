<?php
/**
 * Guards class resolution: the thing every other part of FOG stands on and
 * the one thing no test has ever covered.
 *
 * Unlike the other tests in this directory this one is NOT a source parse --
 * it boots the real autoloader and asks it real questions. That is possible
 * without a database because Initiator's constructor only registers the
 * autoloader (commons/init.php:127-135); it is Initiator::startInit() that
 * goes on to `new System()` and `new Config()` and needs MySQL. So the
 * constructor is called directly and startInit() deliberately is not.
 *
 * FOG_CACHE_DIR, FOG_LOG_DIR and FOG_PLUGIN_DIR are defined here first.
 * init.php guards each with `if (!defined(...))` (commons/init.php:91-125),
 * so pre-defining them redirects the file-list cache into a throwaway
 * directory instead of writing to /opt/fog on the machine running the test.
 *
 * FOG_BASE_DIR is deliberately NOT pre-defined, even though it is the parent
 * of all three. On a deployed tree init.php pulls in commons/fogpaths.php,
 * which the installer generates as a bare `define('FOG_BASE_DIR', ...)` with
 * no guard of its own -- pre-defining it there produces a "Constant already
 * defined" warning. Since every path this test could care about is overridden
 * individually above, FOG_BASE_DIR is left for whoever normally sets it and
 * goes unused.
 *
 * Two ways to run it:
 *
 *   php tests/autoload.test.php                    # the source tree
 *   php tests/autoload.test.php /var/www/html/fog  # a live server
 *
 * The second is a deployment diagnostic, and it answers questions the source
 * tree cannot: whether an upgrade left a duplicate class file behind, whether
 * a hand-installed plugin declares a class its filename does not, whether the
 * generated config.class.php is where the autoloader expects. Given an
 * explicit path the bridge check becomes a REPORT rather than an assertion --
 * a server legitimately runs an older FOG than the checkout you are standing
 * in, and failing over that would make the diagnostic mode useless. Every
 * other check still asserts: they are invariants of any tree that boots.
 *
 * Four things are checked:
 *   1. A representative class from each src/ bucket resolves, by the name its
 *      own file declares -- namespaced, including the 52 discovery-named
 *      page/hook/report/event classes bucketed there too. Nothing resolves
 *      bare any more, core or plugin (ADR 0013 sec 2, ADR 0035).
 *   2. Composer's autoloader is registered and reaches vendor/. Mysqldump
 *      is the proof: it is a FOG class whose parent lives in a package, so
 *      it cannot resolve unless both loaders are in the chain and in the
 *      right order. This check used to pin the opposite -- that twelve
 *      extra types were reachable only as a side effect of loading the
 *      2388-line vendored copy of that library, the single fact that made
 *      the tree PSR-4-hostile. The swap to ifsnop/mysqldump-php removed
 *      it, so the check now pins the arrangement that replaced it.
 *   3. Every autoloadable file declares a class matching its own filename.
 *      Under PSR-4 that is not a convention but the autoloading contract:
 *      every path is DERIVED from a class name and never searched for, so a
 *      file that breaks it is unreachable while looking perfectly fine.
 *   4. The shape of what the FOG\<Name> bridge does and does not answer.
 *
 * Usage: php tests/autoload.test.php [path/to/packages/web]
 * Exit status 0 = pass, 1 = fail.
 */


// An explicit path means "probe that tree", which is a different job from
// "check this checkout" -- see the header. Compared by realpath so that
// pointing it at the source tree by hand is still strict mode.
$default = dirname(__DIR__) . '/packages/web';
$webroot = rtrim($argv[1] ?? $default, '/');
$diagnostic = isset($argv[1])
    && realpath($webroot) !== false
    && realpath($webroot) !== realpath($default);

$init = $webroot . '/commons/init.php';

if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-autoload-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
@mkdir($tmp . '/sessions', 0700, true);

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

/*
 * FOG_CACHE_DIR is forced to a throwaway directory ALWAYS, in both modes,
 * and this is the one line here that must never become conditional.
 *
 * Left alone it defaults under FOG_BASE_DIR, and the boot source lists do
 * not merely read that directory -- they WRITE srcmap.<md5>.json and
 * pluginsrc.<md5>.json into it. Point the harness at a live server
 * without this and a test script rebuilds, or clobbers, that server's live
 * class-file cache. FOG_BASE_DIR and FOG_CACHE_DIR are guarded separately by
 * init.php, which is exactly what makes "real tree, harmless cache" possible.
 *
 * FOG_LOG_DIR goes the same way for the same reason, cheaply.
 */
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');

/*
 * The external plugin root is a real scan root (commons/init.php:299-306),
 * and the two modes want opposite things from it.
 *
 * Checking the checkout: pin it to an empty directory. Its contents differ
 * per machine, and a test whose result depends on what the developer happens
 * to have installed in /opt/fog is not a test.
 *
 * Probing a server: leave it undefined, so fogpaths.php (or init.php's
 * /opt/fog fallback) supplies the real one. Third-party plugins living there
 * are half the reason to run this against a server at all -- a hand-installed
 * plugin whose class name does not match its filename is invisible to hooks,
 * pages and reports, and this is the only thing that would say so.
 */
if (!$diagnostic) {
    define('FOG_PLUGIN_DIR', $tmp . '/plugins');
}

// The constructor calls session_start(). Keep it off the system session path
// so this never depends on that directory being writable by whoever ran it.
ini_set('session.save_path', $tmp . '/sessions');

require $init;
new Initiator();

/*
 * srcFileList() and the PSR-4 tree it describes arrived with 1.6. A 1.5 tree
 * has an Initiator that only sets include_path and registers the built-in
 * resolver, so everything below either fatals on an undefined method or
 * silently checks nothing. Caught here rather than left to blow up two
 * hundred lines later: pointing this at a 1.5 server is a reasonable thing to
 * try, and "that tree predates what this checks" is a useful answer where an
 * uncaught Error is not.
 */
if (!method_exists('Initiator', 'srcFileList')) {
    fwrite(
        STDERR,
        "FAIL: $webroot has no Initiator::srcFileList(), so it predates "
        . "the 1.6 PSR-4 tree this checks. Nothing to test here.\n"
    );
    exit(1);
}

$failures = [];

/*
 * 2. Mysqldump is a FOG class extending a Composer package, so resolving it
 * exercises both loaders: the FOG map finds mysqldump.class.php, and
 * declaring the class then needs Composer to supply the parent. Either half
 * missing is silent until somebody takes a backup -- a fatal on a page that
 * has already sent its headers, which is a bodyless 500.
 */
if (!class_exists('FOG\\Db\\Mysqldump')) {
    $failures[] = 'FOG\\Db\\Mysqldump did not resolve; either Composer is no '
        . 'longer mapping FOG\\ onto src/, or commons/init.php is no longer '
        . "requiring vendor/autoload.php, so the parent class it extends "
        . 'cannot be found';
} elseif (!is_subclass_of('FOG\\Db\\Mysqldump', 'Ifsnop\\Mysqldump\\Mysqldump')) {
    $failures[] = 'FOG\\Db\\Mysqldump resolved but is not a subclass of '
        . 'Ifsnop\Mysqldump\Mysqldump; the Composer package has been '
        . 're-vendored by hand, which is what ADR 0013 exists to prevent';
}

// 1. One name per src/ bucket, plus the two base classes everything descends
// from and the two traits FOGPage is built out of.
//
// Named as each file names ITSELF. Core moved to src/ under a namespace per
// bucket and no longer re-exports itself globally (ADR 0013 §2), so the bare
// spellings this list used to carry now resolve to nothing -- which is the
// decision, not a regression. That now includes the 52 discovery-named
// classes: they left their flat lib/ files and their class_alias trailers
// behind when they were bucketed into src/Pages, src/Hooks, src/Reports and
// src/Events (ADR 0013, amended 2026-08-30), so they resolve only by their
// namespaced spelling, the same as any other core class.
$sample = [
    'FOG\\Items\\Host'                 => 'class', // src/Items
    'FOG\\Managers\\HostManager'       => 'class', // src/Managers
    'FOG\\Base\\FOGBase'               => 'class', // src/Base, root of the hierarchy
    'FOG\\Base\\FOGController'         => 'class',
    'FOG\\Base\\FOGPagePost'           => 'trait', // src/Base, mixed-case filename
    'FOG\\Pages\\HostManagement'       => 'class', // src/Pages, discovery-named
    'FOG\\Pages\\UserGroupManagement'  => 'class', // src/Pages, mixed-case filename
    'FOG\\Db\\PDODB'                   => 'class', // src/Db
    'FOG\\Router\\Route'               => 'class', // src/Router
    'FOG\\Client\\FOGClient'           => 'class', // src/Client
    'FOG\\Service\\TaskScheduler'      => 'class', // src/Service
    'FOG\\Boot\\Registration'          => 'class', // src/Boot
];
foreach ($sample as $name => $kind) {
    $exists = $kind === 'trait' ? trait_exists($name) : class_exists($name);
    if (!$exists) {
        $failures[] = "$name did not resolve through the autoloader";
    }
}

// 1b. And the bare spellings of the core ones do NOT resolve. Without this
// every check above would pass just as happily with the aliases restored,
// which is the state this whole pass exists to leave behind.
foreach (['Host', 'HostManager', 'FOGBase', 'PDODB', 'Route',
    'HostManagement', 'UserGroupManagement'] as $bare) {
    if (class_exists($bare)) {
        $failures[] = "bare $bare resolved; core is aliased into the global "
            . 'namespace again (ADR 0013 §2 retired those aliases), so a '
            . 'plugin or an unswept caller can reach core by its short name';
    }
}

/*
 * 3. Filename == declared name, checked by reading each file rather than by
 * loading it. Loading all ~390 would execute every top-level statement in the
 * tree to prove a property of its text, and one file that fataled would take
 * the whole run with it.
 */
$mismatched = [];
$declares = [];
/*
 * Both lists, because the property is the same one and the floor below is
 * only honest if it covers the whole tree: srcFileList() is core's PSR-4
 * tree and pluginFileList() is every installed plugin's. On CI the second is
 * empty -- lib/plugins is gitignored staging and nothing fetches it -- which
 * is why the floor is set against core alone.
 */
$scanned = array_merge(
    array_values(Initiator::srcFileList()),
    array_values(Initiator::pluginFileList())
);
foreach ($scanned as $path) {
    $stem = basename($path, '.php');
    $src = file_get_contents($path);
    // Anchored to the start of a line so `class` inside a comment, a string
    // or `::class` cannot match. Abstract/final are the only modifiers this
    // tree uses on a first declaration.
    if (!preg_match(
        '/^(?:abstract\s+|final\s+)*(?:class|interface|trait)\s+([A-Za-z0-9_]+)/m',
        $src,
        $m
    )) {
        $mismatched[] = basename($path) . ' declares nothing';
        continue;
    }
    $declares[] = $m[1];
    if (strcasecmp($m[1], $stem) !== 0) {
        $mismatched[] = basename($path) . " declares {$m[1]}";
    }
}
if (count($declares) < 250) {
    $failures[] = 'only ' . count($declares) . ' autoloadable files found; '
        . 'the source roots look wrong';
}
foreach ($mismatched as $m) {
    $failures[] = "filename/class mismatch: $m";
}

// 4. The bridge, and the shape of what it does and does not answer. Asserted
// against the checkout, reported against a server: a server legitimately runs
// an older FOG than the tree you are standing in, and failing over that would
// make the diagnostic mode useless.
//
// It RESOLVES nothing now. It existed to answer the 46 discovery-named files
// that declared a flat `namespace FOG;` under lib/; those were bucketed into
// src/ (ADR 0013, amended 2026-08-30) and the last flat namespace in the tree
// went with the plugin layout (ADR 0035). So every flat FOG\<Name> is a wrong
// spelling of a bucketed class, and the bridge's whole job is to say which
// one was meant -- refusing, with a diagnostic, rather than resolving.
$bridged = class_exists('FOG\HostManagement');
if (!$diagnostic && $bridged) {
    $failures[] = 'FOG\HostManagement resolved; Initiator::_bridgeNamespaced() '
        . 'is answering a flat spelling for a class srcClassMap() already '
        . 'knows about instead of refusing it, which reopens the '
        . 'shadowing hole ADR 0013 closed';
}
// The refusal has to be a refusal, not a silent no-op: the namespaced
// spelling the diagnostic points to still has to resolve.
if (!class_exists('FOG\Pages\HostManagement')) {
    $failures[] = 'FOG\Pages\HostManagement did not resolve; refusing the '
        . 'flat spelling is only correct if the bucketed one still answers';
}
// The rest hold regardless of what HostManagement does: the bridge must not
// invent classes, resolve a nested name it does not own, or answer for a
// foreign namespace.
if (class_exists('FOG\NoSuchThingHere')) {
    $failures[] = 'the bridge invented FOG\NoSuchThingHere';
}
if (class_exists('FOG\Model\Host')) {
    $failures[] = 'the bridge resolved a nested name (FOG\Model\Host); '
        . 'it must only answer for flat FOG\<Name>';
}
if (class_exists('Vendor\Host')) {
    $failures[] = 'the bridge answered for a foreign namespace; a '
        . 'plugin\'s Vendor\Host must not silently become core Host';
}
// A flat FOG\<Name> for a class that lives in a BUCKET under src/ is a wrong
// spelling, not a name to bridge -- the general case FOG\HostManagement above
// is one instance of. Answering it would put core back within reach of a
// name no file declares, and -- because core is absent from the classMap --
// would hand the key to any plugin shipping class/host.class.php.
if (class_exists('FOG\Host')) {
    $failures[] = 'the bridge answered FOG\Host; core is namespaced per '
        . 'bucket, so only FOG\Items\Host names that class';
}

// In diagnostic mode say WHICH FOG was probed. "the bridge is missing" is
// only actionable next to the version that is missing it.
$version = '';
$sys = $webroot . '/src/Base/System.php';
if ($diagnostic && is_readable($sys)
    && preg_match("/FOG_VERSION',\s*'([^']+)'/", file_get_contents($sys), $vm)
) {
    $version = ' ' . $vm[1];
}

printf(
    "autoload%s: %d classes declared in %s%s, %d loaded this run, bridge=%s\n",
    $diagnostic ? ' (diagnostic)' : '',
    count($declares),
    $diagnostic ? $webroot : 'tree',
    $version,
    count(get_declared_classes()),
    $bridged ? 'yes' : 'no'
);

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

echo "PASS\n";
exit(0);
