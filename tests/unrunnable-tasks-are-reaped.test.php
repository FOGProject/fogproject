<?php
/**
 * A task that can never run must not sit in Active Tasks forever.
 *
 * THE ROWS. A `tasks` row whose taskHostID, taskTypeID or -- for an imaging
 * type -- taskImageID matches no row is permanent and unreadable at the same
 * time. taskStateID still resolves, so every "is this task live" test in the
 * tree is an allowlist of getQueuedStates() plus getProgressState() and counts
 * it as active. The Active Tasks list renders each cell from buildQuery()'s
 * LEFT OUTER JOINs and guards it with isset(), which is false for the NULL a
 * non-matching join returns, so the row draws as "() -" with no type icon and
 * no MAC. No host can complete it, and nothing reaped it. Reported as "null
 * tasks" in forum topics 18228 and 18230.
 *
 * The write paths that create such a row are closed elsewhere. What this pins
 * is the sweep for the rows an install is ALREADY carrying, which no code
 * change can reach.
 *
 * THE TWO CHECKS THAT MATTER MOST, because getting either wrong is worse than
 * not having the sweep at all:
 *
 *   - It must find rows by whether they JOIN, not by whether a column is 0. A
 *     0 and the id of something deleted are both dangling and both have to go;
 *     matching on 0 would miss every deleted-id case AND destroy good tasking,
 *     because a wipe, snapin, inventory, password-reset or Secure Boot task
 *     legitimately stores taskImageID 0.
 *   - The image is therefore only asked about for an IMAGING task type.
 *
 * DB-free: this reads the source. The behavior is proved against a live
 * database by background_scripts/prove_unrunnable_task_reaper.php, which fails
 * on an unpatched tree.
 *
 * Usage: php tests/unrunnable-tasks-are-reaped.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

/**
 * Source with comments removed, so a commented-out line can neither satisfy
 * a check nor fail one -- these files carry long docblocks that name the very
 * things being grepped for.
 *
 * @param string $file the file to read
 *
 * @return string
 */
