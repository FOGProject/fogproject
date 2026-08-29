<?php
/**
 * SiteScope decides who sees which hosts. This pins the decisions.
 *
 * The posture is deny-all, so every bug in this class fails in one of two
 * directions and neither is visible from the UI: too tight and an admin
 * quietly loses hosts they own, too loose and a site sees another site's
 * machines. Neither raises an error, which is why the rules are asserted
 * here rather than left to be noticed.
 *
 * Two of the cases below are not about correctness of the membership query
 * at all, they are about it NOT RUNNING:
 *
 *   - a server with no site IN USE must allow everything. In use means "a
 *     site other than the catch-all", and the distinction is the whole
 *     point: schema step 333 creates the catch-all on every server that
 *     upgrades, so a plain row count answers "sites exist" forever, on
 *     installs that never had the plugin. Scoping would switch itself on
 *     for a feature nobody turned on. It also covers the original reason
 *     this short circuit exists -- the window between deploying this code
 *     and running the schema updater, where denying would lock the admin
 *     out of the very page that fixes it.
 *   - a catch-all member must be allowed without consulting membership,
 *     because the catch-all is a flag rather than a list. If it were
 *     satisfied by enumerating members it would look identical on upgrade
 *     day and then silently stop covering hosts registered afterward.
 *
 * So the assertions check the issued SQL, not just the boolean. A short
 * circuit that returns the right answer for the wrong reason -- by falling
 * through to a membership query that happens to match -- passes a
 * value-only test and fails this one.
 *
 * DB-free: FOGBase::$DB is a static, so a recording fake is injected
 * through it and each scenario declares exactly what the database
 * contains. That also lets the unreachable-table cases be tested at all,
 * which is awkward against a real database and is precisely the state a
 * mid-upgrade server is in.
 *
 * Usage: php tests/site-scope.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\SiteScope;

$webroot = dirname(__DIR__) . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-site-scope-test-' . getmypid();
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
// autoloader, so the cache dir is redirected somewhere throwaway and
// startInit() -- the part that needs MySQL -- is never called.
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

if (!class_exists(\FOG\Auth\SiteScope::class, true)) {
    fwrite(STDERR, "FAIL: SiteScope does not resolve\n");
    exit(1);
}

/**
 * Stands in for PDODB. Chainable in the same shape SiteScope uses --
 * query()->fetch()->get() -- and records every statement so a test can
 * assert on what was NOT asked as well as what was.
 *
 * A responder returning the sentinel '__ERROR__' reproduces PDODB's
 * error-flag path; a responder that throws reproduces an unreachable or
 * absent table. Both are real states and both must be answered without a
 * fatal.
 */
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
        $this->error = ('__ERROR__' === $this->_result) ? 'failed' : false;
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

$dbProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'DB');
$dbProp->setAccessible(true);

/**
 * Installs a scenario. $tables declares what the database holds; the
 * responder translates SiteScope's queries against it.
 *
 * Recognizing each query by a fragment unique to it keeps the fake honest:
 * if SiteScope ever stops issuing one of these, the scenario answers 0 or
 * [] rather than silently agreeing, and the expectations below fail.
 */
