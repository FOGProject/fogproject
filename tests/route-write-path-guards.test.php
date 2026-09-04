<?php
/**
 * A behavioral net under the API WRITE path's guards.
 *
 * The sibling of route-read-path-guards.test.php, and it exists for the same
 * reason that one did: the guards on this path are pinned by SOURCE GREPS
 * and by tests of their suppliers, and neither of those can see a guard stop
 * working. Three mutations run against the whole suite on working-1.6 at
 * 2c1db9a3e -- each wrapping one guard in `if (false)` -- left 243 tests
 * green:
 *
 *   Route::edit()        the server-owned refusal        243 passed
 *   Route::deletemass()  the lockout guard               243 passed
 *   Route::joining()     the POST class gate             243 passed
 *
 * api-server-owned-fields.test.php looks like it covers the first. It does
 * not: it asserts Route::serverOwnedFields() returns the right list, then
 * greps edit()'s and create()'s bodies for the string
 * `_refuseServerOwned(`. Wrapping the call in `if (false)` leaves that
 * string exactly where it was. Same shape for the second --
 * lockout-guard-is-unscoped.test.php drives
 * Authorization::adminExistsGiven() thoroughly and never mentions Route.
 *
 * WHAT IS COVERED HERE:
 *
 *   - the server-owned refusal, on both verbs, in both directions -- it
 *     refuses a change, and it does NOT refuse a round trip;
 *   - joining()'s POST class gate;
 *   - _requireFound(), the 404 on an id that does not exist;
 *   - deletemass()'s lockout guard, in both directions;
 *   - that the lockout guard runs ONCE, at the top, and not per cascade
 *     table (F-53).
 *
 * The joining gate took two goes. The first check compared status codes,
 * found `host` and `group` both refused with a 400, and passed with the gate
 * disabled. Two things were wrong and both are fixed here: the child never
 * named self::$reqmethod, so every case fell to the switch's `default:` arm
 * and answered 400 having run nothing; and a status code cannot express what
 * this gate does anyway. It is asserted on the STATEMENT COUNT now -- a
 * refused class issues zero, an allowed one reaches the database -- and both
 * mutations (disabling the verb test, disabling the class test) turn it red.
 *
 * All three of the mutations above are covered now.
 *
 * The lockout guard's DEPTH condition is covered too, by section 6. F-53
 * called for a fixture whose cascade produces an intermediate state reading
 * as a lockout while the whole operation does not, and that turned out to be
 * the wrong thing to build: the condition's contract is not "the guard would
 * refuse at depth", it is "the guard does not RUN at depth". That is directly
 * observable in the query log and needs no lockout at all.
 *
 * WHAT IS NOT. Nothing known. Every mutation tried against this file's
 * subjects is red; the ones that were once green are listed above.
 *
 * DB-free. FogTestHarness::fakeDb() logs every statement and can answer any
 * of them, so no MySQL is involved anywhere here.
 *
 * Usage: php tests/route-write-path-guards.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\User;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

// ---------------------------------------------------------------------------
// Child mode.
//
// Every guard here reads the request body, which the code takes from
// php://input -- and the CLI SAPI does not populate php://input at all,
// whatever stdin is pointed at. So each case runs as a child process under a
// CGI PHP, which does.
//
// A child identifies its case through QUERY_STRING (CGI) or --case= (so the
// same file can be run by hand for debugging).
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
 * One body-fed case, in its own process. Prints a single marker line the
 * parent parses. Never asserts -- the parent owns the verdict.
 *
 * @param string $case the case name
 *
 * @return void
 */
