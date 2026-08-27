<?php
/**
 * `Route::ids()` must not SELECT a column the emitter would strip.
 *
 * `ids()` takes the column to select from the URL -- it is the last segment of
 * "/ids/id=1/name". Until this was closed it validated that column against
 * `$databaseFields` and against nothing else: the column exists, therefore
 * hand it over. `unfilterableFields()`, the list that refuses the same caller
 * FILTERING on `sec_tok`, was never consulted, and the emitter could not make
 * up the difference -- this route answers with an array of scalars carrying
 * no '_lang' stamp, so `stripSensitivePayload()` resolves an empty classname
 * and returns the payload untouched.
 *
 *   GET /fog/host/ids/id=1/sec_tok  -> the host's plaintext fog-client token
 *
 * on `host.view`, the same permission as reading the list. Also ADPass,
 * productKey, user.password and user.token.
 *
 * BEHAVIOURAL, not a source grep, and that distinction is the point of the
 * file. The sibling `sensitive-fields-unfilterable.test.php` asserts that the
 * deciding functions CALL `unfilterableFields()` -- which is the right thing
 * for it to assert and is not a net: inserting `return;` at the top of
 * `_assertNoSensitiveFilter()` leaves every string it greps for in place and
 * the whole suite stays green. So this drives the real function and looks at
 * what comes back.
 *
 * Two arms, because `ids()` has two. Serving a request it refuses outright.
 * Off-request -- the daemons, which reach `ids()` through `getIds()` -- it
 * returns NO ROWS and logs, because `sendResponse()` exits and a daemon that
 * exits is a systemd restart loop (cf. 2d199fa4b). Only the second arm is
 * reachable from a test process, PHP_SAPI being what it is, so that is the
 * one asserted here; the request arm was confirmed by hand under php-cgi:
 *
 *   host  sec_tok  => REFUSED {"error":"Cannot select host field(s): sec_tok"}
 *   host  name     => ALLOWED ["test"]
 *
 * DB-free by the same means as site-scope-lists.test.php: a FakeDB stands in
 * for PDODB. One query has to be answered for real -- HookManager::
 * processEvent() populates its known-event list with
 * `Route::getIds('hookevent')`, which is what makes `sensitiveFieldMap()`
 * re-entrant and is why that map now memoizes before it fires its event.
 *
 * Usage: php tests/route-ids-getfield-sensitive.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\EventManager;
use FOG\Base\HookManager;
use FOG\Router\Route;

$webroot = dirname(__DIR__) . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-ids-getfield-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
@mkdir($tmp . '/plugins', 0700, true);
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
// FOGBase::_writeLog() stamps the schema version into every line, so the
// off-request arm's log call needs this defined. A real install gets it from
// the generated config.class.php, which a test does not have.
if (!defined('FOG_SCHEMA')) {
    define('FOG_SCHEMA', 0);
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
    /** Cell value for any query against a table under test. */
    const SENTINEL = 'LEAKED';

    public $error = false;
    public $log = [];
    private $_result;

    public function query($sql, $a = [], $params = [])
    {
        $this->log[] = $sql;
        // One query must be answered for real: HookManager::processEvent()
        // learns which events exist by calling Route::getIds('hookEvents'),
        // and an empty answer sends it off to save() a new row -- which on a
        // FakeDB is noise, and which drags the logging stack in behind it.
        // Answering with the event names a live server already has keeps the
        // re-entrant call this file pins (processEvent -> getIds -> ids)
        // while stopping there.
        //
        // Everything else answers nothing. Any other query means a sensitive
        // column reached the SQL builder, which is the thing being prevented,
        // and the assertions below read $this->log to say so.
        if (false !== strpos($sql, 'hookEvents')) {
            $this->_result = [
                ['heName' => 'API_SENSITIVE_FIELDS'],
                ['heName' => 'API_SERVER_OWNED_FIELDS'],
            ];
            return $this;
        }
        // A query against a table under test answers with a SENTINEL row
        // rather than nothing. Answering [] would let "the call returned no
        // rows" pass even with the guard removed, because an empty database
        // returns no rows too -- the assertion would be measuring the
        // fixture, not the fix. A row that comes back only when the query
        // actually ran is what makes the difference visible.
        //
        // Scoped to those two tables so the settings reader, which runs its
        // own globalSettings query through this same fake, still gets the
        // empty answer it copes with.
        foreach (['`hosts`', '`users`'] as $table) {
            if (false === strpos($sql, $table)) {
                continue;
            }
            // The row is built from the columns the statement actually names,
            // so an allowed field gets a value back instead of a warning, and
            // a blocked one can only get a value if its column reached the
            // SELECT -- which is the thing under test.
            $row = [];
            if (preg_match('/SELECT (.+?) FROM/is', $sql, $m)) {
                foreach (explode('`,`', trim($m[1], '` ')) as $col) {
                    $row[$col] = self::SENTINEL;
                }
            }
            $this->_result = [$row ?: ['__sentinel__' => self::SENTINEL]];
            return $this;
        }
        $this->_result = [];
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

$db = new FakeDB();
$dbProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'DB');
$dbProp->setAccessible(true);