$scenario = function (array $tables) use ($dbProp) {
    $db = new FakeDB(
        function ($sql, $params) use ($tables) {
            // Catch-all membership -- checked before the plain sites count
            // because both mention `sites`, and before the membership arm
            // because it now EMBEDS the membership union as a subquery
            // (that is what makes a grant of the catch-all site work).
            if (false !== strpos($sql, 'IS NOT NULL AND `s`.`siteID` IN (')) {
                if (isset($tables['catchAllThrows'])) {
                    throw new \Exception('no such table');
                }
                $uid = (int)$params['uid'];
                $hit = in_array($uid, (array)($tables['catchAll'] ?? []), true);
                return ['cnt' => $hit ? 1 : 0];
            }
            // The catch-all's id. Recognized before the plain count for the
            // same reason -- both read `sites`.
            if (false !== strpos($sql, 'IS NOT NULL LIMIT 1')) {
                return ['cnt' => (int)($tables['catchAllID'] ?? 0)];
            }
            if (false !== strpos($sql, 'FROM `sites`')) {
                if (isset($tables['sitesThrows'])) {
                    throw new \Exception('no such table');
                }
                if (isset($tables['sitesErrors'])) {
                    return '__ERROR__';
                }
                // siteCount is the count of sites IN USE, so a fixture
                // saying 0 is a server holding nothing but the catch-all
                // as well as one holding no sites at all.
                return ['cnt' => (int)($tables['siteCount'] ?? 0)];
            }
            if (0 === strpos($sql, 'INSERT')) {
                if (isset($tables['insertErrors'])) {
                    return '__ERROR__';
                }
                return ['cnt' => 1];
            }
            if (false !== strpos($sql, 'FROM `siteUserMembers` WHERE')) {
                $uid = (int)$params['uid'];
                // One query, four arms: `userSites` is what the direct arm
                // contributes and `inheritedSites` is what the three grant
                // arms contribute. Kept as separate fixture keys so a case
                // can say "this user reaches the site ONLY by inheritance"
                // and mean it. Deduped, because the SQL is UNION and not
                // UNION ALL.
                $out = [];
                $seen = array_values(array_unique(array_merge(
                    (array)($tables['userSites'][$uid] ?? []),
                    (array)($tables['inheritedSites'][$uid] ?? [])
                )));
                foreach ($seen as $s) {
                    $out[] = ['siteID' => $s];
                }
                return $out;
            }
            if (false !== strpos($sql, 'FROM `tasks`')) {
                $tid = (int)$params['tid'];
                return ['cnt' => (int)($tables['tasks'][$tid] ?? 0)];
            }
            if (false !== strpos($sql, 'SELECT DISTINCT')) {
                if (isset($tables['filterThrows'])) {
                    throw new \Exception('unreadable');
                }
                $out = [];
                foreach ((array)($tables['members'] ?? []) as $oid) {
                    $out[] = ['oid' => $oid];
                }
                return $out;
            }
            // Single-object membership count.
            $oid = (int)$params['oid'];
            $hit = in_array($oid, (array)($tables['members'] ?? []), true);
            return ['cnt' => $hit ? 1 : 0];
        }
    );
    $dbProp->setValue(null, $db);
    SiteScope::forgetCaches();
    return $db;
};

/** Did any statement touch a membership table? */
$touchedMembership = function (FakeDB $db) {
    foreach ($db->log as $sql) {
        if (preg_match('/`site(Host|User|Group|UserGroup)Members`/', $sql)
            && false === strpos($sql, 'IS NOT NULL AND `s`.`siteID` IN (')
        ) {
            return true;
        }
    }
    return false;
};

/*
 * 1. No sites at all -- the mid-upgrade window. Everything is in scope, and
 *    membership is never consulted (there is nothing to consult).
 */
$db = $scenario(['siteCount' => 0]);
check(
    'zero-site server: single object allowed',
    SiteScope::inScope('host', 5, 3) === true,
    $failures,
    $checks
);
check(
    'zero-site server: list passes through unchanged',
    SiteScope::filterInScope('host', [3, 1, 2], 3) === [3, 1, 2],
    $failures,
    $checks
);
check(
    'zero-site server: membership never queried',
    !$touchedMembership($db),
    $failures,
    $checks
);

/*
 * 1a. And the question asked has to be the right one. Every fixture above
 *     would pass just as happily against `SELECT COUNT(*) FROM sites`, so
 *     the SQL itself is asserted: the catch-all row is created by schema
 *     step 333 on every server that upgrades, so counting it makes "sites
 *     exist" permanently true everywhere and switches deny-all on for
 *     installs that never had the plugin.
 */
$countSql = '';
foreach ($db->log as $sql) {
    if (false !== strpos($sql, 'FROM `sites`')
        && false === strpos($sql, 'INNER JOIN')
    ) {
        $countSql = $sql;
    }
}
check(
    'the in-use question excludes the catch-all row',
    false !== strpos($countSql, '`siteCatchAll` IS NULL'),
    $failures,
    $checks
);

/*
 * 1b. The catch-all id, and putting a user in it. This is what stops a
 *     brand new account being the only one on the server in no site --
 *     step 333 enrolled everybody who existed on upgrade day, and User
 *     ::save() applies the same rule to everybody created afterward.
 */
