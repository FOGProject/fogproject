<?php
/**
 * A plugin can bound a LIST again, not only veto a single object.
 *
 * OBJECT_SCOPE_CHECK is asked about exactly one object, so on its own a
 * plugin could deny `GET /host/5` and had no way at all to narrow
 * `GET /host/list`. On 1.5 the same plugin could, through API_SCOPE_WHERE
 * and API_SCOPE_IDS -- the site plugin's own two handlers. Sites moved into
 * core and the two events did not come with them, so a plugin written
 * against 1.5 lost its list boundary on upgrade: no error, nothing logged,
 * and it fails OPEN, which is the direction that matters.
 *
 * This pins the composition, which is the part that is silent when wrong:
 *
 *   - core's short circuits are about SITES and must not suppress a
 *     plugin's boundary on some other dimension;
 *   - the '*' exemption IS shared, because the single-object and list paths
 *     have to agree about who is scoped;
 *   - either side may narrow, neither may widen -- fragments are ANDed and
 *     id lists intersected;
 *   - the fragment tri-state (null / sql / '1=0') is not the id list's
 *     (null / array / []), and '' is silence in one and impossible in the
 *     other;
 *   - `GET /<class>/<id>` answers the same boundary the list does;
 *   - dispatching a scope event must not recurse into dispatching a scope
 *     event.
 *
 * DB-free by the same means as site-scope.test.php: FOGBase::$DB takes a
 * recording fake, the hook manager is replaced with one the test drives,
 * and the permission cache is primed so no roles query is needed.
 *
 * Usage: php tests/plugin-list-scope-events.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;
use FOG\Auth\SiteScope;

$webroot = dirname(__DIR__) . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-plugin-scope-test-' . getmypid();
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

foreach (
    [
        \FOG\Auth\Authorization::class,
        \FOG\Auth\SiteScope::class,
        \FOG\Items\Host::class,
    ] as $needed
) {
    if (!class_exists($needed, true)) {
        fwrite(STDERR, "FAIL: $needed does not resolve\n");
        exit(1);
    }
}

/** Minimal stand-in for PDODB; see site-scope.test.php for the rationale. */
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

/**
 * A hook manager that dispatches to whatever the test registered, on the
 * real event names. Registering the real strings is the point: the whole
 * defect being fixed is that a plugin registers 'API_SCOPE_WHERE' and
 * nothing fires it, so a test that used a tidier name would pass while
 * the bug was still there.
 */
class FakeHookManager
{
    public $listeners = [];
    public $fired = [];

    public function processEvent($event, $arguments = [])
    {
        $this->fired[$event] = ($this->fired[$event] ?? 0) + 1;
        if (isset($this->listeners[$event])) {
            call_user_func($this->listeners[$event], $arguments);
        }
    }
}

$dbProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'DB');
$dbProp->setAccessible(true);
$hookProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'HookManager');
$hookProp->setAccessible(true);
$permProp = new \ReflectionProperty(\FOG\Auth\Authorization::class, '_permCache');
$permProp->setAccessible(true);

/**
 * The plugin fragments below all carry this token so the fake database can
 * tell the object-existence check apart from SiteScope's own queries
 * without having to parse SQL.
 */
$TOKEN = '/*pluginfrag*/';

$scenario = function (
    array $tables,
    array $perms,
    array $listeners = []
) use ($dbProp, $hookProp, $permProp, $TOKEN) {
    $db = new FakeDB(
        function ($sql, $params) use ($tables, $TOKEN) {
            // The single-object existence check the plugin fragment is
            // evaluated through. Answered from the declared allow list.
            if (false !== strpos($sql, $TOKEN)) {
                $oid = (int)$params['oid'];
                $ok = in_array(
                    $oid,
                    (array)($tables['pluginObjects'] ?? []),
                    true
                );
                return ['cnt' => $ok ? 1 : 0];
            }
            if (false !== strpos($sql, 'IS NOT NULL AND `s`.`siteID` IN (')) {
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
                    $out[] = ['siteID' => $s];
                }
                return $out;
            }
            // allInScopeIDs, and inScope's membership count.
            if (isset($params['oid'])) {
                $oid = (int)$params['oid'];
                $hit = in_array($oid, (array)($tables['members'] ?? []), true);
                return ['cnt' => $hit ? 1 : 0];
            }
            $out = [];
            foreach ((array)($tables['members'] ?? []) as $oid) {
                $out[] = ['oid' => $oid];
            }
            return $out;
        }
    );
    $db->error = (bool)($tables['dbError'] ?? false);
    $dbProp->setValue(null, $db);
    $permProp->setValue(null, $perms);
    $hm = new FakeHookManager();
    $hm->listeners = $listeners;
    $hookProp->setValue(null, $hm);
    SiteScope::forgetCaches();
    return [$db, $hm];
};

