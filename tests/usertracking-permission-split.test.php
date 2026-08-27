<?php
/**
 * `userTracking` must not be readable under the `report` grant.
 *
 * `userTracking` records which named person logged into which host and when
 * -- a movement log for employees. It used to share the `report` permission
 * node with `history` and `imagingLog`, so one `report.view` grant, the kind
 * a helpdesk role gets so it can read an imaging report, also handed over
 * that log for the whole fleet. ADR 0023 splits it into its own node.
 *
 * There are THREE doors onto the same table and closing one is worth nothing
 * on its own, which is what this test is really for:
 *
 *   1. `Hosts_And_Users` -- a report, so it takes the `report` page node by
 *      construction. Every report is one node selected by a base64 `f`
 *      parameter, and Authorization::REPORT_NODES is what re-points this one.
 *   2. The REST class `usertracking` -- API_CLASS_ENTITIES.
 *   3. The Login History tab on the host and group edit pages, whose
 *      `getLoginHist` sub resolves to `<node>.view` by naming convention and
 *      so answered `host.view`/`group.view`: the grant nearly every operator
 *      holds. SUB_OVERRIDES re-points those, and the tabs are hidden to match
 *      so the grid does not draw and then fetch a denial.
 *
 * The REPORT_NODES key is the fragile part and is checked against the tree:
 * it is derived from the report's FILENAME (loadCustomReports() strips the
 * extension, FOGPageManager turns spaces back into underscores), so renaming
 * `hosts_and_users.report.php` would silently reopen door 1 with no error
 * anywhere. Renaming it now fails here instead.
 *
 * DB-free, like permission-purge-guard.test.php and for the same reason: the
 * Initiator constructor only registers the autoloader, and everything checked
 * here is a class constant, coreRegistry()'s literal, or _reportNode(), which
 * is pure. registry() is deliberately NOT called -- it fires a hook event and
 * would need the globals a real boot brings.
 *
 * Usage: php tests/usertracking-permission-split.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-usertracking-split-test-' . getmypid();
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

if (!class_exists(\FOG\Auth\Authorization::class, true)) {
    fwrite(STDERR, "FAIL: Authorization did not resolve\n");
    exit(1);
}

/*
 * 1. The node exists, is core-owned so an uninstall cannot purge its grants,
 *    and declares view only -- rows are written by the fog-client's own
 *    endpoint, which is the permission-exempt `client` node.
 */
$core = Authorization::coreRegistry();
check(
    "coreRegistry() declares a 'usertracking' node",
    isset($core['usertracking']),
    $failures,
    $checks
);
check(
    "'usertracking' declares exactly ['view']",
    ($core['usertracking'] ?? null) === ['view'],
    $failures,
    $checks
);
check(
    "isCoreOwnedNode('usertracking') is true",
    Authorization::isCoreOwnedNode('usertracking') === true,
    $failures,
    $checks
);

/*
 * 2. Door 2: the REST class no longer resolves to `report`.
 */
$entities = Authorization::API_CLASS_ENTITIES;
check(
    "API_CLASS_ENTITIES['usertracking'] is 'usertracking', not 'report'",
    ($entities['usertracking'] ?? null) === 'usertracking',
    $failures,
    $checks
);

/*
 * 3. Door 3: both Login History endpoints are overridden. Without these the
 *    'get' prefix resolves them to host.view / group.view.
 */
$overrides = Authorization::SUB_OVERRIDES;
foreach (['host', 'group'] as $node) {
    check(
        "SUB_OVERRIDES['$node']['getloginhist'] is 'usertracking.view'",
        ($overrides[$node]['getloginhist'] ?? null) === 'usertracking.view',
        $failures,
        $checks
    );
}

/*
 * 4. Door 1: the report remap, exercised rather than read. Both shapes `f`
 *    arrives in must resolve, and anything else must fall back to `report`
 *    so an uploaded custom report behaves as it always has.
 */
$m = new \ReflectionMethod('FOG\\Auth\\Authorization', '_reportNode');
$m->setAccessible(true);
$reportNode = function ($rawSub) use ($m) {
    return $m->invoke(null, $rawSub);
};

$f = base64_encode('hosts and users');
$cases = [
    // The sidebar builds keys of this shape and asks for a permission per
    // entry so it can hide the ones the user lacks.
    'file&f=' . $f => 'usertracking',
    // A real request carries it in the query string; the sub is bare. The
    // query string is unreadable from CLI, so this asserts the fallback.
    'file' => 'report',
    // Case: the decoded name is normalised before lookup.
    'file&f=' . base64_encode('HOSTS AND USERS') => 'usertracking',
    // Any other report keeps the report node.
    'file&f=' . base64_encode('imaging log') => 'report',
    'file&f=' . base64_encode('host list') => 'report',
    // Not base64 at all: selects no report, must not resolve to one.
    'file&f=????' => 'report',
    '' => 'report',
];
foreach ($cases as $rawSub => $expect) {
    $got = $reportNode($rawSub);
    check(
        "_reportNode('$rawSub') is '$expect' (got '$got')",
        $got === $expect,
        $failures,
        $checks
    );
}