$db = $scenario(['siteCount' => 0, 'catchAllID' => 11]);
check(
    'catchAllID finds the catch-all',
    SiteScope::catchAllID() === 11,
    $failures,
    $checks
);
check(
    'joinCatchAll enrolls the user',
    SiteScope::joinCatchAll(4) === true,
    $failures,
    $checks
);
$insert = '';
foreach ($db->log as $sql) {
    if (0 === strpos($sql, 'INSERT')) {
        $insert = $sql;
    }
}
check(
    // Membership is UNIQUE, so the database already answers "already a
    // member" -- a check-then-insert would be a second round trip and a
    // race besides.
    'joinCatchAll inserts, ignoring an existing membership',
    false !== strpos($insert, 'INSERT IGNORE INTO `siteUserMembers`'),
    $failures,
    $checks
);
$db = $scenario(['siteCount' => 0, 'catchAllID' => 0]);
check(
    'joinCatchAll does nothing without a catch-all',
    SiteScope::joinCatchAll(4) === false,
    $failures,
    $checks
);
check(
    'joinCatchAll issues no insert without a catch-all',
    !preg_grep('/^INSERT/', $db->log),
    $failures,
    $checks
);
$db = $scenario(
    ['siteCount' => 0, 'catchAllID' => 11, 'insertErrors' => true]
);
check(
    'a failed enrollment reports failure rather than claiming success',
    SiteScope::joinCatchAll(4) === false,
    $failures,
    $checks
);

/*
 * 2. A `sites` table that cannot be read at all -- code deployed, schema
 *    step not yet run. Must behave like case 1, not fatal and not deny.
 */
$db = $scenario(['sitesThrows' => true]);
check(
    'absent sites table allows, does not fatal',
    SiteScope::inScope('host', 5, 3) === true,
    $failures,
    $checks
);
$db = $scenario(['sitesErrors' => true]);
check(
    'sites table error flag allows, does not fatal',
    SiteScope::inScope('host', 5, 3) === true,
    $failures,
    $checks
);

/*
 * 3. Catch-all member. Allowed for a host that is in NO site at all -- the
 *    whole point of the flag -- and without a membership query.
 */
$db = $scenario(
    [
        'siteCount' => 2,
        'catchAll' => [3],
        'userSites' => [3 => []],
        'members' => [],
    ]
);
check(
    'catch-all user sees a host in no site',
    SiteScope::inScope('host', 5, 3) === true,
    $failures,
    $checks
);
check(
    'catch-all user: membership never queried',
    !$touchedMembership($db),
    $failures,
    $checks
);
$db = $scenario(
    ['siteCount' => 2, 'catchAll' => [3], 'userSites' => [3 => []]]
);
check(
    'catch-all user: list passes through unchanged',
    SiteScope::filterInScope('host', [9, 8], 3) === [9, 8],
    $failures,
    $checks
);

/*
 * 4. A scoped user. Sees what their sites contain and nothing else. This is
 *    the pair the whole class exists for.
 */
$fixture = [
    'siteCount' => 2,
    'catchAll' => [],
    'userSites' => [3 => [7]],
    'members' => [42],
];
$scenario($fixture);
check(
    'scoped user sees a host in their site',
    SiteScope::inScope('host', 42, 3) === true,
    $failures,
    $checks
);
$scenario($fixture);
check(
    'scoped user does not see a host outside it',
    SiteScope::inScope('host', 5, 3) === false,
    $failures,
    $checks
);
$scenario($fixture);
check(
    'scoped user: node name is case-insensitive',
    SiteScope::inScope('HOST', 42, 3) === true,
    $failures,
    $checks
);

/*
 * 5. A user in no site is denied, rather than treated as unrestricted. The
 *    inverse mistake -- empty meaning "no restriction" -- is the one that
 *    would silently hand every host to every user, so it gets its own case.
 */
