<?php
/**
 * A new Deploy must not inherit snapins an earlier task left queued, and a
 * Capture must not silently cancel them (forum 18325).
 *
 * THE FAILURE. A snapin job can outlive its task row: a Deploy queues
 * snapins, the client never runs them, the task ends, and the job stays
 * queued. Two paths then went wrong:
 *
 *  - Host::createImagePackage() cancels only a host's ACTIVE non-imaging
 *    task (6c8aa43cb), so a job with no task row survived a new Deploy. Its
 *    snapins ran on the new install, and a Deploy with snapins appended to
 *    that old job (Host::_createSnapinTasking reuses the host's snapinjob).
 *  - Group::createImagePackage() excludes only hosts whose active task is an
 *    init type, so a host with a queued snapin task got a Deploy row beside
 *    it, and a second queued job.
 *
 * THE RULE. A Deploy cancels the host's queued snapin work first. A Capture
 * refuses to start over queued snapins, unless the caller cancels them with
 * Host::cancelQueuedSnapins() (the "Cancel queued snapins first" option).
 *
 * Drives the real methods against the suite's fake database: every SELECT is
 * answered from a fixture keyed on the table name, and the captured INSERT
 * and UPDATE statements are what the assertions read. The storage node's
 * online probe is stubbed so no socket is opened.
 *
 * Usage: php tests/deploy-cancels-leftover-snapins.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\Group;
use FOG\Items\Host;
use FOG\Router\Route;

require __DIR__ . '/lib/fog-test-harness.php';

// A code path under test can end the process (Host::createImagePackage() echoes
// JSON and calls exit when it does not rethrow) with exit status 0. CI would
// read that as a pass, so a run that ends before its final report fails.
$GLOBALS['reported'] = false;
register_shutdown_function(function () {
    if (!$GLOBALS['reported']) {
        fwrite(STDERR, "FAIL: test ended before its final report\n");
        exit(1);
    }
});

FogTestHarness::boot('deploy-cancels-leftover-snapins');
$db = FogTestHarness::fakeDb();

/**
 * Stands in for FOGURLRequests: the master node lookup asks whether the node
 * is reachable, and the answer here is always yes.
 */
class AlwaysOnline
{
    public function isAvailable($ip, $timeout = 1, $port = -1)
    {
        return [true];
    }
}
FogTestHarness::setStatic('FOGBase', 'FOGURLRequests', new AlwaysOnline());

$fails = [];
$checks = 0;
function check($what, $ok, &$fails, &$checks)
{
    $checks++;
    if (!$ok) {
        $fails[] = $what;
    }
}

/**
 * One database row for $class: every column the model declares, '' unless
 * $values names it. A load runs arrayChangeKey() over the whole field map, so
 * a row missing a column warns on every read.
 */
function row($class, array $values)
{
    $obj = FOGCore::getClass($class);
    $p = new \ReflectionProperty($obj, 'databaseFields');
    $p->setAccessible(true);
    $row = [];
    foreach ($p->getValue($obj) as $column) {
        $row[$column] = '';
    }
    return array_merge($row, $values);
}

// The fixture: host 1 is assigned image 7, whose primary storage group 3 has
// node 5 as its enabled master. Snapin 9 exists for the Deploy-with-snapins
// case. Job 55 (with snapin task 70) is the leftover; $GLOBALS['leftoverOn']
// switches it on (a global, so the responder closure reads the live value).
$GLOBALS['leftoverOn'] = false;
// Active task rows the `tasks` reads answer with: an imaging task (the host is
// "in task") and snapin-typed tasks (type 12). Both empty by default.
$GLOBALS['activeTasks'] = [];
$GLOBALS['snapinTaskRows'] = [];
// Set to make the fixture image disabled, so a later check refuses the task.
$GLOBALS['imageOff'] = false;
// Active multicast sessions the pool reports; the fixture pool allows one.
$GLOBALS['mcActive'] = 0;
// The host ids each snapinJobs read asked about, in order.
$GLOBALS['jobSelectHosts'] = [];
$imageRow = row('Image', ['imageID' => 7, 'imageName' => 'win',
    'imagePath' => 'win', 'imageEnabled' => 1, 'imageTypeID' => 1,
    'imagePartitionTypeID' => 1, 'imageOSID' => 9, 'imageFormat' => 0,
    'imageProtect' => 0,
    'imageTypeName' => 'Single Disk', 'imageTypeValue' => 1,
    'imagePartitionTypeName' => 'Everything', 'imagePartitionTypeValue' => 1]);
