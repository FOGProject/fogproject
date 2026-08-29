<?php
/**
 * The three rules for counting an imaging run out of taskLog.
 *
 * ADR 0030 decisions 2 and 3. taskLog holds one row per state TRANSITION,
 * not one per run, so counting deployments from it takes three rules that
 * every one of them fails SILENTLY -- a broken query still returns a
 * plausible number, and nothing distinguishes it from a right one by eye.
 * That is the whole reason this file exists and the reason every check here
 * was mutation-verified rather than merely written.
 *
 *   fold to one row per task     a task through three states is one image
 *   exclude the canceled state  a queued-then-canceled deploy carries an
 *                                image name on its only row and touched
 *                                nothing; one canceled MID image has an
 *                                In-Progress row and did
 *   attribute to MIN(createTime) a run spanning midnight writes rows on two
 *                                days and is one run, on the first
 *
 * THREE HALVES, and they fail differently:
 *
 *   the SQL semantics       run for real against TEMPORARY tables, because
 *                           a GROUP BY is not provable by reading it
 *   the series contract     zero fill, ordering and the clamp, driven
 *                           through a fake connection with canned rows
 *   the definition moved    dashboardpage.page.php must no longer contain
 *                           the query. Asserting only that it CALLS
 *                           ImagingStats would pass with the old query
 *                           still sitting next to the call
 *
 * The SQL is reached through Reflection rather than copied, so this cannot
 * drift from what runs -- the same reason TaskManagement has
 * _logQueryFrom(). The database half skips without FOG_TEST_DSN, the
 * convention tests/schema-executes.test.php and
 * tests/tasklog-report-retention.test.php both use, and needs no CREATE
 * DATABASE right: an ordinary FOG database user can run it.
 *
 * Usage:
 *   php tests/imaging-stats.test.php
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;dbname=fog' FOG_TEST_USER=... \
 *     FOG_TEST_PASS=... php tests/imaging-stats.test.php
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

use FOG\Audit\ImagingStats;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('imaging-stats');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

$root = dirname(__DIR__) . '/packages/web';
$manifest = include $root . '/commons/schema-expected.php';

/*
 * 1. The SQL names only columns taskLog actually has.
 *
 *    A misspelled column is an SQL error at runtime and nothing at all at
 *    review time, and this query is on a page that swallows its own errors
 *    into an empty graph.
 */
$sql = FogTestHarness::callStatic('FOG\Audit\ImagingStats', '_runsPerDaySql');
$columns = array_keys($manifest['tables']['taskLog']['columns']);

foreach (['taskID', 'createTime', 'logImageName', 'taskStateID'] as $needed) {
    $t->check(
        "the query's `$needed` is a real taskLog column",
        in_array($needed, $columns, true)
            && false !== strpos($sql, '`' . $needed . '`')
    );
}

/*
 * 2. Nothing is interpolated. The window and the canceled state are the
 *    only values in the statement and both must be placeholders -- a state
 *    id is hookable, so a literal here would go stale silently the moment a
 *    plugin moved it.
 */
$t->check(
    'the window is bound, not interpolated',
    false !== strpos($sql, ':start') && false !== strpos($sql, ':end')
);
$t->check(
    'the canceled state is bound, not a literal',
    false !== strpos($sql, ':canceled')
        && 1 !== preg_match('/taskStateID`?\s*<>\s*\d/', $sql)
);

/*
 * 3. The three rules, as structure. These are the weak form -- the DB half
 *    below is what actually proves them -- but they run everywhere and they
 *    name the rule, so a deletion is reported as the rule it removed rather
 *    than as a diff.
 */
$t->check(
    'rule 1: transition rows are folded per task',
    1 === preg_match('/GROUP\s+BY\s+`taskID`/i', $sql)
);
$t->check(
    'rule 2: the canceled state is excluded',
    1 === preg_match('/`taskStateID`\s*<>\s*:canceled/i', $sql)
);
$t->check(
    'rule 3: a run is attributed to its earliest row',
    1 === preg_match('/MIN\(\s*`createTime`\s*\)/i', $sql)
);
$t->check(
    'an imaging row is identified by its image name, not its task type',
    false !== strpos($sql, "`logImageName` <> ''")
        && false === strpos($sql, 'logTaskTypeName')
);

