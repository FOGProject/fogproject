<?php
/**
 * The dashboard's imaging chart counts imaging RUNS, not canceled intentions.
 *
 * DashboardPage::get30day() feeds the "imaging history" graph. Since ADR 0022
 * decision 3 retired imagingLog it reads taskLog, and taskLog holds one row
 * per state transition rather than one per run -- so the query has always had
 * to fold a task's rows back down to one, and has always decided what counts
 * as an imaging row with `logImageName <> ''`.
 *
 * That test was sufficient only while the sole writer was
 * TaskingElement::taskLog(), which runs on check-in -- so a row existed only
 * for a task that had actually started. TaskLog::recordState() now writes one
 * on EVERY transition of an imaging task, cancellation included. A deploy that
 * is queued and canceled before any machine boots therefore has exactly one
 * taskLog row, carrying an image name, and counting it reports an image
 * deployed on a day nothing was imaged.
 *
 * Two things this pins, both of which are silent when wrong -- the chart
 * renders either way and simply shows the wrong number:
 *
 * 1. **The canceled state is excluded.** Not the Failed state and not the
 *    queued ones: a task canceled part-way through imaging still has its
 *    In-Progress row from check-in and still counts, which is correct -- it
 *    did image. Only a task whose sole rows are cancellations drops out.
 *
 * 2. **A run is attributed to the day it STARTED**, via MIN() per task rather
 *    than each row's own date. A capture that begins before midnight and
 *    completes after it writes rows on two days. That could not happen while
 *    every row carried the task's createdTime -- the timestamp bug held this
 *    property up by accident -- so stamping rows at the transition is exactly
 *    what makes it need saying.
 *
 * Asserted on the query text, the way tests/activity-window.test.php asserts
 * on ActivityWindow's: the method emits headers and a JSON body, so driving it
 * proves less than reading the one statement it is built around. The columns
 * it names are cross-checked against commons/schema-expected.php so a rename
 * by a future schema step fails here rather than on the dashboard.
 *
 * Usage: php tests/imaging-count-excludes-cancels.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('imaging-count-excludes-cancels');
FogTestHarness::fakeDb();

$t = new FogChecks();

$root = dirname(__DIR__);
$source = file_get_contents(
    $root . '/packages/web/lib/pages/dashboardpage.page.php'
);

$body = '';
if (preg_match('#public function get30day\(\).*?\n    \}\n#s', $source, $m)) {
    $body = $m[0];
}
if (!$t->check('DashboardPage::get30day() is found', '' !== $body)) {
    $t->finish();
}

// The statement itself, so a stray mention of one of these words elsewhere in
// the method cannot stand in for the query actually carrying it.
$sql = '';
if (preg_match('#"(SELECT.*?)"#s', $body, $m)) {
    $sql = $m[1];
}
if (!$t->check('its taskLog query is found', '' !== $sql)) {
    $t->finish();
}

$t->check(
    'it still reads taskLog',
    false !== stripos($sql, '`taskLog`')
);
$t->check(
    'it still identifies an imaging row by its image name',
    false !== strpos($sql, "`logImageName` <> ''")
);

/*
 * 1. Canceled rows are excluded, and the state is resolved rather than
 *    written as a literal 5 -- CANCELLED_STATE is a hook, so a plugin can
 *    move it and a hardcoded id would quietly exclude the wrong state.
 */
$t->check(
    'canceled rows are excluded',
    false !== strpos($sql, '`taskStateID` <> :canceled')
);
$t->check(
    'the canceled state is resolved, not hardcoded',
    false !== strpos($body, "':canceled' => self::getCancelledState()")
);
$t->check(
    'no other state is excluded with it',
    1 === substr_count($sql, '`taskStateID`')
);

/*
 * 2. One row per task, dated by its earliest transition in the window.
 */
$t->check(
    'a task is folded down to one run',
    false !== stripos($sql, 'GROUP BY `taskID`')
);
$t->check(
    'a run is dated by its earliest transition, not by each row',
    (bool)preg_match('#MIN\(\s*`createTime`\s*\)#i', $sql)
);
$t->check(
    'the days are grouped off that earliest transition',
    (bool)preg_match('#GROUP BY DATE\(\s*`started`\s*\)#i', $sql)
);
// COUNT(DISTINCT taskID) was how the old shape deduplicated. The inner
// GROUP BY does it now, and leaving both in would not be wrong so much as a
// sign the rewrite was half-applied -- the outer query counts runs.
$t->check(
    'the outer query counts runs, not rows',
    false !== stripos($sql, 'COUNT(*)')
);

/*
 * The window still bounds the scan. Moving BETWEEN outside the derived table
 * would make every poll of this endpoint -- and it polls -- scan all of
 * taskLog to compute a MIN it then throws away.
 */
$t->check(
    'the date window bounds the inner scan',
    (bool)preg_match(
        '#SELECT\s+`taskID`.*?BETWEEN\s+:start\s+AND\s+:end#s',
        $sql
    )
);

/*
 * Every column the query names exists on taskLog, read from the manifest the
 * installer builds from rather than from a copy kept here.
 */
$expected = include $root . '/packages/web/commons/schema-expected.php';
$columns = [];
foreach ((array)$expected['tables'] as $name => $table) {
    if (0 !== strcasecmp($name, 'taskLog')) {
        continue;
    }
    foreach ((array)$table['columns'] as $column => $type) {
        $columns[] = strtolower($column);
    }
}
if ($t->check('taskLog is in the schema manifest', count($columns) > 0)) {
    foreach (['taskID', 'createTime', 'logImageName', 'taskStateID'] as $column) {
        $t->check(
            "`$column` is a real taskLog column",
            in_array(strtolower($column), $columns, true)
        );
    }
}

$t->finish();
