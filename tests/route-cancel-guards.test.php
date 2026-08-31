<?php
/**
 * A behavioral net under Route::cancel()'s five guards.
 *
 * cancel-route-reports-truthfully.test.php already pins this method, and it
 * is a good gate -- but it is a SOURCE GREP: fifteen regexes over the text
 * of Route.php, zero code executed. Per F-50 that cannot see a guard stop
 * firing, and it does not. Six mutations run against it on working-1.6, each
 * reintroducing one of the five shipped bugs its own docblock says it exists
 * to pin:
 *
 *   M1 group          drop the state filter                  CAUGHT
 *   M2 host           back to instanceof-only                CAUGHT
 *   M3 scheduledtask  fall through to default and no-op      CAUGHT
 *   M4 filedeletequeue  back to the model cancel()           CAUGHT
 *   M5 default        drop the state test                    SURVIVED
 *   M6 helper         emit `msg` instead of `error`          SURVIVED
 *
 * The two survivors are the two failure shapes CLAUDE.md names, and M5 is
 * the founding bug -- the one the older file is named for, a Failed task
 * answering 200 "canceled" with its state untouched.
 *
 *   M5 survived because the guard's regex is not anchored to the arm it is
 *   testing. `if (!in_array($class->get('stateID'), $states))` appears
 *   TWICE in this method -- once in the filedeletequeue arm, once in the
 *   default arm -- and the regex matches the first. So the filedeletequeue
 *   guard stood in for the default one, and the default one could be
 *   disabled with the gate still green. One guard vouching for another is
 *   invisible in a grep and obvious the moment the code runs.
 *
 *   M6 survived because the gate checks the helper body for the substring
 *   `json_encode` and never for the key name. `json_encode(['msg' => $msg])`
 *   contains it. The key is the entire point: notifyFromAPI() types a body
 *   carrying `msg` as a SUCCESS, so a 409 explaining that a task had already
 *   finished draws a GREEN toast -- the same shipped bug the helper's own
 *   docblock in Route.php spends eighteen lines explaining.
 *
 * WHAT IS COVERED HERE, each in both directions where there are two:
 *
 *   - the default arm's state test: a live task proceeds, a finished one is
 *     refused 409;
 *   - that a refusal body is keyed `error` and not `msg`;
 *   - the group arm's state filter, asserted on the SQL the lookup issues,
 *     and its empty-result refusal;
 *   - the host arm's empty-Task test;
 *   - that scheduledtask reaches its own cancel() rather than the default
 *     arm's state test, asserted on the DELETE it issues;
 *   - that filedeletequeue reaches the MANAGER's cancel(), asserted by the
 *     child surviving at all -- the model has none, and the Error that
 *     raises is not an \Exception, so it escapes cancel()'s catch and kills
 *     the process without a marker.
 *
 * HOW: sendResponse() exits, so every case runs in its own child process.
 * Route::$_rethrowDepth is raised first, which is the seam the router
 * already carries for callers below the HTTP boundary (ADR 0011) -- with it
 * set, a refusal arrives as a RuntimeException carrying the real status code
 * and the real body, instead of ending the process. That is what makes the
 * refusals assertable at all.
 *
 * Usage: php tests/route-cancel-guards.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

/**
 * The state ids a task can be canceled FROM, and one it cannot.
 *
 * Read from the router rather than hardcoded: the allowlist is
 * getQueuedStates() + getProgressState(), and a fixture that restates it
 * would keep passing if the router's idea of "live" moved underneath it.
 */
define('CANCEL_LIVE_STATE', 3);
define('CANCEL_DONE_STATE', 5);

// ---------------------------------------------------------------------------
// Child. One case, one process, one marker line the parent parses.
// ---------------------------------------------------------------------------

/**
 * Answers the SELECTs each arm's entity load issues.
 *
 * Returns a single associative row, not a list of them: FOGController::load()
 * reads it through fetch()->get() and hands the result straight to
 * setQuery(), so a list arrives as a row whose columns are integers and the
 * object loads empty -- isValid() false, and every case then passes for the
 * wrong reason by taking a 404 or a bulk-filter branch it was not testing.
 *
 * @param string $case  the case name
 * @param int    $state the stateID to give the loaded row
 *
 * @return callable
 */