$macRow = row('MACAddressAssociation', ['hmID' => 1, 'hmHostID' => 1,
    'hmMAC' => '00:11:22:33:44:55', 'hmPrimary' => 1]);
$fixture = [
    // A Host load joins its image's and primary MAC's columns into the host
    // row. Host 2 is the second member of the group cases.
    'hosts' => [
        array_merge($imageRow, $macRow, row('Host', ['hostID' => 1,
            'hostName' => 'host1', 'hostImage' => 7, 'hostPending' => 0])),
        array_merge($imageRow, $macRow, row('Host', ['hostID' => 2,
            'hostName' => 'host2', 'hostImage' => 7, 'hostPending' => 0])),
    ],
    'hostMAC' => [$macRow],
    'images' => [$imageRow],
    'imageGroupAssoc' => [['igaID' => 1, 'igaImageID' => 7,
        'igaStorageGroupID' => 3, 'igaPrimary' => 1]],
    'nfsGroups' => [row('StorageGroup', ['ngID' => 3, 'ngName' => 'default'])],
    'nfsGroupMembers' => [row('StorageNode', ['ngmID' => 5,
        'ngmMemberName' => 'master', 'ngmGroupID' => 3, 'ngmIsMasterNode' => 1,
        'ngmIsEnabled' => 1, 'ngmHostname' => '10.0.0.1', 'ngmInterface' => 'eth0',
        'ngmRootPath' => '/images', 'ngmFTPPath' => '/images', 'ngmUser' => 'fog',
        'ngmPass' => 'x', 'ngmMaxClients' => 10])],
    'snapins' => [row('Snapin', ['sID' => 9, 'sName' => 'tool',
        'sEnabled' => 1])],
    'taskTypes' => [
        row('TaskType', ['ttID' => 1, 'ttName' => 'Deploy', 'ttIcon' => 'd.png',
            'ttType' => 'down', 'ttIsAccess' => 'both']),
        row('TaskType', ['ttID' => 2, 'ttName' => 'Capture', 'ttIcon' => 'c.png',
            'ttType' => 'up', 'ttIsAccess' => 'host']),
        row('TaskType', ['ttID' => 8, 'ttName' => 'Multicast', 'ttIcon' => 'm.png',
            'ttType' => 'down', 'ttIsAccess' => 'both']),
        row('TaskType', ['ttID' => 12, 'ttName' => 'All Snapins', 'ttIcon' => 's.png',
            'ttType' => 'snapin', 'ttIsAccess' => 'both']),
    ],
];
// The leftover job and its snapin task, served only while the switch is on.
$leftoverRows = [
    'snapinJobs' => [row('SnapinJob', ['sjID' => 55, 'sjHostID' => 1,
        'sjStateID' => 1, 'sjAbortOnFail' => 0])],
    'snapinTasks' => [row('SnapinTask', ['stID' => 70, 'stJobID' => 55,
        'stState' => 1, 'stSnapinID' => 9, 'stSequence' => 1])],
];
// Route::listem() prepares on the raw PDO handle, not on query(). Answer
// those from the same fixture, narrowed by any `xID IN (...)` list.
$db->pdo = new class($fixture) extends FogFakePdo {
    private $fx;
    public function __construct(array $fx)
    {
        $this->fx = $fx;
    }
    public function prepare($sql)
    {
        $this->log[] = $sql;
        preg_match('/FROM `(\w+)`/', $sql, $m);
        $rows = $this->fx[$m[1] ?? ''] ?? [];
        if (preg_match('/`(\w+ID)` IN \(([^)]*)\)/', $sql, $in)) {
            $ids = array_map(function ($v) { return trim($v, "' "); }, explode(',', $in[2]));
            $rows = array_values(array_filter($rows, function ($r) use ($in, $ids) {
                return in_array((string)($r[$in[1]] ?? ''), $ids, true);
            }));
        }
        if (preg_match('/^\s*SELECT\s+COUNT/i', $sql)) {
            $n = count($rows);
            if ('multicastSessions' === ($m[1] ?? '')) {
                // How many multicast sessions are active (assertCapacity()).
                $n = (int)$GLOBALS['mcActive'];
            }
            return new FogFakeStatement([[0 => $n, 'cnt' => $n, 'total' => $n]]);
        }
        if (preg_match_all('/JOIN `(\w+)`/', $sql, $j)) {
            foreach ($j[1] as $joined) {
                foreach ($rows as &$r) {
                    $r = array_merge($this->fx[$joined][0] ?? [], $r);
                }
                unset($r);
            }
        }
        return new FogFakeStatement($rows);
    }
};

