<?php
/**
 * Cancelling a task says so in the log.
 *
 * taskLog was written from exactly two places, TaskQueue::checkIn() and
 * TaskQueue::checkout(), both reached through a TaskingElement -- which exists
 * only for a machine that has checked in. Cancellation has no TaskingElement:
 * Task::cancel() set stateID and saved, TaskManager::cancel() ran a bulk UPDATE,
 * and neither wrote a row. So the last thing the log ever said about a cancelled
 * task was that it was In-Progress, and Task Management's log pane showed it
 * that way for good.
 *
 * Route::cancel() dispatches to both: the group arm goes to
 * TaskManager->cancel($taskIDs), the host arm to $Task->cancel(), and the
 * default task arm reaches each of them. Covering these two therefore covers
 * every arm of the endpoint. Cancellations that happen as a SIDE EFFECT of
 * something else -- host.class.php's "Cancelled due to new tasking", multicast
 * teardown, the file-delete queue -- go straight to TaskManager->update() and
 * are deliberately out of scope here.
 *
 * Two halves, asserted differently because the two methods differ:
 *
 *   Task::cancel()         DRIVEN. It is reachable with a stub task, so what is
 *                          asserted is the INSERT that comes out.
 *   TaskManager::cancel()  SOURCE. It is a bulk UPDATE whose correctness is an
 *                          ORDERING -- the ids have to be collected before the
 *                          update, because the WHERE selects on the states
 *                          being cancelled out of and matches nothing after.
 *                          An ordering is what is checked, so the check is on
 *                          the order of the statements.
 *
 * Usage: php tests/tasklog-records-cancel.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('tasklog-records-cancel');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * A Task whose relationships resolve without a database.
 *
 * Same technique, and the same reason, as RetentionTask in
 * tests/tasklog-report-retention.test.php.
 */
class CancelTask extends \FOG\Task
{
    public $stubHost;

    public function getHost()
    {
        return $this->stubHost;
    }

    public function getTaskTypeText()
    {
        return 'Capture';
    }

    public function isImagingTask()
    {
        return false;
    }
}

$host = FOGCore::getClass('Host')
    ->set('id', 42)
    ->set('name', 'lab-07');

// id, typeID and hostID are all three of Task's required fields: recordState()
// refuses an invalid task, so a stub missing one of them would make this file
// pass for the wrong reason.
$task = new CancelTask();
$task->stubHost = $host;
$task
    ->set('id', 7)
    ->set('typeID', 2)
    ->set('hostID', 42)
    ->set('stateID', 3)
    ->set('createdBy', 'fog');

$task->cancel();

$inserts = [];
foreach ($db->log as $sql) {
    if (false !== stripos($sql, 'INSERT INTO `taskLog`')) {
        $inserts[] = $sql;
    }
}

$t->check(
    'Task::cancel() writes a taskLog row',
    1 === count($inserts)
);
if (count($inserts)) {
    // The row has to name the state, or the log can show a transition without
    // saying what it transitioned to.
    $t->check(
        'the row records a task state',
        false !== strpos($inserts[0], '`taskStateID`')
    );
    $t->check(
        'the row is typed as a state change',
        false !== strpos($inserts[0], '`logType`')
    );
    $t->check(
        'the row carries the denormalized host name',
        false !== strpos($inserts[0], '`logHostName`')
    );
}

/*
 * The row must not be written when the save did not happen -- a log that claims
 * a transition the database never took is worse than one that misses it.
 */
$source = file_get_contents(
    dirname(__DIR__) . '/packages/web/lib/fog/task.class.php'
);
$cancelBody = '';
if (preg_match('#public function cancel\(\).*?\n    \}\n#s', $source, $m)) {
    $cancelBody = $m[0];
}
if ($t->check('Task::cancel() is found', '' !== $cancelBody)) {
    $t->check(
        'Task::cancel() records the state',
        false !== strpos($cancelBody, 'TaskLog::recordState(')
    );
    $t->check(
        'Task::cancel() records it only if the save succeeded',
        (bool)preg_match(
            '#if\s*\(\s*\$this->set\(\s*.stateID.*?->save\(\)\s*\)#s',
            $cancelBody
        )
    );
}

/*
 * TaskManager::cancel(): the ordering.
 */
$manager = file_get_contents(
    dirname(__DIR__) . '/packages/web/lib/fog/taskmanager.class.php'
);
$bulkBody = '';
if (preg_match(
    '#public function cancel\(\$taskids\).*?\n    \}\n#s',
    $manager,
    $m
)) {
    $bulkBody = $m[0];
}

if ($t->check('TaskManager::cancel() is found', '' !== $bulkBody)) {
    $t->check(
        'TaskManager::cancel() records the state',
        false !== strpos($bulkBody, 'TaskLog::recordState(')
    );

    // The ids of the tasks being cancelled have to be read while the WHERE
    // still matches them. Positions, because this is the whole correctness
    // argument: getIds -> update -> recordState, in that order.
    // Anchored on the assignment, not on Route::getIds('task', ...) alone:
    // the method already collects hostIDs that way BEFORE the update, so a
    // looser match would satisfy the ordering check below without the task
    // ids being collected at all.
    $idsAt = strpos($bulkBody, '$cancelledIDs = Route::getIds(');
    $updateAt = strpos($bulkBody, '$this->update(');
    $recordAt = strpos($bulkBody, 'TaskLog::recordState(');

    $t->check(
        'it enumerates the tasks it is about to cancel',
        false !== $idsAt
    );
    $t->check(
        'it enumerates them BEFORE the update',
        false !== $idsAt && false !== $updateAt && $idsAt < $updateAt
    );
    $t->check(
        'it records them AFTER the update',
        false !== $updateAt && false !== $recordAt && $updateAt < $recordAt
    );
    $t->check(
        'it records only if the update succeeded',
        (bool)preg_match('#\$updated\s*=\s*\$this->update\(#', $bulkBody)
        && (bool)preg_match('#if\s*\(\s*\$updated\s*\)#', $bulkBody)
    );
}

$t->finish();
