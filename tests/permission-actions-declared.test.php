<?php
/**
 * Every reachable API operation resolves to an action its node declares.
 *
 * Authorization::can() answers TRUE for any permission string when the caller
 * holds '*', including one naming an action the node never declared. That is
 * what put "Create New Audit" in the sidebar: audit declares ['view',
 * 'manage'], the menu builder asked can('audit.create'), and an administrator
 * sailed through to a sub the page does not implement. The menu now derives
 * its links from the registry instead, so that symptom is fixed -- but the
 * looseness in can() is not, and this test measures exactly how much is
 * riding on it before anyone tightens it.
 *
 * The answer, today, is 67 route/class pairs across 12 permissions. They are
 * REAL operations -- POST /fog/task, DELETE /fog/tasklog, PUT /fog/setting --
 * reachable now and working now, and every one of them resolves to an action
 * its node does not list. Making can() strict without declaring these first
 * would deny all 67 to everybody, administrators included, because a
 * permission the registry cannot name is a permission no one can hold.
 *
 * So this test does not assert that the set is empty. It asserts that the set
 * is EXACTLY the list below:
 *
 *   - A pair that is not on the list is new. Either the operation should be
 *     declared by its node, or it should not be routable; both are decisions,
 *     and this is what forces one to be made while the change is small.
 *   - A pair on the list that is no longer reachable is stale, and a stale
 *     entry is a standing exemption for an operation that may come back
 *     meaning something else.
 *
 * TWO KINDS live in the list, and they do not have the same answer:
 *
 *   task/settings/report/plugin are genuine operations the registry
 *   under-declares. Declaring them costs nothing anyone did not already have
 *   -- only '*' can do them today -- and buys the ability to delegate them.
 *
 *   usertracking is not. coreRegistry() says, in as many words, that it has
 *   no `create` because rows come from the fog-client's own endpoint and
 *   nothing legitimate POSTs one. That the REST layer offers create, update
 *   and delete on it anyway is the finding, not the baseline: those are
 *   movement records for named people.
 *
 * DB-free. resolveApiPermission() reads constant tables and Route's class
 * lists, none of which need a boot -- verified, it is the PAGE resolver that
 * reaches registry() and therefore a hook. coreRegistry() is used rather than
 * registry() for the same reason, which means a plugin node is absent and its
 * classes are skipped, exactly as the runtime guard would skip them.
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

/*
 * The known set. Format: permission => [route/class, ...], both sorted, so a
 * diff against the computed set reads as a plain list of what moved.
 */
const KNOWN_UNDECLARED = [
    // Plugin install/removal over REST. `install` is declared and these are
    // not, which is the wrong way round from ADR 0009's own reasoning:
    // uploading an archive introduces executable code, and creating a plugin
    // row is how one gets activated.
    'plugin.create' => ['create/plugin', 'join/plugin'],
    'plugin.delete' => ['delete/plugin'],
    // `report` declares view and create. history and imaginglog are the two
    // report-entity classes that are also WRITABLE tables.
    'report.delete' => ['delete/history', 'delete/imaginglog'],
    'report.edit' => ['update/history', 'update/imaginglog'],
    // `settings` declares view and edit. Four classes map onto it and all
    // four take create/join/delete.
    'settings.create' => [
        'create/hookevent', 'create/notifyevent', 'create/oui',
        'create/setting', 'join/hookevent', 'join/notifyevent', 'join/oui',
        'join/setting'
    ],
    'settings.delete' => [
        'delete/hookevent', 'delete/notifyevent', 'delete/oui',
        'delete/setting'
    ],
    // The big one: `task` declares view and task, and eleven classes map onto
    // it with the full generic CRUD set. Creating a task over REST is a
    // documented operation, so this is under-declaration, not over-exposure.
    'task.create' => [
        'create/filedeletequeue', 'create/multicastsession',
        'create/multicastsessionassociation', 'create/nodefailure',
        'create/scheduledtask', 'create/snapinjob', 'create/snapintask',
        'create/task', 'create/tasklog', 'create/taskstate',
        'create/tasktype', 'join/filedeletequeue', 'join/multicastsession',
        'join/multicastsessionassociation', 'join/nodefailure',
        'join/scheduledtask', 'join/snapinjob', 'join/snapintask',
        'join/task', 'join/tasklog', 'join/taskstate', 'join/tasktype'
    ],
    'task.delete' => [
        'delete/filedeletequeue', 'delete/multicastsession',
        'delete/multicastsessionassociation', 'delete/nodefailure',
        'delete/scheduledtask', 'delete/snapinjob', 'delete/snapintask',
        'delete/task', 'delete/tasklog', 'delete/taskstate',
        'delete/tasktype'
    ],
    'task.edit' => [
        'update/filedeletequeue', 'update/multicastsession',
        'update/multicastsessionassociation', 'update/nodefailure',
        'update/scheduledtask', 'update/snapinjob', 'update/snapintask',
        'update/task', 'update/tasklog', 'update/taskstate',
        'update/tasktype'
    ],
    // NOT under-declaration. coreRegistry() states that usertracking has no
    // create because nothing legitimate POSTs one; these three say the REST
    // layer offers create, update and delete anyway, on movement records for
    // named people.
    'usertracking.create' => ['create/usertracking', 'join/usertracking'],
    'usertracking.delete' => ['delete/usertracking'],
    'usertracking.edit' => ['update/usertracking'],
];

