<?php
/**
 * The net under the API read path's access controls.
 *
 * Every mechanism that removes or masks a row or a column between a request
 * arriving and a payload leaving is enumerated in
 * docs/route-listem-access-control-map.md. Section 6 of that document records
 * why this file exists: EIGHT separate single-line deletions, each removing
 * one of those mechanisms -- including `_applySiteScope()` itself, the
 * emitter's `stripSensitivePayload()` call, and the `_lang` stamp the emitter
 * resolves the classname from -- every one of them left the suite reporting
 * "72 passed, 0 failed".
 *
 * The route tests that existed pinned SYMBOLS.
 * `sensitive-fields-unfilterable.test.php` greps for the strings
 * `_assertNoSensitiveFilter(`, `HTTP_BAD_REQUEST` and `'nosearch'`, so a
 * `return;` inserted above the refusal leaves all three in place.
 * `site-scope-lists.test.php` exercises `Authorization::scopedObjectIDs()`
 * exhaustively -- the SUPPLIER of scope ids -- and never names `Route`.
 * `listem-envelope.test.php` is a caller-side lint. So the behavior was
 * unpinned in exactly the file nobody can review by reading.
 *
 * This drives the real functions against `tests/lib/fog-test-harness.php`,
 * which fakes the database rather than the code under test. It is the first
 * commit of docs/route-listem-plan.md and it gates the rest: nothing in
 * route.class.php gets decomposed until every mutation in that table turns
 * this file red.
 *
 * It does. All eight, plus six more found while building it -- the table now
 * stands at fourteen and is reproduced in the plan. Three of the six are
 * worth knowing about because they are the ones a reader would assume were
 * already covered and are not:
 *
 *   - The dedicated sensitive-filter guard can be deleted outright and the
 *     request is STILL refused, by the unknown-key arm below it, because that
 *     arm computes its valid-key list by subtracting the same blocked list.
 *     "Was it refused?" therefore cannot see the real guard go. The arms are
 *     told apart by whether the refusal carries a `valid` list.
 *   - `search()` applies the site boundary a SECOND time, after
 *     `API_MASSDATA_MAPPING`. Section 6 stays green without it, because
 *     listem() already scoped. It only protects rows a plugin added in
 *     between -- so that is what section 6b asserts.
 *   - Pinning `stripSensitivePayload()`'s behavior does not pin the
 *     emitter's CALL to it. That mutation went uncaught until `printer()`
 *     itself was driven through `asValue()`.
 *
 * WHAT IS DELIBERATELY NOT ASSERTED. That the SQL is valid. The fake answers
 * statements structurally, so this says what the PHP layer does with rows,
 * never that a server would accept the query.
 *
 * Usage: php tests/route-read-path-guards.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;
use FOG\Auth\SiteScope;
use FOG\Base\FOGCore;
use FOG\Base\Hook;
use FOG\Items\User;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

// ---------------------------------------------------------------------------
// Child mode.
//
// Two of the guards below live on the REQUEST BODY, which the code reads as
// php://input -- and the CLI SAPI does not populate php://input at all,
// whatever stdin is pointed at. So those cases run as child processes under a
// CGI PHP, which does. See child() for the mechanics and for what it costs.
//
// A child identifies its case through QUERY_STRING (CGI) or --case= (so the
// same file can still be run by hand from a shell for debugging).
// ---------------------------------------------------------------------------
$childCase = null;
foreach (array_slice(isset($argv) ? $argv : [], 1) as $arg) {
    if (0 === strpos($arg, '--case=')) {
        $childCase = substr($arg, 7);
    }
}
if (null === $childCase && isset($_GET['case'])) {
    $childCase = (string)$_GET['case'];
}
if (null !== $childCase) {
    runChild($childCase);
    exit(0);
}

/**
 * One body-fed case, run in its own process. Prints a single line the parent
 * parses. Never asserts -- the parent owns the verdict.
 *
 * @param string $case the case name
 *
 * @return void
 */
function runChild($case)
{
    FogTestHarness::boot('read-path-child');
    $db = FogTestHarness::fakeDb();

    // A JSON search body naming a field the emitter strips must be refused.
    // getsearchbody() intersects the body with the class's own fields, which
    // is every sensitive field too -- so the intersection is not the guard,
    // _assertNoSensitiveFilter() is.
    if (0 === strpos($case, 'body-filter:')) {
        list(, $class, $field) = explode(':', $case);
        try {
            Route::asValue(
                function () use ($class) {
                    Route::listem($class);
                }
            );
            echo "ALLOWED\n";
        } catch (\RuntimeException $e) {
            echo 'REFUSED ' . str_replace("\n", ' ', $e->getMessage()) . "\n";
        }
        return;
    }

    // A DataTables column search against a stripped column must not reach the
    // WHERE clause. listem() marks those columns 'nosearch' after
    // CUSTOMIZE_DT_COLUMNS; FOGManagerController::filter() is what honors it.
    // Asserted on the SQL because that is the only place the marking becomes
    // observable -- $columns never leaves listem().
    if ('nosearch' === $case) {
        Route::listem('host');
        Route::getData();
        $found = [];
        foreach ($db->pdo->log as $sql) {
            foreach (['hostProductKey', 'hostName'] as $col) {
                if (false !== strpos($sql, '`' . $col . '` LIKE')) {
                    $found[$col] = true;
                }
            }
        }
        echo 'SEARCHED ' . implode(',', array_keys($found)) . "\n";
        return;
    }

    // The hard-coded column drop, which is a SECOND secret list beside
    // sensitiveFieldMap() and has to agree with it. Removing a name from it
    // puts the column back in the grid's SELECT, so the SQL is where it
    // becomes observable -- $tmpcolumns never leaves _listColumns().
    if (0 === strpos($case, 'removed-columns:')) {
        list(, $class) = explode(':', $case);
        Route::listem($class);
        Route::getData();
        $cols = 'host' === $class
            ? ['hostADPass', 'hostADUser', 'hostSecToken', 'hostPubKey']
            : ['uPass', 'uAPIToken'];
        $found = [];
        foreach ($db->pdo->log as $sql) {
            foreach ($cols as $col) {
                if (false !== strpos($sql, '`' . $col . '`')) {
                    $found[$col] = true;
                }
            }
        }
        echo 'SELECTED ' . implode(',', array_keys($found)) . "\n";
        return;
    }

    // Whether the single-purpose read routes carry the site boundary in their
    // SQL. Run as a child because the boundary is deliberately NOT applied
    // off-request -- getIds()/getNames() are called from ~90 places in core
    // and the services, a daemon has no FOGUser, and a userless caller is in
    // no site and so reaches nothing. Applying it to a process rather than a
    // request would stop every replicator and scheduler on a site-configured
    // server from finding its work.
    //
    // The parent asserts the CLI arm, this asserts the request arm, and
    // between them they pin the gate rather than just one side of it.
    if ('scope-sql' === $case) {
        $scoped = (new User())->set('id', 7)->set('name', 'scoped');
        foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
            FogTestHarness::setStatic($cls, 'FOGUser', $scoped);
        }
        siteScenario(
            $db,
            ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => [5]], 'members' => [2, 3]],
            [7 => ['host.view']]
        );
        $carried = [];
        foreach (['names', 'ids', 'unisearch'] as $route) {
            $before = count($db->log);
            Route::$data = [];
            try {
                if ('names' === $route) {
                    Route::names('Host');
                } elseif ('ids' === $route) {
                    Route::ids('Host', [], 'name');
                } else {
                    Route::unisearch('x');
                }
            } catch (\Throwable $e) {
                // The payload is not what is being read; the statements are.
            }
            Route::$data = [];
            // Only the statements against the entity's own table count. The
            // boundary lookup issues its own queries against the membership
            // tables, and those mention siteHostMembers by their nature --
            // counting them would make this pass whatever the route did.
            $hit = false;
            foreach (array_slice($db->log, $before) as $sql) {
                if (false === strpos($sql, '`hosts`')) {
                    continue;
                }
                if (false === strpos($sql, 'siteHostMembers')) {
                    continue;
                }
                // Present is not the same as binding correctly, and for
                // unisearch the difference is the whole defect. Its match
                // clause is a chain of ORs, so `WHERE a OR b AND scope`
                // binds the boundary to the last arm only -- AND is tighter
                // than OR -- and every other arm goes on matching
                // server-wide. The SQL is valid and the statement mentions
                // the membership table either way, so nothing short of
                // reading the parenthesisation can tell the two apart.
                //
                // names() and ids() build a WHERE with no OR in it, so there
                // is no ambiguity to check there.
                if ('unisearch' === $route
                    && !preg_match('/WHERE\s*\(.*\)\s*AND\s.*siteHostMembers/s', $sql)
                ) {
                    continue;
                }
                $hit = true;
            }
            if ($hit) {
                $carried[] = $route;
            }
        }
        echo 'BOUNDED ' . implode(',', $carried) . "\n";
        return;
    }

    echo "UNKNOWN CASE\n";
}