$scenario(['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => []]]);
check(
    'user with no sites is denied a single object',
    SiteScope::inScope('host', 42, 3) === false,
    $failures,
    $checks
);
$scenario(['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => []]]);
check(
    'user with no sites gets an empty list',
    SiteScope::filterInScope('host', [1, 2, 3], 3) === [],
    $failures,
    $checks
);

/*
 * 6. Nodes this does not scope are allowed without any query at all.
 *    Images and snapins are deliberately unscoped; if that ever changes it
 *    must be a decision, not a side effect.
 */
$db = $scenario(['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => []]]);
check(
    'unscoped node is allowed',
    SiteScope::inScope('image', 5, 3) === true,
    $failures,
    $checks
);
check(
    'unscoped node issues no query',
    count($db->log) === 0,
    $failures,
    $checks
);
$db = $scenario(['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => []]]);
check(
    'unscoped node list is unchanged',
    SiteScope::filterInScope('snapin', [4, 5], 3) === [4, 5],
    $failures,
    $checks
);

/*
 * 7. Tasks are derived from their host. A task in the user's site resolves
 *    through to the host membership query with the HOST's id -- checked
 *    explicitly, because passing the task id through by mistake would still
 *    produce a plausible true/false.
 */
$db = $scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'tasks' => [900 => 42],
        'members' => [42],
    ]
);
check(
    'task in scope via its host',
    SiteScope::inScope('task', 900, 3) === true,
    $failures,
    $checks
);
$hostQuery = '';
foreach ($db->log as $sql) {
    if (false !== strpos($sql, '`siteHostMembers`')) {
        $hostQuery = $sql;
    }
}
check(
    'task scope queries siteHostMembers, not a task table',
    '' !== $hostQuery,
    $failures,
    $checks
);

$db = $scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'tasks' => [900 => 5],
        'members' => [42],
    ]
);
check(
    'task out of scope via its host',
    SiteScope::inScope('task', 900, 3) === false,
    $failures,
    $checks
);

// A task that does not exist, or whose host is gone, cannot be shown to be
// in scope. Allowing it would make a deleted host's tasks visible to
// everyone.
$scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'tasks' => [],
        'members' => [42],
    ]
);
check(
    'task with no resolvable host is denied',
    SiteScope::inScope('task', 900, 3) === false,
    $failures,
    $checks
);

// Filtering a task list keeps only the in-scope ones.
$scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'tasks' => [900 => 42, 901 => 5],
        'members' => [42],
    ]
);
check(
    'task list filters to the in-scope task',
    SiteScope::filterInScope('task', [900, 901], 3) === [900],
    $failures,
    $checks
);

/*
 * 8. filterInScope's contract: input order preserved, duplicates collapsed,
 *    out-of-scope ids dropped. Order matters because callers feed the
 *    result straight back into a grid.
 */
$scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'members' => [8, 9],
    ]
);
check(
    'filter keeps order and drops out-of-scope ids',
    SiteScope::filterInScope('host', [9, 5, 8], 3) === [9, 8],
    $failures,
    $checks
);
$scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'members' => [8],
    ]
);
check(
    'filter collapses duplicate ids',
    SiteScope::filterInScope('host', [8, 8, 8], 3) === [8],
    $failures,
    $checks
);

/*
 * 9. Unreadable membership is not permission. If the filter query fails,
 *    the answer is an empty list, never the unfiltered input.
 */
$scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'filterThrows' => true,
    ]
);
check(
    'unreadable membership denies rather than passing through',
    SiteScope::filterInScope('host', [1, 2], 3) === [],
    $failures,
    $checks
);

/*
 * 10. The per-request caches. Two decisions for one user must not re-ask
 *     the same three questions -- this runs on every row of a host grid.
 */
$db = $scenario(
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [3 => [7]],
        'members' => [42],
    ]
);
SiteScope::inScope('host', 42, 3);
$after = count($db->log);
SiteScope::inScope('host', 43, 3);
check(
    'second decision issues only the membership query',
    count($db->log) === $after + 1,
    $failures,
    $checks
);
check(
    'forgetCaches makes the next call re-read',
    (function () use ($db) {
        SiteScope::forgetCaches();
        $before = count($db->log);
        SiteScope::inScope('host', 42, 3);
        return count($db->log) - $before > 1;
    })(),
    $failures,
    $checks
);

/*
 * 11. Users are independent. A catch-all user's answer must not be served
 *     from cache to a scoped one -- a per-user cache keyed wrongly is how
 *     one admin's view leaks into another's.
 */
$scenario(
    [
        'siteCount' => 2,
        'catchAll' => [3],
        'userSites' => [3 => [], 4 => []],
        'members' => [],
    ]
);
$catchAllUser = SiteScope::inScope('host', 5, 3);
$otherUser = SiteScope::inScope('host', 5, 4);
check(
    'catch-all user allowed, other user denied, same request',
    $catchAllUser === true && $otherUser === false,
    $failures,
    $checks
);

/*
 * 12. Inherited membership. A site reaches a user directly, through a user
 *     group, or through a role -- and a role reaches a user two ways, so
 *     four arms. These cases prove the behavior; case 13 pins the SQL,
 *     because the fake answers one union query and cannot tell which arm
 *     produced a row.
 */
$scenario(
    [
        'siteCount' => 2,
        'userSites' => [7 => []],
        'inheritedSites' => [7 => [2]],
        'members' => [5],
    ]
);
check(
    'a user with no direct membership is scoped by inheritance alone',
    SiteScope::inScope('host', 5, 7) === true,
    $failures,
    $checks
);