function reapStrip($file)
{
    $clean = '';
    foreach (token_get_all((string)file_get_contents($file)) as $token) {
        if (is_array($token)
            && (T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0])
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return $clean;
}

function check($what, $ok, &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

$man = reapStrip("$root/packages/web/src/Managers/TaskManager.php");
$log = reapStrip("$root/packages/web/src/Items/TaskLog.php");
$sched = reapStrip("$root/packages/web/src/Service/TaskScheduler.php");

/* ------------------------------------------------------------- 1. the sweep */

$reap = '';
if (preg_match('/public function reapUnrunnable\(\).*?\n    \}/s', $man, $m)) {
    $reap = $m[0];
}
check(
    'TaskManager has a sweep for unrunnable tasks',
    '' !== $reap,
    $failures,
    $checks
);

/*
 * The schema guard. This runs from a daemon, which reaches the window between
 * the files landing and the database being upgraded before any person does.
 * Pointing a task at a taskStates row that is not there renders blank and
 * cannot be filtered for -- worse than leaving it where it was.
 */
check(
    'it refuses to run until the Failed state row exists',
    1 === preg_match(
        "/getClass\('TaskState',\s*\\\$failed\)->isValid\(\)/",
        $reap
    ),
    $failures,
    $checks
);

check(
    'it only considers tasks FOG still counts as active',
    false !== strpos($reap, 'getQueuedStates()')
    && false !== strpos($reap, 'getProgressState()'),
    $failures,
    $checks
);

/* ------------------------------------------ 2. resolution, not zero */

/*
 * The load-bearing one. Three LEFT OUTER JOINs and three IS NULL tests: that
 * is the same question the Active Tasks list asks when it draws "() -", so
 * "is this row unreadable" and "is this row reaped" cannot drift apart.
 */
foreach (['hosts', 'taskTypes', 'images'] as $table) {
    check(
        "it LEFT OUTER JOINs `$table` to ask whether the reference resolves",
        1 === preg_match(
            '/LEFT OUTER JOIN `' . $table . '`/',
            $reap
        ),
        $failures,
        $checks
    );
}
check(
    'and selects on the join failing, not on the column being 0',
    3 === preg_match_all('/IS NULL/', $reap)
    && 0 === preg_match('/`task(Host|Type|Image)ID`\s*=\s*0/', $reap),
    $failures,
    $checks
);

/*
 * The one that protects good data. taskImageID 0 is CORRECT for every task
 * type that has no image -- proven on a live install carrying three such rows,
 * two All Snapins and one Enroll Secure Boot. Asking about the image without
 * gating on the type would fail all of them.
 */
check(
    'the image is only asked about for an imaging task type',
    1 === preg_match(
        '/`images`\.`imageID` IS NULL.*?AND.*?`tasks`\.`taskTypeID` IN/s',
        $reap
    ),
    $failures,
    $checks
);
check(
    'and "imaging" comes from TaskType, not a list written down here',
    false !== strpos($reap, 'TaskType::DEPLOYTASKS')
    && false !== strpos($reap, 'TaskType::CAPTURETASKS'),
    $failures,
    $checks
);

/* ---------------------------------------------------- 3. what it writes */

/*
 * Failed, not Canceled. Canceled means an administrator stopped it; losing
 * the difference between "somebody stopped this" and "this broke" is the
 * distinction an operator needs at the moment they are reading the task list.
 */
check(
    'it moves the task to Failed',
    false !== strpos($reap, 'getFailedState()'),
    $failures,
    $checks
);
check(
    'and never to Canceled',
    false === strpos($reap, 'getCancelledState()'),
    $failures,
    $checks
);

check(
    'it records the state transition like every other one',
    false !== strpos($reap, 'TaskLog::recordStates('),
    $failures,
    $checks
);

/*
 * And the reason, as a separate row. A state row saying only "Failed" against
 * a task whose host is gone tells an operator nothing they can act on, and the
 * log pane's DEFAULT filter is error plus warning -- so an error row is the
 * one that is actually seen.
 */
check(
    'it writes the reason into the log',
    false !== strpos($reap, 'TaskLog::TYPE_ERROR')
    && false !== strpos($reap, "'text',"),
    $failures,
    $checks
);
check(
    'the reason names which reference did not resolve',
    false !== strpos($reap, "_('host no longer exists')")
    && false !== strpos($reap, "_('task type no longer exists')")
    && false !== strpos($reap, "_('image no longer exists')"),
    $failures,
    $checks
);

/*
 * A batched INSERT never runs TaskLog's constructor, which is where a singly
 * written row gets its ip and its type -- so a reaped row must set both or it
 * is distinguishable from every other row for no reason.
 */
check(
    'the batched rows carry the ip and type the constructor would have set',
    false !== strpos($reap, "'ip',")
    && false !== strpos($reap, "'type',"),
    $failures,
    $checks
);

/*
 * There is no REMOTE_ADDR in a daemon: filter_input(INPUT_SERVER,
 * 'REMOTE_ADDR') is null under CLI, `ip` is varchar(15) NOT NULL, and a null
 * bound into a NOT NULL column is error 1048 on a strict server -- which is
 * every server since GH-1245. save() coerces this for a single row;
 * insertBatch() does not, so both batched writers have to cast.
 */
foreach (['reap' => $reap, 'recordStates' => $log] as $where => $src) {
    check(
        "the batched write in $where casts the remote address to a string",
        0 === preg_match('/[^)]self::\$remoteaddr,/', $src),
        $failures,
        $checks
    );
}

/* ------------------------------------------------- 4. it actually runs */

check(
    'the task scheduler runs the sweep',
    false !== strpos($sched, 'reapUnrunnable()'),
    $failures,
    $checks
);

/*
 * ORDER IS THE POINT. The "No tasks found!" exit counts SCHEDULED and
 * power-management tasks and throws when there are none, ending the pass. Both
 * housekeeping sweeps used to sit after it, so on any install with no schedule
 * they never ran -- which is most small installs, and exactly the population
 * reporting tasks stuck active forever.
 */
$reapPos = strpos($sched, 'reapUnrunnable()');
$expirePos = strpos($sched, 'expireTaskCheckin()');
$throwPos = strpos($sched, 'No tasks found!');
check(
    'the "no scheduled tasks" exit was found',
    false !== $throwPos,
    $failures,
    $checks
);
check(
    'the sweep runs BEFORE the "no scheduled tasks" exit',
    false !== $reapPos && false !== $throwPos && $reapPos < $throwPos,
    $failures,
    $checks
);
check(
    'and so does the expired-check-in sweep, which used to be skipped with it',
    false !== $expirePos && false !== $throwPos && $expirePos < $throwPos,
    $failures,
    $checks
);

check(
    'the scan reached the sources and did not pass vacuously',
    strlen($man) > 4000 && strlen($log) > 4000 && strlen($sched) > 4000,
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
