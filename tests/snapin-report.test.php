<?php
/**
 * The Snapin Report is wired to SnapinStats, and reads outcomes correctly.
 *
 * The wiring every ADR 0030 report shares -- names, columns, gate, label --
 * is in tests/lib/report-wiring.php. What is here is this report's own:
 *
 *   - the outcome is `stReturnCode`, not `stState`. They can legitimately
 *     disagree (a job canceled after its tasks finished leaves successful
 *     runs under a canceled job), and reading the wrong one turns 43
 *     working installs into 43 cancellations.
 *   - a run is one with a COMPLETION date in the window. Bounding on
 *     check-in instead would count tasks that have no outcome yet.
 *   - zero is success. Any other code, negative included, is a failure.
 *
 * Usage: php tests/snapin-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/report-wiring.php';

FogTestHarness::boot('snapin-report');
$db = FogTestHarness::fakeDb();

use FOG\Audit\SnapinStats;

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$code = FogReportWiring::check(
    $t,
    $web,
    'snapin_report',
    'Snapin_Report',
    'snapin',
    'snapinreport-table'
);
FogReportWiring::checkSql($t, 'FOG\Audit\SnapinStats');

/*
 * 1. The counting rules stay in SnapinStats.
 */
$t->check(
    'the page has no snapinTasks query of its own',
    false === strpos($code, '`snapinTasks`')
);
foreach (['totals(', 'runsPerDay(', 'failuresPerDay(', 'failuresBySnapin(',
    'runs('] as $call) {
    $t->check(
        "it reads SnapinStats::$call)",
        false !== strpos($code, 'SnapinStats::' . $call)
    );
}
$t->check(
    'this report defaults to a month',
    '-30 days' === constant('FOG\Snapin_Report::DEFAULT_WINDOW')
);

/*
 * 2. The statements. Every one bounds on the COMPLETION date -- the column
 *    that says a run has an outcome at all -- and binds the window rather
 *    than interpolating it.
 */
$builders = [
    '_runsPerDaySql',
    '_failuresPerDaySql',
    '_failuresBySnapinSql',
    '_runsSql'
];
foreach ($builders as $builder) {
    $sql = FogTestHarness::callStatic('FOG\Audit\SnapinStats', $builder);
    $t->check(
        "$builder bounds on the completion date, not the check-in",
        false !== strpos($sql, '`stCompleteDate` BETWEEN :start AND :end')
        && false === strpos($sql, '`stCheckinDate` BETWEEN')
    );
    $t->check(
        "$builder binds the window rather than interpolating it",
        false === strpos($sql, '20')
    );
}
$failSql = FogTestHarness::callStatic(
    'FOG\Audit\SnapinStats',
    '_failuresPerDaySql'
);
$t->check(
    'a failure is a non-zero return code, not a task state',
    false !== strpos($failSql, '`stReturnCode` <> 0')
    && false === strpos($failSql, 'stState')
);
$runsSql = FogTestHarness::callStatic('FOG\Audit\SnapinStats', '_runsSql');
$t->check(
    'the grid query is bounded in SQL, not after the fetch',
    1 === preg_match('/LIMIT\s+(\d+)\s*$/', trim($runsSql), $lim)
        && (int)$lim[1] === SnapinStats::MAX_ROWS + 1
);
$t->check(
    'the grid carries the state AND the code, so a disagreement is visible',
    false !== strpos($runsSql, '`st`.`stReturnCode` AS `code`')
    && false !== strpos($runsSql, '`st`.`stState` AS `stateID`')
);
// A snapin or host deleted since the run must not delete the evidence that
// it ran, which is what makes these LEFT joins rather than inner ones.
$t->check(
    'the joins are LEFT, so a deleted snapin does not drop its runs',
    0 === substr_count($runsSql, 'INNER JOIN')
    && 3 === substr_count($runsSql, 'LEFT OUTER JOIN')
);

/*
 * 3. The derivation, through a fake connection. Zero is success and
 *    everything else is not -- including a negative code, which is what
 *    the lab's one real failure carries.
 */
$db->responder = function ($sql) {
    if (false === strpos($sql, 'snapinTasks')) {
        return null;
    }
    return [
        ['id' => '1', 'snapin' => 'a', 'hostID' => '7', 'hostName' => 'h1',
         'completed' => '2026-03-01 09:00:00', 'code' => '0'],
        ['id' => '2', 'snapin' => 'a', 'hostID' => '7', 'hostName' => 'h1',
         'completed' => '2026-03-01 10:00:00', 'code' => '1'],
        ['id' => '3', 'snapin' => 'b', 'hostID' => '9', 'hostName' => 'h2',
         'completed' => '2026-03-02 09:00:00', 'code' => '-1'],
        ['id' => '4', 'snapin' => 'b', 'hostID' => '0', 'hostName' => 'gone',
         'completed' => '2026-03-02 10:00:00', 'code' => '0'],
    ];
};
$totals = SnapinStats::totals(
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);
$t->check('the runs tile counts the run rows', 4 === $totals['runs']);
$t->check(
    'a non-zero code is a failure, and a NEGATIVE one is too',
    2 === $totals['failures']
);
$t->check(
    'the snapins tile counts DISTINCT snapins',
    2 === $totals['snapins']
);
$t->check(
    'the machines tile counts DISTINCT hosts',
    3 === $totals['hosts']
);
$t->check(
    'a complete result is not reported as truncated',
    false === $totals['truncated']
);

/*
 * 4. The series contract. A day with no runs is a zero and not a gap --
 *    a chart draws a line straight across a missing day, so an idle week
 *    reads as steady activity.
 */
$db->responder = function ($sql) {
    if (false === strpos($sql, 'snapinTasks')) {
        return null;
    }
    return [['d' => '2026-03-02', 'c' => '4']];
};
$series = SnapinStats::runsPerDay(
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);
$t->check('the window produces one point per day', 5 === count($series));
$t->check(
    'a day with no runs is a zero, not a gap',
    0 === $series[0]['count'] && 4 === $series[1]['count']
        && 0 === $series[4]['count']
);
$t->check(
    'the last day of the window is included',
    '2026-03-05' === $series[4]['date']
);
// Both series have to be the same length or the two lines do not line up
// on one axis, which is the whole reason they share a chart.
$failures = SnapinStats::failuresPerDay(
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);
$t->check(
    'runs and failures come back the same length, so they share an axis',
    count($series) === count($failures)
    && array_column($series, 'date') === array_column($failures, 'date')
);

$t->finish();
