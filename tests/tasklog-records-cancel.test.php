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
class CancelTask extends \FOG\Items\Task
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
    dirname(__DIR__) . '/packages/web/src/Items/Task.php'
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
 * TaskManager::cancel()'s row writer, DRIVEN.
 *
 * The bulk arm cancels every active task in a group in one statement, so its
 * log rows have to be written the same way. recordStates() reads the tasks
 * back and hands TaskLogManager one batch; what is asserted here is that it
 * stays ONE insert however many tasks it is given, and that the row it builds
 * is indistinguishable from the one recordState() writes for a single task.
 *
 * Two tasks on purpose, and deliberately unalike: an imaging one whose image
 * belongs on the row, and a snapin one carrying a stale image name that must
 * not reach it -- recordState() gates that on isImagingTask(), and a second
 * definition of "imaging" living in this method is exactly the drift that
 * would make one cancelled task's row disagree with another's.
 */
$bulkInsert = '';
$bulkBound = [];
$bulkInsertCount = 0;
$bulkSelect = '';
$db->responder = function ($sql, $params) use (
    &$bulkInsert,
    &$bulkBound,
    &$bulkInsertCount,
    &$bulkSelect
) {
    if (false !== stripos($sql, 'INSERT INTO `taskLog`')) {
        $bulkInsert = $sql;
        $bulkBound = (array)$params;
        ++$bulkInsertCount;
        return null;
    }
    if (false !== stripos($sql, 'FROM `tasks`')) {
        $bulkSelect = $sql;
        // What the join yields AFTER the update: state 5 (Cancelled) is read
        // off the row, never passed in, which is what lets a task whose state
        // moved concurrently log the truth instead of the caller's assumption.
        return [
            [
                'taskID' => 7,
                'stateID' => 5,
                'createdBy' => 'fog',
                'typeID' => 1,
                'hostID' => 42,
                'hostName' => 'lab-07',
                'taskTypeName' => 'Deploy',
                'imageName' => 'win11-base'
            ],
            [
                // Host deleted out from under the task: the join misses, so
                // the id must come out 0 rather than the dangling taskHostID.
                'taskID' => 8,
                'stateID' => 5,
                'createdBy' => 'fog',
                'typeID' => 12,
                'hostID' => null,
                'hostName' => '',
                'taskTypeName' => 'All Snapins',
                'imageName' => 'should-not-be-recorded'
            ]
        ];
    }
    return null;
};
\FOG\Items\TaskLog::recordStates([7, 8]);
$db->responder = null;

if ($t->check('recordStates() writes to taskLog', '' !== $bulkInsert)) {
    $t->check(
        'two tasks cost ONE insert, not one apiece',
        1 === $bulkInsertCount
    );
    $t->check(
        'both tasks are in that one insert',
        in_array(7, $bulkBound, true) && in_array(8, $bulkBound, true)
    );
    // The same columns recordState() writes. `ip` and `logType` are the two
    // that a batched insert can silently lose: recordState() gets both from
    // TaskLog's constructor, which this path never runs.
    foreach (
        [
            'taskStateID',
            'ip',
            'createTime',
            'logType',
            'logHostID',
            'logHostName',
            'logTaskTypeName',
            'logImageName'
        ] as $column
    ) {
        $t->check(
            "the batch names `$column`",
            false !== strpos($bulkInsert, '`' . $column . '`')
        );
    }
    $t->check(
        'the state comes from the reloaded task',
        in_array(5, $bulkBound, true)
    );
    $t->check(
        'a state row is typed as one',
        in_array(\FOG\Items\TaskLog::TYPE_STATE, $bulkBound, true)
    );
    $t->check(
        "an imaging task's image is recorded",
        in_array('win11-base', $bulkBound, true)
    );
    $t->check(
        "a non-imaging task's stale image is NOT recorded",
        !in_array('should-not-be-recorded', $bulkBound, true)
    );
    // Two halves of the same property. The id has to be READ from the hosts
    // join -- `tasks`.`taskHostID` survives the host row it points at, so
    // taking it from there would record a dangling id where recordState()
    // records 0 -- and the miss has to arrive as 0 rather than as NULL.
    $t->check(
        'the host id is read from the hosts join, not from tasks',
        false !== strpos($bulkSelect, '`hosts`.`hostID` AS `hostID`')
    );
    $t->check(
        'a task whose host is gone records host 0, not a dangling id',
        in_array(0, $bulkBound, true)
    );
    // A failed log write must not fail the cancel. insertBatch() throws where
    // FOGController::save() returns false, and the only caller sits inside
    // Route::cancel()'s try -- so without a catch here a caller whose tasks
    // really were cancelled would be answered with an error.
    $t->check(
        'a failed batch does not escape to the caller',
        (bool)preg_match(
            '#try\s*\{.*?insertBatch\(.*?\}\s*catch\s*\(#s',
            file_get_contents(
                dirname(__DIR__)
                . '/packages/web/src/Items/TaskLog.php'
            )
        )
    );

    // Same shape check as the timestamp gate: the row is stamped now, and no
    // test can predict the clock, so this matches on the year.
    $stamped = false;
    foreach ($bulkBound as $value) {
        if (is_string($value)
            && preg_match('#^(\d{4})-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $value, $m)
            && (int)$m[1] >= 2025
        ) {
            $stamped = true;
        }
    }
    $t->check('the rows are stamped with the present', $stamped);
}

/*
 * TaskManager::cancel(): the ordering.
 */
$manager = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Managers/TaskManager.php'
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
        false !== strpos($bulkBody, 'TaskLog::recordStates(')
    );

    // The bulk path must stay bulk. Rebuilding a Task per id to write its row
    // costs the reload, the host, the task type, the image and the INSERT --
    // five queries apiece against a statement that cancels a whole group at
    // once, which is what recordStates() exists to avoid.
    $t->check(
        'it does not rebuild a Task per cancelled id',
        false === strpos($bulkBody, 'TaskLog::recordState(new Task')
    );

    // The ids of the tasks being cancelled have to be read while the WHERE
    // still matches them. Positions, because this is the whole correctness
    // argument: getIds -> update -> recordStates, in that order.
    //
    // Anchored on the ids reaching recordStates() rather than on a variable
    // spelling, so renaming a local does not fail this file. It cannot anchor
    // on Route::getIds('task', ...) alone: the method already collects hostIDs
    // that way BEFORE the update, so a looser match would satisfy the ordering
    // below without the task ids being collected at all.
    $recordAt = strpos($bulkBody, 'TaskLog::recordStates(');
    $idsVar = '';
    if (preg_match('#TaskLog::recordStates\(\s*(\$\w+)#', $bulkBody, $m)) {
        $idsVar = $m[1];
    }
    $idsAt = ('' === $idsVar)
        ? false
        : strpos($bulkBody, $idsVar . ' = Route::getIds(');
    $updateAt = strpos($bulkBody, '$this->update(');

    $t->check(
        'it records the ids it enumerated, not a fresh query',
        '' !== $idsVar
    );
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
    // The guard, by shape rather than by the name of the local it tests: the
    // update's result has to be what decides whether a row is written.
    $t->check(
        'it records only if the update succeeded',
        (bool)preg_match(
            '#(\$\w+)\s*=\s*\$this->update\(.*?if\s*\(\s*\1\s*\)#s',
            $bulkBody
        )
    );
}

$t->finish();