$inserts = [];
$updates = [];
$db->responder = function ($sql, $params) use ($fixture, $leftoverRows, &$inserts, &$updates) {
    if (preg_match('/^\s*INSERT INTO `(\w+)`/i', $sql, $m)) {
        $inserts[] = ['table' => $m[1], 'sql' => $sql, 'params' => $params];
        return [];
    }
    if (preg_match('/^\s*UPDATE `(\w+)`/i', $sql, $m)) {
        $updates[] = ['table' => $m[1], 'sql' => $sql, 'params' => $params];
        return [];
    }
    if (false !== stripos($sql, 'information_schema')) {
        return [];
    }
    if (!preg_match('/FROM `(\w+)`/i', $sql, $m)) {
        return null;
    }
    $table = $m[1];
    if (preg_match('/SELECT\s+COUNT/i', $sql)) {
        $n = count($fixture[$table] ?? []);
        if ('multicastSessions' === $table) {
            // How many multicast sessions are active (see assertCapacity()).
            $n = (int)$GLOBALS['mcActive'];
        }
        return [['total' => $n, 'cnt' => $n, 0 => $n]];
    }
    if ('globalSettings' === $table) {
        // The pool holds one session, so assertCapacity() refuses once
        // $GLOBALS['mcActive'] reaches 1. Any other setting reads as unset.
        $settings = [];
        if (false !== strpos($sql, 'FOG_MULTICAST_MAX_SESSIONS')) {
            $settings[] = ['settingKey' => 'FOG_MULTICAST_MAX_SESSIONS',
                'settingValue' => '1'];
        }
        return $settings;
    }
    // The host ids a list query filters on (`xHostID IN (:where_0_*)`).
    $listedHosts = [];
    foreach ($params as $key => $val) {
        if (preg_match('/^:?where_0_\d+$/', $key)) {
            $listedHosts[] = (int)$val;
        }
    }
    if ('tasks' === $table) {
        // Active tasks come from two pools. A query for the snapin task
        // types (12, 13) gets the snapin pool; everything else -- the init
        // type filter, a host's own active task -- gets the imaging pool.
        // A single-entity load looks in both.
        $typeVals = [];
        foreach ($params as $key => $val) {
            if (preg_match('/^:?where_2_\d+$/', $key)) {
                $typeVals[] = (int)$val;
            }
        }
        sort($typeVals);
        $pool = $GLOBALS['activeTasks'];
        if ([12, 13] === $typeVals) {
            $pool = $GLOBALS['snapinTaskRows'];
        } elseif (preg_match('/WHERE `taskID`=:id\b/', $sql)) {
            $pool = array_merge($GLOBALS['activeTasks'], $GLOBALS['snapinTaskRows']);
        }
        if ($listedHosts) {
            $pool = array_values(array_filter($pool, function ($r) use ($listedHosts) {
                return in_array((int)$r['taskHostID'], $listedHosts, true);
            }));
        }
        $rows = $pool;
    } elseif (isset($leftoverRows[$table])) {
        if ('snapinJobs' === $table) {
            $GLOBALS['jobSelectHosts'][] = $listedHosts;
        }
        // The leftover job is the host's only queued snapin work.
        $rows = $GLOBALS['leftoverOn'] ? $leftoverRows[$table] : [];
        if ($listedHosts && 'snapinJobs' === $table) {
            $rows = array_merge([], array_filter($rows, function ($r) use ($listedHosts) {
                return in_array((int)$r['sjHostID'], $listedHosts, true);
            }));
        }
        return $rows;
    } else {
        $rows = $fixture[$table] ?? [];
    }
    if ($GLOBALS['imageOff'] && in_array($table, ['hosts', 'images'], true)) {
        // Switch the fixture image off for the "later check refuses" cases.
        $rows = array_map(function ($r) {
            return array_merge($r, ['imageEnabled' => 0]);
        }, $rows);
    }
    // A single-entity load reads fetch()->get() as ONE row; a manager find
    // reads a list. Tell them apart by the load's shape.
    if (preg_match('/WHERE `(\w+)`=:id\b/', $sql, $m)) {
        $id = $params[':id'] ?? $params['id'] ?? null;
        foreach ($rows as $row) {
            if ((string)($row[$m[1]] ?? '') === (string)$id) {
                return $row;
            }
        }
        return [];
    }
    return $rows;
};

