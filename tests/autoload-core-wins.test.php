<?php
/**
 * A plugin file may not shadow a core class of the same name.
 *
 * Initiator::autoload() maps a lowercased basename to a path. Two files
 * claiming one key used to be resolved by whichever the directory walk
 * reached first -- readdir order, which differs per install, so the same
 * code resolves to a different file on two servers with nothing to say so.
 * That is the usertracking failure recorded in _scanClassFiles(), and it had
 * a second edge: external plugins under FOG_PLUGIN_DIR already lost to core
 * (BASEPATH is scanned first and first-wins did the rest), but the BUNDLED
 * plugins live inside BASEPATH at lib/plugins, so they shared one directory
 * walk with lib/fog and the winner was a coin flip. A bundled plugin
 * shipping class/authorization.class.php could replace core's Authorization
 * on some installs and not others.
 *
 * autoload() now prefers the core file and logs the collision. This test
 * pins that, because the rule is invisible until it matters and its failure
 * mode is a class that silently is not the class the caller meant.
 *
 * It matters right now for a specific reason. Sites are moving out of the
 * site plugin and into core, and core's Site cannot be declared while the
 * plugin's site/class/site.class.php is still on disk without exactly this
 * collision. Core winning is what makes the changeover a decision rather
 * than a coin flip -- and it is why core's Site lands in the same commit as
 * the FOG_PLUGINS_VERSION bump that removes the plugin's copy, not before.
 *
 * Scope note, once core moves to src/ (docs/composer-psr4-plan.md). This test
 * keeps guarding the classMap's core-wins rule, and that rule keeps mattering:
 * the classMap still holds the 46 discovery-named files and the generated
 * config.class.php, none of which move. What it stops covering is the classes
 * that DO move, because a core file that is no longer in the map cannot win a
 * collision inside it -- for those, the guarantee is provided by ORDER
 * instead, and tests/psr4-bridge.test.php is the half that holds it.
 *
 * Two halves, then, and they are not interchangeable: this one says the map
 * prefers core, that one says the map is not consulted for a core class in
 * the first place. Deleting either leaves a plugin able to shadow some part
 * of core.
 *
 * DB-free, same shape as autoload.test.php: the Initiator constructor only
 * registers the autoloader, so the cache dir is redirected somewhere
 * throwaway and startInit() -- the part that needs MySQL -- is never called.
 *
 * Usage: php tests/autoload-core-wins.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web';
$init = $root . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-core-wins-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
// An external plugin root, so both plugin shapes are exercised.
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

if (!defined('FOG_CACHE_DIR')) {
    define('FOG_CACHE_DIR', $tmp . '/cache');
}
if (!defined('FOG_LOG_DIR')) {
    define('FOG_LOG_DIR', $tmp . '/log');
}
if (!defined('FOG_PLUGIN_DIR')) {
    define('FOG_PLUGIN_DIR', $tmp . '/extplugins');
}

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
 * 1. The rule itself. _isPluginPath() is private, so it is reached through
 *    Reflection rather than being made public for a test -- the classifier
 *    is an autoloader implementation detail and should stay one.
 */
$m = new \ReflectionMethod('Initiator', '_isPluginPath');
$m->setAccessible(true);
$isPlugin = function ($path) use ($m) {
    return $m->invoke(null, $path);
};

