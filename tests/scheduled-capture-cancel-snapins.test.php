<?php
/**
 * A scheduled Capture cancels the host's queued snapins when its row says so
 * (forum 18325).
 *
 * THE FAILURE. PR #2127 made a Capture refuse to start over queued snapins,
 * unless the caller passes $cancelSnapins (the instant form's "Cancel queued
 * snapins first" box). A scheduled Capture had no place to keep that choice:
 * scheduledTasks.stOther1..stOther5 were all in use. So a scheduled Capture
 * over snapins that never finish was skipped on every cycle, for ever.
 *
 * THE RULE. A scheduled Capture behaves like an instant one, option
 * included. The option lives in scheduledTasks.stCancelSnapins (schema 467),
 * the page stores it, and FOGTaskScheduler passes it on as the 15th argument
 * of createImagePackage().
 *
 * Part 1 drives the REAL FOGTaskScheduler::_commonOutput() against the
 * suite's fake database: one active scheduled Capture over host 1, whose
 * snapin job 55 never finished. Part 2 reads the source of the two pages
 * that store the option. Part 3 reads the schema step.
 *
 * The step itself runs against a real server in
 * tests/schema-467-scheduled-cancel-snapins-db.test.php.
 *
 * Usage: php tests/scheduled-capture-cancel-snapins.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\ScheduledTask;
use FOG\Router\Route;
use FOG\Service\TaskScheduler;

require __DIR__ . '/lib/fog-test-harness.php';

// The daemon pass can end the process; a run that ends before its report
// must not read as a pass.
$GLOBALS['reported'] = false;
register_shutdown_function(function () {
    if (!$GLOBALS['reported']) {
        fwrite(STDERR, "FAIL: test ended before its final report\n");
        exit(1);
    }
});

FogTestHarness::boot('scheduled-capture-cancel-snapins');
$db = FogTestHarness::fakeDb();

/**
 * Stands in for FOGURLRequests: the storage node is always reachable.
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
 * One database row for $class: every declared column, '' unless named.
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

// Host 1 has image 7 on storage group 3 (node 5). Job 55 / snapin task 70
// is the queued snapin work. The scheduled row is switched per case by
// $GLOBALS['stCancel'].
$GLOBALS['stCancel'] = 0;
$imageRow = row('Image', ['imageID' => 7, 'imageName' => 'win',
    'imagePath' => 'win', 'imageEnabled' => 1, 'imageTypeID' => 1,
    'imagePartitionTypeID' => 1, 'imageOSID' => 9, 'imageFormat' => 0,
    'imageProtect' => 0,
    'imageTypeName' => 'Single Disk', 'imageTypeValue' => 1,
    'imagePartitionTypeName' => 'Everything', 'imagePartitionTypeValue' => 1]);
$macRow = row('MACAddressAssociation', ['hmID' => 1, 'hmHostID' => 1,
    'hmMAC' => '00:11:22:33:44:55', 'hmPrimary' => 1]);
$fixture = [
    'hosts' => [
        array_merge($imageRow, $macRow, row('Host', ['hostID' => 1,
            'hostName' => 'host1', 'hostImage' => 7, 'hostPending' => 0])),
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
        row('TaskType', ['ttID' => 2, 'ttName' => 'Capture', 'ttIcon' => 'c.png',
            'ttType' => 'up', 'ttIsAccess' => 'host']),
    ],
    'snapinJobs' => [row('SnapinJob', ['sjID' => 55, 'sjHostID' => 1,
        'sjStateID' => 1, 'sjAbortOnFail' => 0])],
    'snapinTasks' => [row('SnapinTask', ['stID' => 70, 'stJobID' => 55,
        'stState' => 1, 'stSnapinID' => 9, 'stSequence' => 1])],
];
// Route::listem() prepares on the raw PDO handle. Answer it from the fixture
// (the scheduled row is built per call, so it follows $GLOBALS['stCancel']).
$fixtureFor = function ($table) use ($fixture) {
    if ('scheduledTasks' === $table) {
        return [row('ScheduledTask', ['stID' => 31, 'stName' => 'Nightly capture',
            'stType' => 'C', 'stTaskTypeID' => 2, 'stMinute' => '*',
            'stHour' => '*', 'stDOM' => '*', 'stMonth' => '*', 'stDOW' => '*',
            'stIsGroup' => 0, 'stGroupHostID' => 1, 'stShutDown' => '',
            'stOther1' => 0, 'stOther2' => 0, 'stOther3' => 'tester',
            'stOther4' => 0, 'stOther5' => 0, 'stActive' => 1,
            'stCancelSnapins' => $GLOBALS['stCancel']])];
    }
    return $fixture[$table] ?? [];
};
$db->pdo = new class($fixtureFor) extends FogFakePdo {
    private $fx;
    public function __construct($fx)
    {
        $this->fx = $fx;
    }
    public function prepare($sql)
    {
        $this->log[] = $sql;
        preg_match('/FROM `(\w+)`/', $sql, $m);
        $fx = $this->fx;
        $rows = $fx($m[1] ?? '');
        if (preg_match('/`(\w+ID)` IN \(([^)]*)\)/', $sql, $in)) {
            $ids = array_map(function ($v) { return trim($v, "' "); }, explode(',', $in[2]));
            $rows = array_values(array_filter($rows, function ($r) use ($in, $ids) {
                return in_array((string)($r[$in[1]] ?? ''), $ids, true);
            }));
        }
        if (preg_match('/^\s*SELECT\s+COUNT/i', $sql)) {
            $n = count($rows);
            return new FogFakeStatement([[0 => $n, 'cnt' => $n, 'total' => $n]]);
        }
        if (preg_match_all('/JOIN `(\w+)`/', $sql, $j)) {
            foreach ($j[1] as $joined) {
                foreach ($rows as &$r) {
                    $r = array_merge($fx($joined)[0] ?? [], $r);
                }
                unset($r);
            }
        }
        return new FogFakeStatement($rows);
    }
};

$inserts = [];
$updates = [];
$db->responder = function ($sql, $params) use ($fixtureFor, &$inserts, &$updates) {
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
        $n = count($fixtureFor($table));
        return [['total' => $n, 'cnt' => $n, 0 => $n]];
    }
    if ('globalSettings' === $table) {
        // The scheduler is on. Every other setting reads as unset.
        $settings = [];
        if (false !== strpos($sql, 'SCHEDULERGLOBALENABLED')) {
            $settings[] = ['settingKey' => 'SCHEDULERGLOBALENABLED',
                'settingValue' => '1'];
        }
        return $settings;
    }
    // No active task, no power-management row: only the scheduled row runs.
    if (in_array($table, ['tasks', 'powerManagement'], true)) {
        return [];
    }
    $rows = $fixtureFor($table);
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
 * Run one daemon pass over the scheduled Capture. Returns the log it wrote.
 */