/*
 * 5. Every REPORT_NODES key names a report that is actually on disk. The key
 *    is filename-derived, so a rename would silently drop the gate.
 */
foreach (Authorization::REPORT_NODES as $report => $node) {
    check(
        "REPORT_NODES key '$report' has a report file",
        is_readable($webroot . '/lib/reports/' . $report . '.report.php'),
        $failures,
        $checks
    );
    check(
        "REPORT_NODES['$report'] names a core registry node",
        isset($core[$node]),
        $failures,
        $checks
    );
}

/*
 * 6. The tabs are hidden to match the endpoint. A drawn grid whose fetch is
 *    denied reads as a broken page, not as a permission boundary.
 */
$tabPages = [
    'hostmanagement' => 'host-login-history',
    'groupmanagement' => 'group-login-history',
];
foreach ($tabPages as $page => $tabId) {
    $src = (string) @file_get_contents(
        $webroot . '/lib/pages/' . $page . '.page.php'
    );
    check(
        "$page.page.php gates its Login History tab on usertracking.view",
        false !== strpos($src, "Authorization::can('usertracking.view')")
        && false !== strpos($src, $tabId),
        $failures,
        $checks
    );
}

/*
 * ADR 0020 phase 1: `utAction`'s codes are class constants, and the two
 * places that care read them from there.
 *
 * The column is an int with no lookup table and no constraint, so the only
 * thing tying the client endpoint's action map to the list formatter is that
 * both happened to spell the same three literals. That is not a link -- a
 * fourth code added on one side and not the other produces rows the grid
 * renders as a bare number, which is exactly the failure the default arm in
 * the formatter was added to make visible. The constants ARE the link, so
 * they only stay one if nothing goes back to a literal.
 */
$model = (string) file_get_contents(
    "$root/packages/web/src/Items/UserTracking.php"
);
$consts = [
    'ACTION_LOGOUT' => '0',
    'ACTION_LOGIN' => '1',
    'ACTION_SERVICE_START' => '99'
];
foreach ($consts as $name => $value) {
    check(
        "UserTracking declares $name = $value",
        1 === preg_match(
            '/const\s+' . $name . '\s*=\s*' . $value . '\s*;/',
            $model
        ),
        $failures,
        $checks
    );
}

$client = (string) file_get_contents(
    "$root/packages/web/src/Client/UserTrack.php"
);
preg_match('/\$actions\s*=\s*\[(.*?)\]/s', $client, $m);
$map = isset($m[1]) ? $m[1] : '';
check(
    'the client endpoint maps its action names through the constants',
    '' !== $map
    && false !== strpos($map, 'UserTracking::ACTION_LOGIN')
    && false !== strpos($map, 'UserTracking::ACTION_SERVICE_START')
    && false !== strpos($map, 'UserTracking::ACTION_LOGOUT'),
    $failures,
    $checks
);
check(
    'and holds no bare action literal of its own',
    '' !== $map && 0 === preg_match('/=>\s*\d+/', $map),
    $failures,
    $checks
);

$route = (string) file_get_contents(
    "$root/packages/web/src/Router/Route.php"
);
// The switch moved out of the column definition into
// Route::_userTrackingAction() when ADR 0023 item 5 gave the activity
// viewer a summary column that has to say the same thing about the same
// code -- two copies of the mapping is exactly the drift this file exists
// to stop. Read the helper, and separately check that the grid column
// still delegates to it rather than growing its own copy back.
preg_match(
    '/private static function _userTrackingAction\(.*?\n    \}/s',
    $route,
    $m
);
$fmt = isset($m[0]) ? $m[0] : '';
check(
    'the action mapping lives in one helper',
    '' !== $fmt,
    $failures,
    $checks
);
preg_match(
    "/'db'\s*=>\s*'utAction'.*?\n(\s*)\];/s",
    $route,
    $mCol
);
$col = isset($mCol[0]) ? $mCol[0] : '';
check(
    'the grid column delegates to it instead of mapping codes itself',
    '' !== $col
    && false !== strpos($col, '_userTrackingAction(')
    && 0 === preg_match('/UserTracking::ACTION_/', $col),
    $failures,
    $checks
);
check(
    "the mapping reads the same constants",
    '' !== $fmt
    && 3 === preg_match_all('/UserTracking::ACTION_/', $fmt),
    $failures,
    $checks
);
check(
    'and no longer compares against quoted literals',
    '' !== $fmt && 0 === preg_match("/case\s*'\d+'\s*:/", $fmt),
    $failures,
    $checks
);
check(
    'while keeping the default arm that renders an unknown code',
    '' !== $fmt && false !== strpos($fmt, "_('Unknown')"),
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