/** A site-scoped fixture: user 3 is in site 7, which holds hosts 1 and 2. */
$scoped = [
    'siteCount' => 2,
    'catchAll' => [],
    'userSites' => [3 => [7]],
    'members' => [1, 2],
];

/** Listener factories, in the shape a real hook handler has. */
$whereListener = function ($frag) {
    return function ($arguments) use ($frag) {
        $arguments['where'] = $frag;
    };
};
$idsListener = function ($ids) {
    return function ($arguments) use ($ids) {
        $arguments['ids'] = $ids;
    };
};

$HOSTID = '`hosts`.`hostID`';
$IMAGEID = '`images`.`imageID`';

/*
 * 1. Inert with nothing registered. This is every stock install, and it is
 *    the regression that would be noticed last.
 */
$scenario($scoped, [3 => ['image.view']]);
check(
    'no listener, unscoped node: still null',
    Authorization::scopedObjectWhere('image', $IMAGEID, 3) === null,
    $failures,
    $checks
);

$scenario($scoped, [3 => ['host.view']]);
$coreOnly = Authorization::scopedObjectWhere('host', $HOSTID, 3);
check(
    "no listener, scoped user: core's fragment, unwrapped",
    is_string($coreOnly)
    && false !== strpos($coreOnly, 'IN (SELECT')
    && '(' !== substr($coreOnly, 0, 1),
    $failures,
    $checks
);

$scenario($scoped, [3 => ['host.view']]);
check(
    'no listener: scopedObjectIDs unchanged',
    Authorization::scopedObjectIDs('host', 3) === [1, 2],
    $failures,
    $checks
);

/*
 * 2. The defect itself. A listener bounds a list on a node core does NOT
 *    site-scope -- which is precisely what OBJECT_SCOPE_CHECK can never do,
 *    because it is only ever asked about one object.
 */
list($db, $hm) = $scenario(
    $scoped,
    [3 => ['image.view']],
    ['API_SCOPE_WHERE' => $whereListener('`images`.`imageID` IN (4,5)')]
);
check(
    'a listener bounds a list on a node core does not scope',
    Authorization::scopedObjectWhere('image', $IMAGEID, 3)
    === '`images`.`imageID` IN (4,5)',
    $failures,
    $checks
);
check(
    'API_SCOPE_WHERE is actually fired',
    ($hm->fired['API_SCOPE_WHERE'] ?? 0) === 1,
    $failures,
    $checks
);

/*
 * 3. Both sides answering. ANDed, and BOTH parenthesised -- a fragment is
 *    written by someone else and may contain OR, and `a OR b AND c` binds
 *    the boundary to one arm while the rest keeps matching server-wide.
 *    unisearch() carries a comment about the same mistake.
 */
$scenario(
    $scoped,
    [3 => ['host.view']],
    ['API_SCOPE_WHERE' => $whereListener('`hosts`.`hostID` = 1 OR 1=1')]
);
$both = Authorization::scopedObjectWhere('host', $HOSTID, 3);
check(
    'core AND plugin, both parenthesised',
    is_string($both)
    && 1 === preg_match(
        '#^\(.*IN \(SELECT.*\) AND \(`hosts`\.`hostID` = 1 OR 1=1\)$#s',
        $both
    ),
    $failures,
    $checks
);

/*
 * 4. The id-list fallback, for a plugin that predates the fragment event.
 *    Turned into SQL rather than handed back for a post-filter: a boundary
 *    applied to rows the database has already LIMITed empties pages while
 *    later pages still hold rows the caller may see (ADR 0019).
 */
