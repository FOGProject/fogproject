<?php
/**
 * A task that has never checked in must still be able to check in.
 *
 * GH forums 18225 -- every imaging task on 1.5.10.2328+ failed its first
 * check-in. `service/Pre_Stage1.php` returned a bodyless HTTP 500, FOS never
 * saw `##@GO` and retried forever, and the only thing an operator got was
 * "failed to check in".
 *
 *   PHP Fatal error: Uncaught TypeError: Task::isAlmostExpired(): Argument #1
 *   ($checkInTime) must be of type string, null given
 *
 * Three things line up, and GH-1245 supplied the last one:
 *
 *  - isExpired()/isAlmostExpired() declare `string`. A userland scalar
 *    parameter with no null default REJECTS null even in coercive mode -- it
 *    is a TypeError, not a coercion to ''.
 *  - this branch's FOGController::get() returns null for a key the row holds
 *    no value for. working-1.6's returns '', which is why the identical two
 *    lines are harmless there -- so this test cannot be ported by symmetry,
 *    it is guarding a difference between the branches.
 *  - schema 284 made tasks.taskCheckIn nullable and save() learned to write a
 *    real SQL NULL for an emptied date. Before that a never-checked-in task
 *    read back '0000-00-00 00:00:00' -- a string, which the declaration
 *    accepted.
 *
 * The test therefore drives taskCheckIn() on a task whose taskCheckIn column
 * is a real SQL NULL, which is what every freshly created task now looks
 * like. It pins the guard at the CALL SITE: reverting `?? ''` in
 * Task::taskCheckIn() puts the TypeError straight back, whereas asserting on
 * isExpired('') alone would still pass.
 *
 * Needs a real 1.5 schema -- the NULL has to come out of a nullable column,
 * so a fake database cannot show it. SKIPs when there is none:
 *
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;port=13313;dbname=fog15' \
 *   FOG_TEST_USER=root FOG_TEST_PASS= php tests/task-checkin-null-time.test.php
 *
 * Usage: php tests/task-checkin-null-time.test.php
 * Exit status 0 = pass (or skip), 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';

require __DIR__ . '/lib/scope-harness.php';

$reason = scopeHarnessDbReason();
if (null !== $reason) {
    echo "SKIP: $reason\n";
    exit(0);
}

$tmp = sys_get_temp_dir() . '/fog-checkin-' . getmypid();
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
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once $web . '/commons/init.php';
new Initiator();
$dbProp = new \ReflectionProperty('FOGBase', 'DB');
$dbProp->setAccessible(true);
$db = new PDODB();
$dbProp->setValue(null, $db);
// save() stamps createdBy from the acting user and reads it unconditionally.
// Nothing is signed in here -- an invalid User is what a logged-out request
// already has.
$userProp = new \ReflectionProperty('FOGBase', 'FOGUser');
$userProp->setAccessible(true);
$userProp->setValue(null, new User(0));
// TaskState::getQueuedState() and friends fire a hook to let a plugin
// renumber the states, so the manager has to exist before any of them is
// asked. LoadGlobals is what seats it in a real request.
$hookProp = new \ReflectionProperty('FOGBase', 'HookManager');
$hookProp->setAccessible(true);
$hookProp->setValue(null, new HookManager());
$eventProp = new \ReflectionProperty('FOGBase', 'EventManager');
$eventProp->setAccessible(true);
$eventProp->setValue(null, new EventManager());

$failures = array();
$checks = 0;

$check = function ($label, $cond) use (&$failures, &$checks) {
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
};

// The column has to be nullable for the bug to exist at all. Say so, rather
// than passing quietly on a database that predates schema 284.
$colNull = $db->query(
    "SELECT `IS_NULLABLE` AS `n` FROM `information_schema`.`COLUMNS`"
    . " WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'tasks'"
    . " AND `COLUMN_NAME` = 'taskCheckIn'"
)->fetch()->get('n');
if ('YES' !== strtoupper((string) $colNull)) {
    echo "SKIP: tasks.taskCheckIn is not nullable here (schema 284 not applied)\n";
    exit(0);
}

// hostName is varchar(16) and unique; keep the whole fixture name inside it.
$mark = 'zzci' . (getmypid() % 100000);
register_shutdown_function(
    function () use ($db, $mark) {
        $db->query(
            "DELETE `tasks` FROM `tasks` JOIN `hosts`"
            . " ON `hosts`.`hostID` = `tasks`.`taskHostID`"
            . " WHERE `hosts`.`hostName` LIKE '" . $mark . "%'"
        );
        $db->query("DELETE FROM `hosts` WHERE `hostName` LIKE '" . $mark . "%'");
    }
);

$hostID = scopeHarnessInsert(
    $db,
    'hosts',
    array(
        'hostName' => $mark,
        'hostIP' => '',
        'hostUseAD' => '0',
    )
);

/*
 * A queued capture task with taskCheckIn as a real SQL NULL -- exactly what
 * save() now writes for a task nobody has checked in yet. scopeHarnessInsert()
 * would fill a NOT NULL column, so the NULL is stated explicitly.
 */