function runPass($cancel)
{
    global $inserts, $updates;
    $inserts = [];
    $updates = [];
    $GLOBALS['stCancel'] = $cancel;
    $log = tempnam(sys_get_temp_dir(), 'schedlog');
    $ref = new \ReflectionClass(TaskScheduler::class);
    // Each run is a first pass. The scheduler remembers its previous pass,
    // and runs a minute only once, so a second pass in the same minute
    // would not run the `* * * * *` task again.
    $last = $ref->getProperty('_lastPass');
    $last->setAccessible(true);
    $last->setValue(null, null);
    $sched = $ref->newInstanceWithoutConstructor();
    $prop = $ref->getProperty('log');
    $prop->setAccessible(true);
    $prop->setValue(null, $log);
    $method = $ref->getMethod('_commonOutput');
    $method->setAccessible(true);
    $method->invoke($sched);
    $out = (string)file_get_contents($log);
    unlink($log);
    return $out;
}

/**
 * The statements written to $table by $list.
 */
function writesTo(array $list, $table)
{
    return array_values(array_filter($list, function ($w) use ($table) {
        return $table === $w['table'];
    }));
}

// ---- 1. cancelSnapins = 1: the snapins are canceled, the capture starts ---

$log = runPass(1);
check('fixture: the scheduled capture was found and run',
    false !== strpos($log, 'Found a scheduled task that should run'), $fails, $checks);
check('cancelSnapins=1: the queued snapin job is canceled',
    count(writesTo($updates, 'snapinJobs')) >= 1, $fails, $checks);
check('cancelSnapins=1: the queued snapin tasks are canceled',
    count(writesTo($updates, 'snapinTasks')) >= 1, $fails, $checks);
check('cancelSnapins=1: the capture task is inserted',
    count(writesTo($inserts, 'tasks')) >= 1, $fails, $checks);