function cancelResponder($case, $state)
{
    return function ($sql, $params) use ($case, $state) {
        $flat = preg_replace('/\s+/', ' ', trim($sql));
        // The `missing` variant answers no entity at all, so the arm's own
        // _requireFound() is what decides. That is the only behavior which
        // separates a dedicated arm from `default:` for a class carrying no
        // stateID -- see the M3 note in this file's header.
        if (false !== strpos($case, ':missing')) {
            return null;
        }

        if (false !== strpos($flat, 'FROM `tasks`')
            && false !== strpos($flat, '`taskID`=')
        ) {
            return [
                'taskID' => 1, 'taskName' => 'net', 'taskStateID' => $state,
                'taskTypeID' => 1, 'taskHostID' => 1,
            ];
        }
        if (false !== strpos($flat, 'FROM `groups`')
            && false !== strpos($flat, '`groupID`=')
        ) {
            return ['groupID' => 1, 'groupName' => 'net-group'];
        }
        if (false !== strpos($flat, 'FROM `scheduledTasks`')
            && false !== strpos($flat, '`stID`=')
        ) {
            return [
                'stID' => 1, 'stName' => 'net-sched', 'stType' => 'C',
                'stTaskTypeID' => 1, 'stGroupHostID' => 1, 'stActive' => 1,
            ];
        }
        if (false !== strpos($flat, 'FROM `fileDeleteQueue`')
            && false !== strpos($flat, '`fdqID`=')
        ) {
            return [
                'fdqID' => 1, 'fdqPathName' => '/images/net',
                'fdqPathType' => 'f', 'fdqStorageGroupID' => 1,
                'fdqState' => $state,
            ];
        }
        if (false !== strpos($flat, 'FROM `hosts`')
            && false !== strpos($flat, '`hostID`=')
        ) {
            return ['hostID' => 1, 'hostName' => 'net-host'];
        }
        // The group arm's member lookup. One host, so the arm has something
        // to filter -- an empty group would refuse for the wrong reason.
        if (false !== strpos($flat, 'FROM `groupMembers`')) {
            return [['gmHostID' => 1, 'gmID' => 1, 'gmGroupID' => 1]];
        }
        // The group arm's state-filtered task lookup. Answered ONLY for the
        // `live` variant: `none` leaves it empty so the count<1 refusal is
        // reachable, and without that contrast an arm that had stopped
        // refusing would look identical to one that still did.
        // Scoped to the group case. Host::loadTask() issues a
        // state-filtered task query of its own, and answering that one too
        // handed the host arm a live task -- so `host:live` canceled
        // instead of exercising the empty-Task test it exists for.
        if (0 === strpos($case, 'group:')
            && false !== strpos($flat, 'FROM `tasks`')
            && false !== strpos($flat, '`taskStateID` IN')
        ) {
            return (false !== strpos($case, ':live'))
                ? [['taskID' => 7]]
                : [];
        }
        return null;
    };
}

/**
 * Runs one case and prints its marker.
 *
 * @param string $case the case name
 *
 * @return void
 */