/**
 * Is a CGI PHP available to run body-fed cases with?
 *
 * @return string|false the binary, or false
 */
function cgiBinary()
{
    static $bin = null;
    if (null !== $bin) {
        return $bin;
    }
    $found = trim((string)@shell_exec('command -v php-cgi 2>/dev/null'));
    $bin = ('' !== $found && is_executable($found)) ? $found : false;
    return $bin;
}

/**
 * Run one body-fed case in a child process.
 *
 * Under php-cgi, not the CLI binary, and that is not a preference. The CLI
 * SAPI does not populate php://input at all -- `file_get_contents(
 * 'php://input')` returns an empty string there however stdin is redirected
 * (php://stdin is a different stream and is not what the code reads). A CGI
 * SAPI fills it from stdin given CONTENT_LENGTH, so this is the only way to
 * exercise the two request-body guards against the real functions.
 *
 * It buys a second thing for free: SAPI is 'cgi-fcgi' in the child, so guards
 * with a serving-a-request arm take that arm rather than their off-request one.
 *
 * @param string $case the case name
 * @param string $body the raw request body
 *
 * @return string the child's first non-header output line
 */
function child($case, $body)
{
    $bin = cgiBinary();
    if (false === $bin) {
        return 'NO CGI';
    }
    $env = [
        'REDIRECT_STATUS' => '200',
        'SCRIPT_FILENAME' => __FILE__,
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'CONTENT_LENGTH' => (string)strlen($body),
        'QUERY_STRING' => 'case=' . rawurlencode($case),
        'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
    ];
    $pipes = [];
    $proc = proc_open(
        [$bin, '-q'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $env
    );
    if (!is_resource($proc)) {
        return 'SPAWN FAILED';
    }
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    // Strip the CGI header block, then find the marker line. The bootstrap
    // emits warnings of its own under CGI (session ini settings after headers)
    // which are not this test's business.
    $body = $out;
    $split = preg_split('/\r?\n\r?\n/', $out, 2);
    if (count($split) === 2) {
        $body = $split[1];
    }
    foreach (preg_split('/\r?\n/', $body) as $candidate) {
        $candidate = trim($candidate);
        if ('' === $candidate) {
            continue;
        }
        if (preg_match('/^(REFUSED|ALLOWED|SEARCHED|SELECTED|BOUNDED|UNKNOWN CASE)/', $candidate)) {
            return $candidate;
        }
    }
    // Surface the child's own output rather than reporting a bare mismatch; a
    // bootstrap failure here otherwise reads as a guard failure.
    return 'NO MARKER: ' . trim(str_replace("\n", ' | ', $body . ' ' . $err));
}

// ---------------------------------------------------------------------------
// Parent.
// ---------------------------------------------------------------------------
FogTestHarness::boot('read-path');
$db = FogTestHarness::fakeDb();
$t = new FogChecks();

/**
 * A hook standing in for a plugin that declares its own secrets.
 *
 * Both tiers, because they behave differently at the emitter and the
 * difference is a documented contract: 'fields' comes back on a direct
 * single-entity GET (fog-client reads host.ADPass that way) and is stripped
 * everywhere else, while 'always' never leaves the server at all. Core ships
 * an empty 'always' tier, so without a plugin declaring one there is nothing
 * to tell the two apart with.
 */
class NetPluginSecretsHook extends Hook
{
    public $name = 'NetPluginSecretsHook';
    public $description = 'test double';
    public $active = true;

    public function declareSecrets($arguments)
    {
        $arguments['fields']['host'][] = 'kernelArgs';
        $arguments['always']['host'][] = 'biosexit';
    }
}

$pluginHook = new NetPluginSecretsHook();
FogTestHarness::getStatic('FOGBase', 'HookManager')
    ->register('API_SENSITIVE_FIELDS', [$pluginHook, 'declareSecrets']);
FogTestHarness::setStatic('Route', '_sensitiveMap', null);

/*
 * ===========================================================================
 * 1. The derived list. Everything below asks Route what it considers secret;
 *    if that answer is wrong or empty, every later check passes vacuously.
 * ===========================================================================
 */
$hostBlocked = Route::unfilterableFields('host');
$userBlocked = Route::unfilterableFields('user');

$t->check(
    'unfilterableFields(host) carries the tier-1 fields',
    count(
        array_diff(
            (array)(Route::$sensitiveFields['host'] ?? []),
            $hostBlocked
        )
    ) === 0
);
/*
 * user has no tier-1 entry at all as of GH-1326: token and password are both
 * tier 2, so the class is simply absent from $sensitiveFields the way image
 * and snapin are. Assert the derived list directly rather than diffing a tier
 * that is not there -- diffing it would be vacuous even if it did not fatal,
 * and this pins the property that actually matters: both user secrets are
 * blocked, wherever they are declared.
 *
 * It DID fatal, which is the second reason to write it this way.
 * array_diff(null, ...) is a TypeError on PHP 8 and only a warning on 7.4, so
 * the 7.4 job passed and the 8.3 job died -- the null-to-internal-param class.
 */
$t->check(
    'unfilterableFields(user) carries both user secrets',
    count(array_diff(['token', 'password'], $userBlocked)) === 0
);
$t->check(
    'unfilterableFields folds in what a plugin declared (tier 1)',
    in_array('kernelArgs', $hostBlocked, true)
);
$t->check(
    'unfilterableFields folds in what a plugin declared (tier 2)',
    in_array('biosexit', $hostBlocked, true)
);

/*
 * ===========================================================================
 * 2. Input side: a URL filter naming a stripped field is REFUSED, and the
 *    refusal names the field.
 *
 *    Refused rather than ignored, and that is the load-bearing half: dropping
 *    the key would answer with the UNFILTERED set, so a caller asking for one
 *    host would receive all of them.
 *
 *    Two guards refuse this, independently, and the third check below is what
 *    tells them apart. `_assertFilterKeys()` calls
 *    `_assertNoSensitiveFilter()` and THEN computes its list of valid keys by
 *    subtracting `unfilterableFields()` -- so a blocked field is also an
 *    "unknown" one, and deleting the explicit call still yields a 400. That
 *    makes "was it refused?" alone unable to see the dedicated guard go, and
 *    the layer that remains is the accidental one: the day someone stops
 *    subtracting from `$valid` (a reasonable-looking tidy-up -- the field IS
 *    declared in `$databaseFields`) the refusal disappears with nothing red.
 *
 *    The arms are distinguishable without asserting on translated prose: the
 *    unknown-key arm answers with a `valid` list of alternatives, the
 *    sensitive-field arm answers with `error` alone. Absence of `valid` is
 *    therefore "the dedicated guard fired", and it is worth pinning in its own
 *    right -- a refusal that enumerated the fields you may filter on instead
 *    would be answering the question it exists to refuse.
 * ===========================================================================
 */
foreach ([['host', $hostBlocked], ['user', $userBlocked]] as list($class, $blocked)) {
    foreach ($blocked as $field) {
        $refusal = null;
        $refusalCode = null;
        try {
            Route::asValue(
                function () use ($class, $field) {
                    Route::listem($class, $field . '=x', true);
                }
            );
        } catch (\RuntimeException $e) {
            $refusal = $e->getMessage();
            $refusalCode = $e->getCode();
        }
        Route::$data = [];
        $t->check(
            "URL filter on $class.$field is refused",
            null !== $refusal
        );
        $t->check(
            "the refusal for $class.$field names the field",
            null !== $refusal && false !== strpos($refusal, $field)
        );
        $decoded = null === $refusal ? null : json_decode($refusal, true);
        $t->check(
            "$class.$field is refused by the sensitive-field guard, not the"
            . ' unknown-key fallback',
            is_array($decoded)
            && isset($decoded['error'])
            && !array_key_exists('valid', $decoded)
        );
        // DEC-5. The refusal is a 400 and listem()'s catch used to relabel
        // every failure 406 -- invisible over plain HTTP, where the inner
        // sendResponse() exits before the catch, and wrong here, which is
        // the seam every service and client endpoint reads through.
        $t->check(
            "the refusal for $class.$field keeps its own 400, not listem()'s"
            . ' catch-all 406',
            400 === $refusalCode
        );
    }
}

/*
 * DEC-5, the other half: a code that is NOT an HTTP status still becomes 406.
 * A PDOException carries a SQLSTATE ('42S22'), which casts to a plausible
 * 42, and a hand-thrown Exception defaults to 0 -- either one reaching
 * breakHead() as a status is a worse answer than the catch-all it replaced.
 */
$sendCaught = new \ReflectionMethod(\FOG\Router\Route::class, '_sendCaught');
$sendCaught->setAccessible(true);
foreach ([['42S22', 406], [0, 406], [399, 406], [404, 404], [600, 406]] as list($raised, $want)) {
    $got = null;
    try {
        Route::asValue(
            function () use ($sendCaught, $raised) {
                $sendCaught->invoke(null, new \Exception('probe', (int)$raised));
            }
        );
    } catch (\RuntimeException $e) {
        $got = $e->getCode();
    }
    Route::$data = [];
    $t->check(
        "_sendCaught() maps an inner code of " . var_export($raised, true)
        . " to $want",
        $want === $got
    );
}

/*
 * The unknown-key refusal must not advertise a blocked field back as a valid
 * alternative. `_assertFilterKeys()` says so in a comment and nothing tested
 * it; the subtraction that implements it is also the accidental second layer
 * behind the guard above, so losing it silently would leave that guard alone.
 */
$unknown = null;
try {
    Route::asValue(
        function () {
            Route::listem('host', 'nosuchfield=x', true);
        }
    );
} catch (\RuntimeException $e) {
    $unknown = json_decode($e->getMessage(), true);
}
Route::$data = [];
$t->check(
    'an unknown filter key is refused and lists the valid alternatives',
    is_array($unknown) && isset($unknown['valid']) && count($unknown['valid']) > 0
);
$advertised = array_intersect((array)($unknown['valid'] ?? []), $hostBlocked);
$t->check(
    'the valid-alternatives list names no field the emitter strips',
    count($advertised) < 1
);

/*
 * A filter on an ordinary field is NOT refused. Without this the guard could
 * be "refuse every filter" and everything above would still pass.
 */
$allowed = true;
try {
    Route::asValue(
        function () {
            Route::listem('host', 'name=x', true);
        }
    );
} catch (\RuntimeException $e) {
    $allowed = false;
}
Route::$data = [];
$t->check('a filter on an ordinary field is still accepted', $allowed);

/*
 * ===========================================================================
 * 3. Input side, second entry point: the JSON search body. Child processes,
 *    because this one is read off php://input.
 * ===========================================================================
 */
if (false === cgiBinary()) {
    // The suite's convention for a tool it cannot find: say SKIP, name the
    // tool, and let everything else run (cf. secureboot-authvars.test.sh).
    // Stated loudly because these are the only two guards in the map that
    // this file then leaves unpinned.
    echo "SKIP: php-cgi not found; the two request-BODY guards "
        . "(JSON search body, DataTables column search) are not exercised.\n";
} else {
    foreach ([['host', 'sec_tok'], ['host', 'ADPass'], ['user', 'password'], ['user', 'token']] as list($class, $field)) {
        $line = child(
            "body-filter:$class:$field",
            json_encode([$field => 'x'])
        );
        $t->check(
            "JSON body filter on $class.$field is refused (got: $line)",
            0 === strpos($line, 'REFUSED') && false !== strpos($line, $field)
        );
    }
    $line = child('body-filter:host:name', json_encode(['name' => 'x']));
    $t->check(
        "a JSON body filter on an ordinary field is accepted (got: $line)",
        'ALLOWED' === $line
    );
}

/*
 * ===========================================================================
 * 4. Column side: a stripped column must not be SEARCHABLE either.
 *
 *    The value never comes back, so a match can only be read as an answer
 *    about a value the caller may not see -- and a DataTables filter is a
 *    substring LIKE, so the answer is repeatable one character at a time.
 *    productKey is the case that matters: it is deliberately left IN the grid
 *    row (the Product Keys report calls listem('host') and has nothing to
 *    report without it), so 'nosearch' is the only thing closing it.
 * ===========================================================================
 */
$body = http_build_query(
    [
        'columns' => [
            ['data' => 'productKey', 'searchable' => 'true', 'search' => ['value' => 'ABC']],
            ['data' => 'name', 'searchable' => 'true', 'search' => ['value' => 'ABC']],
        ],
    ]
);
if (false !== cgiBinary()) {
    $line = child('nosearch', $body);
    $t->check(
        "an ordinary column is searched, so the fixture is live (got: $line)",
        false !== strpos($line, 'hostName')
    );
    $t->check(
        "a stripped column is NOT searched (got: $line)",
        false === strpos($line, 'hostProductKey')
    );
}

/*
 * ===========================================================================
 * 4a. The hard-coded column drop, named as the control it is.
 *
 *     _listColumns() opens by dropping user.password/token and the host
 *     secret set from $tmpcolumns outright, before any hook sees them, so
 *     those columns are never built, never reach the SELECT and never reach
 *     the payload. docs/route-listem-access-control-map.md 2 calls this out
 *     as a SECOND secret list that has to agree with sensitiveFieldMap().
 *
 *     tests/route-column-contract.test.php DOES catch this -- disabling
 *     either arm adds 7 and 2 columns respectively and the golden file goes
 *     red. This is not a coverage gap and is not asserted here because one
 *     was found. It is asserted here because the column contract reports
 *     "the table changed", and its documented remedy for an intended change
 *     is `--update`. A secret arriving in the grid is the one table change
 *     that must never be resolved that way, and a check that names the
 *     leaked column cannot be silenced by regenerating a fixture.
 * ===========================================================================
 */
if (false !== cgiBinary()) {
    foreach (['host' => 'hostADPass', 'user' => 'uPass'] as $cls => $secret) {
        $line = child('removed-columns:' . $cls, '');
        // Marker first: without it a child that died during bootstrap
        // returns a string containing neither column name, and the check
        // below would read that silence as a pass.
        $t->check(
            "$cls: the removed-columns probe ran (got: $line)",
            0 === strpos($line, 'SELECTED')
        );
        $t->check(
            "$cls: the dropped secret columns stay out of the SELECT "
            . "(got: $line)",
            false === strpos($line, $secret)
        );
    }
}

/*
 * ===========================================================================
 * 4b. Nested entities are stripped as what they ARE, not as what they are
 *     nested inside.
 *
 *     stripSensitivePayload() picks the classname off the payload's own
 *     '_lang' stamp, which is the only thing a list payload carries. A
 *     storage node embedded in a storage group row was therefore stripped
 *     as a storagegroup -- i.e. not at all -- and the node's FTP password,
 *     which reaches the root-running replicator's lftp call, went out in
 *     the storage group grid to anyone holding storagegroup.view. Same
 *     shape for task.host, task.image and host.inventory.
 *
 *     Closed in getter(), because that is the one place that knows what
 *     class it is shaping whatever payload the result ends up inside.
 *     Tier 2 only there: tier 1 exists so a single-entity GET still
 *     carries it (fog-client reads host ADPass back to join a domain),
 *     and only the emitter knows whether it is emitting one record or a
 *     list. Both halves are asserted -- stripping without the carve-out
 *     would be just as wrong, and silently breaks domain joins.
 * ===========================================================================
 */
// The core declaration itself, first: every check below is meaningless
// if the class no longer names these as secret.
$nodeBlocked = Route::unfilterableFields('storagenode');
$t->check(
    'a storage node FTP credential is declared sensitive',
    in_array('pass', $nodeBlocked, true) && in_array('key', $nodeBlocked, true)
);

// That a nested entity is stripped as WHAT IT IS used to be asserted here
// against getter()'s source, because getter('storagenode', ...) runs live
// lookups this DB-free fixture cannot serve. It no longer has to be: the
// strip happens at the emitter now, over the class registry embed() fills
// in, and stripSensitivePayload() takes plain arrays. So this is the real
// behavior rather than a regex over the shape of a line.
//
// Why the move: getter()'s per-level strip only ever reached embeds built
// by recursing into getter(). The ones that were a plain ->get() -- task's
// storagenode among them -- were stripped at no level at all, and it also
// made Route::getItem() hand redacted objects to internal callers while
// Route::getList() handed whole ones. See
// tests/api-nested-secret-strip.test.php for the full set.
$nestedProp = new \ReflectionProperty('FOG\\Router\\Route', 'nestedClasses');
$nestedProp->setAccessible(true);
$nestedProp->setValue(null, ['task' => ['storagenode' => 'storagenode']]);
$emitClass = new \ReflectionProperty('FOG\\Router\\Route', 'emitClassname');
$emitClass->setAccessible(true);
$emitClass->setValue(null, 'task');

$nestedOut = Route::stripSensitivePayload(
    [
        'id' => 1,
        '_lang' => 'task',
        'storagenode' => [
            'id' => 3,
            'name' => 'debian',
            'pass' => 'NODEFTPPW',
            'key' => 'NODEHMACKEY',
        ],
    ]
);
$t->check(
    'a storagenode nested in a task is stripped as a storagenode, not as'
    . ' a task',
    !array_key_exists('pass', $nestedOut['storagenode'])
    && !array_key_exists('key', $nestedOut['storagenode'])
);
$t->check(
    'stripping the nested entity leaves its non-secret columns alone',
    'debian' === ($nestedOut['storagenode']['name'] ?? null)
);

// The tier-1 carve-out must NOT follow the nesting. A host reached through
// GET /host/{id} keeps ADPass so fog-client can join a domain; the same
// host reached as task.host is a different request and keeps nothing.
$nestedProp->setValue(null, ['task' => ['host' => 'host']]);
$emitClass->setValue(null, 'task');
$nestedHost = Route::stripSensitivePayload(
    [
        'id' => 1,
        '_lang' => 'task',
        'host' => ['id' => 9, 'name' => 'pc', 'ADPass' => 'JOINPW'],
    ]
);
$t->check(
    'tier 1 does NOT survive in a nested position (task.host.ADPass)',
    !array_key_exists('ADPass', $nestedHost['host'])
);
$nestedProp->setValue(null, []);
$emitClass->setValue(null, '');

// Tier 1 must NOT be dropped by the always-only pass -- that carve-out is
// why fog-client can still read a host's ADPass back to join a domain,
// and losing it breaks domain joins silently.
$kept = Route::stripSensitive(
    'host',
    ['id' => 1, 'name' => 'probe', 'ADPass' => 'JOINPW'],
    true
);
$t->check(
    'the always-only pass leaves tier 1 alone (fog-client reads ADPass)',
    'JOINPW' === ($kept['ADPass'] ?? null)
);
$t->check(
    'the both-tiers pass does drop it, which is what a list gets',
    !isset(Route::stripSensitive('host', ['ADPass' => 'JOINPW'])['ADPass'])
);

/*
 * ===========================================================================
 * 5. Emitter side.
 * ===========================================================================
 */
$listPayload = [
    '_lang' => 'host',
    'data' => [
        [
            'id' => 1,
            'name' => 'a',
            'sec_tok' => 'TIER1',
            'productKey' => 'TIER1',
            'kernelArgs' => 'PLUGIN-TIER1',
            'biosexit' => 'PLUGIN-TIER2',
        ],
    ],
];
$stripped = Route::stripSensitivePayload($listPayload);
$row = $stripped['data'][0];
$t->check('list payload keeps the ordinary fields', isset($row['id'], $row['name']));
$t->check('list payload drops tier 1', !isset($row['sec_tok'], $row['productKey']));
$t->check('list payload drops a plugin tier-1 field', !isset($row['kernelArgs']));
$t->check('list payload drops a plugin tier-2 field', !isset($row['biosexit']));

/*
 * A single-entity payload keeps tier 1 and drops tier 2. This is the
 * documented "secrets only on a direct single GET" contract fog-client
 * depends on for host ADPass, so it is pinned as a REQUIREMENT rather than
 * left as whatever falls out.
 */
FogTestHarness::setStatic('Route', 'emitClassname', 'host');
$single = Route::stripSensitivePayload(
    [
        'id' => 1,
        'name' => 'a',
        'ADPass' => 'TIER1',
        'kernelArgs' => 'PLUGIN-TIER1',
        'biosexit' => 'PLUGIN-TIER2',
    ]
);
$t->check('single-entity payload KEEPS tier 1 (fog-client reads ADPass)', isset($single['ADPass']));
$t->check('single-entity payload keeps a plugin tier-1 field', isset($single['kernelArgs']));
$t->check('single-entity payload drops tier 2', !isset($single['biosexit']));
FogTestHarness::setStatic('Route', 'emitClassname', '');

/*
 * And the EMITTER actually calls it. Everything above pins what
 * stripSensitivePayload() does; none of it notices if printer() stops calling
 * it, which is the whole failure mode this file was written for -- pinning a
 * function's behavior is not pinning its use.
 *
 * printer() ends the response, so it is driven through asValue(): with a
 * result wrapper on the stack sendResponse() raises instead of exiting, and
 * the exception message is verbatim the JSON that would have gone on the wire
 * (ADR 0011). So this asserts against the bytes a client would receive.
 */
$emitted = null;
try {
    Route::asValue(
        function () use ($listPayload) {
            Route::printer($listPayload);
        }
    );
} catch (\RuntimeException $e) {
    $emitted = $e->getMessage();
}
Route::$data = [];
$t->check('printer() emitted something', null !== $emitted && '' !== $emitted);
$t->check(
    'printer() does not put a tier-1 secret on the wire',
    null !== $emitted && false === strpos($emitted, 'TIER1')
);
$t->check(
    'printer() does not put a plugin secret on the wire',
    null !== $emitted && false === strpos($emitted, 'PLUGIN-')
);
$t->check(
    'printer() still emits the ordinary fields',
    null !== $emitted && false !== strpos($emitted, '"name"')
);

/*
 * An UNSTAMPED payload is returned untouched. This is not an aspiration, it
 * is today's behavior, and it is pinned deliberately: it is why SEC-1 --
 * ids() handing back any column named in the URL -- had to be closed at the
 * input rather than at the emitter. If this ever starts stripping, the
 * argument in docs/route-listem-plan.md's commit 0 changes and someone should
 * be made to notice.
 */
$unstamped = Route::stripSensitivePayload(['data' => [['id' => 1, 'sec_tok' => 'S']]]);
$t->check(
    'an unstamped payload is returned unchanged (see commit 0 of the plan)',
    isset($unstamped['data'][0]['sec_tok'])
);

/*
 * A credential setting's value is blanked; its key is not. Searching
 * "PASSWORD" should still find FOG_TFTP_FTP_PASSWORD -- the key was never
 * the secret.
 */
$maskedSetting = Route::stripSensitive(
    'setting',
    ['id' => 1, 'name' => 'FOG_TFTP_FTP_PASSWORD', 'value' => 'hunter2']
);
$t->check('a credential setting loses its value', !isset($maskedSetting['value']));
$t->check('a credential setting keeps its key', 'FOG_TFTP_FTP_PASSWORD' === $maskedSetting['name']);
$plainSetting = Route::stripSensitive(
    'setting',
    ['id' => 2, 'name' => 'FOG_TFTP_PXE_KERNEL', 'value' => 'bzImage']
);
$t->check('an ordinary setting keeps its value', 'bzImage' === ($plainSetting['value'] ?? null));

/*
 * ===========================================================================
 * 6. Row side: the site boundary, driven end to end through listem().
 *
 *    Through listem() and not by calling _applySiteScope() directly, because
 *    a decomposition can preserve the behavior of a method nobody calls any
 *    more. That is the mutation this whole file exists for.
 *
 *    scopedObjectIDs() answers three ways and two of them are falsy:
 *      null = no boundary applies, leave the list alone
 *      []   = the user may see nothing
 *    Collapsing them -- `if (!$ids) { return; }` -- shows every host to a
 *    user with no site, silently. Each case below is asserted by identity.
 * ===========================================================================
 */
$scopedUser = (new User())->set('id', 7)->set('name', 'scoped');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $scopedUser);
}