function runChild($case)
{
    FogTestHarness::boot('write-path-child');
    // The verb, as FOGBase populates it in production. The harness boot does
    // not run FOGBase's request-init, so self::$reqmethod is NULL here unless
    // it is named -- and joining()'s POST gate reads exactly that. Left NULL,
    // every joining case falls to the switch's `default:` arm and answers
    // 400 without ever reaching the gate, which is why the first attempt at
    // netting it could not tell the gate's removal apart from working code.
    FogTestHarness::setStatic(
        'FOGBase',
        'reqmethod',
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
    );
    $db = FogTestHarness::fakeDb();
    // One synthetic row for every SELECT, so an edit finds its object and
    // reaches the field loop. Without this the call answers 404 before any
    // guard runs, and the case passes for the wrong reason.
    $db->pdo->rowCount = 1;
    // Zero, deliberately. A create asks "does one of these already exist"
    // as a COUNT through the PDO layer, and a non-zero answer refuses it as
    // "Already created" -- which IS a refusal, so the server-owned case
    // would report a pass while never reaching the guard it is about.
    $db->pdo->countValue = 0;
    // An edit has to find its object or it answers 404 before any guard
    // runs, and the case then passes for the wrong reason. FOGController
    // ::load() issues `SELECT users.* ... WHERE uId=:id` through query(),
    // not through the PDO layer, and a `*` select is the one shape
    // FogFakePdo cannot synthesize columns for -- so the row is named here.
    // Column spellings are User::$databaseFields verbatim: `uId`, not
    // `uID`. A wrong key is not an error, it is an empty field.
    // Every column the class declares, so a load leaves no field undefined;
    // FOGBase reads each one by name and a missing key is a warning per
    // field, not an error. Built from $databaseFields rather than typed out,
    // so a schema change cannot leave this fixture describing a table that
    // no longer exists.
    $userRow = [];
    $userVars = FOGCore::getClass('User', '', true);
    foreach ($userVars['databaseFields'] as $friendly => $column) {
        $userRow[$column] = '';
    }
    $userRow[$userVars['databaseFields']['id']] = 1;
    $userRow[$userVars['databaseFields']['name']] = 'probe';
    $userRow[$userVars['databaseFields']['token']] = 'stored-token';
    $db->responder = function ($sql, $params) use ($userRow) {
        // FOGController::load() issues `SELECT users.* ... WHERE uId=:id`
        // through query(), not through the PDO layer, and a `*` select is
        // the one shape FogFakePdo cannot synthesize columns for. Answered
        // as a FLAT row: load() reads it through fetch()->get() with no
        // field, which is a single row rather than a list of them.
        // Only the load BY ID. A lookup by any other column is the
        // uniqueness check a create runs before it inserts, and answering
        // that with this row makes every create fail as "Already created"
        // -- which is a refusal, so the case would look like it passed.
        if (false !== strpos($sql, 'FROM `users`')
            && false !== strpos($sql, ':id')
        ) {
            return $userRow;
        }
        return null;
    };

    $admin = (new User())->set('id', 1)->set('name', 'fog');
    foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
        FogTestHarness::setStatic($cls, 'FOGUser', $admin);
    }
    FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

    // edit:<class>:<id> and create:<class> both write a body straight onto an
    // object. The marker reports how the call ended and, when it was allowed
    // through, which statements it issued -- because "refused" and "silently
    // dropped the field" are the two outcomes that have to be told apart, and
    // only the second one writes.
    if (0 === strpos($case, 'edit:') || 0 === strpos($case, 'create:')) {
        $parts = explode(':', $case);
        try {
            Route::asValue(
                function () use ($parts) {
                    if ('edit' === $parts[0]) {
                        Route::edit($parts[1], (int)$parts[2]);
                        return;
                    }
                    Route::create($parts[1]);
                }
            );
            $wrote = [];
            foreach ($db->log as $sql) {
                if (preg_match('/^\s*(INSERT|UPDATE|REPLACE)/i', $sql)) {
                    $wrote[] = preg_replace('/\s+/', ' ', substr($sql, 0, 200));
                }
            }
            echo 'ALLOWED ' . implode(' ;; ', $wrote) . "\n";
        } catch (\Throwable $e) {
            echo 'REFUSED ' . str_replace("\n", ' ', $e->getMessage()) . "\n";
        }
        return;
    }

    // joining:<class> drives the bulk join route. The marker carries the
    // STATEMENT COUNT as well as the outcome, because the POST class gate
    // and the failures further down the same arm all end in a 400 -- the
    // count is the only thing that tells "refused at the gate" apart from
    // "ran, then failed".
    // missing:<class>:<id> asks for an item that does not exist. The fixture
    // above is deliberately undone first: rowCount 0 and a responder that
    // answers nothing, so _newEntity() hands back an object whose isValid()
    // is false.
    if (0 === strpos($case, 'lockout:')) {
        $parts = explode(':', $case);
        $ids = array_map('intval', explode(',', $parts[1]));
        $users = [1, 2];
        $db->responder = function ($sql, $params) use ($users, $ids) {
            $flat = preg_replace('/\s+/', ' ', trim($sql));
            if (false !== strpos($flat, 'FROM `users` WHERE `uId` IN')) {
                return array_map(function ($u) { return ['uId' => $u]; }, $ids);
            }
            if ($flat === 'SELECT `uID` FROM `users`') {
                return array_map(function ($u) { return ['uID' => $u]; }, $users);
            }
            if (false !== strpos($flat, '`uAuthSource` FROM `users`')) {
                return array_map(function ($u) { return ['uID' => $u, 'uAuthSource' => '']; }, $users);
            }
            if (false !== strpos($flat, '`uAPIOnly` FROM `users`')) {
                return array_map(function ($u) { return ['uID' => $u, 'uAPIOnly' => '0']; }, $users);
            }
            if (false !== strpos($flat, 'FROM `roleUserAssoc`')) {
                return array_map(function ($u) { return ['ruaRoleID' => 1, 'ruaUserID' => $u]; }, $users);
            }
            if (false !== strpos($flat, 'FROM `userGroupMembers`')) {
                return [];
            }
            if (false !== strpos($flat, 'FROM `roleUserGroupAssoc`')) {
                return [];
            }
            if (false !== strpos($flat, 'FROM `rolePermissions`')) {
                return [['rpRoleID' => 1]];
            }
            return null;
        };
        $db->log = [];
        try {
            Route::asValue(
                function () use ($ids) {
                    Route::deletemass('user', ['id' => $ids]);
                }
            );
            echo 'ALLOWED STATEMENTS ' . count($db->log) . "\n";
        } catch (\Throwable $e) {
            echo 'REFUSED ' . str_replace("\n", ' ', $e->getMessage())
                . ' STATEMENTS ' . count($db->log) . "\n";
        }
        return;
    }

    // depth:<id> deletes ONE of two administrators, which is a delete the
    // outer guard must allow. What it is for is the cascade underneath: the
    // user arm re-enters deletemass() for roleuserassociation and
    // usergroupmember, and BOTH of those are classes
    // assertAdminRemainsAfterDelete() has a real arm for. If the guard were
    // to run at depth, it would ask its question again about a half-applied
    // state -- and the only way to see whether it did is that its
    // association arm reads roleUserAssoc with a WHERE on it, through
    // Authorization::_remaining(). Nothing else in this call does.
    //
    // So the marker carries that count. Zero means the guard ran once, at
    // the top, which is the contract.
    if (0 === strpos($case, 'depth:')) {
        $parts = explode(':', $case);
        $ids = array_map('intval', explode(',', $parts[1]));
        $users = [1, 2];
        // The rua row ids the cascade will find for the user being deleted.
        // They have to be non-empty or assertAdminRemainsAfterDelete()
        // returns on `count($ids) < 1` before doing anything, and the
        // mutation would then be invisible for the most boring reason there
        // is.
        $ruaIDs = [11];
        $db->responder = function ($sql, $params) use ($users, $ids, $ruaIDs) {
            $flat = preg_replace('/\s+/', ' ', trim($sql));
            if (false !== strpos($flat, 'FROM `users` WHERE `uId` IN')) {
                return array_map(function ($u) { return ['uId' => $u]; }, $ids);
            }
            if ($flat === 'SELECT `uID` FROM `users`') {
                return array_map(function ($u) { return ['uID' => $u]; }, $users);
            }
            if (false !== strpos($flat, '`uAuthSource` FROM `users`')) {
                return array_map(function ($u) { return ['uID' => $u, 'uAuthSource' => '']; }, $users);
            }
            if (false !== strpos($flat, '`uAPIOnly` FROM `users`')) {
                return array_map(function ($u) { return ['uID' => $u, 'uAPIOnly' => '0']; }, $users);
            }
            // The cascade's own lookup, and _remaining()'s: both are
            // roleUserAssoc with a WHERE. Answered with row ids when the
            // projection is `ruaID`, with the membership pair when it is
            // the wholesale read adminExistsGiven() does.
            if (false !== strpos($flat, 'FROM `roleUserAssoc`')) {
                if (false !== strpos($flat, 'SELECT `ruaID`')) {
                    return array_map(
                        function ($r) { return ['ruaID' => $r]; },
                        $ruaIDs
                    );
                }
                if (false !== strpos($flat, 'SELECT `ruaUserID`')) {
                    return array_map(
                        function ($u) { return ['ruaUserID' => $u]; },
                        $ids
                    );
                }
                if (false !== strpos($flat, 'SELECT `ruaRoleID` FROM')) {
                    return [['ruaRoleID' => 1]];
                }
                return array_map(
                    function ($u) { return ['ruaRoleID' => 1, 'ruaUserID' => $u]; },
                    $users
                );
            }
            if (false !== strpos($flat, 'FROM `userGroupMembers`')) {
                return [];
            }
            if (false !== strpos($flat, 'FROM `roleUserGroupAssoc`')) {
                return [];
            }
            if (false !== strpos($flat, 'FROM `rolePermissions`')) {
                return [['rpRoleID' => 1]];
            }
            return null;
        };
        // An OFFSET rather than `$db->log = []`, unlike the cases above.
        // They only ever count the log; this one iterates it, and after an
        // assignment of [] phpstan knows the array is empty and calls the
        // loop dead. Taking the mark instead is also the more honest thing
        // to do -- the harness's own record is left intact.
        $logFrom = count($db->log);
        $outcome = 'ALLOWED';
        try {
            Route::asValue(
                function () use ($ids) {
                    Route::deletemass('user', ['id' => $ids]);
                }
            );
        } catch (\Throwable $e) {
            $outcome = 'REFUSED ' . str_replace("\n", ' ', $e->getMessage());
        }
        // Whether the guard ran AT ALL on this call, so the check below is
        // not one-sided: adminExistsGiven(), localAdminExists() and
        // interactiveAdminExists() are the only things in the tree that read
        // the users table bare, and all three are reached only through the
        // guard.
        $userReads = 0;
        $scoped = 0;
        $issued = array_slice($db->log, $logFrom);
        foreach ($issued as $q) {
            if ('SELECT `uID` FROM `users`' === preg_replace('/\s+/', ' ', trim($q))) {
                $userReads++;
            }
        }
        foreach ($issued as $q) {
            $flat = preg_replace('/\s+/', ' ', trim($q));
            // Not just "reads roleUserAssoc with a WHERE" -- the cascade
            // itself does that, looking up the rows it is about to delete,
            // and it projects `ruaID`. _remaining() is the one that asks
            // roleUserAssoc WHICH USER or WHICH ROLE a set of rows belongs
            // to, and it is the only caller here that projects `ruaUserID`
            // or `ruaRoleID` with a WHERE on it. adminExistsGiven()'s own
            // membership read selects both columns and carries no WHERE.
            if (false !== strpos($flat, 'FROM `roleUserAssoc`')
                && false !== strpos($flat, 'WHERE')
                && (false !== strpos($flat, 'SELECT `ruaUserID` FROM')
                    || false !== strpos($flat, 'SELECT `ruaRoleID` FROM'))
            ) {
                $scoped++;
            }
        }
        echo $outcome . ' USERREADS ' . ($userReads > 0 ? 'yes' : 'no')
            . ' RUAREADS ' . $scoped . "\n";
        return;
    }

    if (0 === strpos($case, 'missing:')) {
        $parts = explode(':', $case);
        $db->pdo->rowCount = 0;
        $db->responder = function ($sql, $params) {
            return null;
        };
        $db->log = [];
        try {
            Route::asValue(
                function () use ($parts) {
                    Route::edit($parts[1], (int)$parts[2]);
                }
            );
            echo 'ALLOWED STATEMENTS ' . count($db->log) . "\n";
        } catch (\Throwable $e) {
            echo 'REFUSED ' . str_replace("\n", ' ', $e->getMessage())
                . ' STATEMENTS ' . count($db->log) . "\n";
        }
        return;
    }

    if (0 === strpos($case, 'joining:')) {
        $parts = explode(':', $case);
        // Route-only. Booting the harness issues a handful of statements of
        // its own, and a count that includes them cannot express "the gate
        // fired before anything ran", which is the whole assertion.
        $db->log = [];
        try {
            Route::asValue(
                function () use ($parts) {
                    Route::joining($parts[1]);
                }
            );
            echo 'ALLOWED STATEMENTS ' . count($db->log) . "\n";
        } catch (\Throwable $e) {
            echo 'REFUSED ' . str_replace("\n", ' ', $e->getMessage())
                . ' STATEMENTS ' . count($db->log) . "\n";
        }
        return;
    }

    echo "UNKNOWN CASE\n";
}