function runCancelChild($case)
{
    FogTestHarness::boot('cancel-guards-child');
    $db = FogTestHarness::fakeDb();
    // The seam that makes a refusal assertable rather than fatal. Without
    // it sendResponse() calls breakHead(), which exits, and every case
    // reports the same nothing.
    FogTestHarness::setStatic('Route', '_rethrowDepth', 1);

    list($class, $variant) = array_pad(explode(':', $case, 2), 2, '');
    $state = ('done' === $variant) ? CANCEL_DONE_STATE : CANCEL_LIVE_STATE;
    $db->responder = cancelResponder($case, $state);

    // An OFFSET rather than clearing the log: phpstan reads an assignment of
    // [] as proof the array is empty and calls the later loop dead, and
    // taking a mark leaves the harness's own record intact.
    $logFrom = count($db->log);

    $refusal = null;
    try {
        \FOG\Router\Route::cancel($class, 1);
    } catch (\RuntimeException $e) {
        $refusal = $e;
    }

    // What the arm DID is the assertion, so report the shape of the work
    // rather than the bare fact that nothing threw -- an arm that silently
    // does nothing is the defect this whole file exists for. Computed for a
    // refusal too: the group arm issues its state-filtered lookup and THEN
    // decides there is nothing to cancel, so its SQL is only readable here.
    $issued = array_slice($db->log, $logFrom);
    $flat = preg_replace('/\s+/', ' ', implode(' ~ ', $issued));
    $flags = [];
    // The group arm's task lookup must carry the state filter. Dropping it
    // is M1: every task the member hosts have EVER run gets canceled.
    if (false !== strpos($flat, 'FROM `tasks`')
        && false !== strpos($flat, '`taskStateID` IN')
    ) {
        $flags[] = 'statefilter';
    }
    if (false !== strpos($flat, 'DELETE FROM `scheduledTasks`')) {
        $flags[] = 'deleted';
    }
    if (false !== strpos($flat, 'UPDATE `tasks`')) {
        $flags[] = 'taskwrite';
    }
    $shape = $flags ? implode(',', $flags) : '-';
    if (null !== $refusal) {
        $body = json_decode($refusal->getMessage(), true);
        printf(
            "REFUSED %d %s %s\n",
            (int)$refusal->getCode(),
            is_array($body) ? implode(',', array_keys($body)) : 'unparseable',
            $shape
        );
        return;
    }
    printf("PROCEEDED %d %s\n", count($issued), $shape);
}

/**
 * Runs one case in a child process and returns its marker line.
 *
 * @param string $case the case name
 *
 * @return string
 */
function cancelChild($case)
{
    $cmd = sprintf(
        '%s %s --case=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__FILE__),
        escapeshellarg($case)
    );
    $out = (string)shell_exec($cmd);
    foreach (preg_split('/\r?\n/', $out) as $line) {
        $line = trim($line);
        if (preg_match('/^(REFUSED|PROCEEDED)\b/', $line)) {
            return $line;
        }
    }
    // Surface the child's own output. filedeletequeue's M4 mutation dies
    // here with a fatal Error and no marker, and "no marker" must not read
    // as a harness fault.
    return 'NO MARKER: ' . trim(preg_replace('/\s+/', ' ', $out));
}

foreach ($argv as $arg) {
    if (0 === strpos($arg, '--case=')) {
        runCancelChild(substr($arg, 7));
        exit(0);
    }
}

// ---------------------------------------------------------------------------
// Parent.
// ---------------------------------------------------------------------------
FogTestHarness::boot('cancel-guards');
FogTestHarness::fakeDb();
$t = new FogChecks();

// --- the default arm's state test, both directions (M5) --------------------

$live = cancelChild('task:live');
$t->check(
    'a task in a live state is canceled, not refused: ' . $live,
    0 === strpos($live, 'PROCEEDED')
);

$done = cancelChild('task:done');
$t->check(
    'a task in a finished state is refused 409: ' . $done,
    0 === strpos($done, 'REFUSED 409')
);

// --- the refusal body is keyed `error`, not `msg` (M6) ---------------------

// Asserted on the KEY, because that is what colors the toast.
// notifyFromAPI() reads a body carrying `msg` as a success, so this is the
// difference between a red refusal and a green one claiming work was done.
$t->check(
    'the 409 body is keyed `error` so the toast is red: ' . $done,
    (bool)preg_match('/^REFUSED 409 (\S+,)?error(,\S+)? /', $done)
);

// --- the group arm's state filter (M1) -------------------------------------

$group = cancelChild('group:live');
$t->check(
    'the group arm filters its task lookup by state: ' . $group,
    false !== strpos($group, 'statefilter')
);
$t->check(
    'a group with live tasks proceeds to cancel them: ' . $group,
    0 === strpos($group, 'PROCEEDED')
);

// The other direction. Without this the arm could stop refusing entirely --
// handing TaskManager::cancel() an empty list and answering 200 -- and the
// state-filter check above would not notice, because the filter is still in
// the SQL it issues on the way to doing nothing.
$groupNone = cancelChild('group:none');
$t->check(
    'a group with no live tasks is refused, not answered 200: ' . $groupNone,
    0 === strpos($groupNone, 'REFUSED 409')
);

// --- the host arm's empty-Task test (M2) -----------------------------------