/**
 * Point the fake at one site configuration.
 *
 * @param FogFakeDb $db     the fake
 * @param array     $tables the shape of the site tables
 * @param array     $perms  user id => permission strings
 *
 * @return void
 */
function siteScenario($db, array $tables, array $perms)
{
    $db->responder = function ($sql, $params) use ($tables) {
        $uid = (int)($params['uid'] ?? 0);
        // unisearch() runs its own LIKE query through query(), not through
        // the prepared-statement path the fake PDO answers. Only needed by
        // the search() case; every other scenario leaves 'searchHits' unset
        // and falls through as before.
        if (isset($tables['searchHits']) && false !== strpos($sql, 'LIKE :item1')) {
            $out = [];
            foreach ((array)$tables['searchHits'] as $hid) {
                $out[] = ['hostID' => $hid, 'hostName' => 'host' . $hid];
            }
            return $out;
        }
        if (false !== strpos($sql, 'IS NOT NULL AND `s`.`siteID` IN (')) {
            return ['cnt' => in_array($uid, (array)($tables['catchAll'] ?? []), true) ? 1 : 0];
        }
        if (false !== strpos($sql, 'FROM `sites`')) {
            return ['cnt' => (int)($tables['siteCount'] ?? 0)];
        }
        if (false !== strpos($sql, 'FROM `siteUserMembers` WHERE')) {
            $out = [];
            foreach ((array)($tables['userSites'][$uid] ?? []) as $s) {
                $out[] = ['siteID' => $s];
            }
            return $out;
        }
        if (isset($tables['members']) && false !== stripos($sql, 'Members')) {
            $out = [];
            foreach ((array)$tables['members'] as $oid) {
                $out[] = ['oid' => $oid];
            }
            return $out;
        }
        return null;
    };
    FogTestHarness::setStatic('Authorization', '_permCache', $perms);
    SiteScope::forgetCaches();
}

