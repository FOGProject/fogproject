<?php
/**
 * FOS reports what a Performance Test measured.
 *
 * PHP version 7.4+
 *
 * @category Performance_Test_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGBase;
use FOG\Base\FOGCore;
use FOG\Items\HostPerfTest;

/**
 * FOS reports what a Performance Test measured.
 *
 * FOS boots with mode=perftest, times the disk, the network and
 * decompression, and posts the result here before it completes the task.
 * Each run adds one row to hostPerfTests. The host page shows them.
 *
 * ADVISORY, like every other machine entry point. Nothing reads a row as a
 * security control. It is still narrowed: the host must have a Performance
 * Test task in flight, and every field is checked. The rules are in
 * HostPerfTest::refusal(), so a test can drive them without a database.
 *
 * FOS treats a body of exactly `##` as success. Any other body is shown on
 * the client screen as the reason for the failure. So a refusal answers 200
 * with the reason as plain text. A non-2xx is invisible to the caller and
 * reads as a transport failure.
 *
 * @category Performance_Test_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: the caller is FOS, which cannot present a
 * credential. Declared per file -- see Authorization::_hasNoPrincipal().
 */
define('FOG_MACHINE_REQUEST', true);

require '../commons/base.inc.php';

header('Content-type: text/plain');

/**
 * Say what happened and stop.
 *
 * @param string $msg the body to send
 *
 * @return void
 */
$done = function ($msg) {
    echo $msg . "\n";
    exit;
};

$in = [];
foreach (
    [
        'mac', 'disk', 'diskmbps', 'netmbps', 'cpumbps', 'ratio', 'format',
        'sample', 'bottleneck', 'kernel', 'initversion'
    ] as $key
) {
    $in[$key] = (string)filter_input(INPUT_POST, $key);
}

FOGCore::getHostItem(false, false, true, false, false, $in['mac']);
$Host = FOGBase::$Host;

$row = [];
$reason = HostPerfTest::refusal($Host, $in, $row);
if ('' !== $reason) {
    $done($reason);
}

$row['dateTime'] = FOGBase::niceDate()->format('Y-m-d H:i:s');
$Result = new HostPerfTest();
foreach ($row as $field => $value) {
    $Result->set($field, $value);
}
if (!$Result->save()) {
    $done('The result could not be stored');
}

// FOS looks for exactly this and nothing else.
echo '##';