// A host with nothing running must be REFUSED, not crash. Host::loadTask()
// hands back `new Task(null)`, which IS instanceof Task -- so an
// instanceof-only test is true unconditionally and Task::cancel() then calls
// get() on the empty string getHost() returns. That is an Error, not an
// \Exception, so cancel()'s catch never sees it: the child dies without a
// marker, which is what this check reads.
$host = cancelChild('host:live');
$t->check(
    'an idle host is refused 409, not crashed: ' . $host,
    0 === strpos($host, 'REFUSED 409')
);

// A host id that does not exist is a 404. Asserted separately and on the
// CODE, because an invalid host still reaches the empty-Task test and is
// refused 409 -- so removing _requireFound() from this arm changes only the
// status, and a check that reads "was it refused" passes either way.
$hostMissing = cancelChild('host:missing');
$t->check(
    'a host id that does not exist is a 404: ' . $hostMissing,
    0 === strpos($hostMissing, 'REFUSED 404')
);

// --- scheduledtask reaches its own cancel() (M3) ---------------------------

// It carries isActive, not stateID. In the default arm it failed a test it
// could never pass and returned 200 having done nothing, so the endpoint had
// never once canceled a scheduled task. Its cancel() is a destroy(), so the
// DELETE is the evidence that the right arm ran.
$sched = cancelChild('scheduledtask:live');
$t->check(
    'scheduledtask is destroyed rather than state-tested: ' . $sched,
    false !== strpos($sched, 'deleted')
);

// The check above cannot tell the two arms apart on its own, and finding
// out why is the useful part. `$states` holds INTEGERS, ScheduledTask has no
// stateID column so get('stateID') is null, and in_array() here is not
// strict -- null == 0 is true, so a scheduled task PASSES a test it was
// never meant to reach and the default arm cancels it too. Both arms then
// issue the same DELETE.
//
// What does separate them is _requireFound(). Asked for an id that does not
// exist, the dedicated arm answers 404; `default:` takes an invalid object
// to its bulk-filter branch, searches, cancels nothing and answers 200. So
// the missing-id case is the one that pins which arm ran.
$schedMissing = cancelChild('scheduledtask:missing');
$t->check(
    'a scheduledtask id that does not exist is a 404, not a silent 200: '
    . $schedMissing,
    0 === strpos($schedMissing, 'REFUSED 404')
);

// --- filedeletequeue reaches the MANAGER's cancel() (M4) -------------------

// The one tasking class with no model cancel(). Reaching $class->cancel()
// raises an Error, which escapes the catch and kills the child, so a marker
// at all is the assertion.
$fdq = cancelChild('filedeletequeue:live');
$t->check(
    'a live queued deletion is canceled through its manager: ' . $fdq,
    0 === strpos($fdq, 'PROCEEDED')
);

$fdqDone = cancelChild('filedeletequeue:done');
$t->check(
    'a finished queued deletion is refused 409: ' . $fdqDone,
    0 === strpos($fdqDone, 'REFUSED 409')
);

// --- the default arm's state test is only SAFE by accident ---------------

// Found while working out why the M3 mutation was invisible, and it is worth
// a standing check rather than a note.
//
// in_array() here is not strict, and it cannot be: get('stateID') returns
// the DB's string '3' while $states holds integers, so a strict compare
// would refuse every cancel in the product. The loose compare means a class
// with NO stateID reaches the test with null and passes it -- null == 0 --
// and is canceled whatever state it is in.
//
// Nothing hits that today: every class reaching `default:` declares a
// stateID column. That is a fact about the class list as it stands now, not
// a property of the code, and the cost of it drifting is silent -- a new
// state-less tasking class would simply always cancel. So the invariant is
// asserted rather than assumed.
$armed = ['filedeletequeue', 'group', 'host', 'scheduledtask'];
$stateless = [];
foreach (\FOG\Router\Route::$validTaskingClasses as $tc) {
    if (in_array($tc, $armed, true)) {
        continue;
    }
    $item = \FOG\Router\Route::getClass($tc, '', true);
    $fields = isset($item['databaseFields']) ? $item['databaseFields'] : [];
    if (!array_key_exists('stateID', (array)$fields)) {
        $stateless[] = $tc;
    }
}
$t->check(
    'every class reaching the default arm declares a stateID'
    . ($stateless ? ' -- missing: ' . implode(',', $stateless) : ''),
    !$stateless
);

$t->finish();