/**
 * The php-cgi binary, or false when there is not one.
 *
 * @return string|false
 */
function cgiBinary()
{
    $found = trim((string)@shell_exec('command -v php-cgi 2>/dev/null'));
    return ('' !== $found && is_executable($found)) ? $found : false;
}

/**
 * Run one body-fed case in a child process.
 *
 * @param string $case   the case name
 * @param string $body   the raw request body
 * @param string $method the request method
 *
 * @return string the child's marker line
 */
function child($case, $body, $method = 'PUT')
{
    $bin = cgiBinary();
    if (false === $bin) {
        return 'NO CGI';
    }
    $env = [
        'REDIRECT_STATUS' => '200',
        'SCRIPT_FILENAME' => __FILE__,
        'REQUEST_METHOD' => $method,
        'CONTENT_TYPE' => 'application/json',
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

    $payload = $out;
    $split = preg_split('/\r?\n\r?\n/', $out, 2);
    if (count($split) === 2) {
        $payload = $split[1];
    }
    foreach (preg_split('/\r?\n/', $payload) as $candidate) {
        $candidate = trim($candidate);
        if ('' === $candidate) {
            continue;
        }
        if (preg_match('/^(REFUSED|ALLOWED|UNKNOWN CASE)/', $candidate)) {
            return $candidate;
        }
    }
    // Surface the child's own output rather than a bare mismatch; a bootstrap
    // failure here otherwise reads as a guard failure.
    return 'NO MARKER: ' . trim(str_replace("\n", ' | ', $payload . ' ' . $err));
}

// ---------------------------------------------------------------------------
// Parent.
// ---------------------------------------------------------------------------
FogTestHarness::boot('write-path');
FogTestHarness::fakeDb();
$t = new FogChecks();

if (false === cgiBinary()) {
    echo "SKIP: no php-cgi binary; the request-body guards cannot be driven\n";
    exit(0);
}

/*
 * ===========================================================================
 * 1. A server-maintained field cannot be written, on either verb.
 *
 *    Route::serverOwnedFields() is the list, and
 *    api-server-owned-fields.test.php pins its CONTENTS. What is pinned here
 *    is that edit() and create() actually consult it, and that they REFUSE
 *    rather than dropping the field -- a silently ignored write leaves a
 *    client believing state it never achieved.
 *
 *    Asserted on the MESSAGE, not on "was it refused". With the guard
 *    disabled the call is still refused, because writing a server-owned
 *    column into this fixture fails on its own further down and answers
 *    500. A check reading only "refused" therefore passes with the guard
 *    gone; the message is the only part that names the guard.
 * ===========================================================================
 */
$refusal = 'is maintained by the server and cannot be set';

$line = child('edit:user:1', json_encode(['token' => 'forged']));
$t->check(
    "edit(): writing a server-owned field is refused BY THE GUARD "
    . "(got: $line)",
    false !== strpos($line, $refusal)
);
$t->check(
    "edit(): the refusal names the field (got: $line)",
    false !== strpos($line, 'token')
);

$line = child('create:user', json_encode(['token' => 'forged']), 'POST');
$t->check(
    "create(): writing a server-owned field is refused BY THE GUARD "
    . "(got: $line)",
    false !== strpos($line, $refusal)
);
$t->check(
    "create(): the refusal names the field (got: $line)",
    false !== strpos($line, 'token')
);

/*
 * ===========================================================================
 * 2. It refuses a CHANGE, not a round trip.
 *
 *    Reading an object and PUTting the whole thing back is ordinary REST,
 *    and a single-entity GET returns these fields, so a body carrying the
 *    value already stored is asking for nothing. _refuseServerOwned()
 *    compares before it refuses for exactly that reason, and nothing
 *    asserted the comparison -- a guard tightened to refuse on presence
 *    alone would break every round-tripping client with the suite green.
 *
 *    The stored token on the fixture row is 'stored-token'; sending it back
 *    must not produce the message above.
 * ===========================================================================
 */
$line = child('edit:user:1', json_encode(['token' => 'stored-token']));
$t->check(
    "edit(): re-sending the STORED value is not refused as a write "
    . "(got: $line)",
    false === strpos($line, $refusal)
);

/*
 * ===========================================================================
 * 3. joining() refuses POST for every class but `group`.
 *
 *    The bulk POST arm creates rows by NAME and attaches hosts to them. It
 *    is written for groups and nothing else -- _joiningCreate() sets only
 *    `name` and then calls addHost(), which no other class in the switch
 *    would do anything sensible with -- so the gate at the top of joining()
 *    is what keeps the arm from running for a class it was never written
 *    for.
 *
 *    Asserted on the STATEMENT COUNT, not on the status code. Both classes
 *    end in a refusal on this fixture, and the earlier attempt at netting
 *    this guard compared status codes, found them identical, and passed
 *    with the gate disabled. The gate's whole observable effect is that
 *    NOTHING runs: a refused class issues zero statements, an allowed one
 *    reaches the database. That is the difference the mutation moves.
 *
 *    Also why the child now names self::$reqmethod: left NULL, every case
 *    here falls to the switch's `default:` arm and answers 400 having run
 *    nothing, so a disabled gate is indistinguishable from a working one.
 * ===========================================================================
 */
$joinBody = json_encode(['names' => ['probe-group'], 'hosts' => []]);

$line = child('joining:host', $joinBody, 'POST');
$t->check(
    "joining(): POST to a non-group class is refused BEFORE the arm runs "
    . "(got: $line)",
    false !== strpos($line, 'REFUSED')
    && false !== strpos($line, 'STATEMENTS 0')
);

$line = child('joining:group', $joinBody, 'POST');
$t->check(
    "joining(): POST to `group` still reaches the create arm -- otherwise "
    . "the check above passes because nothing works (got: $line)",
    false === strpos($line, 'STATEMENTS 0')
);

/*
 * ===========================================================================
 * 4. An id that does not exist is a 404, not a write.
 *
 *    _requireFound() is one guard with seven call sites -- indiv(), edit(),
 *    task() and four of cancel()'s arms -- and until it was one function it
 *    was seven copies of the same five lines, none of them netted. Disabling
 *    it left the whole suite green.
 *
 *    It matters on edit() in particular: past the guard, the object is an
 *    empty one with id 0, and the field loop would happily populate it and
 *    save. A PUT to a nonexistent id would then CREATE a row and report it
 *    under the id the caller asked for, which is not the id it got.
 * ===========================================================================
 */
$line = child('missing:user:987654', json_encode(['name' => 'ghost']));
$t->check(
    "edit(): a PUT to an id that does not exist answers 404 (got: $line)",
    false !== strpos($line, 'REFUSED')
    && false !== strpos($line, '404')
);

/*
 * ===========================================================================
 * 5. deletemass() asks whether the install would still have an administrator.
 *
 *    The last of the three mutations recorded above, and the one that took a
 *    fixture rather than a fix: Authorization::assertAdminRemainsAfterDelete()
 *    reads the whole RBAC picture -- users, roleUserAssoc, userGroupMembers,
 *    roleUserGroupAssoc, rolePermissions -- so netting the CALL SITE means
 *    answering all of it. lockout-guard-is-unscoped.test.php drives the
 *    supplier directly and never mentions Route; nothing asserted that
 *    deletemass() consults it, and disabling the call left 243 tests green.
 *
 *    The fixture is two users, both administrators through role 1, which
 *    holds '*'. Deleting one of them is fine and must go through; deleting
 *    both is the lockout and must not. Both directions matter for the usual
 *    reason -- a guard hardwired to refuse everything passes a one-sided
 *    check while making the delete endpoint useless.
 *
 *    Asserted on 'able to administer FOG', which is the wording all three
 *    refusals share. Which of them fires is a property of the fixture (this
 *    one trips the local-admin arm first), not of the call site being netted.
 * ===========================================================================
 */
$line = child('lockout:1,2', '{}', 'DELETE');
$t->check(
    "deletemass(): deleting every administrator is refused (got: $line)",
    false !== strpos($line, 'REFUSED')
    && false !== strpos($line, 'able to administer FOG')
);

$line = child('lockout:1', '{}', 'DELETE');
$t->check(
    "deletemass(): deleting one of two administrators is NOT refused -- "
    . "otherwise the check above passes on a guard that refuses everything "
    . "(got: $line)",
    false === strpos($line, 'able to administer FOG')
);

/*
 * ===========================================================================
 * 6. ...and it asks ONCE, at the top, not again inside the cascade.
 *
 *    `if (self::$_deleteDepth < 1)`. The cascade re-enters deletemass() for
 *    each dependent table, and two of the four a user delete cascades into --
 *    roleuserassociation and usergroupmember -- are classes
 *    assertAdminRemainsAfterDelete() has a real arm for. Running it there
 *    would judge a half-applied state that is part of one operation already
 *    judged as a whole, and refuse deletes that are fine.
 *
 *    Recorded as F-53 in docs/refactor-facts.md as the one mutation section 5
 *    did not cover: `if (true || self::$_deleteDepth < 1)` left every check
 *    green, because the fixture there cascades into nothing.
 *
 *    The signal is which QUERIES the call makes, because the outcome cannot
 *    tell you -- the delete succeeds either way on this fixture; what changes
 *    is how many times the install was interrogated about its administrators.
 *
 *      USERREADS  the bare `SELECT `uID` FROM `users`` that
 *                 adminExistsGiven(), localAdminExists() and
 *                 interactiveAdminExists() issue, and nothing else does.
 *                 Proves the guard ran at the top of THIS call, so the
 *                 RUAREADS assertion cannot pass by the guard being gone.
 *      RUAREADS   roleUserAssoc read with a WHERE and projecting ruaUserID
 *                 or ruaRoleID -- the shape only Authorization::_remaining()
 *                 produces, and _remaining() is reachable only from the
 *                 guard's ASSOCIATION arms, which only a call at depth can
 *                 reach. The cascade's own lookup of the rows it is about to
 *                 delete projects ruaID and is deliberately not counted.
 *
 *    Deleting one of two administrators, so the whole operation is allowed
 *    and the only question left is how often it was asked.
 * ===========================================================================
 */
$line = child('depth:1', '{}', 'DELETE');
$t->check(
    "deletemass(): the lockout guard runs at the top of the delete "
    . "(got: $line)",
    false !== strpos($line, 'USERREADS yes')
);
$t->check(
    "deletemass(): the lockout guard does NOT run again inside the cascade "
    . "(got: $line)",
    false !== strpos($line, 'RUAREADS 0')
);

$t->finish();
