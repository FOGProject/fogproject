<?php
/**
 * The Fleet Report is wired to FleetStats, and reads staleness correctly.
 *
 * The wiring every ADR 0030 report shares -- names, columns, gate, label,
 * and the placeholder rule -- is in tests/lib/report-wiring.php. What is
 * here is this report's own:
 *
 *   - THE WINDOW MEANS SOMETHING DIFFERENT. Every other report selects
 *     events between two dates; this one measures a STATE as of the end
 *     date, and calls a host imaged inside the range current. That has to
 *     be said on the page, not only in a docblock, so it is checked.
 *   - NEVER IS ITS OWN BUCKET. A machine that has never been imaged and
 *     one imaged three years ago are different problems, and folding them
 *     together hides whichever is rarer.
 *   - BOTH FORMS OF NO DATE COUNT AS NEVER. NULL on a fresh registration,
 *     '0000-00-00 00:00:00' on an install upgraded across the zero-date
 *     schema work. Testing one of them silently reports the other as
 *     imaged in the year zero -- an age of about 740,000 days, which lands
 *     in the oldest bucket and looks like a real answer.
 *   - THE TILES ARE ASKED OF THE DATABASE, not derived from the grid,
 *     which is the opposite of the imaging and snapin reports and is
 *     deliberate: the grid is capped and the tiles describe the whole
 *     fleet.
 *
 * Usage: php tests/fleet-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/report-wiring.php';

FogTestHarness::boot('fleet-report');
$db = FogTestHarness::fakeDb();

use FOG\Audit\FleetStats;

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$code = FogReportWiring::check(
    $t,
    $web,
    'fleet_report',
    'Fleet_Report',
    'host',
    'fleetreport-table'
);
FogReportWiring::checkSql($t, 'FOG\Audit\FleetStats');

/*
 * 1. The counting rules stay in FleetStats.
 */
$t->check(
    'the page has no hosts query of its own',
    false === strpos($code, '`hosts`')
);
foreach (['totals(', 'ageBuckets(', 'addedPerDay(', 'hosts('] as $call) {
    $t->check(
        "it reads FleetStats::$call)",
        false !== strpos($code, 'FleetStats::' . $call)
    );
}
$t->check(
    'this report defaults to a quarter',
    '-90 days' === constant('FOG\Reports\Fleet_Report::DEFAULT_WINDOW')
);

/*
 * 2. The different meaning of the window is stated ON THE PAGE. A control
 *    that means two things on two reports is worse than no control, and a
 *    docblock is not visible to whoever is reading the chart.
 */
$t->check(
    'the page says what the range means here',
    false !== strpos($code, 'counts as current')
    && false !== strpos($code, 'measured back from the end date')
);

/*
 * 3. The statements.
 */
$dateCols = [
    '_ageBucketSql' => '`hosts`.`hostLastDeploy`',
    '_hostsSql' => '`hosts`.`hostLastDeploy`',
    '_totalsSql' => '`hosts`.`hostLastDeploy`'
];
foreach ($dateCols as $builder => $col) {
    $sql = FogTestHarness::callStatic('FOG\Audit\FleetStats', $builder);
    $t->check(
        "$builder treats NULL and the zero date alike",
        false !== strpos($sql, "$col IS NULL")
        && false !== strpos($sql, "$col = '0000-00-00 00:00:00'")
    );
}
$bucketSql = FogTestHarness::callStatic(
    'FOG\Audit\FleetStats',
    '_ageBucketSql'
);
$t->check(
    'never is its own bucket, not the oldest one',
    1 === preg_match('/THEN 0/', $bucketSql)
);
$t->check(
    'the buckets are one CASE, so they cannot overlap or leave a gap',
    1 === substr_count($bucketSql, 'GROUP BY')
    && false === strpos($bucketSql, 'UNION')
);
$hostsSql = FogTestHarness::callStatic('FOG\Audit\FleetStats', '_hostsSql');
$t->check(
    'the grid query is bounded in SQL, not after the fetch',
    1 === preg_match('/LIMIT\s+(\d+)\s*$/', trim($hostsSql), $lim)
        && (int)$lim[1] === FleetStats::MAX_ROWS + 1
);
$t->check(
    'the stalest hosts sort to the top, which is the point of the grid',
    false !== strpos($hostsSql, 'ORDER BY `ageDays` DESC')
);
$t->check(
    'a host with no image or no inventory still appears',
    0 === substr_count($hostsSql, 'INNER JOIN')
    && 2 === substr_count($hostsSql, 'LEFT OUTER JOIN')
);
$totalsSql = FogTestHarness::callStatic('FOG\Audit\FleetStats', '_totalsSql');
$t->check(
    'the tiles are counted over the whole fleet, not over the capped grid',
    false !== strpos($totalsSql, 'COUNT(*) AS `hosts`')
    && false === strpos($totalsSql, 'LIMIT')
);
foreach (['_ageBucketSql', '_addedPerDaySql', '_totalsSql', '_hostsSql']
    as $builder
) {
    $sql = FogTestHarness::callStatic('FOG\Audit\FleetStats', $builder);
    $t->check(
        "$builder binds the as-of date rather than interpolating it",
        false === strpos($sql, '20')
    );
}

/*
 * 4. The derivation, through a fake connection.
 */
$db->responder = function ($sql) {
    if (false === strpos($sql, 'ageDays')) {
        return null;
    }
    return [['b' => '0', 'c' => '5'], ['b' => '3', 'c' => '2']];
};
$buckets = FleetStats::ageBuckets(
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);
$t->check(
    'every bucket is present even when nothing is in it',
    count(FleetStats::bucketLabels()) === count($buckets)
);
$t->check(
    'a bucket the query did not return is a zero, not a missing slice',
    5 === $buckets[0]['count'] && 0 === $buckets[1]['count']
        && 2 === $buckets[3]['count'] && 0 === $buckets[4]['count']
);
$t->check(
    'the buckets come back in the order the CASE numbers them',
    array_column($buckets, 'label') === array_values(
        FleetStats::bucketLabels()
    )
);
$t->check(
    'there is a bucket per boundary, plus never, plus the open end',
    count(FleetStats::AGE_BUCKETS) + 2 === count(FleetStats::bucketLabels())
);

$db->responder = function ($sql) {
    if (false === strpos($sql, 'hostCreateDate')) {
        return null;
    }
    return [['d' => '2026-03-03', 'c' => '2']];
};
$added = FleetStats::addedPerDay(
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);
$t->check('the window produces one point per day', 5 === count($added));
$t->check(
    'a day with no registrations is a zero, not a gap',
    0 === $added[0]['count'] && 2 === $added[2]['count']
);
$t->check(
    'the last day of the window is included',
    '2026-03-05' === $added[4]['date']
);

$t->finish();
