<?php
/**
 * Guards the FOG_MACHINE_REQUEST declaration that site scope keys on.
 *
 * Authorization::_hasNoPrincipal() lifts the site boundary for a caller
 * that has no user and cannot acquire one -- a booting NIC, FOS, the
 * fog-client, a daemon. It decides that from a constant each of those entry
 * points DECLARES, not from the absence of a bound $FOGUser, because those
 * are different statements: a machine entry point has no user, while a
 * route that merely failed to authenticate one is a bug. Keying on absence
 * would hand that bug the same exemption, so a route that ever lost its 401
 * would silently lose site scoping in the same stroke.
 *
 * That safety is only real while the declaration is actually present on the
 * machine surface and absent everywhere else, and neither half announces
 * itself when it breaks:
 *
 *   - a new file under service/ that forgets it is scoped to a user that
 *     does not exist, so it reaches nothing -- the exact failure 46fc53a20
 *     caused, one endpoint at a time;
 *   - a declaration added to management/ or api/ hands every unauthenticated
 *     caller of that entry point the whole server, which is what
 *     route-read-path-guards.test.php section (j) exists to prevent.
 *
 * Static source check (no DB, no server).
 *
 * Usage: php tests/machine-request-declared.test.php [path/to/repo]
 * Exit status 0 = pass, 1 = fail.
 */

$root = $argv[1] ?? dirname(__DIR__);
$web = $root . '/packages/web';

$failures = [];

/**
 * Does this file bootstrap FOG at all?
 *
 * A file that never pulls in base.inc.php has no scope layer to reach, so
 * it needs no declaration -- service/ipxe/index.php is a bare redirect.
 *
 * @param string $src file contents
 *
 * @return bool
 */
$bootstraps = function ($src) {
    return 1 === preg_match(
        "#require(_once)?\s+.{0,40}commons/base\.inc\.php#",
        $src
    );
};

$declares = function ($src) {
    return 1 === preg_match(
        "/define\(\s*'FOG_MACHINE_REQUEST'\s*,\s*true\s*\)/",
        $src
    );
};

// 1. Every bootstrapping entry point on the machine surface must declare it.
$machine = array_merge(
    (array)glob($web . '/service/*.php'),
    (array)glob($web . '/service/ipxe/*.php'),
    [$root . '/packages/service/lib/service_lib.php']
);
$declared = 0;
foreach ($machine as $file) {
    if (!is_readable($file)) {
        $failures[] = 'cannot read ' . $file;
        continue;
    }
    $src = file_get_contents($file);
    if (!$bootstraps($src)) {
        continue;
    }
    if (!$declares($src)) {
        $failures[] = str_replace($root . '/', '', $file)
            . ' bootstraps FOG but does not declare FOG_MACHINE_REQUEST'
            . ' -- it will be scoped to a user that does not exist and'
            . ' reach nothing';
        continue;
    }
    $declared++;
}
if ($declared < 40) {
    $failures[] = "only $declared machine entry points declare"
        . ' FOG_MACHINE_REQUEST; the surface has not been that small since'
        . ' the constant was introduced, so something moved or the glob'
        . ' above stopped matching';
}

// 2. No entry point that CAN carry a principal may declare it.
foreach (['/management', '/api'] as $dir) {
    foreach ((array)glob($web . $dir . '/*.php') as $file) {
        if (!is_readable($file) || !$declares(file_get_contents($file))) {
            continue;
        }
        $failures[] = str_replace($root . '/', '', $file)
            . ' declares FOG_MACHINE_REQUEST -- this entry point can carry a'
            . ' user, so declaring it exempts every unauthenticated caller'
            . ' of it from site scope';
    }
}

// 3. The predicate must still be gated on the constant. Without this the
//    two checks above pass while guarding nothing.
$auth = $web . '/lib/fog/authorization.class.php';
if (!is_readable($auth)) {
    $failures[] = 'cannot read ' . $auth;
} elseif (!preg_match(
    "/function _hasNoPrincipal\([^)]*\)\s*\{\s*return\s+defined\(\s*"
    . "'FOG_MACHINE_REQUEST'\s*\)/s",
    file_get_contents($auth)
)) {
    $failures[] = '_hasNoPrincipal() no longer opens on'
        . " defined('FOG_MACHINE_REQUEST') -- the exemption is back to being"
        . ' inferred from the absence of a principal';
}

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

echo "PASS ($declared machine entry points declared)\n";
exit(0);