$db->pdo->rowCount = 4;
$db->pdo->countValue = 4;

// (a) An unrestricted user: scopedObjectIDs() returns null and the list is
//     untouched. This is also the baseline the other two are compared against.
siteScenario($db, ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => []]], [7 => ['*']]);
Route::listem('host', false, true);
$unscoped = Route::$data;
Route::getData();
$t->check('unrestricted user sees every row', count($unscoped['data']) === 4);
$t->check('unrestricted user: recordsTotal is the real total', 4 === $unscoped['recordsTotal']);

// (b) A user with sites configured but belonging to none: [] means deny all.
//     THE case a falsy check turns into "show everything".
siteScenario($db, ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => []]], [7 => ['host.view']]);
Route::listem('host', false, true);
$denied = Route::$data;
Route::getData();
$t->check('a user with no sites sees NOTHING through listem()', count($denied['data']) === 0);
$t->check('a user with no sites: counts do not describe hidden rows', 0 === $denied['recordsTotal']);

// (c) A scoped user sees exactly their sites' objects and no others.
siteScenario(
    $db,
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => [5]], 'members' => [2, 3]],
    [7 => ['host.view']]
);
Route::listem('host', false, true);
$scoped = Route::$data;
Route::getData();
$t->check('a scoped user sees only in-scope rows', array_column($scoped['data'], 'id') === [2, 3]);
$t->check('a scoped user sees no out-of-scope row', count($scoped['data']) === 2);