$scenario(
    [
        'siteCount' => 2,
        'userSites' => [7 => []],
        'inheritedSites' => [7 => [2]],
        'members' => [],
    ]
);
check(
    'inheritance still denies an object outside the inherited site',
    SiteScope::inScope('host', 5, 7) === false,
    $failures,
    $checks
);

// Union, not union all: the same site reached twice is one id. Anything
// downstream doing in_array() would not notice, but filterInScope() and the
// IN () it builds would carry the duplicate into SQL.
$scenario(
    [
        'siteCount' => 2,
        'userSites' => [7 => [2]],
        'inheritedSites' => [7 => [2, 3]],
        'members' => [5],
    ]
);
check(
    'a site reached both directly and by inheritance appears once',
    SiteScope::userSiteIDs(7) === [2, 3],
    $failures,
    $checks
);

// The property that makes this separable from Phase 1: no arrangement of
// grants can take a site away. Direct membership survives an empty
// inheritance set.
$scenario(
    [
        'siteCount' => 2,
        'userSites' => [7 => [2]],
        'inheritedSites' => [7 => []],
        'members' => [5],
    ]
);
check(
    'inheritance never narrows: direct membership stands alone',
    SiteScope::userSiteIDs(7) === [2],
    $failures,
    $checks
);

$scenario(['siteCount' => 2, 'userSites' => [7 => []], 'members' => [5]]);
check(
    'no membership and no grant is still deny-all',
    SiteScope::inScope('host', 5, 7) === false,
    $failures,
    $checks
);

/*
 * 13. The arms themselves. The fixtures above would pass against a query
 *     that read one table, so the shape is pinned here: four arms, the
 *     joins each one needs, and UNION rather than UNION ALL.
 */
$db = $scenario(['siteCount' => 2, 'userSites' => [7 => []], 'members' => []]);
SiteScope::userSiteIDs(7);
$armSql = '';
foreach ($db->log as $sql) {
    if (0 === strpos($sql, 'SELECT `sumSiteID` AS `siteID`')) {
        $armSql = $sql;
        break;
    }
}
$flat = preg_replace('/\s+/', ' ', $armSql);
foreach ([
    'direct membership' => 'FROM `siteUserMembers` WHERE `sumUserID` = :uid',
    'user group grant' => 'FROM `siteUserGroupGrants` INNER JOIN '
        . '`userGroupMembers` ON `ugmGroupID` = `suggGroupID`',
    'role grant, role held directly' => 'FROM `siteRoleGrants` INNER JOIN '
        . '`roleUserAssoc` ON `ruaRoleID` = `srgRoleID`',
    'role grant, role held via a user group' => 'INNER JOIN '
        . '`roleUserGroupAssoc` ON `rugRoleID` = `srgRoleID` INNER JOIN '
        . '`userGroupMembers` ON `ugmGroupID` = `rugGroupID`',
] as $what => $fragment) {
    check(
        "userSiteIDs() carries the $what arm",
        false !== strpos($flat, $fragment),
        $failures,
        $checks
    );
}
check(
    'the arms are UNION, not UNION ALL -- the result is a set',
    3 === substr_count($flat, ' UNION ')
    && false === strpos($flat, 'UNION ALL'),
    $failures,
    $checks
);

/*
 * 13a. And isUnscoped() asks the SAME question, over the same SQL. If it
 *      kept its own copy reading only direct membership, granting a role
 *      the catch-all site would put the catch-all's id in the user's list
 *      while leaving isUnscoped() false -- so the user would be scoped to
 *      the one site that means "no restriction", and see nothing.
 */
$db = $scenario(['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => []], 'members' => []]);
SiteScope::inScope('host', 5, 7);
$catchAllSql = '';
foreach ($db->log as $sql) {
    if (false !== strpos($sql, 'IS NOT NULL AND `s`.`siteID` IN (')) {
        $catchAllSql = $sql;
        break;
    }
}
$flatCatch = preg_replace('/\s+/', ' ', $catchAllSql);
check(
    'the catch-all check embeds the same four arms',
    false !== strpos($flatCatch, '`siteUserGroupGrants`')
    && false !== strpos($flatCatch, '`siteRoleGrants`')
    && 3 === substr_count($flatCatch, ' UNION '),
    $failures,
    $checks
);

$dbProp->setValue(null, null);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
