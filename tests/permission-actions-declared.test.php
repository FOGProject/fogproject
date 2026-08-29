<?php
/**
 * Every routable API operation resolves to an action its node declares.
 *
 * Authorization::can() used to answer TRUE for any permission string when the
 * caller held '*', including one naming an action the node never declared.
 * That is what put "Create New Audit" in the sidebar: audit declares ['view',
 * 'manage'], the menu builder asked can('audit.create'), and an administrator
 * sailed through to a sub the page does not implement. can() is strict now --
 * a permission the registry cannot name is refused before '*' is consulted --
 * and this is the other end of that change.
 *
 * It has to be, because strictness is only safe while the registry is honest.
 * When this test was first written the mismatch was 67 route/class pairs
 * across 12 permissions, all of them REAL working operations: POST /fog/task,
 * DELETE /fog/tasklog, PUT /fog/setting. Tightening can() without dealing
 * with those would have denied every one of them to everybody, administrators
 * included. They were resolved two different ways, because they were two
 * different findings:
 *
 *   - task, settings, report and plugin were under-declared. Eleven classes
 *     map onto `task` alone and the generic CRUD routes had always answered
 *     for them. coreRegistry() now declares create/edit/delete there, which
 *     takes nothing from anyone -- only '*' could perform them before -- and
 *     makes them grantable to a role for the first time.
 *
 *   - usertracking was over-exposed. coreRegistry() states that the node has
 *     no `create` because rows come from the fog-client's own endpoint and
 *     nothing legitimate POSTs one, and the REST layer offered create, join,
 *     update and delete on it regardless -- on movement records for named
 *     people. Route::$readOnlyClasses now keeps the four write verbs off it,
 *     so the answer is 404 rather than a permission nobody can hold.
 *
 * So the set must be EMPTY, and a new entry means the same decision has come
 * round again for some new class or route: declare it, or stop routing it.
 * The failure text says both, because which one is right is not something a
 * test can know.
 *
 * DB-free. resolveApiPermission() reads constant tables and Route's class
 * lists, none of which need a boot -- it is the PAGE resolver that reaches
 * registry() and therefore a hook. coreRegistry() is used rather than
 * registry() for the same reason, so a plugin node is absent and its classes
 * are skipped here, exactly as can()'s check skips them at runtime.
 *
 * The page surface is not covered here and does not need to be: its only
 * undeclared resolutions are POSTs to a read-only page's renderer (activity,
 * audit and task index), which are not operations -- _subToAction() falls
 * through to 'edit' for any POST it does not recognize, and refusing those is
 * the point rather than a regression.
 *
 * Usage: php tests/permission-actions-declared.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-perm-actions-test-' . getmypid();
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

$reg = FOG\Auth\Authorization::coreRegistry();

/*
 * The pairs the router actually defines. Route::defineRoutes() gives the read
 * routes to every $validClasses entry, the WRITE routes to writableClasses()
 * only, task to $validTaskingClasses and cancel/active to $validActiveTasks.
 * Pairing every route with every class instead would invent combinations no
 * URI answers -- it inflates the finding by a factor of three, which is how
 * the first pass of this sweep read 25 permissions instead of 12.
 */
$readRoutes = ['list', 'indiv', 'search', 'count', 'names', 'ids'];
$writeRoutes = ['create', 'join', 'update', 'delete'];
$pairs = [];
foreach (FOG\Router\Route::$validClasses as $class) {
    foreach ($readRoutes as $route) {
        $pairs[] = [$route, $class];
    }
}
foreach (FOG\Router\Route::writableClasses() as $class) {
    foreach ($writeRoutes as $route) {
        $pairs[] = [$route, $class];
    }
}
foreach (FOG\Router\Route::$validTaskingClasses as $class) {
    $pairs[] = ['task', $class];
}
foreach (FOG\Router\Route::$validActiveTasks as $class) {
    $pairs[] = ['cancel', $class];
    $pairs[] = ['active', $class];
}

// A scan that resolves nothing has broken, and would then "pass" by finding
// nothing undeclared. Bound it from below.
if (count($pairs) < 400) {
    fwrite(
        STDERR,
        'FAIL: enumerated only ' . count($pairs) . ' route/class pairs, '
        . "expected 400+. Route's class lists or this scan are broken.\n"
    );
    exit(1);
}

$failures = [];
$checks = 0;
$found = [];
foreach ($pairs as $pair) {
    list($route, $class) = $pair;
    $checks++;
    $perm = FOG\Auth\Authorization::resolveApiPermission($route, $class);
    if (null === $perm || 0 === strpos($perm, 'unmapped.')) {
        continue;
    }
    $split = explode('.', $perm, 2);
    if (count($split) < 2) {
        continue;
    }
    list($node, $action) = $split;
    if (!isset($reg[$node]) || in_array($action, $reg[$node], true)) {
        continue;
    }
    $found[$perm][] = $route . '/' . $class;
}
ksort($found);
foreach ($found as $perm => $where) {
    sort($where);
    $failures[] = "$perm is routable (" . implode(', ', $where)
        . ') and its node does not declare that action, so can() refuses it '
        . 'to everyone -- a holder of \'*\' included, since the string is '
        . 'ungrantable. Declare the action in Authorization::coreRegistry() '
        . 'if the operation is meant to exist, or add the class to '
        . 'Route::$readOnlyClasses if it is not.';
}

/*
 * The other half of the same contract: a class kept off the write routes must
 * not still be able to reach them. Without this the read-only list could be
 * emptied and nothing above would notice -- every pair would resolve to a
 * declared action again, because declaring is the OTHER way to satisfy this
 * test.
 */
foreach (FOG\Router\Route::$readOnlyClasses as $class) {
    $checks++;
    if (in_array($class, FOG\Router\Route::writableClasses(), true)) {
        $failures[] = "$class is in Route::\$readOnlyClasses and still in "
            . 'writableClasses(), so the write routes expand over it anyway.';
    }
    $checks++;
    if (!in_array($class, FOG\Router\Route::$validClasses, true)) {
        $failures[] = "$class is in Route::\$readOnlyClasses but not in "
            . '$validClasses, so it is not served at all -- the read side is '
            . 'the reason it is on the read-only list rather than removed.';
    }
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks routable operation(s); every one resolves to an action its "
    . "node declares\n";
exit(0);
