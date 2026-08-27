<?php
/**
 * Core, not a plugin, enforces the site boundary for a single object.
 *
 * Authorization::objectInScope() used to be inert: it fired
 * OBJECT_SCOPE_CHECK and did nothing itself, so the boundary existed only
 * while the site plugin was installed to listen for it. Sites are core
 * now, and this pins the four properties of that move that are each
 * silent when broken.
 *
 *   - a global '*' holder is never subject to a site boundary, and the
 *     check that says so runs BEFORE anything else. "Full admin sees
 *     everything" has to be structural rather than a rule SiteScope is
 *     trusted to remember.
 *   - an install with no sites behaves exactly as it did before. That
 *     covers both a server that never used sites and one running this
 *     code against a schema that has not been deployed yet.
 *   - a scoped user is denied an object outside their sites, with no
 *     plugin installed and no listener registered.
 *   - composition is DENY-WINS. A listener may still deny an object core
 *     would allow, but may not GRANT one core denies -- otherwise any
 *     plugin could hand out another site's hosts by setting a flag.
 *
 * DB-free by the same means as site-scope.test.php: FOGBase::$DB takes a
 * recording fake, and Authorization's permission cache is primed directly
 * so the '*' path never needs a real roles query.
 *
 * Usage: php tests/object-scope-enforcement.test.php
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

$tmp = sys_get_temp_dir() . '/fog-object-scope-test-' . getmypid();
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

foreach ([\FOG\Auth\Authorization::class, \FOG\Auth\SiteScope::class] as $needed) {
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
 * Collects the hook manager into a state where OBJECT_SCOPE_CHECK can be
 * fired with a listener of the test's choosing, standing in for a
 * third-party plugin. Registering on the live HookManager is the honest
 * way to test composition -- it is the same path a real plugin takes.
 */
$hookProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'HookManager');
$hookProp->setAccessible(true);
$dbProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'DB');
$dbProp->setAccessible(true);
$permProp = new \ReflectionProperty(\FOG\Auth\Authorization::class, '_permCache');
$permProp->setAccessible(true);

/** A hook manager that fires only the listener a test hands it. */
class FakeHookManager
{
    public $listener = null;
    public $fired = 0;

    public function processEvent($event, $arguments = [])
    {
        if ('OBJECT_SCOPE_CHECK' !== $event) {
            return;
        }
        $this->fired++;
        if (null !== $this->listener) {
            call_user_func($this->listener, $arguments);
        }
    }
}

$scenario = function (
    array $tables,
    array $perms,
    $listener = null
) use ($dbProp, $hookProp, $permProp) {
    $db = new FakeDB(
        function ($sql, $params) use ($tables) {
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
            $oid = (int)$params['oid'];
            $hit = in_array($oid, (array)($tables['members'] ?? []), true);
            return ['cnt' => $hit ? 1 : 0];
        }
    );
    $dbProp->setValue(null, $db);
    $permProp->setValue(null, $perms);
    $hm = new FakeHookManager();
    $hm->listener = $listener;
    $hookProp->setValue(null, $hm);
    SiteScope::forgetCaches();
    return [$db, $hm];
};

/*
 * 1. A '*' holder passes regardless of sites, and does so without any
 *    site query at all. Checking the query count is the point: if the
 *    short circuit were ever reordered below the site lookup, the boolean
 *    would still be right and only this assertion would notice.
 */
list($db, $hm) = $scenario(
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [3 => []]],
    [3 => ['*']]
);
check(
    'global * holder is in scope for an object in no site',
    Authorization::objectInScope('host', 42, 3) === true,
    $failures,
    $checks
);
check(
    'global * holder costs no site query',
    count($db->log) === 0,
    $failures,
    $checks
);
check(
    'global * holder does not even fire the event',
    $hm->fired === 0,
    $failures,
    $checks
);

/*
 * 2. An install with no sites is unchanged. This is the mid-upgrade and
 *    never-used-sites case, and it is the one that would lock an admin out
 *    of the page that fixes it.
 */
list($db, $hm) = $scenario(['siteCount' => 0], [3 => ['host.edit']]);
check(
    'no sites configured: object allowed',
    Authorization::objectInScope('host', 42, 3) === true,
    $failures,
    $checks
);

/*
 * 3. A scoped user, with no plugin and no listener. This is the whole
 *    point of the move: the boundary holds on a stock install.
 */
$fixture = [
    'siteCount' => 2,
    'catchAll' => [],
    'userSites' => [3 => [7]],
    'members' => [42],
];
$scenario($fixture, [3 => ['host.edit']]);
check(
    'scoped user allowed an object in their site, no listener needed',
    Authorization::objectInScope('host', 42, 3) === true,
    $failures,
    $checks
);
$scenario($fixture, [3 => ['host.edit']]);
check(
    'scoped user denied an object outside their site, no listener needed',
    Authorization::objectInScope('host', 5, 3) === false,
    $failures,
    $checks
);

/*
 * 4. id < 1 still passes untouched -- lists, creates and mass operations
 *    are not single-object decisions and are filtered elsewhere.
 */
list($db, $hm) = $scenario($fixture, [3 => ['host.edit']]);
check(
    'id 0 passes without a scope decision',
    Authorization::objectInScope('host', 0, 3) === true,
    $failures,
    $checks
);
check(
    'id 0 costs no query',
    count($db->log) === 0,
    $failures,
    $checks
);

/*
 * 5. Composition. A listener may deny what core allows...
 */
$deny = function ($arguments) {
    $arguments['allowed'] = false;
};
$scenario($fixture, [3 => ['host.edit']], $deny);
check(
    'a listener can still deny an object core would allow',
    Authorization::objectInScope('host', 42, 3) === false,
    $failures,
    $checks
);

// ...but may NOT grant one core denies. If this ever inverts, any plugin
// can hand out every other site's hosts by setting one flag.
$grant = function ($arguments) {
    $arguments['allowed'] = true;
};
$scenario($fixture, [3 => ['host.edit']], $grant);
check(
    'a listener cannot grant an object core denies (deny wins)',
    Authorization::objectInScope('host', 5, 3) === false,
    $failures,
    $checks
);

// The event still fires for listeners that only observe.
list($db, $hm) = $scenario($fixture, [3 => ['host.edit']]);
Authorization::objectInScope('host', 42, 3);
check(
    'OBJECT_SCOPE_CHECK is still fired',
    $hm->fired === 1,
    $failures,
    $checks
);

// A listener that already denied spares the site query -- deny is deny.
list($db, $hm) = $scenario($fixture, [3 => ['host.edit']], $deny);
Authorization::objectInScope('host', 42, 3);
check(
    'an early deny skips the site query',
    count($db->log) === 0,
    $failures,
    $checks
);

/*
 * 6. Unscoped nodes are unaffected. Images and snapins are deliberately
 *    not site-scoped; that must stay a decision rather than drift.
 */
$scenario($fixture, [3 => ['image.edit']]);
check(
    'an unscoped node is allowed for a scoped user',
    Authorization::objectInScope('image', 5, 3) === true,
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