$scenario(
    $scoped,
    [3 => ['image.view']],
    ['API_SCOPE_IDS' => $idsListener([4, 5])]
);
check(
    'an ids-only listener still bounds the QUERY',
    Authorization::scopedObjectWhere('image', $IMAGEID, 3)
    === '`images`.`imageID` IN (4,5)',
    $failures,
    $checks
);

// Every element goes through intval once, in _pluginScopeIDs, which is what
// lets the fragment interpolate them. If that ever moves, this is the only
// thing standing between a listener and the query.
$scenario(
    $scoped,
    [3 => ['image.view']],
    ['API_SCOPE_IDS' => $idsListener(['4; DROP TABLE `images`', '5abc'])]
);
check(
    'ids are intval-ed before they reach the fragment',
    Authorization::scopedObjectWhere('image', $IMAGEID, 3)
    === '`images`.`imageID` IN (4,5)',
    $failures,
    $checks
);

// Deny-all is SQL and is TRUTHY. '' would read as silence one level up and
// show every row on the server.
$scenario(
    $scoped,
    [3 => ['image.view']],
    ['API_SCOPE_IDS' => $idsListener([])]
);
$denyAll = Authorization::scopedObjectWhere('image', $IMAGEID, 3);
check(
    'an empty id list becomes 1=0, and it is truthy',
    $denyAll === '1=0' && (bool)$denyAll,
    $failures,
    $checks
);

// A listener answering both: the fragment wins and the id list is not even
// asked for. One boundary, never two half-applied ones.
list($db, $hm) = $scenario(
    $scoped,
    [3 => ['image.view']],
    [
        'API_SCOPE_WHERE' => $whereListener('`images`.`imageID` IN (9)'),
        'API_SCOPE_IDS' => $idsListener([4, 5])
    ]
);
check(
    'the fragment wins over the id list',
    Authorization::scopedObjectWhere('image', $IMAGEID, 3)
    === '`images`.`imageID` IN (9)',
    $failures,
    $checks
);
check(
    'the id list is not consulted when a fragment answered',
    !isset($hm->fired['API_SCOPE_IDS']),
    $failures,
    $checks
);

/*
 * 5. Tri-states. '' is silence in the fragment event -- treating it as
 *    deny-all would make an accidental '' deny, and treating it as a
 *    boundary would emit `WHERE ()`.
 */
$scenario(
    $scoped,
    [3 => ['image.view']],
    [
        'API_SCOPE_WHERE' => $whereListener('   '),
        'API_SCOPE_IDS' => $idsListener([4])
    ]
);
check(
    "an empty fragment is read as silence, not as a boundary",
    Authorization::scopedObjectWhere('image', $IMAGEID, 3)
    === '`images`.`imageID` IN (4)',
    $failures,
    $checks
);

/*
 * 6. scopedObjectIDs composes by INTERSECTION, with the same null-vs-[]
 *    distinction the core path already carries.
 */
$scenario(
    $scoped,
    [3 => ['host.view']],
    ['API_SCOPE_IDS' => $idsListener([2, 3])]
);
check(
    'core ids intersected with plugin ids',
    Authorization::scopedObjectIDs('host', 3) === [2],
    $failures,
    $checks
);

$scenario(
    $scoped,
    [3 => ['host.view']],
    ['API_SCOPE_IDS' => $idsListener([9])]
);
check(
    'an empty intersection is [], not null',
    Authorization::scopedObjectIDs('host', 3) === [],
    $failures,
    $checks
);

$scenario(
    $scoped,
    [3 => ['image.view']],
    ['API_SCOPE_IDS' => $idsListener([4, 5])]
);
check(
    'plugin ids alone bound an otherwise unbounded node',
    Authorization::scopedObjectIDs('image', 3) === [4, 5],
    $failures,
    $checks
);

/*
 * 7. Neither side may WIDEN. A plugin naming ids outside the user's sites
 *    gains it nothing -- if this ever inverts, any plugin can hand out
 *    every other site's hosts.
 */
$scenario(
    $scoped,
    [3 => ['host.view']],
    ['API_SCOPE_IDS' => $idsListener([1, 2, 50, 51])]
);
check(
    'a plugin cannot widen past core (ids)',
    Authorization::scopedObjectIDs('host', 3) === [1, 2],
    $failures,
    $checks
);

