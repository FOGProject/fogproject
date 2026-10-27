<?php
/**
 * service/perftest.php accepts a Performance Test report only from a host that
 * is running that task, and only when every field is one FOS could have sent.
 *
 * THE RISK. The endpoint has no credential: FOS cannot present one. So the
 * two things that stop a stranger adding rows are the rule that the host must
 * have a Performance Test task in flight, and strict checking of each field.
 * FOS treats a body of exactly `##` as success and shows anything else on
 * the client screen, so a refusal must carry a reason that names the cause.
 *
 * WHAT THIS PINS is HostPerfTest::refusal(), which the endpoint calls before
 * it stores anything, and HostPerfTest::formatRatio()/formatRate(), which the
 * host page's Performance tab uses. The endpoint's own wiring (refusal before
 * save, exact `##` on success) is pinned from its source, because the script
 * needs a database to run.
 *
 * DB-free: the host and task are stand-ins; the rules are the shipped code.
 *
 * Usage: php tests/perftest-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

$root = dirname(__DIR__);
$web = $root . '/packages/web';

require_once $web . '/src/Base/FOGBase.php';
require_once $web . '/src/Base/FOGController.php';
require_once $web . '/src/Items/TaskType.php';
require_once $web . '/src/Items/HostPerfTest.php';

use FOG\Items\HostPerfTest;
use FOG\Items\TaskType;

/**
 * A stand-in for a Host or a Task: isValid() and get() are all the rules use.
 */
class PerfStub
{
    private $_valid;
    private $_data;

    public function __construct($valid, array $data = [])
    {
        $this->_valid = $valid;
        $this->_data = $data;
    }

    public function isValid()
    {
        return $this->_valid;
    }

    public function get($key)
    {
        return $this->_data[$key] ?? null;
    }
}

/**
 * A host with the given task in flight.
 *
 * @param int|null $typeID the task type, or null for no task
 *
 * @return PerfStub
 */
function perfHost($typeID)
{
    $task = null === $typeID
        ? new PerfStub(false)
        : new PerfStub(true, ['id' => 77, 'typeID' => $typeID]);
    return new PerfStub(true, ['id' => 12, 'task' => $task]);
}

/**
 * A report FOS could have sent, with some fields replaced.
 *
 * @param array $over the fields to replace
 *
 * @return array
 */
function perfReport(array $over = [])
{
    return array_merge(
        [
            'mac' => '00:11:22:33:44:55',
            'disk' => '/dev/nvme0n1',
            'diskmbps' => '1800',
            'netmbps' => '110',
            'cpumbps' => '650',
            'ratio' => '250',
            'format' => 'zstd',
            'sample' => base64_encode('win11/d1p2.img'),
            'bottleneck' => 'network',
            'kernel' => '6.18.59',
            'initversion' => '20261027',
        ],
        $over
    );
}

$t = new FogChecks();
$good = perfHost(TaskType::PERF_TEST);

// --- who may report ---------------------------------------------------------
$row = [];
$t->check(
    'an unknown host is refused',
    'Unknown host' === HostPerfTest::refusal(
        new PerfStub(false),
        perfReport(),
        $row
    ) && [] === $row
);
$t->check(
    'a null host is refused',
    'Unknown host' === HostPerfTest::refusal(null, perfReport(), $row)
);
$msg = HostPerfTest::refusal(perfHost(null), perfReport(), $row);
$t->check(
    'a host with no active task is refused',
    'No active Performance Test task for this host' === $msg && [] === $row
);
foreach ([TaskType::DEPLOY, TaskType::CAPTURE, TaskType::ENROLL_SECUREBOOT, 26]
    as $other) {
    $msg = HostPerfTest::refusal(perfHost($other), perfReport(), $row);
    $t->check(
        "a host whose active task is type $other is refused",
        'No active Performance Test task for this host' === $msg
            && [] === $row
    );
}