// LoadGlobals is what normally builds these, and it needs a real database.
// sensitiveFieldMap()/serverOwnedFields() fire events, so a HookManager has to
// exist -- a real one, because the re-entrancy this file pins runs through
// HookManager::processEvent()'s own getIds() call. It loads nothing: the
// plugin dir is the empty temp dir above and every core hook ships
// $active = false.
$hmProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'HookManager');
$hmProp->setAccessible(true);
$hmProp->setValue(null, new HookManager());
$emProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'EventManager');
$emProp->setAccessible(true);
$emProp->setValue(null, new EventManager());

// After the managers, not before: constructing them re-runs FOGBase's own
// wiring, which puts a real PDODB back.
$dbProp->setValue(null, $db);

/*
 * 1. The two tiers are what `ids()` must refuse, and they are read from the
 *    same place the emitter reads. Asserted first: every check below is
 *    meaningless if this list is empty.
 */
$hostBlocked = Route::unfilterableFields('host');
$userBlocked = Route::unfilterableFields('user');
check(
    'unfilterableFields(host) is non-empty and names the client-protocol token',
    in_array('sec_tok', $hostBlocked, true) && count($hostBlocked) > 1,
    $failures,
    $checks
);
check(
    'unfilterableFields(user) names the API credential',
    in_array('token', $userBlocked, true)
    && in_array('password', $userBlocked, true),
    $failures,
    $checks
);

/*
 * 2. Asking for a blocked field yields NO ROWS and issues no query for it.
 *    The row check and the query check are both needed: returning [] while
 *    still having SELECTed the column would mean the value was read into the
 *    process, and a later change to the emitter could surface it.
 */
foreach ([['host', $hostBlocked], ['user', $userBlocked]] as [$cls, $blocked]) {
    // The friendly name is what a caller writes in the URL; the DATABASE
    // COLUMN is what appears in the SQL, and the two rarely look alike --
    // 'sec_tok' is `hostSecToken`, 'password' is `uPass`. Grepping the
    // statement for the friendly name therefore catches only the handful
    // where one happens to be a substring of the other, which is a test that
    // passes for the wrong reason on five fields out of ten.
    $vars = Route::getClass($cls, '', true);
    foreach ($blocked as $field) {
        $column = $vars['databaseFields'][$field] ?? null;
        check(
            "$cls.$field is a real column, so this case is meaningful",
            null !== $column,
            $failures,
            $checks
        );
        $before = count($db->log);
        Route::$data = 'unset-sentinel';
        Route::ids($cls, 'id=1', $field);
        $out = Route::$data;
        Route::getData();
        // Through the envelope, not around it. ids() answers with the rows
        // under `data` -- so counting $out itself counts the envelope, which
        // is 1 whether the guard held or not and would pass on a leak.
        check(
            "ids($cls, …, $field) answers with the data envelope",
            is_array($out) && array_key_exists('data', $out),
            $failures,
            $checks
        );
        $rows = (is_array($out) && isset($out['data'])) ? $out['data'] : null;
        check(
            "ids($cls, …, $field) returns no rows",
            is_array($rows) && count($rows) === 0,
            $failures,
            $checks
        );
        $issued = array_slice($db->log, $before);
        $selected = false;
        foreach ($issued as $sql) {
            if (null !== $column && false !== stripos($sql, (string)$column)) {
                $selected = true;
            }
        }
        check(
            "ids($cls, …, $field) never names `$column` in SQL",
            !$selected,
            $failures,
            $checks
        );
    }
}

