<?php
/**
 * Core's Site model, and the three registrations that make it reachable.
 *
 * Sites came in from the site plugin, and the move is only finished if the
 * name, the node and the permissions all land where the plugin had them.
 * Each of these is silent when wrong:
 *
 *   - a field map that does not match schema step 331 produces an empty
 *     result set, not an error;
 *   - a missing Route::$validClasses entry means the REST routes for
 *     /fog/site are simply never registered, so the endpoint 404s with
 *     nothing to say why;
 *   - a missing API_CLASS_ENTITIES entry falls through to
 *     'unmapped.site', which only a '*' holder passes -- so the API works
 *     perfectly for the administrator testing it and for nobody else;
 *   - a registry node spelled differently from the plugin's would drop
 *     every existing site.* grant on upgrade, without a word.
 *
 * DB-free: every assertion is a class property or a constant.
 *
 * Usage: php tests/site-model.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-site-model-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
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
    define('FOG_PLUGIN_DIR', $tmp . '/plugins');
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
 * 1. The model resolves, and to CORE. The site plugin declared a class of
 *    the same name for years; if a stale copy is still on disk the
 *    autoloader prefers core, but this pins which file actually won so a
 *    lingering plugin cannot quietly supply the model.
 */
if (!class_exists('Site', true)) {
    fwrite(STDERR, "FAIL: Site does not resolve\n");
    exit(1);
}
$ref = new \ReflectionClass('Site');
check(
    'Site extends FOGController',
    $ref->isSubclassOf('FOGController'),
    $failures,
    $checks
);
check(
    'Site resolves to core, not to a plugin copy',
    false === strpos($ref->getFileName(), DIRECTORY_SEPARATOR . 'plugins'),
    $failures,
    $checks
);

$instance = $ref->newInstanceWithoutConstructor();
$prop = function ($name) use ($ref, $instance) {
    $p = $ref->getProperty($name);
    $p->setAccessible(true);
    return $p->getValue($instance);
};

/*
 * 2. The field map matches schema step 331 exactly. `sites`, not the
 *    plugin's `site` -- pointing at the dropped table is the single
 *    likeliest way for this move to go wrong, and it reads as "no sites
 *    exist", which SiteScope then treats as "allow everything".
 */
check(
    'Site maps to `sites`, not the plugin\'s dropped `site`',
    $prop('databaseTable') === 'sites',
    $failures,
    $checks
);
$fields = $prop('databaseFields');
$expected = [
    'id' => 'siteID',
    'name' => 'siteName',
    'description' => 'siteDesc',
    'catchall' => 'siteCatchAll'
];
foreach ($expected as $friendly => $column) {
    check(
        "Site maps $friendly => $column",
        ($fields[$friendly] ?? null) === $column,
        $failures,
        $checks
    );
}
check(
    'Site requires a name',
    in_array('name', $prop('databaseFieldsRequired'), true),
    $failures,
    $checks
);
foreach (['users', 'hosts', 'groups', 'usergroups'] as $assoc) {
    check(
        "Site carries the $assoc association",
        in_array($assoc, $prop('additionalFields'), true),
        $failures,
        $checks
    );
}

/*
 * 3. The list query counts members with COUNT(DISTINCT ...). Four LEFT
 *    OUTER JOINs multiply each other's rows -- 3 hosts and 2 users produce
 *    6 rows -- so a plain COUNT reports 6 of each and the site list shows
 *    numbers that are simply wrong.
 */
$sql = $prop('sqlQueryStr');
check(
    'member counts are COUNT(DISTINCT ...)',
    substr_count($sql, 'COUNT(DISTINCT') === 4,
    $failures,
    $checks
);
foreach (
    [
        'siteHostMembers',
        'siteUserMembers',
        'siteGroupMembers',
        'siteUserGroupMembers'
    ] as $table
) {
    check(
        "list query joins $table",
        false !== strpos($sql, $table),
        $failures,
        $checks
    );
}

/*
 * 4. The manager.
 */
check(
    'SiteManager resolves',
    class_exists('SiteManager', true),
    $failures,
    $checks
);
if (class_exists('SiteManager', true)) {
    $mref = new \ReflectionClass('SiteManager');
    check(
        'SiteManager resolves to core, not to a plugin copy',
        false === strpos($mref->getFileName(), DIRECTORY_SEPARATOR . 'plugins'),
        $failures,
        $checks
    );
    check(
        'SiteManager targets `sites`',
        $mref->newInstanceWithoutConstructor()->tablename === 'sites',
        $failures,
        $checks
    );
    // Core tables are built by commons/schema.php. A manager that could
    // also create them would be a second, divergent definition of the same
    // tables -- which is how the plugin's schema and core's drifted apart
    // in the first place.
    check(
        'SiteManager does not redeclare the schema',
        !$mref->hasMethod('schema') || $mref->getMethod('schema')
            ->getDeclaringClass()->getName() !== 'SiteManager',
        $failures,
        $checks
    );
}

/*
 * 5. The three registrations.
 */
check(
    'site is a valid API class',
    in_array('site', Route::$validClasses, true),
    $failures,
    $checks
);
check(
    'site maps to its own registry node in API_CLASS_ENTITIES',
    (Authorization::API_CLASS_ENTITIES['site'] ?? null) === 'site',
    $failures,
    $checks
);
$registry = Authorization::coreRegistry();
check(
    'site is a core-owned registry node',
    isset($registry['site']),
    $failures,
    $checks
);
foreach (['view', 'create', 'edit', 'delete'] as $action) {
    check(
        "site.$action is a registered permission",
        in_array($action, (array)($registry['site'] ?? []), true),
        $failures,
        $checks
    );
}
// Core-owned matters beyond tidiness: purgePermissions() refuses to wipe a
// core node's grants, so an admin who "forgets" a leftover site plugin
// cannot take every site.* grant with it.
check(
    'site is protected from plugin-uninstall permission purges',
    Authorization::isCoreOwnedNode('site'),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
