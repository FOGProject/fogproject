<?php
/**
 * A task log row is stamped when the transition happened.
 *
 * TaskingElement::taskLog() built its row with
 * `->set('createdTime', $this->Task->get('createdTime'))`, and TaskLog's field
 * map is `'createdTime' => 'createTime'` -- ONE column, not two. So every row a
 * task ever wrote carried the instant the TASK was created, not the instant the
 * state changed.
 *
 * The symptom is a Task Management log pane that cannot be read: In-Progress and
 * Complete come out sharing a timestamp to the second, nothing can be ordered
 * within a task, and a task that transitioned twice looks like it has duplicate
 * rows. It also quietly mis-buckets everything downstream, because `createTime`
 * is the column both the retention window and the dashboard's per-event count
 * read.
 *
 * Two halves, failing differently:
 *
 *   the WRITER stamps the transition        (TaskLog::recordState, driven)
 *   the CALLER no longer supplies its own   (TaskingElement::taskLog, source)
 *
 * The writer half is driven for real against a fake connection: the values
 * arrive as bound parameters, so what is asserted is the INSERT that comes out
 * rather than a copy of the code that builds it.
 *
 * Usage: php tests/tasklog-stamps-transition-time.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('tasklog-transition-time');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * A Task whose relationships resolve without a database.
 *
 * recordState() reaches the host, the image and the task type through
 * relationships a fake connection cannot populate. Overridden so this stays
 * about what recordState() DOES with them. Same technique, and the same
 * reason, as RetentionTask in tests/tasklog-report-retention.test.php.
 */
class TransitionTask extends \FOG\Task
{
    public $stubHost;
    public $stubImage;

    public function getHost()
    {
        return $this->stubHost;
    }

    public function getImage()
    {
        return $this->stubImage;
    }

    public function getTaskTypeText()
    {
        return 'Capture';
    }

    public function isImagingTask()
    {
        return true;
    }
}

$host = FOGCore::getClass('Host')
    ->set('id', 42)
    ->set('name', 'lab-07');
// Image declares name/path/imageTypeID/osID required, and recordState() only
// takes the name off an image that isValid() -- so all four are set here.
$image = FOGCore::getClass('Image')
    ->set('id', 9)
    ->set('name', 'win11-base')
    ->set('path', 'win11-base')
    ->set('imageTypeID', 1)
    ->set('osID', 5);

// Deliberately old and unmistakable: if this value reaches the row, the bug is
// back. A task created in 2000 that transitions today must not be logged as
// having transitioned in 2000.
$taskCreated = '2000-01-01 00:00:00';

$task = new TransitionTask();
$task->stubHost = $host;
$task->stubImage = $image;
// typeID and hostID as well as id: Task declares all three required, and
// recordState() refuses an invalid task on purpose -- TaskManager::cancel()
// reloads each id after the update, and an id that no longer resolves must not
// produce a row claiming a transition.
$task
    ->set('id', 7)
    ->set('typeID', 2)
    ->set('hostID', 42)
    ->set('stateID', 3)
    ->set('createdTime', $taskCreated)
    ->set('createdBy', 'fog');

$insert = '';
$bound = [];
// Checked rather than assumed, so a tree without the method reports a failure
// instead of dying with a fatal halfway through the file.
if (!method_exists('FOG\TaskLog', 'recordState')) {
    $t->check('TaskLog::recordState() exists', false);
    $t->finish();
}
$db->responder = function ($sql, $params) use (&$insert, &$bound) {
    if (false !== stripos($sql, 'INSERT INTO `taskLog`')) {
        $insert = $sql;
        $bound = (array)$params;
    }
    return null;
};
\FOG\TaskLog::recordState($task);
$db->responder = null;

if ($t->check('recordState() issues an INSERT against taskLog', '' !== $insert)) {
    $t->check(
        'the INSERT names `createTime`',
        false !== strpos($insert, '`createTime`')
    );
    $t->check(
        "the task's own createdTime ($taskCreated) is NOT written",
        !in_array($taskCreated, $bound, true)
    );

    // Find the value that WAS written: a timestamp in the row that is not the
    // task's. Matched by shape rather than by an exact clock reading, which no
    // test can predict.
    $stamps = [];
    foreach ($bound as $value) {
        if (is_string($value)
            && preg_match('#^(\d{4})-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $value, $m)
            && (int)$m[1] >= 2025
        ) {
            $stamps[] = $value;
        }
    }
    $t->check(
        'a present-day timestamp is written instead',
        count($stamps) > 0
    );

    // The rest of the row, so a change that fixed the timestamp by dropping
    // the identity columns cannot pass this file.
    $expected = [
        'logHostID' => 42,
        'logHostName' => 'lab-07',
        'logTaskTypeName' => 'Capture',
        'logImageName' => 'win11-base',
    ];
    foreach ($expected as $column => $value) {
        $t->check(
            "the INSERT names `$column`",
            false !== strpos($insert, '`' . $column . '`')
        );
        $t->check(
            "`$column` is written as " . var_export($value, true),
            in_array($value, $bound, true)
        );
    }
}

/*
 * The caller half. TaskingElement::taskLog() must not build a row of its own
 * any more -- one definition of a state row, in TaskLog::recordState(), is what
 * lets the cancel paths write one at all. Asserted on the source because the
 * class' constructor IS the request (it resolves the host from $_REQUEST and
 * dies if there is no tasking), so the method cannot be reached by building it.
 */
$element = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/TaskHandling/TaskingElement.php'
);

$body = '';
if (preg_match(
    '#protected function taskLog\(\).*?\n    \}\n#s',
    $element,
    $m
)) {
    $body = $m[0];
}

if ($t->check('TaskingElement::taskLog() is found', '' !== $body)) {
    $t->check(
        'taskLog() delegates to TaskLog::recordState()',
        false !== strpos($body, 'TaskLog::recordState(')
    );
    $t->check(
        'taskLog() no longer passes the task createdTime',
        false === strpos($body, "get('createdTime')")
    );
    $t->check(
        'taskLog() no longer builds a row of its own',
        false === strpos($body, "->set('taskStateID'")
    );
}

$t->finish();