/*
 * 3. The fields a caller legitimately asks for are untouched. Without this the
 *    guard could be "refuse everything" and every check above would pass.
 *    `getIds()`'s real call sites ask for id, name, path, snapinpath, hostID,
 *    ip, mac, siteID and friends.
 */
foreach ([['host', 'id'], ['host', 'name'], ['host', 'ip'], ['user', 'name']] as [$cls, $field]) {
    $before = count($db->log);
    Route::ids($cls, 'id=1', $field);
    $out = Route::$data;
    Route::getData();
    check(
        "ids($cls, …, $field) still runs its query",
        count($db->log) > $before,
        $failures,
        $checks
    );
    // And still returns what the query produced. "Runs a query" alone would
    // pass for a guard that queries and then discards. Read through the
    // envelope for the reason given in 2: `count($out)` is 1 for an envelope
    // holding no rows at all.
    $rows = (is_array($out) && isset($out['data'])) ? $out['data'] : null;
    check(
        "ids($cls, …, $field) still returns its rows",
        is_array($rows)
        && count($rows) === 1
        && FakeDB::SENTINEL === reset($rows),
        $failures,
        $checks
    );
}

/*
 * 3b. The internal wrappers hand back the ROWS, not the envelope.
 *
 *     `ids()` and `names()` answer on the wire with their rows under `data`,
 *     because a bare top-level array cannot be described to a code generator
 *     (see OpenAPI::_rawArrayResponse()). That envelope is the wire format;
 *     an internal caller wants the rows, and there are ~220 of them --
 *     HookManager::processEvent() array_flip()s the result, deletemass()
 *     uses it as a WHERE value, TaskManager::cancel() passes it to an IN.
 *     None of them error on the wrong shape. They read null and carry on,
 *     which is the failure mode getRows() was written to end.
 *
 *     Asserted behaviourally, against the same FakeDB: the row that comes
 *     back is the SENTINEL the fake produced, so a wrapper that returned the
 *     envelope, an empty list, or a hard-coded value cannot pass.
 */
$ids = Route::getIds('host', 'id=1', 'name');
check(
    'getIds() returns the rows, not the data envelope',
    is_array($ids)
    && !array_key_exists('data', $ids)
    && count($ids) === 1
    && FakeDB::SENTINEL === reset($ids),
    $failures,
    $checks
);
check(
    'getIds() clears Route::$data behind it',
    '' === Route::$data,
    $failures,
    $checks
);
// A blocked field still yields no rows through the wrapper -- the refusal
// arm sets the same envelope, so unwrapping must not turn [] into [[]].
check(
    'getIds() on a refused field returns no rows',
    [] === Route::getIds('host', 'id=1', 'sec_tok'),
    $failures,
    $checks
);
// names() projects id and name together, so its rows are objects and every
// call site reads $row->name. The fake answers with a SENTINEL per selected
// column, which is enough to see the shape survive.
$names = Route::getNames('host');
check(
    'getNames() returns the rows, not the data envelope',
    is_array($names)
    && count($names) === 1
    && is_object(reset($names))
    && FakeDB::SENTINEL === reset($names)->name,
    $failures,
    $checks
);

/*
 * 4. `sensitiveFieldMap()` is re-entrant.
 *
 *    HookManager::processEvent() populates $knownEvents with
 *    Route::getIds('hookevent'), so firing any event re-enters Route. With the
 *    map memoized only AFTER its event, a Route path that consults the map
 *    arrives back here with it still null and fires again -- an OOM in about
 *    forty frames. The guard added to ids() is exactly such a path, so this
 *    is not a hypothetical: it is the reason the memo moved.
 *
 *    Proven by clearing the memo and asking again from scratch. A regression
 *    exhausts memory rather than failing, which is loud enough.
 */
$mapProp = new \ReflectionProperty(\FOG\Router\Route::class, '_sensitiveMap');
$mapProp->setAccessible(true);
$mapProp->setValue(null, null);
$knownProp = new \ReflectionProperty(\FOG\Base\HookManager::class, 'knownEvents');
$knownProp->setAccessible(true);
$knownProp->setValue(null, null);
Route::ids('host', 'id=1', 'sec_tok');
Route::getData();
check(
    'a cold sensitiveFieldMap() survives ids() re-entering through processEvent',
    is_array($mapProp->getValue()) && isset($mapProp->getValue()['fields']),
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
