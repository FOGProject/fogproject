<?php
/**
 * The Audit Report is wired to AuditStats, and reads outcomes correctly.
 *
 * The wiring every ADR 0030 report shares -- names, columns, gate, label,
 * the window control and the placeholder rule -- is in
 * tests/lib/report-wiring.php. What is here is this report's own:
 *
 *   - DENIED AND FAILED ARE NOT THE SAME THING. Denied is authorization
 *     refusing an action; failed is one that was permitted and went wrong.
 *     A single "errors" number mixing them is actionable as neither, and
 *     the two are one enum value apart in the same column, so adding them
 *     together is a one-character mistake.
 *   - THE GATE IS `audit`, and this is the one report where the narrowing
 *     is not a convenience. An audit row necessarily discloses ATTEMPTED
 *     usernames.
 *   - `alText` IS NOT SELECTED. It is a longtext of stored prose, one per
 *     row, and pulling it into a 5000-row JSON response would carry the
 *     full detail of every change to a grid that does not show it.
 *   - THE TWO SERIES SHARE AN AXIS. They are drawn on one chart, so they
 *     have to be zero filled across the same days -- which is why they are
 *     two statements and not one with two columns.
 *
 * Usage: php tests/audit-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/report-wiring.php';

FogTestHarness::boot('audit-report');
$db = FogTestHarness::fakeDb();

use FOG\Audit\Audit;
use FOG\Audit\AuditStats;

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$code = FogReportWiring::check(
    $t,
    $web,
    'audit_report',
    'Audit_Report',
    'audit',
    'auditreport-table'
);
FogReportWiring::checkSql($t, 'FOG\Audit\AuditStats');

/*
 * 1. The counting rules stay in AuditStats.
 */
$t->check(
    'the page has no auditLog query of its own',
    false === strpos($code, '`auditLog`')
);
foreach (['totals(', 'eventsPerDay(', 'deniedPerDay(', 'byActor(',
    'byType(', 'events('] as $call
) {
    $t->check(
        "it reads AuditStats::$call)",
        false !== strpos($code, 'AuditStats::' . $call)
    );
}
$t->check(
    'this report defaults to a month',
    '-30 days' === constant('FOG\Reports\Audit_Report::DEFAULT_WINDOW')
);

/*
 * 2. The statements. Every one is bounded on the window -- an unbounded
 *    read of an audit table is the one that takes the server with it.
 */
$builders = [
    '_perDaySql',
    '_deniedPerDaySql',
    '_byActorSql',
    '_byTypeSql',
    '_totalsSql',
    '_eventsSql'
];
foreach ($builders as $builder) {
    $sql = FogTestHarness::callStatic('FOG\Audit\AuditStats', $builder);
    $t->check(
        "$builder is bounded on the window",
        false !== strpos($sql, '`alCreatedTime` BETWEEN :start AND :end')
    );
    $t->check(
        "$builder binds the window rather than interpolating it",
        false === strpos($sql, '20')
    );
}
$deniedSql = FogTestHarness::callStatic(
    'FOG\Audit\AuditStats',
    '_deniedPerDaySql'
);
$t->check(
    'the refusal series selects the denied outcome, and only that one',
    false !== strpos($deniedSql, "`alOutcome` = '" . Audit::DENIED . "'")
    && false === strpos($deniedSql, Audit::FAILED)
);
$totalsSql = FogTestHarness::callStatic(
    'FOG\Audit\AuditStats',
    '_totalsSql'
);
foreach ([Audit::DENIED, Audit::FAILED, Audit::PARTIAL] as $outcome) {
    $t->check(
        "the `$outcome` outcome is counted on its own",
        1 === substr_count($totalsSql, "`alOutcome` = '$outcome'")
    );
}
$t->check(
    'and they are never summed into one number',
    false === strpos($totalsSql, ' OR `alOutcome`')
    && false === strpos($totalsSql, 'IN (')
);
$eventsSql = FogTestHarness::callStatic(
    'FOG\Audit\AuditStats',
    '_eventsSql'
);
$t->check(
    'the stored prose is left in the Audit Log page it belongs to',
    false === strpos($eventsSql, 'alText')
    && false === strpos($eventsSql, 'SELECT *')
);
$t->check(
    'the grid query is bounded in SQL, not after the fetch',
    1 === preg_match('/LIMIT\s+(\d+)\s*$/', trim($eventsSql), $lim)
        && (int)$lim[1] === AuditStats::MAX_ROWS + 1
);
$t->check(
    'rows written in the same second still order deterministically',
    false !== strpos($eventsSql, 'ORDER BY `alCreatedTime` DESC, `alID` DESC')
);

/*
 * 3. The derivation, through a fake connection.
 */
$window = [
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
];
$db->responder = function ($sql) {
    if (false === strpos($sql, 'AS `events`')) {
        return null;
    }
    return [[
        'events' => '40', 'actors' => '3', 'addresses' => '5',
        'denied' => '4', 'failed' => '2', 'partial' => '1'
    ]];
};
$totals = AuditStats::totals($window[0], $window[1]);
$t->check(
    'refusals and failures stay separate all the way to the tiles',
    4 === $totals['denied'] && 2 === $totals['failed']
);
$t->check(
    'a complete result is not reported as truncated',
    false === $totals['truncated']
);

// The two series are drawn on one chart, so they must be zero filled over
// the same days -- which they cannot be if the fill comes from the rows.
$db->responder = function ($sql) {
    if (false === strpos($sql, 'alCreatedTime')) {
        return null;
    }
    if (false !== strpos($sql, "'denied'")) {
        return [['d' => '2026-03-04', 'c' => '2']];
    }
    return [
        ['d' => '2026-03-02', 'c' => '10'],
        ['d' => '2026-03-04', 'c' => '30']
    ];
};
$events = AuditStats::eventsPerDay($window[0], $window[1]);
$denied = AuditStats::deniedPerDay($window[0], $window[1]);
$t->check('the window produces one point per day', 5 === count($events));
$t->check(
    'a quiet day is a zero, not a gap',
    0 === $events[0]['count'] && 10 === $events[1]['count']
    && 30 === $events[3]['count']
);
$t->check(
    'the refusal series covers the same days even though it has fewer rows',
    count($events) === count($denied)
    && array_column($events, 'date') === array_column($denied, 'date')
);
$t->check(
    'and it lands on the right day',
    0 === $denied[0]['count'] && 2 === $denied[3]['count']
);

$db->responder = function ($sql) {
    if (false === strpos($sql, 'GROUP BY `alCreatedBy`')) {
        return null;
    }
    $rows = [['label' => '', 'c' => '11']];
    for ($i = 1; $i <= 12; $i++) {
        $rows[] = ['label' => 'user' . $i, 'c' => (string)(30 - $i)];
    }
    return $rows;
};
$actors = AuditStats::byActor($window[0], $window[1]);
$t->check(
    'the tail folds so the chart stays readable',
    AuditStats::TOP_N + 1 === count($actors)
);
$t->check(
    'an unauthenticated attempt is named, not drawn as an empty slice',
    in_array(_('Unattributed'), array_column($actors, 'label'), true)
    && !in_array('', array_column($actors, 'label'), true)
);
$t->check(
    'nothing is lost in the fold',
    array_sum(array_column($actors, 'count')) === 11 + array_sum(range(18, 29))
);

$t->finish();
