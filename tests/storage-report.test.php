<?php
/**
 * The Storage Report is wired to StorageStats, and totals the estate right.
 *
 * The wiring every ADR 0030 report shares -- names, columns, gate, label,
 * and the placeholder rule -- is in tests/lib/report-wiring.php. What is
 * here is this report's own:
 *
 *   - THE SIZES ARE ALLOCATION, NOT USAGE. `imageServerSize` is what the
 *     image measured when it was captured, copied to the rest of the group
 *     by replication. Presenting it as current node usage would be wrong
 *     in a way nobody reading the page could detect, so the page has to
 *     say which it is.
 *   - AN IMAGE IN TWO GROUPS COUNTS IN BOTH. The bytes are on both sets of
 *     nodes. That makes the group totals sum to MORE than the estate
 *     total, which is why the estate total is its own query -- deriving it
 *     from the chart would double-count every shared image.
 *   - NO NODE CREDENTIALS. `nfsGroupMembers` carries ngmUser, ngmPass and
 *     ngmKey in the same row as the node name.
 *   - THE SIZE COLUMN SORTS ON BYTES. "9 GiB" is above "10 GiB" as a
 *     string, and a size column that sorts wrong is worse than one that
 *     does not sort.
 *
 * Usage: php tests/storage-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/report-wiring.php';

FogTestHarness::boot('storage-report');
$db = FogTestHarness::fakeDb();

use FOG\Audit\StorageStats;

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$code = FogReportWiring::check(
    $t,
    $web,
    'storage_report',
    'Storage_Report',
    'storagenode',
    'storagereport-table'
);
FogReportWiring::checkSql($t, 'FOG\Audit\StorageStats');

/*
 * 1. The counting rules stay in StorageStats.
 */
$t->check(
    'the page has no images query of its own',
    false === strpos($code, '`images`')
);
foreach (['totals(', 'sizeByGroup(', 'largest(', 'addedPerDay(', 'images(']
    as $call
) {
    $t->check(
        "it reads StorageStats::$call)",
        false !== strpos($code, 'StorageStats::' . $call)
    );
}
$t->check(
    'this report defaults to a year, because images move slowly',
    '-365 days' === constant('FOG\Storage_Report::DEFAULT_WINDOW')
);
$t->check(
    'the page says the sizes are allocation, not node usage',
    false !== strpos($code, 'not ')
    && false !== strpos($code, 'current node usage')
);

/*
 * 2. Nothing here selects a node credential. The columns sit in the same
 *    row as the node name, so a SELECT * would have shipped them.
 */
$builders = [
    '_sizeByGroupSql',
    '_largestSql',
    '_addedPerDaySql',
    '_totalsSql',
    '_estateSql',
    '_imagesSql'
];
$all = '';
foreach ($builders as $builder) {
    $all .= FogTestHarness::callStatic('FOG\Audit\StorageStats', $builder);
}
foreach (['ngmPass', 'ngmUser', 'ngmKey', 'SELECT *'] as $forbidden) {
    $t->check(
        "no statement selects $forbidden",
        false === strpos($all, $forbidden)
    );
}
$t->check(
    'and neither does the page',
    false === strpos($code, 'ngmPass') && false === strpos($code, 'ngmKey')
);

/*
 * 3. The statements.
 */
