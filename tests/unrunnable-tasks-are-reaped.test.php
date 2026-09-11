<?php
/**
 * A task that can never run must not sit in Active Tasks forever, and every
 * state a task moves through must reach the log.
 *
 * THE ROWS. A `tasks` row whose taskHostID, taskTypeID or -- for an imaging
 * type -- taskImageID matches no row is permanent and unreadable at the same
 * time. taskStateID still resolves, so every "is this task live" test in the
 * tree is an allowlist of getQueuedStates() plus getProgressState() and counts
 * it as active. The Active Tasks list renders each cell from a LEFT OUTER JOIN
 * and guards it with isset(), which is false for the NULL a non-matching join
 * returns, so the row draws with no host, no image and no type. No host can
 * complete it, and nothing reaped it. Reported as "null tasks" in forum topics
 * 18228 and 18230.
 *
 * The write paths that create such a row are closed elsewhere (GH-1391). What
 * this pins is the sweep for the rows an install is ALREADY carrying, which no
 * code change can reach.
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
 * AND THE LOG IT WRITES INTO. The sweep needs somewhere truthful to record
 * itself, and on this branch there was nowhere: a state row could only be
 * written by TaskingElement, so cancellation -- which reaches `tasks` through
 * Task::cancel() and TaskManager::cancel(), neither of which has one -- wrote
 * nothing at all, and In-Progress stayed the last word on a cancelled task
 * forever (GH-1378 causes 1 and 2). Both are wired here, so the same checks
 * cover them.
 *
 * DB-free: this reads the source. The behaviour is proved against a live
 * database by background_scripts/prove_unrunnable_task_reaper_15.php, which
 * fails on an unpatched tree.
 *
 * Usage: php tests/unrunnable-tasks-are-reaped.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = array();
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

/**
 * Records a check.
 *
 * @param string $what the defect, stated as what would be wrong
 * @param bool   $ok   whether it passed
 *
 * @return void
 */
function reapCheck($what, $ok)
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

$man = reapStrip("$root/packages/web/lib/fog/taskmanager.class.php");
$log = reapStrip("$root/packages/web/lib/fog/tasklog.class.php");
$sched = reapStrip("$root/packages/web/lib/service/taskscheduler.class.php");
$task = reapStrip("$root/packages/web/lib/fog/task.class.php");
$element = reapStrip("$root/packages/web/lib/reg-task/taskingelement.class.php");

/* ------------------------------------------------------------- 1. the sweep */

$reap = '';
if (preg_match('/public function reapUnrunnable\(\).*?\n    \}/s', $man, $m)) {
    $reap = $m[0];
}
reapCheck(
    'TaskManager has no reapUnrunnable(), so an install carrying tasks that '
    . 'can never run keeps carrying them',
    '' !== $reap
);

reapCheck(
    'the sweep does not wait for the Failed taskStates row to exist. It runs '
    . 'from a daemon, which reaches the window between the files landing and '
    . 'the database being upgraded before any person does, and a task '
    . 'pointing at a missing state renders blank and cannot be filtered for',
    false !== strpos($reap, "getClass('TaskState', \$failed)->isValid()")
);

reapCheck(
    'the sweep is no longer restricted to active tasks, so it would rewrite '
    . 'finished history',
    false !== strpos($reap, 'getQueuedStates()')
    && false !== strpos($reap, 'getProgressState()')
);

/* ------------------------------------- 2. resolution, not zero (the big one) */

reapCheck(
    'the sweep no longer joins all three of hosts, taskTypes and images, so '
    . 'it cannot see the same danglings the Active Tasks list draws blank',
    3 === substr_count($reap, 'LEFT OUTER JOIN')
);

reapCheck(
    'the sweep matches on a column being 0 instead of on the join failing. A '
    . '0 and the id of something deleted are both dangling; matching on 0 '
    . 'misses every deleted-id case, and for taskImageID it would destroy '
    . 'good tasking -- a wipe, snapin, inventory, password-reset or Secure '
    . 'Boot task stores 0 there by design',
    3 === substr_count($reap, 'IS NULL')
    && false === strpos($reap, '`taskImageID` = 0')
    && false === strpos($reap, '`taskHostID` = 0')
    && false === strpos($reap, '`taskTypeID` = 0')
);