$taskID = scopeHarnessInsert(
    $db,
    'tasks',
    array(
        'taskName' => 'checkin fixture',
        'taskHostID' => (int) $hostID,
        'taskImageID' => 1,
        'taskStateID' => FOGCore::getQueuedState(),
        'taskTypeID' => 2,
        'taskCreateBy' => 'fog',
        'taskCheckIn' => null,
        'taskScheduledStartTime' => null,
    )
);

$stored = $db->query(
    'SELECT `taskCheckIn` AS `c` FROM `tasks` WHERE `taskID` = ' . (int) $taskID
)->fetch()->get('c');
$check('fixture really holds SQL NULL', null === $stored);

$Task = new Task($taskID);
$check('fixture task loads', $Task->isValid());
$check(
    "get('checkInTime') is null, which is the whole trigger",
    null === $Task->get('checkInTime')
);

/*
 * The call TaskQueue::checkIn() makes on its first line. Throwable, not
 * Exception: a TypeError is an Error, so the catch in TaskQueue::checkIn()
 * would not have caught it either -- which is why the failure reached the
 * client as a bodyless 500 rather than as a message.
 */
$thrown = null;
try {
    $Task->taskCheckIn();
} catch (\Throwable $e) {
    $thrown = get_class($e) . ': ' . $e->getMessage();
}
$check(
    'taskCheckIn() does not throw on a null check-in time' .
        (null === $thrown ? '' : ' -- ' . $thrown),
    null === $thrown
);

// And it did the work, not merely survived: a first check-in is expired by
// definition, so the task moves to the checked-in state and both times land.
$row = $db->query(
    'SELECT `taskStateID` AS `s`, `taskCheckIn` AS `c`,'
    . ' `taskScheduledStartTime` AS `t` FROM `tasks`'
    . ' WHERE `taskID` = ' . (int) $taskID
)->fetch();
$check(
    'task moved to the checked-in state',
    (int) $row->get('s') === (int) FOGCore::getCheckedInState()
);
$check('a check-in time was written', '' !== (string) $row->get('c'));
$check('a scheduled start time was written', '' !== (string) $row->get('t'));

// Second call: the "already checked in, not yet expiring" arm, which reads
// the value just written rather than a null. Guards against a fix that only
// works for the null case.
$thrown = null;
try {
    (new Task($taskID))->taskCheckIn();
} catch (\Throwable $e) {
    $thrown = get_class($e) . ': ' . $e->getMessage();
}
$check(
    'a repeat check-in does not throw either' .
        (null === $thrown ? '' : ' -- ' . $thrown),
    null === $thrown
);

if ($failures) {
    printf("FAIL (%d of %d checks)\n", count($failures), $checks);
    foreach ($failures as $f) {
        printf("  - %s\n", $f);
    }
    exit(1);
}
printf("ok (%d checks)\n", $checks);
exit(0);