$totalsSql = FogTestHarness::callStatic(
    'FOG\Audit\StorageStats',
    '_totalsSql'
);
$t->check(
    'the estate total is SUMmed, not counted',
    false !== strpos($totalsSql, 'SUM(`imageServerSize`)')
);
$t->check(
    'an estate with no images totals zero rather than NULL',
    false !== strpos($totalsSql, 'COALESCE(SUM(`imageServerSize`), 0)')
);
$t->check(
    'never deployed covers NULL and the zero date alike',
    false !== strpos($totalsSql, '`imageLastDeploy` IS NULL')
    && false !== strpos($totalsSql, "`imageLastDeploy` = '0000-00-00 00:00:00'")
);
$t->check(
    'the second use of the start date has its own name',
    false !== strpos($totalsSql, ':staleBefore')
);
$estateSql = FogTestHarness::callStatic(
    'FOG\Audit\StorageStats',
    '_estateSql'
);
$t->check(
    'groups and nodes are counted separately, not through a join',
    false === strpos($estateSql, 'JOIN')
    && 3 === substr_count($estateSql, 'SELECT COUNT(*)')
);
$groupSql = FogTestHarness::callStatic(
    'FOG\Audit\StorageStats',
    '_sizeByGroupSql'
);
$t->check(
    'the per-group total is driven from the association, not from images',
    1 === preg_match('/FROM\s+`imageGroupAssoc`/', $groupSql)
);
$t->check(
    'an image whose group has gone still contributes its bytes',
    0 === substr_count($groupSql, 'INNER JOIN')
    && 2 === substr_count($groupSql, 'LEFT OUTER JOIN')
);
$imagesSql = FogTestHarness::callStatic(
    'FOG\Audit\StorageStats',
    '_imagesSql'
);
$t->check(
    'the grid query is bounded in SQL, not after the fetch',
    1 === preg_match('/LIMIT\s+(\d+)\s*$/', trim($imagesSql), $lim)
        && (int)$lim[1] === StorageStats::MAX_ROWS + 1
);
$t->check(
    'the group count per image is a COUNT, so an image is one row',
    false !== strpos($imagesSql, 'COUNT(`imageGroupAssoc`.`igaID`)')
    && false !== strpos($imagesSql, 'GROUP BY `images`.`imageID`')
);
$largestSql = FogTestHarness::callStatic(
    'FOG\Audit\StorageStats',
    '_largestSql'
);
$t->check(
    'the largest-images chart is bounded in SQL',
    1 === preg_match('/LIMIT\s+(\d+)\s*$/', trim($largestSql), $lim)
        && (int)$lim[1] === StorageStats::TOP_IMAGES
);

/*
 * 4. The size column sorts on bytes, not on the formatted string.
 */
$js = file_get_contents(
    $web . '/management/js/fog/report/fog.report.file.js'
);
$block = substr($js, (int)strpos($js, "case 'storage report':"));
$block = substr($block, 0, (int)strpos($block, 'break;'));
$t->check(
    'the size column orders on the raw byte column beside it',
    1 === preg_match('/orderData: \[7\],\s*targets: \[1\]/', $block)
);
$t->check(
    'and that column is hidden rather than shown twice',
    1 === preg_match('/targets: \[7\],\s*visible: false/', $block)
);

/*
 * 5. The derivation, through a fake connection.
 */
$window = [
    new \DateTimeImmutable('2026-03-01 00:00:00'),
    new \DateTimeImmutable('2026-03-05 23:59:59')
];
$db->responder = function ($sql) {
    if (false !== strpos($sql, 'nfsGroups`) AS `groups`')) {
        return [['groups' => '2', 'nodes' => '5', 'enabledNodes' => '4']];
    }
    if (false !== strpos($sql, 'AS `images`')) {
        return [[
            'images' => '7', 'bytes' => '1024', 'notReplicated' => '1',
            'neverDeployed' => '2', 'stale' => '3', 'added' => '1'
        ]];
    }
    return null;
};
$totals = StorageStats::totals($window[0], $window[1]);
$t->check(
    'the image totals and the estate counts arrive in one array',
    7 === $totals['images'] && 1024 === $totals['bytes']
    && 5 === $totals['nodes'] && 2 === $totals['groups']
);
$t->check(
    'never deployed is a subset of stale, not a separate population',
    $totals['neverDeployed'] <= $totals['stale']
);

$db->responder = function ($sql) {
    if (false === strpos($sql, 'imageGroupAssoc')) {
        return null;
    }
    return [
        ['label' => 'default', 'c' => '900'],
        ['label' => null, 'c' => '100']
    ];
};
$groups = StorageStats::sizeByGroup($window[0], $window[1]);
$t->check(
    'bytes under a deleted group are named, not dropped',
    2 === count($groups)
    && in_array(_('Unassigned'), array_column($groups, 'label'), true)
);
$t->check(
    'nothing is lost naming them',
    1000 === array_sum(array_column($groups, 'count'))
);

$db->responder = function ($sql) {
    if (false === strpos($sql, 'imageDateTime')) {
        return null;
    }
    return [['d' => '2026-03-04', 'c' => '3']];
};
$added = StorageStats::addedPerDay($window[0], $window[1]);
$t->check('the window produces one point per day', 5 === count($added));
$t->check(
    'a day with no images added is a zero, not a gap',
    0 === $added[0]['count'] && 3 === $added[3]['count']
);
$t->check(
    'the last day of the window is included',
    '2026-03-05' === $added[4]['date']
);

$t->finish();