// --- every field class is checked -------------------------------------------
$bad = [
    ['diskmbps', 'abc', 'a non-digit rate'],
    ['netmbps', '-5', 'a negative rate'],
    ['cpumbps', '12.5', 'a decimal rate'],
    ['diskmbps', '', 'an empty rate'],
    ['diskmbps', '1000001', 'an over-cap rate'],
    ['ratio', '100001', 'an over-cap ratio'],
    ['ratio', '2.5', 'a decimal ratio'],
    ['format', 'lz4', 'a format outside the list'],
    ['format', 'ZSTD', 'a format in the wrong case'],
    ['bottleneck', 'memory', 'a bottleneck outside the list'],
    ['disk', 'nvme0n1', 'a disk with no /dev/ prefix'],
    ['disk', '/dev/sda; rm -rf /', 'a disk with shell characters'],
    ['disk', '/dev/' . str_repeat('a', 59), 'a disk name over the cap'],
    ['kernel', "6.18\n", 'a kernel with a newline'],
    ['kernel', str_repeat('1', 65), 'a kernel over the cap'],
    ['initversion', str_repeat('1', 33), 'an initversion over the cap'],
    ['initversion', '2026 1027', 'an initversion with a space'],
    ['sample', 'not base64!', 'a sample that is not strict base64'],
    ['sample', 'aGVsbG8', 'a sample with missing padding'],
    ['sample', base64_encode("a\x01b"), 'a sample with a control character'],
    ['sample', base64_encode("a\nb"), 'a sample with a newline'],
    ['sample', base64_encode("\xff\xfe"), 'a sample that is not UTF-8'],
    ['sample', base64_encode(str_repeat('a', 256)), 'a sample over the cap'],
];
foreach ($bad as list($field, $value, $what)) {
    $msg = HostPerfTest::refusal(
        $good,
        perfReport([$field => $value]),
        $row
    );
    $t->check(
        "$what is refused, naming $field, and builds no row",
        'Invalid field: ' . $field === $msg && [] === $row
    );
}

// --- what is accepted -------------------------------------------------------
$msg = HostPerfTest::refusal($good, perfReport(), $row);
$t->check('a valid report is accepted', '' === $msg);
$t->check(
    'the row carries the posted values',
    isset($row['diskMBps'])
        && 1800 === $row['diskMBps']
        && 110 === $row['netMBps']
        && 650 === $row['cpuMBps']
        && 250 === $row['ratio']
        && 'zstd' === $row['format']
        && 'network' === $row['bottleneck']
        && '/dev/nvme0n1' === $row['disk']
        && '6.18.59' === $row['kernel']
        && '20261027' === $row['init']
);
$t->check(
    'the sample is stored decoded',
    isset($row['sample']) && 'win11/d1p2.img' === $row['sample']
);
$t->check(
    'the row carries the host id and the active task id',
    isset($row['hostID'], $row['taskID'])
        && 12 === $row['hostID']
        && 77 === $row['taskID']
);
$msg = HostPerfTest::refusal(
    $good,
    perfReport(
        [
            'disk' => '',
            'diskmbps' => '0',
            'netmbps' => '0',
            'cpumbps' => '0',
            'ratio' => '0',
            'format' => '',
            'sample' => '',
            'bottleneck' => '',
            'kernel' => '',
            'initversion' => '',
        ]
    ),
    $row
);
$t->check(
    'an all-empty, all-zero report (nothing measured) is accepted',
    '' === $msg && '' === $row['sample'] && 0 === $row['ratio']
);
$msg = HostPerfTest::refusal(
    $good,
    perfReport(
        [
            'diskmbps' => '1000000',
            'ratio' => '100000',
            'sample' => base64_encode(str_repeat('a', 255)),
        ]
    ),
    $row
);
$t->check('values exactly at the caps are accepted', '' === $msg);

// --- the tab's formatting ---------------------------------------------------
$t->check('ratio 250 shows as 2.50:1', '2.50:1' === HostPerfTest::formatRatio(250));
$t->check('ratio 100 shows as 1.00:1', '1.00:1' === HostPerfTest::formatRatio('100'));
$t->check('ratio 105 shows as 1.05:1', '1.05:1' === HostPerfTest::formatRatio(105));
$t->check('ratio 99 shows as 0.99:1', '0.99:1' === HostPerfTest::formatRatio(99));
$t->check('ratio 0 shows as a dash', '-' === HostPerfTest::formatRatio(0));
$t->check('rate 1800 shows as 1800', '1800' === HostPerfTest::formatRate('1800'));
$t->check(
    'rate 0 shows as not measured',
    'not measured' === HostPerfTest::formatRate(0)
);

// --- the endpoint's wiring --------------------------------------------------
$src = file_get_contents($web . '/service/perftest.php');
$refusalAt = strpos($src, 'HostPerfTest::refusal(');
$saveAt = strpos($src, '->save()');
$t->check(
    'the endpoint checks the report before it stores anything',
    false !== $refusalAt && false !== $saveAt && $refusalAt < $saveAt
);
$t->check(
    'the endpoint declares itself a machine request',
    false !== strpos($src, "define('FOG_MACHINE_REQUEST', true);")
);
$t->check(
    'the endpoint answers exactly ## on success',
    (bool)preg_match("/echo '##';\s*$/", $src)
);

$t->finish();
