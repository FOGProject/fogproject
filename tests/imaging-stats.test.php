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
 * 4b. The tiles.
 *
 *     totals() is derived from the same fold the grid is, so a tile cannot
 *     report a different number of runs from the rows beneath it. What the
 *     responder proves is the DERIVATION: distinct machines and distinct
 *     images, counted off run rows rather than asked of the database
 *     separately, and a run whose host has since been deleted still
 *     counting as a machine.
 */
$db->responder = function ($sql) {
    if (false === strpos($sql, 'taskLog')) {
        return null;
    }
    return [
        ['taskID' => '1', 'hostID' => '7', 'hostName' => 'a',
         'imageName' => 'win11', 'started' => '2026-03-01 09:00:00'],
        ['taskID' => '2', 'hostID' => '7', 'hostName' => 'a',
         'imageName' => 'win11', 'started' => '2026-03-02 09:00:00'],
        ['taskID' => '3', 'hostID' => '9', 'hostName' => 'b',
         'imageName' => 'ubuntu', 'started' => '2026-03-03 09:00:00'],
        // Deleted since: no id left to group on, but a real machine was
        // imaged and the tile has to say so.
        ['taskID' => '4', 'hostID' => '0', 'hostName' => 'gone',
         'imageName' => 'ubuntu', 'started' => '2026-03-04 09:00:00'],
        ['taskID' => '5', 'hostID' => '0', 'hostName' => 'alsogone',
         'imageName' => 'ubuntu', 'started' => '2026-03-05 09:00:00'],
    ];
};
$totals = ImagingStats::totals(
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);
$t->check('the runs tile counts the run rows', 5 === $totals['runs']);
$t->check(
    'the machines tile counts DISTINCT hosts, not runs',
    4 === $totals['hosts']
);
$t->check(
    'the images tile counts DISTINCT images',
    2 === $totals['images']
);
$t->check(
    'a complete result is not reported as truncated',
    false === $totals['truncated']
);
// The bound is in the QUERY. A slice after the fetch still materializes
// every row a wide window matched, which is the "grid All -> blank 500"
// this codebase has fixed once already; and asking for one row past the
// cap is what makes `truncated` answerable without a second COUNT.
$runsSql = FogTestHarness::callStatic('FOG\Audit\ImagingStats', '_runsSql');
$t->check(
    'the grid query is bounded in SQL, not after the fetch',
    1 === preg_match('/LIMIT\s+(\d+)\s*$/', trim($runsSql), $lim)
        && (int)$lim[1] === ImagingStats::MAX_ROWS + 1
);
// Asserted behaviorally too: a responder that answers with the full cap
// plus one has to be reported as truncated, and the extra row dropped.
$db->responder = function ($sql) {
    if (false === strpos($sql, 'taskLog')) {
        return null;
    }
    $rows = [];
    for ($i = 0; $i <= ImagingStats::MAX_ROWS; $i++) {
        $rows[] = ['taskID' => (string)$i, 'hostID' => (string)$i,
            'hostName' => 'h' . $i, 'imageName' => 'win11',
            'started' => '2026-03-01 09:00:00'];
    }
    return $rows;
};
$over = ImagingStats::totals(
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
);
$t->check(
    'one row past the cap is reported as truncated',
    true === $over['truncated']
);
$t->check(
    'and the extra row is not counted',
    ImagingStats::MAX_ROWS === $over['runs']
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
    $root . '/src/Pages/DashboardPage.php'
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

/*
 * 8. The fold is ONE definition, and the panels agree because they share it.
 *
 *    This is the property the class exists for. A chart whose total does not
 *    match the grid beneath it is not a visible conflict -- nobody adds the
 *    bars up -- so it has to be asserted rather than noticed.
 */
$sqlFold = FogTestHarness::callStatic('FOG\Audit\ImagingStats', '_foldSql');
foreach (['_runsPerDaySql', '_runsByImageSql', '_runsSql'] as $builder) {
    $t->check(
        "$builder reads through the shared fold",
        false !== strpos(
            FogTestHarness::callStatic('FOG\Audit\ImagingStats', $builder),
            $sqlFold
        )
    );
}
// The canceled rule is stated over the RUN now, not row by row. A WHERE on
// the state gives the same set of runs and a different MAX(id) -- the last
// row that was not a cancellation -- so the grid would report a canceled
// deploy as still In-Progress.
$t->check(
    'the canceled rule is a HAVING over the run, not a WHERE on the row',
    1 === preg_match(
        '/HAVING\s+SUM\(`taskStateID` <> :canceled\)\s*>\s*0/i',
        $sqlFold
    )
);
$t->check(
    'the state is not also filtered before the fold',
    1 !== preg_match('/WHERE.*`taskStateID`.*GROUP BY/is', $sqlFold)
);
$t->check(
    'the run keeps its whole history in the group',
    false !== strpos($sqlFold, 'MAX(`id`) AS `lastID`')
        && false !== strpos($sqlFold, 'MIN(`createTime`) AS `started`')
);
// Selecting logImageName bare beside a GROUP BY would be a non-grouped
// column: rejected under ONLY_FULL_GROUP_BY and answered from an arbitrary
// row without it. It comes off the joined last row instead.
$t->check(
    'the image name is joined off the last row, not selected bare',
    false !== strpos(
        FogTestHarness::callStatic('FOG\Audit\ImagingStats', '_runsByImageSql'),
        'JOIN `taskLog` AS `l` ON `l`.`id` = `runs`.`lastID`'
    )
);

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

/*
 * 9. The panels, against the same fixtures.
 *
 *    Task 3 is the one that matters: In-Progress at 11:00 on the 3rd, then
 *    Canceled at 11:05. It IS a run -- a machine was imaged -- and its last
 *    state is Canceled. Under a WHERE-on-the-state fold its last row was the
 *    In-Progress one and a grid built on it called a canceled deploy live.
 */
$byImage = $pdo->prepare(
    FogTestHarness::callStatic('FOG\Audit\ImagingStats', '_runsByImageSql')
);
$byImage->execute($params);
$images = [];
foreach ($byImage->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    $images[$row['image']] = (int)$row['c'];
}
$t->check(
    'runs are grouped by image, biggest first',
    ['win11' => 2, 'ubuntu' => 1] === $images
);

$runsStmt = $pdo->prepare(
    FogTestHarness::callStatic('FOG\Audit\ImagingStats', '_runsSql')
);
$runsStmt->execute($params);
$runRows = $runsStmt->fetchAll(\PDO::FETCH_ASSOC);
$byTask = [];
foreach ($runRows as $row) {
    $byTask[(int)$row['taskID']] = $row;
}

$t->check('one row per run, and only the runs', 3 === count($runRows));
$t->check(
    'the runs are the folded tasks 1, 3 and 4',
    isset($byTask[1], $byTask[3], $byTask[4])
);
// Newest first, so the most recent run is at the top of the grid.
$t->check(
    'the grid is ordered newest first',
    4 === (int)$runRows[0]['taskID'] && 1 === (int)$runRows[2]['taskID']
);
// THE ONE THE HAVING BUYS.
$t->check(
    'a run canceled part way through reports its FINAL state',
    $CANCELED === (int)($byTask[3]['stateID'] ?? 0)
);
$t->check(
    'and still counts as a run, because a machine was imaged',
    isset($byTask[3])
);
$t->check(
    'a completed run reports Complete',
    $COMPLETE === (int)($byTask[1]['stateID'] ?? 0)
);
// Spans midnight: started on the 4th, ended on the 5th, one row.
$t->check(
    'a run spanning midnight carries both ends',
    '2026-03-04' === substr((string)($byTask[4]['started'] ?? ''), 0, 10)
        && '2026-03-05' === substr((string)($byTask[4]['ended'] ?? ''), 0, 10)
);
// Identity off the denormalized last row, which is what lets a run still
// name a host and an image that have since been deleted.
$t->check(
    'a run names its image from its own row',
    'ubuntu' === ($byTask[4]['imageName'] ?? null)
);

/*
 * 10. The tiles cannot disagree with the grid, because they are counted
 *     FROM it. Asserted against the same fixtures the chart used.
 */
$perDay = $pdo->prepare(
    FogTestHarness::callStatic('FOG\Audit\ImagingStats', '_runsPerDaySql')
);
$perDay->execute($params);
$chartTotal = 0;
foreach ($perDay->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    $chartTotal += (int)$row['c'];
}
$t->check(
    'the per-day chart and the grid count the same runs',
    $chartTotal === count($runRows)
);
$t->check(
    'and so does the per-image chart',
    array_sum($images) === count($runRows)
);

$t->finish();
