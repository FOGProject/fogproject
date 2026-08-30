<?php
/**
 * FOGService::idleNap() -- how long a daemon sleeps between passes.
 *
 * The wait loop used to wake ten times a second whatever it was waiting for:
 * usleep(100000) plus a doHousekeeping(), unconditionally. Measured on an idle
 * server that is 43 s of CPU per day per daemon and 432 s across the ten, none
 * of it doing anything -- ImageSize waits an hour between passes and woke
 * 36,000 times to get there.
 *
 * waitUntilDue() now ticks at the old rate only while $procRef holds a live
 * transfer to reap, and otherwise sleeps. This pins the arithmetic of the
 * "otherwise", which is the part with edges:
 *
 *   - the cap, so a long interval never becomes a long blind spot on a changed
 *     $sleeptime;
 *   - the remainder under the cap, which is slept whole so the pass lands on
 *     time rather than early;
 *   - a deadline already past, which must not sleep at all.
 *
 * Static and instant on purpose. The method is split out of waitUntilDue()
 * precisely so this file needs no database, no service instance and no
 * five-second wait -- a test that asserted on elapsed wall-clock would go
 * flaky and then get deleted.
 *
 * The cap's own bounds are not asserted here. Its value is a compile-time
 * constant, so any check of it is dead code by construction and static
 * analysis says so; the range that keeps it sane is documented on the constant
 * instead, where whoever changes it is already reading.
 *
 * Usage: php tests/daemon-idle-nap.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
require_once $repo . '/packages/web/vendor/autoload.php';

$cap = \FOG\Service\FOGService::IDLE_TICK_CAP;

$cases = [
    // [seconds remaining, expected nap, why]
    [3600, $cap, 'a long interval is capped, not slept through'],
    [$cap, $cap, 'exactly the cap sleeps the cap'],
    [$cap + 1, $cap, 'one over the cap is still capped'],
    [3, 3, 'under the cap sleeps the remainder, so the pass is on time'],
    [1, 1, 'one second left sleeps one second'],
    [0, 0, 'due now does not sleep'],
    [-5, 0, 'a deadline already past does not sleep'],
];

$fails = [];
$oks = [];

foreach ($cases as [$remaining, $expected, $why]) {
    $got = \FOG\Service\FOGService::idleNap($remaining);
    if ($got !== $expected) {
        $fails[] = "idleNap($remaining) = $got, expected $expected -- $why";
        continue;
    }
    $oks[] = "idleNap($remaining) = $got -- $why";
}

foreach ($oks as $m) {
    echo "  ok    $m\n";
}
foreach ($fails as $m) {
    echo "  FAIL  $m\n";
}
echo "\n";
if ([] !== $fails) {
    echo 'FAIL (' . count($fails) . ' of '
        . (count($oks) + count($fails)) . " assertions)\n";
    exit(1);
}
echo 'PASS (' . count($oks) . " assertions)\n";