$base = rtrim(BASEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$cases = [
    // path                                          => is it plugin code?
    $base . 'lib/fog/host.class.php'                 => false,
    $base . 'lib/fog/site.class.php'                 => false,
    $base . 'lib/router/route.class.php'             => false,
    $base . 'lib/pages/hostmanagement.page.php'      => false,
    $base . 'lib/plugins/site/class/site.class.php'  => true,
    $base . 'lib/plugins/x/class/authorization.class.php' => true,
    FOG_PLUGIN_DIR . '/mine/class/host.class.php'    => true,
];
foreach ($cases as $path => $expected) {
    check(
        '_isPluginPath(' . str_replace($base, '', $path) . ') is '
        . ($expected ? 'true' : 'false'),
        $isPlugin($path) === $expected,
        $failures,
        $checks
    );
}

/*
 * 2. A path that merely CONTAINS the word plugins is not plugin code. The
 *    classifier decides whether a file may shadow a core class, so a
 *    substring match here would hand that right to any core file whose name
 *    happened to mention plugins -- lib/fog/pluginmanager.class.php, for one.
 */
check(
    'lib/fog/pluginmanager.class.php is core, not plugin',
    $isPlugin($base . 'lib/fog/pluginmanager.class.php') === false,
    $failures,
    $checks
);
check(
    'lib/pages/pluginmanagement.page.php is core, not plugin',
    $isPlugin($base . 'lib/pages/pluginmanagement.page.php') === false,
    $failures,
    $checks
);

/*
 * 3. The rule as autoload() actually applies it, driven with a synthetic
 *    file list so BOTH branches are reached deterministically. Walk order is
 *    the whole problem here, and it is exactly the thing a test cannot
 *    control by putting real files on disk -- hence Reflection on the two
 *    memoised statics rather than a fixture directory.
 *
 *    error_log output is redirected for the duration: the collisions below
 *    are deliberate, and a passing test should not leave four alarming lines
 *    in the tester's PHP log.
 */
$fileList = new \ReflectionProperty('Initiator', 'fileList');
$fileList->setAccessible(true);
$classMap = new \ReflectionProperty('Initiator', 'classMap');
$classMap->setAccessible(true);
$heldList = $fileList->getValue();

$errLog = ini_get('error_log');
$quiet = $tmp . '/collisions.log';
ini_set('error_log', $quiet);

$build = function (array $list) use ($fileList, $classMap) {
    $fileList->setValue(null, $list);
    $classMap->setValue(null, null);
    // Any name will do -- the map is built before the lookup.
    Initiator::autoload('__does_not_exist__');
    return $classMap->getValue();
};

$corePath = $base . 'lib/fog/site.class.php';
$bundled = $base . 'lib/plugins/site/class/site.class.php';
$external = FOG_PLUGIN_DIR . '/mine/class/site.class.php';

$order = [
    'bundled plugin walked first' => [$bundled, $corePath],
    'core walked first' => [$corePath, $bundled],
    'external plugin walked first' => [$external, $corePath],
];
foreach ($order as $label => $list) {
    $map = $build($list);
    check(
        "core wins when the $label",
        ($map['site'] ?? null) === $corePath,
        $failures,
        $checks
    );
}

// Two core files stay first-wins. Deliberately NOT ordered: there is no
// honest basis to prefer one core file over another, and pretending there is
// would hide the rename that actually fixes it.
$map = $build([$base . 'lib/fog/dup.class.php', $base . 'lib/pages/dup.page.php']);
check(
    'two core files keep first-wins',
    ($map['dup'] ?? null) === $base . 'lib/fog/dup.class.php',
    $failures,
    $checks
);

// Every collision above must have been reported. A silent shadow is the
// failure this whole mechanism exists to prevent, so an unlogged one is a
// bug even when the resolution is right.
$logged = is_readable($quiet) ? file_get_contents($quiet) : '';
check(
    'each collision was logged',
    substr_count($logged, 'two files claim the class name') === 4,
    $failures,
    $checks
);

ini_set('error_log', (string)$errLog);
$fileList->setValue(null, $heldList);
$classMap->setValue(null, null);

/*
 * 4. The shipped tree still has no colliding basenames. This is the rule the
 *    precedence above is a backstop for, not a replacement of: two CORE files
 *    colliding are still unordered, and the only fix for those is a rename.
 */
$seen = [];
$collisions = [];
foreach (Initiator::classFileList() as $path) {
    $key = strtolower(
        preg_replace(
            '#\.(report|event|class|hook|page|task)\.php$#',
            '',
            basename($path)
        )
    );
    if (isset($seen[$key])) {
        $collisions[$key] = [$seen[$key], $path];
        continue;
    }
    $seen[$key] = $path;
}
check(
    'no colliding basenames in the scanned tree'
    . (count($collisions)
        ? ' (found: ' . implode(', ', array_keys($collisions)) . ')'
        : ''),
    count($collisions) === 0,
    $failures,
    $checks
);

/*
 * 5. The four site membership models resolve and are wired to the tables
 *    schema step 331 creates. Cheap, and it catches the two mistakes that
 *    would otherwise surface as an empty result set rather than an error:
 *    a class name that does not match its filename, and a databaseFields
 *    key that does not match what assocSetter derives from Site.
 */
$models = [
    'SiteHostMember' => ['siteHostMembers', 'hostID'],
    'SiteUserMember' => ['siteUserMembers', 'userID'],
    'SiteGroupMember' => ['siteGroupMembers', 'groupID'],
    'SiteUserGroupMember' => ['siteUserGroupMembers', 'usergroupID'],
];
foreach ($models as $class => $spec) {
    if (!class_exists($class, true)) {
        check("$class resolves", false, $failures, $checks);
        continue;
    }
    check("$class resolves", true, $failures, $checks);

    $ref = new \ReflectionClass($class);
    check(
        "$class extends FOGController",
        $ref->isSubclassOf('FOGController'),
        $failures,
        $checks
    );

    $table = $ref->getProperty('databaseTable');
    $table->setAccessible(true);
    check(
        "$class maps to `{$spec[0]}`",
        $table->getValue($ref->newInstanceWithoutConstructor()) === $spec[0],
        $failures,
        $checks
    );

    $fields = $ref->getProperty('databaseFields');
    $fields->setAccessible(true);
    $keys = array_keys(
        $fields->getValue($ref->newInstanceWithoutConstructor())
    );
    // siteID is the half assocSetter derives from the Site class name; the
    // object key is the half the scope queries filter on. Both are load
    // bearing and neither is checked anywhere else.
    check(
        "$class has a siteID field",
        in_array('siteID', $keys, true),
        $failures,
        $checks
    );
    check(
        "$class has a {$spec[1]} field",
        in_array($spec[1], $keys, true),
        $failures,
        $checks
    );

    $mgr = $class . 'Manager';
    check(
        "$mgr resolves",
        class_exists($mgr, true),
        $failures,
        $checks
    );
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