// (d) An unscoped NODE is not filtered even for a scoped user. isScopedNode()
//     is what decides, and it has to agree with the single-object path or a
//     node is filtered in lists but not on edit.
siteScenario(
    $db,
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => [5]], 'members' => [2, 3]],
    [7 => ['image.view']]
);
Route::listem('image', false, true);
$image = Route::$data;
Route::getData();
$t->check('an unscoped node is not filtered', count($image['data']) === 4);

/*
 * ---------------------------------------------------------------------------
 * The boundary must be in the SQL, not only on the rows.
 *
 * Everything above would pass with the boundary applied purely as a
 * post-filter -- which is what it used to be, and which is broken on any list
 * with a LIMIT: the database picks the page BEFORE the filter runs, so a user
 * scoped to one site of a 90-host server saw an empty first page (their host
 * sat at offset 75), recordsTotal 0 and nextUrl null. The rows existed; the
 * grid said there were none.
 *
 * The fake answers statements structurally and does not evaluate a WHERE, so
 * no assertion on the returned ROWS can tell the two designs apart. The
 * statement text is where the difference lives, so that is what is read.
 *
 * All three statements matter and are asserted separately: the row query, the
 * filtered count and the total count. A boundary on the rows alone still
 * leaves both counts describing objects the user cannot see.
 * ---------------------------------------------------------------------------
 */

