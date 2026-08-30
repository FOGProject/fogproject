<?php
/**
 * The Hardware Report is wired to InventoryStats, and censuses correctly.
 *
 * The wiring every ADR 0030 report shares -- names, columns, gate, label,
 * and the placeholder rule -- is in tests/lib/report-wiring.php. What is
 * here is this report's own:
 *
 *   - THE BREAKDOWN COLUMN IS NEVER TAKEN FROM THE REQUEST. It cannot be
 *     bound, because a column name is not a value, so it is concatenated --
 *     and the only defense is that it comes from a fixed map. An unmapped
 *     key has to produce nothing rather than an error or a query.
 *   - BLANK IS ITS OWN SLICE. Every inventory column is NOT NULL DEFAULT
 *     '', so "the firmware reported nothing" arrives as an empty string.
 *     Dropping those rows would silently inflate whoever is biggest;
 *     grouping them raw gives a slice with no legend entry.
 *   - THE TAIL FOLDS. A fleet of 300 models is not a doughnut, and a chart
 *     that draws all 300 is unreadable in a way that looks like a bug.
 *
 * Usage: php tests/hardware-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/report-wiring.php';

FogTestHarness::boot('hardware-report');
$db = FogTestHarness::fakeDb();

use FOG\Audit\InventoryStats;

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$code = FogReportWiring::check(
    $t,
    $web,
    'hardware_report',
    'Hardware_Report',
    'host',
    'hardwarereport-table',
    [
        // The grid IS the former Inventory Report: 33 columns served by the
        // router, paging in SQL. `hostLink` is an intentional anchor, so it
        // cannot be neutralised with render.text() and is escaped in the
        // formatter instead.
        'listem' => 'inventory',
        'raw' => ['hostLink']
    ]
);
FogReportWiring::checkSql($t, 'FOG\Audit\InventoryStats');

/*
 * 1. The counting rules stay in InventoryStats.
 */
$t->check(
    'the page has no inventory query of its own',
    false === strpos($code, '`inventory`')
);
foreach (['totals(', 'breakdown(', 'recordedPerDay('] as $call) {
    $t->check(
        "it reads InventoryStats::$call)",
        false !== strpos($code, 'InventoryStats::' . $call)
    );
}
$t->check(
    'this report defaults to a quarter, matching Fleet Report',
    '-90 days' === constant('FOG\Reports\Hardware_Report::DEFAULT_WINDOW')
    && constant('FOG\Reports\Fleet_Report::DEFAULT_WINDOW')
        === constant('FOG\Reports\Hardware_Report::DEFAULT_WINDOW')
);
$t->check(
    'the page says what the range means here',
    false !== strpos($code, 'breakdowns cover every inventoried machine')
    && false !== strpos($code, 'selects inventory recorded inside it')
);

/*
 * 2. The column is chosen from the map, and only from the map. An unmapped
 *    key must reach no query at all -- not a query that happens to return
 *    nothing.
 */
$asked = [];
$db->responder = function ($sql) use (&$asked) {
    $asked[] = $sql;
    return [];
};
$window = [
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
];
$t->check(
    'an unmapped breakdown key returns nothing',
    [] === InventoryStats::breakdown('hostname', $window[0], $window[1])
);
$t->check(
    'and it runs no query at all',
    [] === $asked
);
foreach (InventoryStats::BREAKDOWNS as $key => $column) {
    $t->check(
        "the `$key` breakdown maps to a real inventory column",
        1 === preg_match('/^i[A-Za-z]+$/', $column)
    );
}
$bd = FogTestHarness::callStatic(
    'FOG\Audit\InventoryStats',
    '_breakdownSql',
    ['iSysman']
);
$t->check(
    'the breakdown groups rather than filtering, so blanks are not dropped',
    false === strpos($bd, 'WHERE')
    && false !== strpos($bd, 'GROUP BY')
);
$t->check(
    'a blank column becomes NULL in SQL, to be labeled in PHP',
    false !== strpos($bd, "= ''")
    && false !== strpos($bd, 'THEN NULL')
);

/*
 * 3. The statements.
 */
// The fold. Inventory Report was retired into this page, so the rollup
// must NOT have grown a grid query of its own alongside the router's --
// two paths to the same rows is how they start disagreeing.
$t->check(
    'the rollup builds no grid query; the router serves the rows',
    !method_exists('FOG\Audit\InventoryStats', 'hosts')
);
$t->check(
    'the retired report is gone rather than left as a second door',
    !file_exists($web . '/src/Reports/Inventory_Report.php')
);
$t->check(
    'and its menu label went with it',
    false === strpos(
        (string)file_get_contents(
            $web . '/src/Pages/ReportManagement.php'
        ),
        "_('Inventory Report');"
    )
);
// The rows were reachable with report.view and are now behind host.view --
// ADR 0030 decision 4 applied to the rows this fold moved. That the gate is
// `host` is asserted by FogReportWiring::check() above, through a variable;
// repeating it with the literal here only told PHPStan the constant says
// what the constant says.
foreach (['_recordedPerDaySql', '_totalsSql'] as $builder) {
    $sql = FogTestHarness::callStatic('FOG\Audit\InventoryStats', $builder);
    $t->check(
        "$builder binds the window rather than interpolating it",
        false === strpos($sql, '20')
        && false !== strpos($sql, ':start')
        && false !== strpos($sql, ':end')
    );
}
$totalsSql = FogTestHarness::callStatic(
    'FOG\Audit\InventoryStats',
    '_totalsSql'
);
$t->check(
    'the vendor and model counts ignore blanks rather than counting them',
    2 === substr_count($totalsSql, "NULLIF(TRIM(")
);
$t->check(
    'the tiles are counted over the whole estate, not over the capped grid',
    false === strpos($totalsSql, 'LIMIT')
);

/*
 * 4. The derivation, through a fake connection.
 */
$db->responder = function ($sql) {
    if (false === strpos($sql, 'GROUP BY `label`')) {
        return null;
    }
    $rows = [['label' => null, 'c' => '9']];
    for ($i = 1; $i <= 12; $i++) {
        $rows[] = ['label' => 'model-' . $i, 'c' => (string)(20 - $i)];
    }
    return $rows;
};
$models = InventoryStats::breakdown('model', $window[0], $window[1]);
$t->check(
    'the tail folds so the chart stays readable',
    InventoryStats::TOP_N + 1 === count($models)
);
$t->check(
    'and the folded tail is labeled rather than dropped',
    _('Other') === $models[count($models) - 1]['label']
);
$t->check(
    'nothing is lost in the fold',
    array_sum(array_column($models, 'count')) === 9 + array_sum(range(8, 19))
);
$labels = array_column($models, 'label');
$t->check(
    'a blank manufacturer is named rather than drawn as an empty slice',
    in_array(_('Not reported'), $labels, true)
    && !in_array('', $labels, true)
    && !in_array(null, $labels, true)
);

$db->responder = function ($sql) {
    if (false === strpos($sql, 'iCreateDate')) {
        return null;
    }
    return [['d' => '2026-03-03', 'c' => '4']];
};
$recorded = InventoryStats::recordedPerDay($window[0], $window[1]);
$t->check('the window produces one point per day', 5 === count($recorded));
$t->check(
    'a day with no inventory is a zero, not a gap',
    0 === $recorded[0]['count'] && 4 === $recorded[2]['count']
);
$t->check(
    'the last day of the window is included',
    '2026-03-05' === $recorded[4]['date']
);

$t->finish();
