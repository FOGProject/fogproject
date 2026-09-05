<?php
/**
 * The API must not let a caller write fields the server maintains.
 *
 * Route::edit() and Route::create() copy a JSON body straight into a
 * model's databaseFields. Every column of every class in $validClasses was
 * therefore settable by anyone who could reach the route, and the route's
 * own auth is thin -- an api-enabled non-admin passes. Reported by Aisle
 * Research alongside finding 020.
 *
 * Three properties are asserted, and each exists because the obvious
 * implementation gets it wrong in a different direction:
 *
 *   1. The list is DERIVED, through serverOwnedFields(), so a plugin can
 *      declare its own via API_SERVER_OWNED_FIELDS. A second hand-written
 *      copy somewhere is the failure this project has already had once,
 *      with the sensitive-field lists.
 *   2. A refusal is a 400, not a silent drop. Ignoring a requested write
 *      leaves the caller believing state it never achieved.
 *   3. An UNCHANGED value is allowed through. A single-entity GET returns
 *      these fields, so reading an object and PUTting the whole thing back
 *      is ordinary REST; rejecting that would break every round-tripping
 *      client in order to close a hole none of them are in. This is the
 *      check most likely to be lost to a "simplification", and losing it
 *      is a compatibility break rather than a security one -- so it gets
 *      its own case in both directions.
 *
 * Also pinned here because it lives in the same twelve lines: edit() must
 * not re-set a field the body did not mention. It used to assign every
 * column back to its own current value, which reads as a no-op and is not
 * -- User::set() hashes any non-override write to 'password', so a PUT to
 * a user re-hashed the stored hash and locked that account out for good.
 *
 * DB-free: Route resolves through the autoloader with a stub HookManager
 * and never touches FOGBase::$DB on these paths.
 *
 * Every guard case runs in a CHILD process, allow and refuse alike, and
 * that is not symmetry for its own sake. setErrorMessage() ends the
 * request with a bare exit(), which is status 0 -- so a guard that wrongly
 * refuses would kill this file mid-run and the runner, which keys on exit
 * status, would report a pass. Asserting on a child's OUTPUT is the only
 * way round that; the parent survives to fail.
 *
 * Usage: php tests/api-server-owned-fields.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Router\Route;

$webroot = dirname(__DIR__) . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-server-owned-test-' . getmypid();
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

/** Route fires hooks on these paths; nothing else about it is needed. */
class ServerOwnedStubHooks
{
    public function processEvent($event, $arguments = [])
    {
    }
}

/** Stands in for a loaded model: _refuseServerOwned only calls get(). */
class ServerOwnedStubObj
{
    private $_d;

    public function __construct(array $d)
    {
        $this->_d = $d;
    }

    public function get($key = '')
    {
        return isset($this->_d[$key]) ? $this->_d[$key] : null;
    }
}

$hooks = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'HookManager');
$hooks->setAccessible(true);
$hooks->setValue(null, new ServerOwnedStubHooks());

$refuse = new \ReflectionMethod(\FOG\Router\Route::class, '_refuseServerOwned');
$refuse->setAccessible(true);

/*
 * The guard cases. 'refuse' => must be rejected, 'allow' => must be let
 * through. Each runs as a child; see the file header for why the allow
 * side cannot be run in-process.
 */
$stored = new ServerOwnedStubObj(['sec_tok' => 'abc123']);
$cases = [
    // name => [expectation, object|null, value, why it matters]
    'change-existing' => [
        'refuse', $stored, 'evil',
        'a caller can rewrite a server-maintained field'
    ],
    'non-scalar' => [
        'refuse', $stored, ['evil'],
        'an array body slips past the comparison'
    ],
    'set-on-create' => [
        'refuse', null, 'evil',
        'a create can choose the value edit() refuses to change'
    ],
    'unchanged-roundtrip' => [
        'allow', $stored, 'abc123',
        'reading an object and PUTting it back unchanged now 400s, '
        . 'which breaks every round-tripping client'
    ],
    'empty-on-create' => [
        'allow', null, '',
        'a create that carries the field empty now 400s, though it asks '
        . 'for nothing the server was not going to store anyway'
    ],
];
if (isset($argv[1]) && '--child' === $argv[1]) {
    $case = isset($argv[2]) ? $argv[2] : '';
    if (!isset($cases[$case])) {
        fwrite(STDERR, "unknown child case: $case\n");
        exit(2);
    }
    list(, $obj, $value) = $cases[$case];
    $refuse->invoke(null, $obj, 'sec_tok', $value);
    // Only reached when the guard let it through.
    echo 'ALLOWED';
    exit(0);
}

$failures = [];
$checks = 0;

$check = function ($label, $cond) use (&$failures, &$checks) {
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
};

/*
 * 1. The derived list. Membership itself is asserted for the three classes
 *    the finding covers, because each is a different kind of field and
 *    dropping any one of them is a silent regression.
 */