/**
 * The statements listem() issued for one class, split by kind.
 *
 * @param FogFakeDb $db     the fake
 * @param string    $class  the class to list
 *
 * @return array ['rows' => [...], 'counts' => [...]]
 */
function statementsFor($db, $class)
{
    $before = count($db->pdo->log);
    Route::listem($class, false, true);
    Route::getData();
    $rows = [];
    $counts = [];
    foreach (array_slice($db->pdo->log, $before) as $sql) {
        if (preg_match('/^\s*SELECT\s+COUNT/i', $sql)) {
            $counts[] = $sql;
        } else {
            $rows[] = $sql;
        }
    }
    return ['rows' => $rows, 'counts' => $counts];
}

// (e) A scoped user's statements all carry the membership subquery.
siteScenario(
    $db,
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => [5]], 'members' => [2, 3]],
    [7 => ['host.view']]
);
$stmts = statementsFor($db, 'host');
$all = array_merge($stmts['rows'], $stmts['counts']);
$carrying = 0;
foreach ($all as $sql) {
    if (false !== strpos($sql, 'siteHostMembers')) {
        $carrying++;
    }
}
$t->check('a scoped list issues a row query and both counts', count($stmts['rows']) > 0 && count($stmts['counts']) === 2);
$t->check(
    'every statement of a scoped list carries the membership subquery',
    count($all) > 0 && $carrying === count($all)
);
$t->check(
    'the boundary is a subquery, not an id list',
    count($all) > 0 && false !== strpos($all[0], 'SELECT DISTINCT')
);

// (f) A user in no site denies in SQL, and denies with a TRUTHY fragment --
//     '' would read as "no filter" at every call site that tests the value.
siteScenario($db, ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => []]], [7 => ['host.view']]);
$stmts = statementsFor($db, 'host');
$all = array_merge($stmts['rows'], $stmts['counts']);
$denying = 0;
foreach ($all as $sql) {
    if (false !== strpos($sql, '1=0')) {
        $denying++;
    }
}
$t->check(
    'a user in no site is denied by the SQL, in every statement',
    count($all) > 0 && $denying === count($all)
);
$t->check(
    'the deny-all fragment is truthy, so `if (!$where)` cannot drop it',
    (bool)Authorization::scopedObjectWhere('host', '`hosts`.`hostID`')
);

// (g) An unrestricted user's statements carry no boundary at all. Without
//     this the fix could be "always filter", which breaks every unscoped
//     server -- the overwhelming majority of installs.
siteScenario($db, ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => []]], [7 => ['*']]);
$stmts = statementsFor($db, 'host');
$all = array_merge($stmts['rows'], $stmts['counts']);
$clean = 0;
foreach ($all as $sql) {
    if (false === strpos($sql, 'siteHostMembers') && false === strpos($sql, '1=0')) {
        $clean++;
    }
}
$t->check(
    'an unrestricted user gets no boundary in any statement',
    count($all) > 0 && $clean === count($all)
);
$t->check(
    'no boundary is the ONLY null scopedObjectWhere() returns',
    null === Authorization::scopedObjectWhere('host', '`hosts`.`hostID`')
);

// (h) An unscoped NODE takes no boundary even for a scoped user.
siteScenario(
    $db,
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => [5]], 'members' => [2, 3]],
    [7 => ['image.view']]
);
$stmts = statementsFor($db, 'image');
$all = array_merge($stmts['rows'], $stmts['counts']);
$clean = 0;
foreach ($all as $sql) {
    if (false === strpos($sql, '1=0') && false === strpos($sql, 'Members')) {
        $clean++;
    }
}
$t->check(
    'an unscoped node takes no boundary in its SQL either',
    count($all) > 0 && $clean === count($all)
);

// (i) The fragment's SHAPE, read off the return value rather than inferred
//     from a substring of a statement. Everything above is satisfied by any
//     statement merely MENTIONING the membership table -- `NOT IN` the same
//     subquery mentions it too, and shows the user exactly the objects they
//     may not see.
siteScenario(
    $db,
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => [5]], 'members' => [2, 3]],
    [7 => ['host.view']]
);
$frag = Authorization::scopedObjectWhere('host', '`hosts`.`hostID`');
$t->check(
    'the fragment restricts TO the membership set, not away from it',
    is_string($frag)
    && 1 === preg_match('/^`hosts`\.`hostID` IN \(SELECT /', $frag)
);

