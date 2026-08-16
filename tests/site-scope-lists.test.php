<?php
/**
 * The list half of the site boundary.
 *
 * A single-object check that holds while the LIST is unfiltered is not a
 * boundary -- the user is denied the host edit page and shown every host
 * on the way to it, names, MACs and all. This pins the list side, which
 * moved into core alongside the single-object side.
 *
 * The assertion this file exists for is the difference between NULL and an
 * EMPTY ARRAY:
 *
 *   null  = no boundary applies, leave the list exactly as it is
 *   []    = the user may see nothing
 *
 * Both are falsy. Collapsing them -- `if (!$ids) { return; }` -- silently
 * shows every host to a user with no site, which is the precise failure the
 * whole feature is meant to prevent, and it produces no error and no log
 * line. So each caller of scopedObjectIDs() must distinguish them, and the
 * cases below check both sides for every short circuit.
 *
 * DB-free by the same means as site-scope.test.php.
 *
 * Usage: php tests/site-scope-lists.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-site-lists-test-' . getmypid();
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

/** Minimal stand-in for PDODB; see site-scope.test.php for rationale. */
class FakeDB
{
    public $error = false;
    public $log = [];
    private $_responder;
    private $_result;

    public function __construct(callable $responder)
    {
        $this->_responder = $responder;
    }

    public function query($sql, $a = [], $params = [])
    {
        $this->log[] = $sql;
        $this->_result = call_user_func($this->_responder, $sql, $params);
        return $this;
    }

    public function fetch($mode = null, $type = '')
    {
        return $this;
    }

    public function get($field = '')
    {
        return $this->_result;
    }
}

$dbProp = new \ReflectionProperty('FOGBase', 'DB');
$dbProp->setAccessible(true);
$permProp = new \ReflectionProperty('Authorization', '_permCache');
$permProp->setAccessible(true);

$scenario = function (array $tables, array $perms) use ($dbProp, $permProp) {
    $db = new FakeDB(
        function ($sql, $params) use ($tables) {
            if (false !== strpos($sql, 'INNER JOIN `sites`')) {
                $uid = (int)$params['uid'];
                $hit = in_array($uid, (array)($tables['catchAll'] ?? []), true);
                return ['cnt' => $hit ? 1 : 0];
            }
            if (false !== strpos($sql, 'FROM `sites`')) {
                return ['cnt' => (int)($tables['siteCount'] ?? 0)];
            }
            if (false !== strpos($sql, 'FROM `siteUserMembers` WHERE')) {
                $uid = (int)$params['uid'];
                $out = [];
                foreach ((array)($tables['userSites'][$uid] ?? []) as $s) {
                    $out[] = ['sumSiteID' => $s];
                }
                return $out;
            }
            // allInScopeIDs, for either a membership table or the task join.
            $key = (false !== strpos($sql, 'FROM `tasks`'))
                ? 'taskMembers'
                : 'members';
            $out = [];
            foreach ((array)($tables[$key] ?? []) as $oid) {
                $out[] = ['oid' => $oid];
            }
            return $out;
        }
    );
    $dbProp->setValue(null, $db);
    $permProp->setValue(null, $perms);
    SiteScope::forgetCaches();
    return $db;
};

/*
 * 1. Every short circuit returns null -- "leave the list alone" -- and is
 *    checked with an identity comparison, because == would accept [].
 */
$scenario(['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => []]], [3 => ['*']]);
check(
    'global * holder: null (no restriction), not an empty list',
    Authorization::scopedObjectIDs('host', 3) === null,
    $failures,
    $checks
);

$scenario(['siteCount' => 0], [3 => ['host.view']]);
check(
    'no sites configured: null, not an empty list',
    Authorization::scopedObjectIDs('host', 3) === null,
    $failures,
    $checks
);

$scenario(
    ['siteCount' => 2, 'catchAll' => [3], 'userSites' => [3 => []]],
    [3 => ['host.view']]
);
check(
    'catch-all member: null, not an enumerated list',
    Authorization::scopedObjectIDs('host', 3) === null,
    $failures,
    $checks
);

$scenario(
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => [7]]],
    [3 => ['image.view']]
);
check(
    'unscoped node: null',
    Authorization::scopedObjectIDs('image', 3) === null,
    $failures,
    $checks
);

/*
 * 2. A user with no sites gets [], NOT null. This is the case that a
 *    falsy check would turn into "show everything".
 */
$scenario(
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => []]],
    [3 => ['host.view']]
);
$noSites = Authorization::scopedObjectIDs('host', 3);
check(
    'user with no sites: empty array, and NOT null',
    $noSites === [],
    $failures,
    $checks
);
check(
    'user with no sites: null identity check would be wrong',
    $noSites !== null,
    $failures,
    $checks
);

/*
 * 3. A scoped user gets exactly their sites' objects.
 */
$scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'members' => [42, 43],
    ],
    [3 => ['host.view']]
);
check(
    'scoped user gets their sites objects',
    Authorization::scopedObjectIDs('host', 3) === [42, 43],
    $failures,
    $checks
);

/*
 * 4. Tasks are scoped through their hosts on the list path too. They have
 *    no membership table, so this resolves as a join rather than by asking
 *    per task -- a per-row lookup on a list is the grid query storm.
 */
$db = $scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'taskMembers' => [900],
    ],
    [3 => ['task.view']]
);
check(
    'task list scopes via the host join',
    Authorization::scopedObjectIDs('task', 3) === [900],
    $failures,
    $checks
);
$joined = false;
foreach ($db->log as $sql) {
    if (false !== strpos($sql, 'FROM `tasks`')
        && false !== strpos($sql, '`siteHostMembers`')
    ) {
        $joined = true;
    }
}
check(
    'task scope is one join, not a lookup per task',
    $joined,
    $failures,
    $checks
);

/*
 * 5. allInScopeIDs is deny-all on its own terms: a user with no sites gets
 *    nothing, and an unreadable table is not read as permission.
 */
$scenario(
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => []]],
    [3 => ['host.view']]
);
check(
    'allInScopeIDs is empty for a user with no sites',
    SiteScope::allInScopeIDs('host', 3) === [],
    $failures,
    $checks
);
$scenario(
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => [7]]],
    [3 => ['host.view']]
);
check(
    'allInScopeIDs is empty for a node that is not scoped',
    SiteScope::allInScopeIDs('image', 3) === [],
    $failures,
    $checks
);

/*
 * 6. isScopedNode agrees with the single-object path. If these ever
 *    diverge, a node is filtered in lists but not on edit, or the reverse.
 */
$nodes = [
    'host' => true,
    'user' => true,
    'group' => true,
    'usergroup' => true,
    'task' => true,
    'image' => false,
    'snapin' => false,
    'printer' => false,
];
foreach ($nodes as $node => $expected) {
    check(
        "isScopedNode($node) is " . ($expected ? 'true' : 'false'),
        SiteScope::isScopedNode($node) === $expected,
        $failures,
        $checks
    );
    check(
        "isScopedNode($node) is case-insensitive",
        SiteScope::isScopedNode(strtoupper($node)) === $expected,
        $failures,
        $checks
    );
}

$dbProp->setValue(null, null);
$permProp->setValue(null, []);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
