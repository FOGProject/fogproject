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
 *   1. A representative class from each scan root resolves.
 *   2. The Mysqldump side-effect: twelve of the thirteen types in
 *      lib/db/mysqldump.class.php are reachable ONLY once Mysqldump itself
 *      has loaded. Pinned deliberately -- it is the single fact that stops
 *      the legacy autoloader ever being replaced wholesale by PSR-4, and it
 *      should break a test rather than a customer if someone tries.
 *   3. Every autoloadable file declares a class matching its own filename.
 *      All four discovery paths derive the class name arithmetically from
 *      the basename and none of them parses source for a `class` token, so
 *      a file that breaks this is invisible to hooks, events, pages and
 *      reports while looking perfectly fine.
 *   4. Whether a namespaced name resolves. See EXPECT_BRIDGE below.
 *
 * Usage: php tests/autoload.test.php [path/to/packages/web]
 * Exit status 0 = pass, 1 = fail.
 */

/*
 * Flipped by the commit that adds the FOG\ bridge to Initiator::autoload().
 * Before it, `FOG\Host` misses silently: the map is keyed on a lowercased
 * basename so `fog\host` can never be a key, and the bare spl_autoload()
 * behind it probes for `fog/host.class.php`, which no include_path entry
 * holds. After it, `FOG\Host` resolves to the same class entry as `Host`.
 *
 * This constant existing rather than the assertion simply being deleted is
 * the point: the flip is the bridge's regression test.
 */
const EXPECT_BRIDGE = true;

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
 * Left alone it defaults under FOG_BASE_DIR, and classFileList() does not
 * merely read that directory -- it WRITES filelist.<md5>.json into it
 * (commons/init.php:195, :415-429). Point the harness at a live server
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
 * classFileList() and the O(1) class map arrived with 1.6. A 1.5 tree has an
 * Initiator that only sets include_path and registers the built-in resolver,
 * so everything below either fatals on an undefined method or silently checks
 * nothing. Caught here rather than left to blow up two hundred lines later:
 * pointing this at a 1.5 server is a reasonable thing to try, and "that tree
 * predates what this checks" is a useful answer where an uncaught Error is
 * not.
 */
if (!method_exists('Initiator', 'classFileList')) {
    fwrite(
        STDERR,
        "FAIL: $webroot has no Initiator::classFileList(), so it predates "
        . "the 1.6 class map this checks. Nothing to test here.\n"
    );
    exit(1);
}

$failures = [];

/*
 * 2 first, because it is the only check whose result depends on what has
 * already been loaded. Running it after the sample below -- which loads
 * Mysqldump -- would make it pass for the wrong reason.
 */
if (class_exists('CompressGzip')) {
    $failures[] = 'CompressGzip resolved on its own; it is declared inside '
        . 'mysqldump.class.php and should only be reachable once Mysqldump '
        . 'has loaded';
}
if (!class_exists('Mysqldump')) {
    $failures[] = 'Mysqldump did not resolve';
} elseif (!class_exists('CompressGzip', false)) {
    $failures[] = 'CompressGzip is not declared after loading Mysqldump; '
        . 'mysqldump.class.php no longer declares its twelve extra types, so '
        . 'the one-file-many-classes constraint on PSR-4 may have lifted';
}

// 1. One name per scan root, plus the two base classes everything descends
// from and the two traits FOGPage is built out of.
$sample = [
    'Host'                => 'class',   // lib/fog
    'HostManager'         => 'class',   // lib/fog, manager
    'FOGBase'             => 'class',   // lib/fog, root of the hierarchy
    'FOGController'       => 'class',
    'FOGPagePost'         => 'trait',   // lib/fog, mixed-case filename
    'HostManagement'      => 'class',   // lib/pages
    'UserGroupManagement' => 'class',   // lib/pages, mixed-case filename
    'PDODB'               => 'class',   // lib/db
    'Route'               => 'class',   // lib/router
    'FOGClient'           => 'class',   // lib/client
    'TaskScheduler'       => 'class',   // lib/service
    'Registration'        => 'class',   // lib/reg-task
];
foreach ($sample as $name => $kind) {
    $exists = $kind === 'trait' ? trait_exists($name) : class_exists($name);
    if (!$exists) {
        $failures[] = "$name did not resolve through the autoloader";
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
foreach (Initiator::classFileList() as $path) {
    $stem = preg_replace(
        '#\.(report|event|class|hook|page|task)\.php$#',
        '',
        basename($path)
    );
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
if (count($declares) < 100) {
    $failures[] = 'only ' . count($declares) . ' autoloadable files found; '
        . 'the scan roots look wrong';
}
foreach ($mismatched as $m) {
    $failures[] = "filename/class mismatch: $m";
}

// 4. The bridge. Asserted against the checkout, reported against a server:
// a server legitimately runs an older FOG than the tree you are standing in,
// and failing over that would make the diagnostic mode useless.
$bridged = class_exists('FOG\Host');
if (!$diagnostic && $bridged !== EXPECT_BRIDGE) {
    $failures[] = EXPECT_BRIDGE
        ? 'FOG\Host did not resolve; the Initiator::autoload() bridge is '
            . 'missing or no longer aliases short names'
        : 'FOG\Host resolved unexpectedly; if the bridge has landed, flip '
            . 'EXPECT_BRIDGE at the top of this file';
}
if ($bridged) {
    // An alias, not a second class: same class entry, so `instanceof` and
    // every getClass()/Reflection consumer see one type. get_class() still
    // reports the declared name -- that asymmetry is why namespacing the
    // models is a separate problem from bridging their names.
    $refFqcn = new \ReflectionClass('FOG\Host');
    $refShort = new \ReflectionClass('Host');
    if ($refFqcn->getName() !== $refShort->getName()) {
        $failures[] = 'FOG\Host resolves to a different class entry than '
            . 'Host (' . $refFqcn->getName() . ' vs ' . $refShort->getName()
            . '); it should be an alias, not a copy';
    }
    if (!trait_exists('FOG\FOGPagePost')) {
        $failures[] = 'the bridge does not carry traits';
    }
    if (!class_exists('fog\Image')) {
        $failures[] = 'the bridge is case-sensitive on the namespace prefix; '
            . 'PHP class names are not';
    }
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
}

// In diagnostic mode say WHICH FOG was probed. "the bridge is missing" is
// only actionable next to the version that is missing it.
$version = '';
$sys = $webroot . '/lib/fog/system.class.php';
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