/**
 * The object the callers pass: the tasktype getter's output, not a TaskType.
 */
function taskTypeObject($id)
{
    Route::indiv('tasktype', $id);
    return json_decode(Route::getData());
}

/**
 * Task rows (as the INSERT's bound values) written for one call.
 */
function taskRows(array $inserts)
{
    $rows = [];
    foreach ($inserts as $ins) {
        if ('tasks' !== $ins['table']) {
            continue;
        }
        $byRow = [];
        foreach ($ins['params'] as $key => $val) {
            // Group::createImagePackage() batch-inserts (hostID_0 ...);
            // Host::createImagePackage() saves one Task (taskHostID_insert).
            if (preg_match('/^:?(\w+?)_(\d+|insert)$/', $key, $m)) {
                $byRow[(int)$m[2]][$m[1]] = $val;
            }
        }
        foreach ($byRow as $row) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * The UPDATEs written to $table so far.
 */
function updatesTo($table)
{
    global $updates;
    return array_values(array_filter($updates, function ($u) use ($table) {
        return $table === $u['table'];
    }));
}

/**
 * How many UPDATEs were written since the last reset.
 */
function updateCount()
{
    global $updates;
    return count($updates);
}

/**
 * The INSERTs written to $table so far.
 */
function insertsTo($table)
{
    global $inserts;
    return array_values(array_filter($inserts, function ($i) use ($table) {
        return $table === $i['table'];
    }));
}

/**
 * Task host 1 through Host::createImagePackage(). Returns the exception
 * message, or '' when the call went through.
 *
 * Host::createImagePackage() rethrows under the CLI (and for the iPXE boot
 * script) rather than echoing JSON and calling exit, so this test, which runs
 * under the CLI, reads the refusal directly. No scriptname override is needed.
 */
function taskHost($tasktype, $snapins = false, $cancelSnapins = false)
{
    global $inserts, $updates;
    $inserts = [];
    $updates = [];
    $error = '';
    try {
        (new Host(1))->createImagePackage(
            $tasktype,
            'Task',
            false,
            false,
            $snapins,
            false,
            'tester',
            '',
            false,
            false,
            false,
            false,
            0,
            0,
            $cancelSnapins
        );
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
    return $error;
}

/**
 * Task a selection through Group::createImagePackage(), the way the host
 * list and POST /group/{id}/task do. Returns the exception message or ''.
 */
function taskSelection(array $hosts, $tasktype, $cancelSnapins = false)
{
    global $inserts, $updates;
    $inserts = [];
    $updates = [];
    $GLOBALS['jobSelectHosts'] = [];
    $error = '';
    $Group = (new Group())
        ->set('name', 'selection')
        ->set('hosts', $hosts);
    try {
        $Group->createImagePackage(
            $tasktype,
            'Task',
            false,
            false,
            false,
            true,
            'tester',
            '',
            false,
            false,
            false,
            false,
            0,
            0,
            $cancelSnapins
        );
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
    return $error;
}

$refusal = 'Host has queued snapins. Let them finish, or cancel them, before a capture.';
$deploy = taskTypeObject(1);
$capture = taskTypeObject(2);
check('fixture: tasktype 1 is a deploy', $deploy && $deploy->isDeploy && !$deploy->isCapture, $fails, $checks);
check('fixture: tasktype 2 is a capture', $capture && $capture->isCapture && !$capture->isDeploy, $fails, $checks);

// 1. Host Deploy over a leftover job and no active task: the job and its
//    snapin tasks are cancelled, and the Deploy task is still written.
$GLOBALS['leftoverOn'] = true;
$error = taskHost($deploy);
check('host deploy over a leftover job throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('host deploy cancels the leftover snapin tasks', count(updatesTo('snapinTasks')) >= 1, $fails, $checks);
check('host deploy cancels the leftover snapin job', count(updatesTo('snapinJobs')) >= 1, $fails, $checks);
$jobUpdate = updatesTo('snapinJobs')[0] ?? ['params' => []];
check('the job update targets job 55', in_array(55, array_map('intval', array_values($jobUpdate['params'])), true), $fails, $checks);
check('host deploy still inserts its task row', 1 === count(taskRows($inserts)), $fails, $checks);

// 2. Host Deploy with a snapin over a leftover job: the snapin goes into a
//    NEW job, not job 55.
$error = taskHost($deploy, 9);
check('host deploy with snapins throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('host deploy with snapins opens a new snapin job', 1 === count(insertsTo('snapinJobs')), $fails, $checks);
$snapinTaskInserts = insertsTo('snapinTasks');
$jobIDs = [];
foreach ($snapinTaskInserts as $ins) {
    foreach ($ins['params'] as $key => $val) {
        if (preg_match('/^:?jobID_\d+$/', $key)) {
            $jobIDs[] = (int)$val;
        }
    }
}
check('the new snapin task is inserted', 1 === count($jobIDs), $fails, $checks);
check('the new snapin task is not appended to the old job 55', !in_array(55, $jobIDs, true), $fails, $checks);

// 3. Host Capture over a queued snapin job: refused, nothing written.
$error = taskHost($capture);
check('host capture over queued snapins is refused (got: ' . $error . ')', $refusal === $error, $fails, $checks);
check('the refused capture writes no task row', 0 === count(taskRows($inserts)), $fails, $checks);
check('the refused capture cancels nothing', 0 === updateCount(), $fails, $checks);

// 4. Host Capture with no snapin job: proceeds.
$GLOBALS['leftoverOn'] = false;
$error = taskHost($capture);
check('host capture with no snapin job throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('host capture with no snapin job inserts its task row', 1 === count(taskRows($inserts)), $fails, $checks);

// 5. Group Deploy: the host's queued snapin work is cancelled before the
//    task row is written.
$GLOBALS['leftoverOn'] = true;
$error = taskSelection([1], $deploy);
check('group deploy throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('group deploy cancels the leftover snapin tasks', count(updatesTo('snapinTasks')) >= 1, $fails, $checks);
check('group deploy cancels the leftover snapin job', count(updatesTo('snapinJobs')) >= 1, $fails, $checks);
check('group deploy still inserts its task row', 1 === count(taskRows($inserts)), $fails, $checks);

// 6. Group Capture (one host) over a queued snapin job: refused.
$error = taskSelection([1], $capture);
check('group capture over queued snapins is refused (got: ' . $error . ')', $refusal === $error, $fails, $checks);
check('the refused group capture writes no task row', 0 === count(taskRows($inserts)), $fails, $checks);
check('the refused group capture cancels nothing', 0 === updateCount(), $fails, $checks);

// 7. The opt-in: cancelling first (what the "Cancel queued snapins first"
//    option does) lets the capture through. The helper reads the fixture, so
//    the job is still served after the cancel; only the writes are asserted.
$updates = [];
Host::cancelQueuedSnapins([1]);
check('cancelQueuedSnapins cancels the snapin tasks', 1 <= count(updatesTo('snapinTasks')), $fails, $checks);
check('cancelQueuedSnapins cancels the snapin job', 1 <= count(updatesTo('snapinJobs')), $fails, $checks);
check('queuedSnapinHosts names the host with a live job', [1] === Host::queuedSnapinHosts([1, 2]), $fails, $checks);
$GLOBALS['leftoverOn'] = false;
check('queuedSnapinHosts is empty with no live job', [] === Host::queuedSnapinHosts([1]), $fails, $checks);
check('queuedSnapinHosts is empty for no hosts', [] === Host::queuedSnapinHosts([]), $fails, $checks);

// 8. The active All Snapins task row is cancelled too (not just the job).
//    Called directly: a Host Deploy would also cancel it through Task::cancel(),
//    which would hide a missing TaskManager update in the helper.
$GLOBALS['leftoverOn'] = false;
$GLOBALS['snapinTaskRows'] = [row('Task', ['taskID' => 77, 'taskHostID' => 1,
    'taskTypeID' => 12, 'taskStateID' => 1])];
$updates = [];
Host::cancelQueuedSnapins([1]);
$taskUpdate = updatesTo('tasks')[0] ?? ['params' => []];
check('cancelQueuedSnapins cancels the active All Snapins task row', 1 === count(updatesTo('tasks')), $fails, $checks);
check('the task update targets task 77', in_array(77, array_map('intval', array_values($taskUpdate['params'])), true), $fails, $checks);
$GLOBALS['snapinTaskRows'] = [];

// 9. Capture with the opt-in over queued snapins: they are cancelled and the
//    capture task is written.
$GLOBALS['leftoverOn'] = true;
$error = taskHost($capture, false, true);
check('host capture with the opt-in throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('host capture with the opt-in cancels the snapin job', count(updatesTo('snapinJobs')) >= 1, $fails, $checks);
check('host capture with the opt-in inserts its task row', 1 === count(taskRows($inserts)), $fails, $checks);

// 10. Capture with the opt-in on a host already in an imaging task: refused
//     by InTask, and nothing is cancelled.
// A Task load joins its task type's columns in, so the row carries them.
$GLOBALS['activeTasks'] = [array_merge($fixture['taskTypes'][0],
    row('Task', ['taskID' => 88, 'taskHostID' => 1,
    'taskTypeID' => 1, 'taskStateID' => 1]))];
$error = taskHost($capture, false, true);
check('host capture with the opt-in is refused when in an imaging task (got: ' . $error . ')', '' !== $error, $fails, $checks);
check('the refused in-task capture cancels nothing', 0 === updateCount(), $fails, $checks);
check('the refused in-task capture writes no task row', 0 === count(taskRows($inserts)), $fails, $checks);
$GLOBALS['activeTasks'] = [];

// 11. A Deploy refused by a later check (image not enabled) cancels nothing,
//     for a host and for a group.
$GLOBALS['imageOff'] = true;
$error = taskHost($deploy);
check('host deploy with a disabled image is refused (got: ' . $error . ')', 'Image is not enabled' === $error, $fails, $checks);
check('the refused host deploy cancels nothing', 0 === updateCount(), $fails, $checks);
$error = taskSelection([1], $deploy);
check('group deploy with a disabled image is refused (got: ' . $error . ')', 'Image is not enabled' === $error, $fails, $checks);
check('the refused group deploy cancels nothing', 0 === updateCount(), $fails, $checks);
$GLOBALS['imageOff'] = false;

// 12. Group capture with the opt-in cancels only for the host being tasked,
//     not for a member that is in an active imaging task (host 2).
$GLOBALS['leftoverOn'] = true;
$GLOBALS['activeTasks'] = [row('Task', ['taskID' => 89, 'taskHostID' => 2,
    'taskTypeID' => 1, 'taskStateID' => 1])];
$error = taskSelection([1, 2], $capture, true);
check('group capture with the opt-in throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('group capture with the opt-in cancels the snapin job', count(updatesTo('snapinJobs')) >= 1, $fails, $checks);
$askedHosts = array_unique(array_merge([], ...$GLOBALS['jobSelectHosts']));
check('the cancel asks about host 1 only (got: ' . implode(',', $askedHosts) . ')', [1] === array_values($askedHosts), $fails, $checks);
$taskedRows = taskRows($inserts);
check('group capture with the opt-in tasks only host 1', 1 === count($taskedRows) && 1 === (int)($taskedRows[0]['hostID'] ?? 0), $fails, $checks);
$GLOBALS['activeTasks'] = [];

// 13. A host's ACTIVE queued All Snapins task is set aside early but cancelled
//     late: a Deploy refused by a later check (image disabled) leaves it and
//     its job alone, and a Deploy that goes through cancels it. The stale
//     task's cancel saves the task row by its id (77); nothing else does.
$GLOBALS['leftoverOn'] = true;
$GLOBALS['activeTasks'] = [array_merge($fixture['taskTypes'][3],
    row('Task', ['taskID' => 77, 'taskHostID' => 1,
    'taskTypeID' => 12, 'taskStateID' => 1]))];
$GLOBALS['imageOff'] = true;
$error = taskHost($deploy);
check('deploy over a queued snapin task is refused by the image check (got: ' . $error . ')', 'Image is not enabled' === $error, $fails, $checks);
check('the refused deploy leaves the snapin task and its job alone', 0 === updateCount() && 0 === count(insertsTo('tasks')) && 0 === count(insertsTo('snapinJobs')), $fails, $checks);
$GLOBALS['imageOff'] = false;
$error = taskHost($deploy);
check('deploy over a queued snapin task throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
$staleSaved = array_filter(insertsTo('tasks'), function ($ins) {
    return in_array(77, array_map('intval', array_values($ins['params'])), true);
});
check('the deploy that goes through cancels the stale snapin task', 1 === count($staleSaved), $fails, $checks);
$GLOBALS['activeTasks'] = [];

// 14. Group multicast Deploy over a leftover job: the job is cancelled.
$multicast = taskTypeObject(8);
check('fixture: tasktype 8 is a multicast', $multicast && $multicast->isMulticast, $fails, $checks);
$GLOBALS['leftoverOn'] = true;
$error = taskSelection([1], $multicast);
check('group multicast deploy throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('group multicast deploy cancels the leftover snapin job', count(updatesTo('snapinJobs')) >= 1, $fails, $checks);
check('group multicast deploy still inserts its task row', 1 === count(taskRows($inserts)), $fails, $checks);

// 15. Group capture with the opt-in over queued snapins proceeds (and the
//     same capture without it is refused, in case 6).
$error = taskSelection([1], $capture, true);
check('group capture with the opt-in over queued snapins proceeds (got: ' . $error . ')', '' === $error, $fails, $checks);
check('and inserts its task row', 1 === count(taskRows($inserts)), $fails, $checks);

// 16. Multicast refused by assertCapacity() (the pool is full) over a leftover
//     job cancels nothing and writes nothing, for a host and for a group.
$GLOBALS['leftoverOn'] = true;
$GLOBALS['mcActive'] = 1;
$error = taskHost($multicast);
check('host multicast with a full pool is refused (got: ' . $error . ')', false !== strpos($error, 'only configured to run 1 multicast task'), $fails, $checks);
check('the refused host multicast cancels nothing', 0 === updateCount(), $fails, $checks);
check('the refused host multicast writes no task, job or session row', 0 === count(insertsTo('tasks')) + count(insertsTo('snapinJobs')) + count(insertsTo('multicastSessions')), $fails, $checks);
$error = taskSelection([1], $multicast);
check('group multicast with a full pool is refused (got: ' . $error . ')', false !== strpos($error, 'only configured to run 1 multicast task'), $fails, $checks);
check('the refused group multicast cancels nothing', 0 === updateCount(), $fails, $checks);
$GLOBALS['mcActive'] = 0;

// 17. Group capture with the opt-in on TWO available hosts: refused as a
//     one-host task type, and nothing is cancelled.
$error = taskSelection([1, 2], $capture, true);
check('group capture on two hosts is refused (got: ' . $error . ')', false !== strpos($error, 'one host at a time'), $fails, $checks);
check('the refused two-host capture cancels nothing', 0 === updateCount(), $fails, $checks);
check('the refused two-host capture writes no task row', 0 === count(taskRows($inserts)), $fails, $checks);

$GLOBALS['reported'] = true;
if (count($fails)) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    echo count($fails) . " of $checks checks failed\n";
    exit(1);
}
echo "PASS: $checks checks\n";
exit(0);