/*
 * 3b. Structural properties inherited from
 *     tests/imaging-count-excludes-cancels.test.php, which this file
 *     replaces. That test asserted on the query while it lived inside
 *     DashboardPage::get30day(); the query is here now, so these came with
 *     it rather than being dropped on the floor.
 */
$t->check(
    'it reads taskLog',
    false !== stripos($sql, '`taskLog`')
);
// Not the Failed state and not the queued ones. Excluding a second state
// would silently stop counting work that really was done.
$t->check(
    'no state other than canceled is excluded',
    1 === substr_count($sql, '`taskStateID`')
);
$t->check(
    'the days are grouped off that earliest transition',
    1 === preg_match('/GROUP BY DATE\(\s*`started`\s*\)/i', $sql)
);
// COUNT(DISTINCT taskID) was how an older shape deduplicated. The inner
// GROUP BY does it now, and both together would not be wrong so much as a
// sign the rewrite was half applied.
$t->check(
    'the outer query counts runs, not rows',
    false !== stripos($sql, 'COUNT(*)')
);
// A perf gate, not a correctness one: moving BETWEEN outside the derived
// table makes every call scan all of taskLog to compute a MIN it then
// throws away -- and this endpoint is polled.
//
// ASSERTED BY POSITION, not by a regex spanning the statement. The check
// inherited from the old test was
// `/SELECT\s+`taskID`.*?BETWEEN\s+:start\s+AND\s+:end/s`, which passes
// with the bound moved to the OUTER query -- `.*?` under /s happily reaches
// across the derived table's closing paren. Verified by making exactly that
// move: the old form stayed green, this one goes red.
$innerEnd = strpos($sql, ') AS `runs`');
$boundAt = strpos($sql, '`createTime` BETWEEN :start AND :end');
$t->check(
    'the date window bounds the INNER scan',
    false !== $innerEnd && false !== $boundAt && $boundAt < $innerEnd
);
// And bounds the indexed COLUMN rather than the derived alias: schema 379
// indexes taskLog.createTime, and `started` is a MIN() result no index can
// answer for.
$t->check(
    'the window is bound once, on the indexed column',
    1 === substr_count($sql, 'BETWEEN')
);

/*
 * 4. The series contract, through a fake connection.
 *
 *    The responder answers the rollup with two days inside a five day
 *    window, so what is asserted is the three days it had to invent.
 */
$asked = [];
$db->responder = function ($sql, $params) use (&$asked) {
    if (false === strpos($sql, 'taskLog')) {
        return null;
    }
    $asked = $params;
    return [
        ['d' => '2026-03-02', 'c' => '4'],
        ['d' => '2026-03-04', 'c' => '1'],
    ];
};

$series = ImagingStats::runsPerDay(
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);

$t->check('the window produces one point per day', 5 === count($series));
$t->check(
    'the series is ordered and complete',
    ['2026-03-01', '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05']
        === array_column($series, 'date')
);
$t->check(
    'a day with runs carries its count as an int',
    4 === $series[1]['count'] && 1 === $series[3]['count']
);
// The one that matters: a chart draws a line straight across a MISSING day,
// so an idle week reads as steady activity. A zero has to be present.
$t->check(
    'a day with no runs is a zero, not a gap',
    0 === $series[0]['count']
        && 0 === $series[2]['count']
        && 0 === $series[4]['count']
);
$t->check(
    'the last day of the window is included',
    '2026-03-05' === $series[4]['date']
);
$t->check(
    'the bounds reach the query as datetimes',
    '2026-03-01 00:00:00' === ($asked[':start'] ?? null)
        && '2026-03-05 23:59:59' === ($asked[':end'] ?? null)
);
$t->check(
    'the canceled state reaches the query',
    5 === (int)($asked[':canceled'] ?? 0)
);

