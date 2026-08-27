<?php
/**
 * Guards the one path that can silently revoke live access.
 *
 * Authorization::purgePermissions() deletes every rolePermissions row in a
 * node namespace. Both callers derive the namespace from `plugins.pName`
 * (pluginmanagement.page.php:914 on uninstall, :1033 on forget), so the
 * string it acts on is a PLUGIN name being used as a REGISTRY node name.
 *
 * Those two are the same thing right up until a capability moves out of a
 * plugin and into core -- at which point the node stays in the registry,
 * owned by core, while a plugin of that name is still installed. Uninstalling
 * the leftover then deletes grants that core is still honouring, and nothing
 * anywhere says so: no error, no log line, just users who quietly lost
 * access. That is the failure this test exists to make impossible.
 *
 * DB-free, so it runs in tests/run-all.sh. Possible because the guard is
 * pure: coreRegistry() is a literal and isCoreOwnedNode() is an array
 * lookup. The behavioural half of the test leans on that deliberately --
 * with no database configured, purgePermissions() can only return without
 * throwing if it bailed out BEFORE reaching Route::getIds(). If the guard
 * is ever removed, this stops passing rather than silently doing nothing.
 *
 * Usage: php tests/permission-purge-guard.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;

$webroot = dirname(__DIR__) . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-purge-guard-test-' . getmypid();
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

// Same reasoning as autoload.test.php: the constructor only registers the
// autoloader, so the cache dir must be redirected somewhere throwaway and
// startInit() must not be called -- that is what would need MySQL.
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

if (!class_exists(\FOG\Auth\Authorization::class, true)) {
    fwrite(STDERR, "FAIL: Authorization did not resolve\n");
    exit(1);
}

/*
 * 1. Every node in the core literal reports as core-owned.
 */
$core = Authorization::coreRegistry();
check(
    'coreRegistry() returned a non-empty array',
    is_array($core) && count($core) > 0,
    $failures,
    $checks
);
foreach (array_keys($core) as $node) {
    check(
        "isCoreOwnedNode('$node') is true",
        Authorization::isCoreOwnedNode($node) === true,
        $failures,
        $checks
    );
}

/*
 * 2. A name that is not a core node is not protected. Without this the guard
 *    could be a blanket "never purge anything", which would leave a real
 *    plugin's permissions orphaned in every role's matrix after uninstall --
 *    the problem purgePermissions was written to solve.
 */
foreach (['capone', 'ldap', 'wolbroadcast', 'definitelynotanode', ''] as $node) {
    check(
        "isCoreOwnedNode('$node') is false",
        Authorization::isCoreOwnedNode($node) === false,
        $failures,
        $checks
    );
}

/*
 * 3. Case and whitespace are normalised. The callers pass strtolower() of a
 *    database value, but the guard must not depend on its callers being
 *    careful -- it is the last thing standing between a stray uninstall and
 *    a silent revocation.
 */
check(
    "isCoreOwnedNode('  HOST  ') is true",
    Authorization::isCoreOwnedNode('  HOST  ') === true,
    $failures,
    $checks
);

/*
 * 4. The behavioural half: with no database configured, purgePermissions()
 *    on a core node must return without throwing, which it can only do by
 *    returning before it reaches Route::getIds().
 */
try {
    Authorization::purgePermissions('host');
    check('purgePermissions("host") returned without touching the DB', true, $failures, $checks);
} catch (\Throwable $e) {
    check(
        'purgePermissions("host") returned without touching the DB'
        . ' (threw ' . get_class($e) . ': ' . $e->getMessage() . ')',
        false,
        $failures,
        $checks
    );
}

if (count($failures)) {
    fwrite(STDERR, "FAIL (" . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