$task = Route::serverOwnedFields('task');
$check(
    'task telemetry is not server-owned, so an api user can rewrite '
    . "another host's imaging progress",
    [] === array_diff(
        [
            'pct', 'bpm', 'timeElapsed', 'timeRemaining',
            'dataCopied', 'dataTotal', 'percent'
        ],
        $task
    )
);

$check(
    'the host client-protocol token set is not server-owned; choosing a '
    . "host's sec_tok is impersonating that host",
    [] === array_diff(
        ['pub_key', 'sec_tok', 'prev_sec_tok', 'sec_time', 'token'],
        Route::serverOwnedFields('host')
    )
);

// Every column that records WHEN a machine spoke. A caller that can write
// one of these is asserting an event that did not happen -- and agentCheckin
// is not only a display field: WakeRelay chooses which hosts are fresh
// enough to relay a wake by it, so a host able to write its own heartbeat
// could nominate itself as a relay for a subnet it is not on.
$check(
    'the observed check-in columns are server-owned, agentCheckin included',
    [] === array_diff(
        ['lastping', 'lastcheckin', 'agentCheckin', 'sbstate', 'sbstatetime'],
        Route::serverOwnedFields('host')
    )
);

$check(
    'user.token is not server-owned; writing it takes over that account '
    . "'s API access",
    in_array('token', Route::serverOwnedFields('user'), true)
);

// The router hands class names in mixed case (the URL segment is
// lowercased, the model name is not), so the lookup cannot be case
// sensitive or the guard silently finds nothing.
$check(
    'serverOwnedFields() is case sensitive, so a mixed-case class name '
    . 'finds no fields and the guard does nothing at all',
    Route::serverOwnedFields('Host') === Route::serverOwnedFields('host')
);

$check(
    'an unlisted class reports fields as server-owned; only the listed '
    . 'ones may be refused',
    [] === Route::serverOwnedFields('image')
);

/*
 * 2. The guard itself, in both directions. A refusal must carry the 400
 *    message rather than dropping the field, and an allowance must
 *    actually come back.
 */
foreach ($cases as $case => $spec) {
    list($expect, , , $why) = $spec;
    $checks++;
    $out = (string)shell_exec(
        sprintf(
            '%s %s --child %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
            escapeshellarg($case)
        )
    );
    $allowed = false !== strpos($out, 'ALLOWED');
    $refused = false !== strpos($out, 'cannot be set');
    if ('refuse' === $expect && !$refused) {
        $failures[] = "$case: $why"
            . ($allowed ? '' : ' (no refusal message: ' . trim($out) . ')');
    }
    if ('allow' === $expect && !$allowed) {
        $failures[] = "$case: $why";
    }
}

/*
 * 4. Both entry points are wired to it, and edit() no longer re-sets what
 *    the body did not send.
 */
$route = file_get_contents($webroot . '/src/Router/Route.php');

$bodyOf = function ($src, $needle) {
    $start = strpos($src, $needle);
    if (false === $start) {
        return null;
    }
    $next = preg_match(
        '/\n    (?:public|private|protected)[ a-z]* function /',
        $src,
        $m,
        PREG_OFFSET_CAPTURE,
        $start + strlen($needle)
    );
    return $next
        ? substr($src, $start, $m[0][1] - $start)
        : substr($src, $start);
};

// The field loop that consults the list. Both verbs' loops now live in a
// named phase of their own -- _applyEditFields() and _applyCreateFields() --
// rather than inline in edit() and create(). Read whichever function
// actually carries the loop, because this is a source grep and a grep that
// points at the wrong function passes for the wrong reason: the enclosing
// function still exists and still contains no _refuseServerOwned() call, so
// pointing at edit() itself would fail on working code.
//
// It is a grep, so it can only see the call disappear -- not the call stop
// firing. `if (false) { _refuseServerOwned(...) }` leaves every string here
// in place, and that mutation left the whole suite green until
// route-write-path-guards.test.php was written. That file drives the
// refusal and is what actually holds this guard; these two checks are the
// cheap companion that catches an outright deletion.
foreach (
    [
        'private static function _applyEditFields(' => 'edit',
        'private static function _applyCreateFields(' => 'create'
    ] as $needle => $what
) {
    $check(
        "Route::$what()'s field loop is gone or renamed ($needle)",
        null !== $bodyOf($route, $needle)
    );
    $body = (string)$bodyOf($route, $needle);
    $check(
        "Route::$what() no longer refuses server-maintained fields",
        false !== strpos($body, '_refuseServerOwned(')
        && false !== strpos($body, 'serverOwnedFields(')
    );
}

$edit = (string)$bodyOf($route, 'private static function _applyEditFields(');
$check(
    'Route::edit() assigns a field back to its own stored value again. '
    . 'That is not a no-op: User::set() hashes any non-override write to '
    . "'password', so every PUT to a user re-hashes the stored hash and "
    . 'locks the account out permanently.',
    false === strpos($edit, '$val = $class->get($key);')
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks server-owned field checks\n";
exit(0);