// (j) No valid acting user is DENY, not "unbounded".
//
//     The boundary decision resolves the acting user to an id, and an absent
//     or invalid user resolves to 0 -- which belongs to no site and therefore
//     reaches nothing. That is a real answer, not a missing one, and it is
//     one `if (!$userID)` away from becoming "no boundary applies". Same
//     shape as the null-vs-[] trap one level down, so it is pinned the same
//     way: by identity, on the value, for the case that would otherwise show
//     an unauthenticated caller the whole server.
$savedUser = FogTestHarness::getStatic('Authorization', 'FOGUser');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', null);
}
FogTestHarness::setStatic('Authorization', '_permCache', []);
SiteScope::forgetCaches();
$noUser = Authorization::scopedObjectWhere('host', '`hosts`.`hostID`');
$t->check(
    'no acting user is denied, not unbounded',
    '1=0' === $noUser
);
// The same question of the id-list path, which the pages and the mass-op
// guard use and which this file's SQL assertions cannot reach.
$t->check(
    'no acting user is denied on the id-list path too',
    [] === Authorization::scopedObjectIDs('host')
);
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $savedUser);
}

// (k) The fog-agent prefix declares FOG_MACHINE_REQUEST, and only after the
//     certificate has bound a host.
//
//     (j) is what an agent request would otherwise get: no user, so every
//     scoped read under /agent/v1/ -- the host's own task, its group grants --
//     answers empty on a server with sites configured. The declaration is
//     the cure, and its position is the safety: before the 401 it would be an
//     inference from a missing user, exactly what _hasNoPrincipal's comment
//     rules out. The whole dispatch block is anchored so a rewrite of either
//     half is a visible failure here rather than a silent policy change.
$routeSrc = (string)file_get_contents(__DIR__ . '/../packages/web/src/Router/Route.php');
// Anchored on the constant rather than the literal it holds: the prefix is
// now Route::AGENT_ROUTE_SEGMENT, shared with DatabaseManager::init() so the
// two places that recognize an agent route cannot drift. The closing paren
// picks the dispatch site rather than the declaration above it.
$agentBlockStart = strpos($routeSrc, 'self::AGENT_ROUTE_SEGMENT)');
$agentBlockEnd = false === $agentBlockStart ? false : strpos($routeSrc, '$isunauth = true;', $agentBlockStart);
$agentBlock = (
    false === $agentBlockStart || false === $agentBlockEnd ?
    '' :
    substr($routeSrc, $agentBlockStart, $agentBlockEnd - $agentBlockStart)
);
$agent401 = strpos($agentBlock, 'HTTP_UNAUTHORIZED');
$agentDefine = strpos($agentBlock, "define('FOG_MACHINE_REQUEST', true);");
$t->check(
    'the agent prefix declares FOG_MACHINE_REQUEST',
    false !== $agentDefine
);
$t->check(
    'the agent prefix declares it only after the 401 on an unbound host',
    false !== $agent401 && false !== $agentDefine && $agent401 < $agentDefine
);

/*
 * The null-vs-[] distinction, asserted directly on the filter as well. The
 * end-to-end cases above would both pass if listem() stopped calling the
 * filter AND the filter were also broken; these two cannot.
 */
$payload = ['data' => [['id' => 1], ['id' => 2]], 'recordsTotal' => 2, 'recordsFiltered' => 2];
siteScenario($db, ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => []]], [7 => ['*']]);
Route::$data = $payload;
FogTestHarness::callStatic('Route', '_applySiteScope', ['host']);
$t->check('_applySiteScope leaves a null scope byte-identical', Route::$data === $payload);
siteScenario($db, ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => []]], [7 => ['host.view']]);
Route::$data = $payload;
FogTestHarness::callStatic('Route', '_applySiteScope', ['host']);
$t->check('_applySiteScope empties an [] scope', Route::$data['data'] === []);
Route::$data = [];

/*
 * ===========================================================================
 * 6b. The same boundary on the search() route, which applies it TWICE.
 *
 *     search() calls listem() -- already scoped -- then fires
 *     API_MASSDATA_MAPPING, then scopes again. Deleting that second call
 *     leaves every assertion in section 6 green, because listem() did the
 *     work. The only thing it protects is rows a plugin ADDED to the payload
 *     in between, and hooks receive `data` by reference precisely so they
 *     can. So that is what is asserted: a hook appending an out-of-scope row
 *     must not get it onto the wire.
 *
 *     Worth its own case rather than trusting section 6 to cover it, because
 *     the re-scope reads as redundant -- it is the sort of line a
 *     decomposition drops on the grounds that listem() already did it.
 * ===========================================================================
 */
class NetMassDataInjectHook extends Hook
{
    public $name = 'NetMassDataInjectHook';
    public $description = 'Appends an out-of-scope row to a search result';
    public $active = true;

    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register(
            'API_MASSDATA_MAPPING',
            [$this, 'inject']
        );
    }

    public function inject($arguments)
    {
        $arguments['data']['data'][] = ['id' => 99, 'name' => 'out-of-scope'];
    }
}
new NetMassDataInjectHook();

siteScenario(
    $db,
    [
        'siteCount' => 2,
        'catchAll' => [],
        'userSites' => [7 => [5]],
        'members' => [2, 3],
        'searchHits' => [2, 3, 4]
    ],
    [7 => ['host.view']]
);
Route::$data = [];
Route::search('Host', 'x');
$searched = Route::$data;
Route::getData();
$searchIDs = array_column((array)($searched['data'] ?? []), 'id');
$t->check(
    'search() returned a payload to filter',
    is_array($searched) && isset($searched['data'])
);
$t->check(
    'a hook cannot add an out-of-scope row to a search result',
    !in_array(99, $searchIDs)
);
$t->check(
    'search() still returns the in-scope rows',
    array_values(array_intersect([2, 3], $searchIDs)) === [2, 3]
);

/*
 * ===========================================================================
 * 7. Row side: credential settings matched only on their VALUE.
 * ===========================================================================
 */
$settingRows = [
    'data' => [
        ['id' => 1, 'name' => 'FOG_TFTP_FTP_PASSWORD', 'value' => '', 'description' => ''],
        ['id' => 2, 'name' => 'FOG_TFTP_PXE_KERNEL', 'value' => 'bzImage', 'description' => ''],
    ],
    'recordsTotal' => 2,
    'recordsFiltered' => 2,
];

Route::$data = $settingRows;
FogTestHarness::callStatic('Route', '_applySettingValueScope', ['setting', []]);
$t->check(
    'with NO search term nothing is dropped',
    count(Route::$data['data']) === 2
);

Route::$data = $settingRows;
FogTestHarness::callStatic(
    'Route',
    '_applySettingValueScope',
    ['setting', ['search' => ['value' => 'hunter2']]]
);
$t->check(
    'a credential row matched only on its value is dropped',
    count(Route::$data['data']) === 1
    && 'FOG_TFTP_PXE_KERNEL' === Route::$data['data'][0]['name']
);
$t->check(
    'and the counts are rewritten with it',
    1 === Route::$data['recordsTotal'] && 1 === Route::$data['recordsFiltered']
);

Route::$data = $settingRows;
FogTestHarness::callStatic(
    'Route',
    '_applySettingValueScope',
    ['setting', ['search' => ['value' => 'PASSWORD']]]
);
$t->check(
    'a credential row matched on its KEY is kept',
    count(Route::$data['data']) === 2
);
Route::$data = [];