reapCheck(
    'the image is asked about for every task type, not only imaging ones, so '
    . 'every snapin, wipe, inventory and password-reset task would be reaped',
    false !== strpos($reap, '`images`.`imageID` IS NULL ')
    && false !== strpos($reap, "AND `tasks`.`taskTypeID` IN (")
);

reapCheck(
    'which types count as imaging is written down here rather than asked of '
    . 'TaskType, so this and every other reader of "is this an imaging task" '
    . 'can drift apart',
    false !== strpos($reap, 'isDeploy(true)')
    && false !== strpos($reap, 'isCapture(true)')
);

/* --------------------------------------------------- 3. failed, not cancelled */

reapCheck(
    'the sweep reaps to a state other than Failed. Cancelled means an '
    . 'administrator stopped it, and losing the difference between "somebody '
    . 'stopped this" and "this broke" is the distinction an operator needs',
    false !== strpos($reap, 'getFailedState()')
    && false === strpos($reap, 'getCancelledState()')
);

/* ------------------------------------------------------------ 4. it says why */

reapCheck(
    'the state transition is not recorded, so no reader of the log sees the '
    . 'task move to Failed',
    false !== strpos($reap, 'TaskLog::recordStates(')
);

reapCheck(
    'the reason row is no longer an error row. The reason is the whole value '
    . 'of this row -- a state row saying only "Failed" against a task whose '
    . 'host is gone tells an operator nothing they can act on',
    false !== strpos($reap, 'TaskLog::TYPE_ERROR')
);

reapCheck(
    'the reason no longer names which reference failed to resolve',
    false !== strpos($reap, "_('host no longer exists')")
    && false !== strpos($reap, "_('task type no longer exists')")
    && false !== strpos($reap, "_('image no longer exists')")
);

reapCheck(
    'the batched reason rows dropped ip or type. A batched INSERT never runs '
    . "TaskLog's constructor, which is where a singly-written row gets both, "
    . 'and `ip` is varchar(15) NOT NULL',
    false !== strpos($reap, "'ip',")
    && false !== strpos($reap, "'type',")
);

reapCheck(
    'the reaper stopped casting the remote address. There is none in a '
    . 'daemon: filter_input(INPUT_SERVER, REMOTE_ADDR) is null under CLI and a '
    . 'null bound into a NOT NULL column is error 1048 on a strict server',
    false !== strpos($reap, '(string)self::$remoteaddr')
);

/* -------------------------------- 5. the log it writes into (GH-1378 1 and 2) */

$recordState = '';
if (preg_match('/public static function recordState\(.*?\n    \}/s', $log, $m)) {
    $recordState = $m[0];
}
reapCheck(
    'TaskLog::recordState() is gone, so a state row can once again only be '
    . 'written by a TaskingElement -- and cancellation has none',
    '' !== $recordState
);

reapCheck(
    'recordState() stamps the row with something other than the moment of '
    . 'the transition. Passing the task\'s createdTime made In-Progress and '
    . 'Complete share a timestamp to the second, so nothing could be ordered '
    . 'and repeated transitions read as duplicates',
    false !== strpos($recordState, "->set('createdTime', self::niceDate()")
);

reapCheck(
    'recordState() no longer refuses a task that does not resolve, so the '
    . 'log can claim a transition for a task that is not there',
    false !== strpos($recordState, '$Task->isValid()')
);

$recordStates = '';
if (preg_match('/public static function recordStates\(.*?\n    \}/s', $log, $m)) {
    $recordStates = $m[0];
}
reapCheck(
    'TaskLog::recordStates() is gone, so a bulk cancel has to rebuild a Task '
    . 'per id to record itself',
    '' !== $recordStates
);

reapCheck(
    'recordStates() no longer reads the state back from `tasks`. Trusting the '
    . 'caller\'s ids records what the caller assumed rather than what the row '
    . 'says, and logs a transition for an id that no longer resolves',
    false !== strpos($recordStates, 'FROM `tasks` ')
    && false !== strpos($recordStates, '`taskStateID`')
);

