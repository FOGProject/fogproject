<?php
/**
 * A multicast session must be created in a state the daemon can select.
 *
 * THE FAILURE. FOGMulticastManager only ever starts a udp-sender for a
 * session it finds, and it finds them with
 * `Route::getList('multicastsession', ['stateID' => $queuedStates, ...])`,
 * where `$queuedStates` is `TaskState::getQueuedStates()` plus the progress
 * state -- `range(0, 2)` plus 3. The 0 in that range is not padding: it was
 * the value every session was created with, and it is what "created, not yet
 * started" meant.
 *
 * Schema step 386 converted `multicastSessions`.`msState` from 0 to NULL
 * along with eight other columns, reading the 0 as the "no reference"
 * sentinel it is in the other eight. A NULL matches no IN list. From that
 * commit (650628b1b, 2026-08-29) until the fix this test guards, every
 * multicast session was invisible to its own daemon: the per-host tasks were
 * queued and shown, the machines PXE booted and waited, and no sender was
 * ever started. The Active Multicast Tasks grid could not show the row
 * either -- `getActiveMulticastTasks()` LEFT JOINs msState to taskStates and
 * requires `tsName IN ('queued','checked in','in-progress')`, which a NULL
 * fails -- so there was no way to cancel it from the UI. Reported in forum
 * topic 18238.
 *
 * WHAT THIS PINS is the invariant, not one call site: every place that
 * creates a MulticastSession must name a state, and it must be one the
 * daemon's own selection accepts. Written as a scan over all three creators
 * because a fourth would otherwise reintroduce this silently.
 *
 * Proved against a live database by
 * background_scripts/probe_multicast_session_pickup.php, which creates a
 * real group multicast task and reports whether the daemon's selection
 * returns the session. On an unpatched tree it answers NO.
 *
 * Usage: php tests/multicast-session-is-queued-not-stateless.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function check($what, $ok, &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

/* ------------------------------------------------- 1. the queued-state list */

/*
 * The daemon's accepted set. If this ever stops being a range starting at a
 * value no session is created with, the reasoning above has to be redone --
 * so read it rather than assume it.
 */
$state = (string) file_get_contents($root . '/packages/web/src/Items/TaskState.php');
check(
    'getQueuedStates() is still the list the daemon selects on',
    (bool) preg_match(
        '/function getQueuedStates\(\).*?\$queuedStates = range\(0, 2\);/s',
        $state
    ),
    $failures,
    $checks
);
check(
    'and the literal queued state is a real taskStates row, not 0',
    (bool) preg_match(
        '/function getQueuedState\(\).*?\$queuedState = 1;/s',
        $state
    ),
    $failures,
    $checks
);

/* ------------------------------------------------------ 2. every creator */

$creators = [
    'packages/web/src/Items/Group.php',
    'packages/web/src/Items/Host.php',
    'packages/web/src/Pages/ImageManagement.php',
];

$found = 0;
foreach ($creators as $rel) {
    $src = (string) file_get_contents($root . '/' . $rel);
    if (false === strpos($src, 'new MulticastSession()')) {
        continue;
    }
    $found++;
    /*
     * The chain runs from `new MulticastSession()` to its terminating
     * semicolon. Reading the whole chain rather than one line is what makes
     * "names no state at all" detectable: the bug was an absent value, and a
     * grep for the wrong value cannot see one.
     */
    $chain = '';
    if (preg_match('/new MulticastSession\(\).*?;/s', $src, $m)) {
        $chain = $m[0];
    }
    check(
        "$rel: the session chain was found",
        '' !== $chain,
        $failures,
        $checks
    );
    check(
        "$rel: the session is created queued",
        false !== strpos($chain, "->set('stateID', self::getQueuedState())"),
        $failures,
        $checks
    );
    check(
        "$rel: and never stateless",
        false === strpos($chain, "->set('stateID', null)")
        && false === strpos($chain, "->set('stateID', 0)"),
        $failures,
        $checks
    );
}
check(
    'all three known session creators were seen',
    3 === $found,
    $failures,
    $checks
);

/* -------------------------------------------------- 3. the repair for rows */

/*
 * Existing installs carry sessions written NULL, which no code change
 * reaches. The repair has to be in the schema, and it has to split on
 * whether anything is still waiting -- queuing every stranded row would
 * start senders unattended, canceling every one would throw away the
 * sessions someone is sitting in front of.
 */
$schema = (string) file_get_contents($root . '/packages/web/commons/schema.php');
check(
    'a schema step repairs sessions already written with no state',
    false !== strpos(
        $schema,
        "\"UPDATE `multicastSessions` SET `msState` = 5 WHERE `msState` IS NULL\""
    ),
    $failures,
    $checks
);
check(
    'and revives the ones whose tasks are still active rather than killing them',
    (bool) preg_match(
        '/UPDATE `multicastSessions`.{0,200}?msState` = 1 .{0,600}?'
        . 'multicastSessionsAssoc.{0,400}?taskStateID` IN \(1, 2, 3\)/s',
        $schema
    ),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