/*
 * 5. The window is ordered and capped.
 */
$reversed = ImagingStats::runsPerDay(
    new \DateTimeImmutable('2026-03-05 23:59:59'),
    new \DateTimeImmutable('2026-03-01 00:00:00')
);
$t->check(
    'bounds passed backwards mean the range between them',
    5 === count($reversed)
);

$capped = ImagingStats::runsPerDay(
    new \DateTimeImmutable('2020-01-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);
$t->check(
    'a window wider than MAX_DAYS is capped',
    ImagingStats::MAX_DAYS === count($capped)
);
$t->check(
    'the cap keeps the RECENT end of the window',
    '2026-03-05' === $capped[count($capped) - 1]['date']
);
// A leap year is 366 days and the dashboard's widest view is a year, so a
// cap of 365 would clip the widest shipped view by a day and be reported as
// "the 1 Year graph is missing a day". Asserted by asking for a whole leap
// year and counting what comes back -- comparing the constant to 366 reads
// as the same check and is not one, because both sides are known before the
// code runs and it can only ever be true.
$leap = ImagingStats::runsPerDay(
    new \DateTimeImmutable('2024-01-01 00:00:00'),
    new \DateTimeImmutable('2024-12-31 23:59:59')
);
$t->check('a full leap year comes back unclipped', 366 === count($leap));
$t->check(
    'and still ends on the day it was asked for',
    '2024-12-31' === $leap[365]['date']
);

/*
 * 6. The definition MOVED, which is the point of the exercise.
 *
 *    Checking only that the page calls ImagingStats would pass with the old
 *    query still in place beside the call. What has to be true is that the
 *    page no longer contains one.
 */
$page = (string)file_get_contents(
    $root . '/lib/pages/dashboardpage.page.php'
);
$t->check(
    'the dashboard calls the rollup',
    false !== strpos($page, 'ImagingStats::runsPerDay')
);
$t->check(
    'the dashboard no longer queries taskLog itself',
    false === strpos($page, '`taskLog`')
);
$t->check(
    'and no longer carries a copy of any of the three rules',
    false === strpos($page, 'MIN(`createTime`)')
        && false === strpos($page, "`logImageName` <> ''")
);
// strtotime() on a bare date resolves in PHP's timezone while every bound is
// on FOG's, so the points would be plotted off by the difference between the
// two clocks -- the exact failure this rollup exists to stop, reintroduced
// one line after calling it.
$t->check(
    'the dashboard converts the series on FOG clock, not PHP\'s',
    false !== strpos($page, "self::niceDate(\$point['date'])")
        && false === strpos($page, "strtotime(\$point['date'])")
);

/*
 * 7. The SQL semantics, for real. The GROUP BY is the fix; a fake
 *    connection cannot show it.
 */
$dsn = getenv('FOG_TEST_DSN');
if (false === $dsn || '' === $dsn) {
    echo "SKIP  no FOG_TEST_DSN set; SQL semantics not executed\n";
    $t->finish();
}

if (!in_array('mysql', \PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FAIL: FOG_TEST_DSN is set but pdo_mysql is missing.\n");
    exit(1);
}

$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$pdo = new \PDO(
    $dsn,
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);

// taskLog comes from the manifest, so what is queried is the table the
// release ships. TEMPORARY is asserted before the statement is issued: the
// manifest's create is a plain CREATE TABLE and running it as-is would
// leave a shadow behind in whatever database the DSN names, which may be a
// live one since this needs no CREATE DATABASE right.
$create = (string)($manifest['tables']['taskLog']['create'] ?? '');
$create = preg_replace(
    '/^CREATE TABLE IF NOT EXISTS /i',
    'CREATE TEMPORARY TABLE ',
    $create
);
if (0 !== stripos($create, 'CREATE TEMPORARY TABLE')) {
    fwrite(STDERR, "FAIL: refusing a non-TEMPORARY CREATE for taskLog.\n");
    exit(1);
}
$pdo->exec($create);

$CANCELED = 5;
$INPROGRESS = 3;
$COMPLETE = 4;

$rows = [
    // ONE RULE PER DAY, so a mutation turns exactly the check that names it
    // red. Sharing a day would make one number prove all four jointly and
    // none of them individually, which is how a suite reports "verified" on
    // a rule it never isolated.
    //
    // Rule 1, the 1st. One deploy, three transitions. One image.
    [1, $INPROGRESS, '2026-03-01 09:00:00', 'win11'],
    [1, $COMPLETE,   '2026-03-01 09:20:00', 'win11'],
    [1, $COMPLETE,   '2026-03-01 09:21:00', 'win11'],
    // Rule 2a, the 2nd. Queued and canceled without ever starting: its
    // only row carries an image name and no machine was touched.
    [2, $CANCELED,  '2026-03-02 10:00:00', 'win11'],
    // Rule 2b, the 3rd. Canceled MID image. The In-Progress row survives
    // the exclusion, so this counts -- the exclusion is on the ROW, not on
    // the task, and that difference is the whole of the rule.
    [3, $INPROGRESS, '2026-03-03 11:00:00', 'win11'],
    [3, $CANCELED,  '2026-03-03 11:05:00', 'win11'],
    // Rule 3, the 4th into the 5th. Starts at 23:50, finishes at 00:10.
    [4, $INPROGRESS, '2026-03-04 23:50:00', 'ubuntu'],
    [4, $COMPLETE,   '2026-03-05 00:10:00', 'ubuntu'],
    // The image-name filter, the 6th. Not an imaging task at all.
    [5, $COMPLETE,   '2026-03-06 12:00:00', ''],
    // The window itself. Well outside it.
    [6, $COMPLETE,   '2026-02-01 12:00:00', 'win11'],
];
$ins = $pdo->prepare(
    'INSERT INTO `taskLog` (`taskID`,`taskStateID`,`createTime`,'
    . '`logImageName`,`ip`,`createdBy`,`logType`) '
    . "VALUES (?,?,?,?,'127.0.0.1','fog','state')"
);
foreach ($rows as $r) {
    $ins->execute($r);
}

// Bound against the placeholders the statement ACTUALLY has, rather than a
// fixed three. Not defensiveness: if a rule is ever deleted from the query
// its placeholder goes with it, and passing one PDO has no token for is a
// fatal -- so the suite would die with "Invalid parameter number" instead of
// reddening the check that names the missing rule. A test that crashes says
// less than one that says which rule went.
$params = [
    ':start' => '2026-03-01 00:00:00',
    ':end' => '2026-03-07 23:59:59',
    ':canceled' => $CANCELED
];
foreach (array_keys($params) as $name) {
    if (false === strpos($sql, $name)) {
        unset($params[$name]);
    }
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$got = [];
foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    $got[$row['d']] = (int)$row['c'];
}

$t->check(
    'rule 1 holds: three transitions of one task are ONE image',
    1 === ($got['2026-03-01'] ?? null)
);
$t->check(
    'rule 2a holds: a deploy canceled before starting is not an image',
    !isset($got['2026-03-02'])
);
$t->check(
    'rule 2b holds: a deploy canceled MID image still counts',
    1 === ($got['2026-03-03'] ?? null)
);
$t->check(
    'rule 3 holds: a run spanning midnight counts once...',
    1 === ($got['2026-03-04'] ?? null)
);
$t->check(
    'rule 3 holds: ...on the day it STARTED, not the day it finished',
    !isset($got['2026-03-05'])
);
$t->check(
    'a task carrying no image name is not an imaging run',
    !isset($got['2026-03-06'])
);
$t->check(
    'a run outside the window is not counted',
    !isset($got['2026-02-01'])
);
$t->check(
    'exactly the three expected days came back',
    ['2026-03-01', '2026-03-03', '2026-03-04'] === array_keys($got)
);

$t->finish();