reapCheck(
    'recordStates() stopped casting the remote address -- same 1048 as the '
    . 'reaper, and this one is reached from the multicast manager too',
    false !== strpos($recordStates, '(string)self::$remoteaddr')
);

reapCheck(
    'recordStates() no longer sets ip or type, which a batched INSERT cannot '
    . "get from TaskLog's constructor",
    false !== strpos($recordStates, "'ip',")
    && false !== strpos($recordStates, "'type',")
);

$taskCancel = '';
if (preg_match('/public function cancel\(\).*?\n        return \$this;/s', $task, $m)) {
    $taskCancel = $m[0];
}
reapCheck(
    'Task::cancel() could not be isolated',
    '' !== $taskCancel
);
reapCheck(
    'Task::cancel() records nothing, so In-Progress stays the last word the '
    . 'log ever says about a cancelled task',
    false !== strpos($taskCancel, 'TaskLog::recordState($this)')
);
reapCheck(
    'Task::cancel() records the transition without checking the save '
    . 'happened, so the log can claim a state change the database refused',
    (bool)preg_match(
        '/if \(\$this->set\(\'stateID\', self::getCancelledState\(\)\)->save\(\)\) \{\s*TaskLog::recordState\(\$this\);/s',
        $taskCancel
    )
);

$manCancel = '';
if (preg_match('/public function cancel\(\$taskids\).*?\n    \}/s', $man, $m)) {
    $manCancel = $m[0];
}
reapCheck(
    'TaskManager::cancel() could not be isolated',
    '' !== $manCancel
);
reapCheck(
    'TaskManager::cancel() records nothing, so a group cancel leaves every '
    . 'one of its tasks reading In-Progress in the log',
    false !== strpos($manCancel, 'TaskLog::recordStates($cancelledIDs)')
);
/*
 * Order, not presence. $findWhere selects on the states being cancelled OUT
 * of, so enumerating after the update matches nothing and the recording is
 * silently a no-op -- which passes a presence check perfectly.
 */
$idsPos = strpos($manCancel, '$cancelledIDs = self::getSubObjectIDs(');
$updPos = strpos($manCancel, '$updated = $this->update(');
reapCheck(
    'TaskManager::cancel() enumerates the ids after the update. $findWhere '
    . 'selects on the states being cancelled out of and matches nothing once '
    . 'they are gone, so nothing would be recorded and nothing would say so',
    false !== $idsPos && false !== $updPos && $idsPos < $updPos
);

reapCheck(
    'TaskingElement::taskLog() builds the row itself again instead of going '
    . 'through recordState(), which is how the two callers that have no '
    . 'TaskingElement came to write nothing',
    false !== strpos($element, 'return TaskLog::recordState($this->Task);')
);

/* ------------------------------------------------ 6. the scheduler runs it */

reapCheck(
    'the task scheduler no longer runs the sweep, so nothing does',
    false !== strpos($sched, "getClass('TaskManager')->reapUnrunnable()")
);

/*
 * THE ORDERING ONE. _commonOutput() counts SCHEDULED and power-management
 * tasks and throws ' * No tasks found!' when there are none, which ends the
 * pass. A sweep placed after that never runs on an install with no scheduled
 * task and no power-management task -- most small installs, and exactly the
 * population reporting tasks stuck active forever.
 */
$reapPos = strpos($sched, "getClass('TaskManager')->reapUnrunnable()");
$throwPos = strpos($sched, "' * No tasks found!'");
reapCheck(
    'the sweep runs after the scheduler\'s "No tasks found!" exit, so it '
    . 'never runs at all on an install with no scheduled tasks',
    false !== $reapPos && false !== $throwPos && $reapPos < $throwPos
);

reapCheck(
    'the scan did not reach the sources and would pass vacuously',
    strlen($man) > 5000
    && strlen($log) > 2500
    && strlen($sched) > 5000
    && strlen($task) > 5000
    && strlen($element) > 4000
);

if (count($failures) > 0) {
    echo 'FAIL unrunnable-tasks-are-reaped (' . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS unrunnable-tasks-are-reaped ($checks checks)\n";
exit(0);