/*
 * ===========================================================================
 * 7b. The single-purpose read routes carry the boundary too -- when, and only
 *     when, a request is being served.
 *
 *     names(), ids() and unisearch() answer the same question a list answers
 *     and were not scoped at all: a site-scoped user holding <entity>.view --
 *     the same permission as reading the list -- could enumerate every host
 *     id and name on the server in one request. unisearch() is worse again,
 *     because its route permission is null, so any authenticated api user
 *     reaches it.
 *
 *     The boundary is in the WHERE, not over the results, and for ids() that
 *     is the only thing that works: it constrains ROWS, so it does not care
 *     which COLUMN was asked for, and `/host/ids/id=1/name` returns bare
 *     names with no id to filter on.
 *
 *     Off-request it must NOT apply. Both arms are asserted -- the gate is
 *     the point, not either side of it on its own.
 * ===========================================================================
 */
$savedFogUser = FogTestHarness::getStatic('Authorization', 'FOGUser');
$scopedUser2 = (new User())->set('id', 7)->set('name', 'scoped');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $scopedUser2);
}
siteScenario(
    $db,
    ['siteCount' => 2, 'catchAll' => [], 'userSites' => [7 => [5]], 'members' => [2, 3]],
    [7 => ['host.view']]
);
$cliBounded = [];
foreach (['names', 'ids', 'unisearch'] as $route) {
    $before = count($db->log);
    Route::$data = [];
    try {
        if ('names' === $route) {
            Route::names('Host');
        } elseif ('ids' === $route) {
            Route::ids('Host', [], 'name');
        } else {
            Route::unisearch('x');
        }
    } catch (\Throwable $e) {
        // Statements, not payload.
    }
    Route::$data = [];
    foreach (array_slice($db->log, $before) as $sql) {
        if (false !== strpos($sql, '`hosts`')
            && false !== strpos($sql, 'siteHostMembers')
        ) {
            $cliBounded[] = $route;
            break;
        }
    }
}
$t->check(
    'off-request the read routes take NO boundary (daemons keep working)',
    [] === $cliBounded
);

$line = child('scope-sql', '');
$t->check(
    "serving a request, names/ids/unisearch all carry it (got: $line)",
    'NO CGI' === $line
    || 'BOUNDED names,ids,unisearch' === $line
);
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $savedFogUser);
}

/*
 * ===========================================================================
 * 8. The envelope. Not access control, but it is the API contract every one
 *    of the payload assertions above is written against, and nothing pinned
 *    it -- renaming a key left the suite green.
 * ===========================================================================
 */
siteScenario($db, ['siteCount' => 0], [7 => ['*']]);
Route::listem('host', false, true);
$envelope = Route::$data;
Route::getData();
foreach (
    [
        'draw', 'recordsTotal', 'recordsFiltered', 'truncated', 'data',
        '_lang', 'recordsReturned', 'firstUrl', 'prevUrl', 'nextUrl', 'lastUrl',
    ] as $key
) {
    $t->check("the list envelope carries '$key'", array_key_exists($key, $envelope));
}
$t->check("the envelope's _lang is the classname the emitter needs", 'host' === $envelope['_lang']);

/*
 * ===========================================================================
 * 9. The primary key reaches the count queries.
 *
 *    listem() learns the id column while walking the column table and hands
 *    it to complex(), which interpolates it into BOTH count statements --
 *    `SELECT COUNT(<pk>) FROM <table>`. Lose it and the counts become
 *    `COUNT()`, which no server accepts, so recordsTotal and recordsFiltered
 *    stop being answers.
 *
 *    Pinned because that value now crosses a function boundary
 *    (_gridColumns() returns it by reference) and nothing else here can see
 *    it: the column table is identical whether or not it was captured, and
 *    the fake answers a malformed COUNT as readily as a good one. Asserted on
 *    the SQL for that reason -- it is the only place the value becomes
 *    visible.
 * ===========================================================================
 */
$before = count($db->pdo->log);
Route::listem('host', false, true);
Route::getData();
$counts = [];
foreach (array_slice($db->pdo->log, $before) as $sql) {
    if (preg_match('/^\s*SELECT\s+COUNT/i', $sql)) {
        $counts[] = $sql;
    }
}
$t->check('listem() issues its count queries', count($counts) > 0);
// The COUNT ARGUMENT, parsed out -- not a substring search for the column
// name over the whole statement. `hostID` also appears in two JOIN clauses of
// this very query, so `strpos($sql, 'hostID')` is true whether the key
// arrived or not: it matched the mutation it was written to catch.
$args = [];
foreach ($counts as $sql) {
    $args[] = preg_match('/COUNT\(([^)]*)\)/i', $sql, $m) ? trim($m[1], '` ') : null;
}
$t->check(
    'every count query counts the primary key column, not nothing',
    count($args) > 0 && $args === array_fill(0, count($args), 'hostID')
);

/*
 * ===========================================================================
 * 10. ?expand does not cost a query per row.
 *
 *     GH-707 bounded the grid columns with rel()/primeRel(): the plain read
 *     path is flat at four statements from one row to the whole table. The
 *     ?expand branch never used them and resolved a full object per row, and
 *     then every member of every expanded collection one at a time --
 *     measured on the lab at ~20 statements per row, so EXPAND_MAX_ITEMS =
 *     2500 permitted tens of thousands of statements for one response.
 *
 *     Asserted as a MARGINAL cost -- the slope between two page sizes, not an
 *     absolute count. The intercept is fixture-dependent and uninteresting;
 *     the slope is the defect. A per-row load makes it grow with the page, a
 *     primed one does not.
 *
 *     A threshold, not an exact number, because the exact number is a
 *     property of the fake rather than of FOG. It is set well clear of both
 *     sides: the unprimed slope here is ~17 statements/row and the primed one
 *     ~6. Anything reintroducing a per-row load crosses it; ordinary drift
 *     does not.
 * ===========================================================================
 */
$savedQs = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
$_SERVER['QUERY_STRING'] = 'expand=all';
Route::parseExpand();
siteScenario($db, ['siteCount' => 0], [7 => ['*']]);

/**
 * Statements issued listing $rows rows with every relation expanded.
 *
 * @param FogFakeDb $db   the fake
 * @param int       $rows the page size
 *
 * @return int
 */
function expandCost($db, $rows)
{
    $db->pdo->rowCount = $rows;
    $db->pdo->countValue = $rows;
    $before = count($db->log) + count($db->pdo->log);
    try {
        Route::asValue(
            function () {
                Route::listem('host', false, true);
            }
        );
    } catch (\Throwable $e) {
        // Statements, not payload.
    }
    Route::$data = [];
    return count($db->log) + count($db->pdo->log) - $before;
}

$small = expandCost($db, 2);
$large = expandCost($db, 10);
$perRow = ($large - $small) / 8;
$t->check('the expand branch actually ran', Route::expandRequested() && $large > $small);
$t->check(
    sprintf('?expand does not cost a query per row (%.1f statements/row)', $perRow),
    $perRow < 10
);

$db->pdo->rowCount = 4;
$db->pdo->countValue = 4;
if (null === $savedQs) {
    unset($_SERVER['QUERY_STRING']);
} else {
    $_SERVER['QUERY_STRING'] = $savedQs;
}
Route::parseExpand();

$t->finish();