$reg = FOG\Authorization::coreRegistry();

/*
 * The pairs the router actually defines. Route::defineRoutes() gives the
 * generic set to every $validClasses entry, task to $validTaskingClasses and
 * cancel/active to $validActiveTasks -- pairing every route with every class
 * instead would invent combinations no URI answers and inflate the finding.
 */
$generic = [
    'list', 'indiv', 'search', 'count', 'names', 'ids',
    'create', 'join', 'update', 'delete'
];
$pairs = [];
foreach (FOG\Route::$validClasses as $class) {
    foreach ($generic as $route) {
        $pairs[] = [$route, $class];
    }
}
foreach (FOG\Route::$validTaskingClasses as $class) {
    $pairs[] = ['task', $class];
}
foreach (FOG\Route::$validActiveTasks as $class) {
    $pairs[] = ['cancel', $class];
    $pairs[] = ['active', $class];
}

$found = [];
foreach ($pairs as $pair) {
    list($route, $class) = $pair;
    $perm = FOG\Authorization::resolveApiPermission($route, $class);
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
foreach ($found as &$list) {
    sort($list);
}
unset($list);
ksort($found);

$failures = [];
$checks = 0;

// A scan that resolves nothing has broken, and would then "pass" by finding
// no new pairs. Bound it from below.
$checks++;
if (count($pairs) < 400) {
    fwrite(
        STDERR,
        'FAIL: enumerated only ' . count($pairs) . ' route/class pairs, '
        . "expected 400+. Route's class lists or this scan are broken.\n"
    );
    exit(1);
}

$known = KNOWN_UNDECLARED;
foreach ($known as &$list) {
    sort($list);
}
unset($list);
ksort($known);

foreach ($found as $perm => $where) {
    $checks++;
    if (!isset($known[$perm])) {
        $failures[] = "$perm is reachable over REST (" . implode(', ', $where)
            . ') and its node does not declare that action. Either declare '
            . 'it in Authorization::coreRegistry(), or stop routing it. '
            . 'While it is neither, only a holder of \'*\' can perform it '
            . 'and no role can be granted it.';
        continue;
    }
    $new = array_diff($where, $known[$perm]);
    if (count($new)) {
        $failures[] = "$perm gained route/class pairs that are not on the "
            . 'known list: ' . implode(', ', $new) . '. A new class mapping '
            . 'onto an under-declared node widens what only \'*\' can reach.';
    }
}

foreach ($known as $perm => $where) {
    $checks++;
    if (!isset($found[$perm])) {
        $failures[] = "$perm is on the known-undeclared list and is no longer "
            . 'reachable. Remove it: a stale entry is a standing exemption '
            . 'for an operation that may come back meaning something else.';
        continue;
    }
    $gone = array_diff($where, $found[$perm]);
    if (count($gone)) {
        $failures[] = "$perm no longer resolves for: " . implode(', ', $gone)
            . '. Update the known list -- it is meant to be an inventory, '
            . 'not a floor.';
    }
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

$total = 0;
foreach ($found as $where) {
    $total += count($where);
}
echo "ok  $checks check(s); $total reachable operation(s) resolve to an "
    . "undeclared action, all known\n";
exit(0);
