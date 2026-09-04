<?php
/**
 * A capture through Group::createImagePackage() must write a task row.
 *
 * The host list's selection tasking (HostManagement::deployMultiPost()) and
 * POST /group/{id}/task both go through Group::createImagePackage(), and
 * inside its imaging branch there were only multicast and deploy arms. A
 * capture matched neither, fell out the bottom without inserting anything,
 * and the caller then answered 201 "Tasking created" over an empty tasks
 * table -- the host never captured (#1677).
 *
 * Drives the real method against the suite's fake database: every SELECT it
 * makes is answered from a fixture keyed on the table name, and the INSERT
 * into `tasks` is what the assertions read. The storage node's online probe
 * is stubbed so no socket is opened.
 *
 * Usage: php tests/group-capture-tasking.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\Group;
use FOG\Router\Route;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('group-capture-tasking');
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

// The fixture: hosts 1 and 2 both assigned image 7, whose primary storage
// group 3 has node 5 as its enabled master.
$hostRows = [];
foreach ([1, 2] as $hid) {
    $hostRows[] = row('Host', ['hostID' => $hid, 'hostName' => "host$hid",
        'hostImage' => 7, 'hostPending' => 0]);
}
$fixture = [
    'hosts' => $hostRows,
    // The host list joins the primary MAC in; one row keeps the read quiet.
    'hostMAC' => [['hmID' => 1, 'hmHostID' => 1, 'hmMAC' => '00:11:22:33:44:55',
        'hmPrimary' => 1]],
    'images' => [row('Image', ['imageID' => 7, 'imageName' => 'win',
        'imagePath' => 'win', 'imageEnabled' => 1, 'imageTypeID' => 1,
        'imagePartitionTypeID' => 1, 'imageOSID' => 9, 'imageFormat' => 0,
        'imageProtect' => 0,
        // Columns the image's ImageType / ImagePartitionType relations read
        // out of the same row (a real load joins them in).
        'imageTypeName' => 'Single Disk', 'imageTypeValue' => 1,
        'imagePartitionTypeName' => 'Everything', 'imagePartitionTypeValue' => 1])],
    'imageGroupAssoc' => [['igaID' => 1, 'igaImageID' => 7,
        'igaStorageGroupID' => 3, 'igaPrimary' => 1]],
    'nfsGroups' => [row('StorageGroup', ['ngID' => 3, 'ngName' => 'default'])],
    'nfsGroupMembers' => [row('StorageNode', ['ngmID' => 5,
        'ngmMemberName' => 'master', 'ngmGroupID' => 3, 'ngmIsMasterNode' => 1,
        'ngmIsEnabled' => 1, 'ngmHostname' => '10.0.0.1', 'ngmInterface' => 'eth0',
        'ngmRootPath' => '/images', 'ngmFTPPath' => '/images', 'ngmUser' => 'fog',
        'ngmPass' => 'x', 'ngmMaxClients' => 10])],
    'taskTypes' => [
        row('TaskType', ['ttID' => 1, 'ttName' => 'Deploy', 'ttIcon' => 'd.png',
            'ttType' => 'down', 'ttIsAccess' => 'both']),
        row('TaskType', ['ttID' => 2, 'ttName' => 'Capture', 'ttIcon' => 'c.png',
            'ttType' => 'up', 'ttIsAccess' => 'host']),
    ],
];
// Route::listem() -- which getMasterStorageNode() and the deploy arm's
// Route::getList() run through -- prepares on the raw PDO handle, not on
// query(). Answer those from the same fixture, narrowed by any `xID IN (...)`
// list in the WHERE, with a LEFT JOINed table's row merged in as a real join
// would. The harness default synthesizes rows numbered 1..n, and a node
// numbered 1 does not exist in the fixture, so its follow-up load answered 404.
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
            return new FogFakeStatement([[0 => $n, 'cnt' => $n]]);
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
$db->responder = function ($sql, $params) use ($fixture, &$inserts) {
    if (preg_match('/^\s*INSERT INTO `(\w+)`/i', $sql, $m)) {
        $inserts[] = ['table' => $m[1], 'sql' => $sql, 'params' => $params];
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
        return [['total' => $n, 'cnt' => $n, 0 => $n]];
    }
    if ('tasks' === $table) {
        return [];        // nothing queued or in progress
    }
    $rows = $fixture[$table] ?? [];
    // A single-entity load (FOGController::load) reads fetch()->get() as ONE
    // row; a manager find reads a list. Tell them apart by the load's shape.
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
 * The object both callers pass: the tasktype getter's output, not a TaskType.
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
        // One bound parameter set per row, keyed by the model's field name
        // and the row number: hostID_0, typeID_0, hostID_1 ...
        $byRow = [];
        foreach ($ins['params'] as $key => $val) {
            if (preg_match('/^:?(\w+?)_(\d+)$/', $key, $m)) {
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
 * Task a selection of hosts through Group::createImagePackage(), the way
 * the host list and POST /group/{id}/task do. Returns [task rows written,
 * exception message or ''].
 */
function taskSelection(array $hosts, $tasktype)
{
    global $inserts;
    $inserts = [];
    $error = '';
    $Group = (new Group())
        ->set('name', 'selection')
        ->set('hosts', $hosts);
    try {
        $Group->createImagePackage($tasktype, 'Capture Task', false, false, false, true, 'tester');
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
    return [taskRows($inserts), $error];
}

$capture = taskTypeObject(2);
check('fixture: tasktype 2 is a capture', $capture && $capture->isCapture && !$capture->isDeploy, $fails, $checks);

// 1. One host, capture: the bug. A row must land in `tasks`, for that host,
//    with the capture type, pointed at the master node.
list($rows, $error) = taskSelection([1], $capture);
check('one-host capture throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('one-host capture inserts exactly one task row (got ' . count($rows) . ')', 1 === count($rows), $fails, $checks);
$row = $rows[0] ?? [];
check('the row is for host 1', 1 === (int)($row['hostID'] ?? 0), $fails, $checks);
check('the row is a capture (typeID 2)', 2 === (int)($row['typeID'] ?? 0), $fails, $checks);
check('the row is on the master node (5)', 5 === (int)($row['storagenodeID'] ?? 0), $fails, $checks);
check('the row carries the host\'s image (7)', 7 === (int)($row['imageID'] ?? 0), $fails, $checks);

// 2. Two hosts, capture: refused, nothing written. Capture is a one-host
//    task type and two hosts writing the same image is a race.
list($rows, $error) = taskSelection([1, 2], $capture);
check('two-host capture is refused (got: ' . $error . ')', false !== strpos($error, 'one host at a time'), $fails, $checks);
check('two-host capture writes no task row', 0 === count($rows), $fails, $checks);

// 3. Two hosts, deploy: unchanged -- one row per host.
$deploy = taskTypeObject(1);
list($rows, $error) = taskSelection([1, 2], $deploy);
check('two-host deploy throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('two-host deploy inserts two rows (got ' . count($rows) . ')', 2 === count($rows), $fails, $checks);

if (count($fails)) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    echo count($fails) . " of $checks checks failed\n";
    exit(1);
}
echo "PASS: $checks checks\n";
exit(0);