check('cancelSnapins=1: the task is not skipped',
    false === strpos($log, 'Skipping scheduled task'), $fails, $checks);
check('cancelSnapins=1: the run ends with Task started',
    false !== strpos($log, 'Task started for'), $fails, $checks);

// ---- 2. cancelSnapins = 0: refused, nothing canceled, the daemon goes on --

$log = runPass(0);
check('cancelSnapins=0: the task is skipped and logged',
    false !== strpos($log, 'Skipping scheduled task 31'), $fails, $checks);
check('cancelSnapins=0: the log says why (queued snapins)',
    false !== strpos($log, 'queued snapins'), $fails, $checks);
check('cancelSnapins=0: no snapin job is canceled',
    0 === count(writesTo($updates, 'snapinJobs')), $fails, $checks);
check('cancelSnapins=0: no snapin task is canceled',
    0 === count(writesTo($updates, 'snapinTasks')), $fails, $checks);
check('cancelSnapins=0: no capture task is inserted',
    0 === count(writesTo($inserts, 'tasks')), $fails, $checks);
check('cancelSnapins=0: the daemon pass still reaches the power-management loop',
    false !== strpos($log, 'No unrunnable tasks found') || false !== strpos($log, 'Checking for expired'), $fails, $checks);

// ---- 3. the model, the pages and the schema step ---------------------------

$web = dirname(__DIR__) . '/packages/web';
$model = (new \ReflectionProperty(new ScheduledTask(), 'databaseFields'));
$model->setAccessible(true);
check('ScheduledTask maps cancelSnapins to stCancelSnapins',
    'stCancelSnapins' === ($model->getValue(new ScheduledTask())['cancelSnapins'] ?? null), $fails, $checks);

foreach (['HostManagement', 'GroupManagement'] as $page) {
    $src = (string)file_get_contents("$web/src/Pages/$page.php");
    check("$page reads the cancelSnapins box",
        false !== strpos($src, "\$cancelSnapins = isset(\$_POST['cancelSnapins'])"), $fails, $checks);
    check("$page stores it on the scheduled row",
        (bool)preg_match("/->set\\('cancelSnapins', \\(int\\)\\\$cancelSnapins\\)/", $src), $fails, $checks);
    check("$page passes it on the instant path (15th argument)",
        (bool)preg_match('/\$snapinAbortOnFailure,\s*0,\s*0,\s*\$cancelSnapins\s*\)/', $src), $fails, $checks);
}

$schema = (string)file_get_contents("$web/commons/schema.php");
check('schema.php carries a step labeled 467',
    false !== strpos($schema, "\n// 467\n"), $fails, $checks);
$step = '';
if (preg_match('/\n\/\/ 467\n(.*?)\n\];/s', $schema, $m)) {
    $step = $m[1];
}
check('step 467 adds scheduledTasks.stCancelSnapins as tinyint(1) NOT NULL DEFAULT 0',
    false !== strpos($step, 'ALTER TABLE `scheduledTasks` ')
    && false !== strpos($step, 'ADD COLUMN `stCancelSnapins` tinyint(1) NOT NULL DEFAULT 0'), $fails, $checks);
check('step 467 sets 1 only on the Capture and Capture - Debug rows',
    (bool)preg_match('/UPDATE `scheduledTasks` SET `stCancelSnapins` = 1 "\s*\.\s*"WHERE `stTaskTypeID` IN \(2, 16\)"/', $step), $fails, $checks);
check('the step ids are the Capture task types',
    false !== strpos(
        $step,
        'IN (' . \FOG\Items\TaskType::CAPTURE . ', ' . \FOG\Items\TaskType::CAPTURE_DEBUG . ')'
    ), $fails, $checks);
$manifest = require "$web/commons/schema-expected.php";
check('the manifest carries the column',
    'tinyint(1) NOT NULL DEFAULT 0' === ($manifest['tables']['scheduledTasks']['columns']['stCancelSnapins'] ?? null), $fails, $checks);

$GLOBALS['reported'] = true;
if ($fails) {
    fwrite(STDERR, "FAIL: scheduled capture cancel snapins (" . count($fails) . " of $checks)\n  - "
        . implode("\n  - ", $fails) . "\n");
    exit(1);
}
echo "PASS  scheduled capture cancel snapins ($checks checks)\n";
exit(0);