/*
 * 8. The '*' exemption IS shared, and it is structural: an administrator
 *    who reached an object through GET /host/5 but could not see it in the
 *    list would be looking at two different statements of who may see what.
 */
list($db, $hm) = $scenario(
    $scoped,
    [3 => ['*']],
    ['API_SCOPE_WHERE' => $whereListener('1=0')]
);
check(
    'a global * holder is not narrowed by a plugin either',
    Authorization::scopedObjectWhere('host', $HOSTID, 3) === null,
    $failures,
    $checks
);
check(
    'a global * holder does not even fire the scope events',
    empty($hm->fired),
    $failures,
    $checks
);

/*
 * 9. The single object answers the same boundary the list does. Without
 *    this a plugin hides an object from every list and still serves it
 *    through GET /<class>/<id>.
 */
$objFixture = $scoped + ['pluginObjects' => [1]];

$scenario(
    $objFixture,
    [3 => ['host.edit']],
    ['API_SCOPE_WHERE' => $whereListener($TOKEN . ' 1=1')]
);
check(
    'an object the plugin fragment matches is allowed',
    Authorization::objectInScope('host', 1, 3) === true,
    $failures,
    $checks
);

$scenario(
    $objFixture,
    [3 => ['host.edit']],
    ['API_SCOPE_WHERE' => $whereListener($TOKEN . ' 1=1')]
);
check(
    'an object the plugin fragment excludes is denied',
    Authorization::objectInScope('host', 2, 3) === false,
    $failures,
    $checks
);

$scenario(
    $objFixture,
    [3 => ['host.edit']],
    ['API_SCOPE_IDS' => $idsListener([1])]
);
check(
    'the id-list fallback denies a single object too',
    Authorization::objectInScope('host', 2, 3) === false,
    $failures,
    $checks
);

// A query that cannot run answers NO. Refusing an object the caller was
// entitled to is visible and gets reported; serving one they were not is
// silent.
$scenario(
    $objFixture + ['dbError' => true],
    [3 => ['host.edit']],
    ['API_SCOPE_WHERE' => $whereListener($TOKEN . ' 1=1')]
);
check(
    'a failed existence check denies',
    Authorization::objectInScope('host', 1, 3) === false,
    $failures,
    $checks
);

// A node with no model class has nothing to build a query against and is
// allowed through -- a plugin that wants to deny it still has
// OBJECT_SCOPE_CHECK, which is fired first.
$scenario(
    $objFixture,
    [3 => ['settings.edit']],
    ['API_SCOPE_WHERE' => $whereListener($TOKEN . ' 1=1')]
);
check(
    'a node with no model is allowed rather than denied',
    Authorization::objectInScope('nosuchnodeatall', 1, 3) === true,
    $failures,
    $checks
);

/*
 * 10. Dispatching a scope event must not recurse into dispatching one.
 *     Two ways in, both real: HookManager primes its known-event cache with
 *     a scoped read, and a listener computing its boundary through
 *     getIds()/getNames() does the same one level further out. Unbounded
 *     recursion here exhausts memory rather than erroring, so it presents
 *     as a hung request.
 *
 *     The nested read gets CORE's boundary alone, which is the safe
 *     direction: the outer call still applies the plugin's.
 */
$nested = null;
$reentrant = function ($arguments) use (&$nested, $HOSTID) {
    $nested = Authorization::scopedObjectWhere('host', $HOSTID, 3);
    $arguments['where'] = '`hosts`.`hostID` IN (1)';
};
list($db, $hm) = $scenario(
    $scoped,
    [3 => ['host.view']],
    ['API_SCOPE_WHERE' => $reentrant]
);
$outer = Authorization::scopedObjectWhere('host', $HOSTID, 3);
check(
    'a re-entrant listener terminates, and the outer answer is composed',
    is_string($outer)
    && false !== strpos($outer, 'IN (SELECT')
    && false !== strpos($outer, '`hosts`.`hostID` IN (1)'),
    $failures,
    $checks
);
check(
    'the nested read gets core-only, and the event fires exactly once',
    is_string($nested)
    && false === strpos($nested, '`hosts`.`hostID` IN (1)')
    && ($hm->fired['API_SCOPE_WHERE'] ?? 0) === 1,
    $failures,
    $checks
);

$dbProp->setValue(null, null);
$hookProp->setValue(null, null);
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
