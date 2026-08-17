<?php
/**
 * A plugin may ship its own Composer dependencies -- unless they collide.
 *
 * Boots the real Initiator against a throwaway FOG_PLUGIN_DIR holding four
 * fixture plugins, then asks the autoload chain real questions. Like
 * autoload.test.php this needs no database: the constructor only registers
 * autoloaders, and startInit() -- the part that wants MySQL -- is never
 * called.
 *
 * Four properties, and the last two are the ones that matter:
 *
 *   1. A plugin with vendor/ gets its classes.
 *   2. A plugin without one still works. (It is the normal case; every
 *      bundled plugin today has no vendor/ at all.)
 *   3. A second plugin claiming a namespace the first already claimed is
 *      REFUSED, not silently shadowed. Two copies of one package otherwise
 *      resolve to whichever registered first, which is readdir order.
 *   4. A plugin vendoring a package CORE provides is refused, and core's
 *      copy is what resolves. This is what makes "core ships php-jwt and you
 *      depend on it" a guarantee rather than a request.
 *
 * Usage: php tests/plugin-vendor-autoload.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

if (!is_readable($web . '/vendor/autoload.php')) {
    echo "skip: no core vendor/ in this tree\n";
    exit(0);
}

// ---------------------------------------------------------------- fixtures

$tmp = sys_get_temp_dir() . '/fog-plugin-vendor-' . getmypid();
$plugins = $tmp . '/plugins';

$rmrf = function ($dir) use (&$rmrf) {
    if (!is_dir($dir)) {
        return;
    }
    foreach (array_diff((array) scandir($dir), ['.', '..']) as $e) {
        $p = "$dir/$e";
        is_dir($p) ? $rmrf($p) : unlink($p);
    }
    rmdir($dir);
};
$rmrf($tmp);

/**
 * Write one fixture plugin: a vendor/autoload.php that registers a PSR-4
 * namespace, plus the class file it points at. This is the shape Composer
 * generates -- construct a ClassLoader, register it, return it -- with the
 * generated init class left out because nothing here depends on it.
 */
$fixture = function (string $name, string $ns, string $class) use ($plugins) {
    $dir = "$plugins/$name";
    @mkdir("$dir/vendor", 0777, true);
    @mkdir("$dir/src", 0777, true);
    $nsEsc = str_replace('\\', '\\\\', $ns);
    file_put_contents(
        "$dir/vendor/autoload.php",
        "<?php\n"
        . "\$loader = new \\Composer\\Autoload\\ClassLoader();\n"
        . "\$loader->setPsr4('$nsEsc\\\\', __DIR__ . '/../src');\n"
        . "\$loader->register();\n"
        . "return \$loader;\n"
    );
    file_put_contents(
        "$dir/src/$class.php",
        "<?php\nnamespace $ns;\nclass $class { public static function who() { return '$name'; } }\n"
    );
};

// 1+3: two plugins claiming the same namespace. "aaa" sorts first, so it is
//      the one that should win; "zzz" must be refused.
$fixture('aaa-first', 'AcmeShared', 'Thing');
$fixture('zzz-second', 'AcmeShared', 'Other');
// 4: a plugin vendoring a package core already provides.
$fixture('ccc-conflicts-core', 'Firebase\\JWT', 'JWT');
// 2: a plugin with no vendor/ at all.
@mkdir("$plugins/ddd-plain/src", 0777, true);

// ------------------------------------------------------------------- boot

define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $plugins);
@mkdir(FOG_CACHE_DIR, 0777, true);
@mkdir(FOG_LOG_DIR, 0777, true);

// Keep the refusal messages out of the test's own output; they are expected,
// and one of them is asserted on below.
$errLog = $tmp . '/php-error.log';
ini_set('error_log', $errLog);

require $web . '/commons/init.php';
new Initiator();

// ----------------------------------------------------------------- checks

$fails = [];

// 1. The first plugin's vendored class resolves.
if (!class_exists('AcmeShared\Thing')) {
    $fails[] = 'a plugin with its own vendor/autoload.php did not get its'
        . ' classes -- AcmeShared\Thing does not resolve';
} elseif ('aaa-first' !== AcmeShared\Thing::who()) {
    $fails[] = 'AcmeShared\Thing resolved to the wrong plugin: '
        . AcmeShared\Thing::who();
}

// 3. The colliding plugin was refused, so its own class is unreachable.
if (class_exists('AcmeShared\Other')) {
    $fails[] = 'a second plugin claiming an already-claimed namespace was'
        . ' registered anyway -- AcmeShared\Other resolves, so two copies of'
        . ' one package are live and load order decides which';
}

// 4. Core still owns what core provides.
if (!class_exists('Firebase\JWT\JWT')) {
    $fails[] = "core's own vendored Firebase\\JWT\\JWT stopped resolving";
} else {
    $file = (new \ReflectionClass('Firebase\JWT\JWT'))->getFileName();
    if (false !== strpos($file, 'ccc-conflicts-core')) {
        $fails[] = "a plugin's vendored copy displaced core's: Firebase\\JWT"
            . "\\JWT loaded from $file";
    }
}

// The refusals must be reported. A silent refusal is the same class of
// problem as a silent shadow -- the plugin author has no way to find out.
$log = is_readable($errLog) ? (string) file_get_contents($errLog) : '';
foreach (['zzz-second', 'ccc-conflicts-core'] as $refused) {
    if (false === strpos($log, $refused)) {
        $fails[] = "refusing $refused was not logged, so a plugin author has"
            . ' no way to learn why their dependency stopped resolving';
    }
}

// 2. A plugin with no vendor/ is not an error.
if (false !== strpos($log, 'ddd-plain')) {
    $fails[] = 'a plugin with no vendor/ produced a log line; that is the'
        . ' normal case and must be silent';
}

$rmrf($tmp);

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: plugins may vendor their own dependencies, collisions are refused\n";
exit(0);
