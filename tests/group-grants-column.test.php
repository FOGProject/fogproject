<?php
/**
 * The group list's Grants column: what it asks the database, and what it
 * renders.
 *
 * ADR 0038 Decision 16a requirement 5. Once a group is also a label, the
 * Group Management list is forty rows that all look identically
 * consequential -- and the one thing a reader needs from it is whether this
 * row is a plain label or something that pushes software at every host in
 * it.
 *
 * The column shape (no `db`, a formatter, a primer that touches no relCache
 * key) is pinned by tests/fixtures/route-column-contract.txt. What that
 * fixture cannot see is behavior, which is what this drives:
 *
 *   1. ONE query per page, not one per row and not one per kind of grant.
 *      This is GH-707's rule, and the reason the column is primed at all.
 *   2. It counts all FOUR things a group grants under this ADR -- snapins,
 *      printers, modules and power schedules -- and nothing else. An image is
 *      not among them: a group PUSHES an image imperatively
 *      (Group::addImage), it does not grant one. Power was the fourth grant
 *      to land and the column was written for three, so the check below is
 *      per-table rather than a count: adding a grant and forgetting this
 *      column is a group that reads as a plain label while it pushes
 *      shutdowns at every host in it.
 *   3. The badges come out in a fixed order (snapins, printers, modules,
 *      power schedules)
 *      whatever order the rows come back in. A UNION has no order of its
 *      own, so without the bucketing two groups could show the same grants
 *      in different orders on the same page.
 *   4. Only integers reach the SQL. The ids arrive from the grid's row set
 *      and are interpolated, not bound -- so the casting is the whole of the
 *      defense.
 *   5. A group that grants nothing renders empty, and a group id that was
 *      never primed renders empty rather than reading another row's cache.
 *   6. A failed query blanks the cell instead of throwing. This feeds a grid
 *      CELL; taking the whole list down over it would be the wrong trade,
 *      and it is the opposite of Assign\Resolver, where a missing row is a
 *      grant silently not applied.
 *   7. The header row and the JS column list agree in COUNT and in ORDER.
 *      fog.group.list.js binds by position, so a header added on one side
 *      only is DataTables error 18 -- "Incorrect column count" -- which
 *      kills the whole grid and says nothing. See
 *      tests/grid-header-column-agreement.test.php for the host list's
 *      structural fix; the group list is small enough to still bind by
 *      index, so the index is what gets pinned.
 *   8. The renderer escapes, and only decorates for type 'display'. Server
 *      text in markup, and GH-1446: registerExportTable() escapes each cell,
 *      so a badge baked into the value lands in the CSV as a literal
 *      '<span class="...'.
 *
 * Usage: php tests/group-grants-column.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('group-grants-column');

$t = new FogChecks();
$db = FogTestHarness::fakeDb();

$root = dirname(__DIR__) . '/packages/web';

/**
 * Primes the cache against a canned answer and returns the SQL that was run.
 *
 * @param FogFakeDb $db     the fake
 * @param array     $ids    group ids to prime
 * @param array     $answer rows the UNION returns
 *
 * @return array the statements the primer issued
 */
function primeGrants($db, array $ids, array $answer)
{
    $db->log = [];
    $db->responder = function ($sql) use ($answer) {
        return false !== strpos($sql, 'groupSnapinAssoc') ? $answer : null;
    };
    FogTestHarness::callStatic('Route', '_primeGroupGrants', [$ids]);
    $db->responder = null;

    return $db->log;
}

/**
 * @param int $id group id
 *
 * @return array the primed strings
 */
function grantsFor($id)
{
    return (array)FogTestHarness::callStatic(
        'Route',
        '_groupGrants',
        [['groupID' => $id]]
    );
}

// Deliberately out of display order, and interleaved across groups, so
// invariant 3 is testing the bucketing rather than the fixture's own order.
$answer = [
    ['kind' => 'module', 'gid' => 1, 'n' => 1],
    ['kind' => 'power', 'gid' => 1, 'n' => 2],
    ['kind' => 'printer', 'gid' => 2, 'n' => 4],
    ['kind' => 'snapin', 'gid' => 1, 'n' => 2],
    ['kind' => 'printer', 'gid' => 1, 'n' => 1],
];
$log = primeGrants($db, [1, 2, 3], $answer);

$t->check('the primer issues exactly one query', 1 === count($log));

$sql = isset($log[0]) ? $log[0] : '';
foreach (
    [
        'groupSnapinAssoc',
        'groupPrinterAssoc',
        'groupModuleAssoc',
        'groupPowerManagement'
    ] as $tbl
) {
    $t->check(
        "the one query counts $tbl",
        false !== strpos($sql, '`' . $tbl . '`')
    );
}
$t->check(
    'it counts nothing else a group is associated with',
    false === strpos($sql, 'groupMembers')
    && false === strpos($sql, 'groupImageAssoc')
);
$t->check(
    'it asks only for the ids on the page',
    5 === substr_count($sql, 'IN (1,2,3)')
);
$t->check(
    'each arm groups by the assoc row it counts',
    5 === substr_count($sql, 'GROUP BY')
);

// Invariant 3: fixed display order regardless of the row order above.
$t->check(
    'group 1 reads snapins, printers, modules, power in that order',
    ['2 snapins', '1 printer', '1 module', '2 power schedules']
    === grantsFor(1)
);
$t->check(
    'a single grant takes the singular form',
    ['1 module'] === array_slice(grantsFor(1), -2, 1)
);
$t->check(
    'group 2 reads only what it grants',
    ['4 printers'] === grantsFor(2)
);
// Invariant 5, both halves.
$t->check(
    'a group with no grants reads empty',
    [] === grantsFor(3)
);
$t->check(
    'a group that was never primed reads empty',
    [] === grantsFor(99)
);
$t->check(
    'a row with no group id reads empty',
    [] === (array)FogTestHarness::callStatic('Route', '_groupGrants', [[]])
);

// Invariant 4. The ids are interpolated, so this is the whole defense.
$log = primeGrants($db, ['4; DROP TABLE `groups`', '0', '-3', 7], []);
$sql = isset($log[0]) ? $log[0] : '';
$t->check(
    'a non-numeric id cannot carry SQL into the query',
    false === strpos($sql, 'DROP TABLE')
);
$t->check(
    'a non-numeric id is cast, not dropped',
    5 === substr_count($sql, 'IN (4,7)')
);
$t->check(
    'ids that are not positive are dropped',
    false === strpos($sql, '-3') && false === strpos($sql, ',0')
);
$log = primeGrants($db, ['nope', 0], []);
$t->check(
    'no usable id runs no query at all',
    0 === count($log)
);
$t->check(
    'and leaves the cache empty rather than stale',
    [] === grantsFor(1)
);

// Invariant 6. The responder still answers, so a primer that ignored the
// error would fill the cell from those rows -- without that this passes for
// the wrong reason, because the fake returns no rows on a plain miss anyway.
$db->log = [];
$db->responder = function ($sql) {
    return false !== strpos($sql, 'groupSnapinAssoc')
        ? [['kind' => 'snapin', 'gid' => 1, 'n' => 9]]
        : null;
};
$db->error = 'the database went away';
FogTestHarness::callStatic('Route', '_primeGroupGrants', [[1]]);
$db->error = false;
$db->responder = null;
$t->check(
    'a failed query blanks the cell instead of throwing',
    [] === grantsFor(1)
);

// Invariant 7. Header order server-side, column order client-side.
$page = (string)file_get_contents($root . '/src/Pages/GroupManagement.php');
$js = (string)file_get_contents(
    $root . '/management/js/fog/group/fog.group.list.js'
);
$header = [];
if (preg_match('/\$this->headerData = \[(.*?)\];/s', $page, $m)) {
    preg_match_all("/_\('([^']+)'\)/", $m[1], $hm);
    $header = $hm[1];
}
$columns = [];
if (preg_match('/columns: \[(.*?)\],\s*rowId/s', $js, $m)) {
    preg_match_all("/data: '([^']+)'/", $m[1], $cm);
    $columns = $cm[1];
}
$t->check('the header row was found', count($header) > 0);
$t->check('the JS column list was found', count($columns) > 0);
$t->check(
    'header and column counts agree, or DataTables kills the grid',
    count($header) === count($columns)
);
$t->check(
    'Grants is the last header',
    'Grants' === end($header)
);
$t->check(
    'grants is the column at that same index',
    'grants' === end($columns)
);
$grantsIndex = array_search('grants', $columns, true);
$t->check(
    'the grants columnDef targets that index',
    false !== $grantsIndex
    && preg_match(
        '/orderable: false,.*?targets: ' . (int)$grantsIndex . '\b/s',
        $js
    )
);
$t->check(
    'the attributes list has an entry per header',
    preg_match('/\$this->attributes = \[(.*?)\];/s', $page, $am)
    && count($header) === substr_count($am[1], PHP_EOL . '            [')
);

// Invariant 8, on the renderer itself rather than on the file.
$render = '';
if (preg_match('/render: function\(data, type, row\) \{(.*?)\n {16}\}/s', $js, $m)) {
    $render = $m[1];
}
$t->check('the grants renderer was found', '' !== $render);
$t->check(
    'it hands back the plain value for anything but display',
    false !== strpos($render, "type !== 'display'")
    && false !== strpos($render, 'return data;')
);
$t->check(
    'it reads the list the server sends alongside',
    false !== strpos($render, 'row.grants_list')
);
$t->check(
    'it escapes every string it puts in markup',
    false !== strpos($render, '$.escapeHtml(String(grant))')
);
// The other half of invariant 8: the value the formatter joins is plain
// text. The formatter is implode(', ', ...) over exactly these strings, so
// a badge introduced server-side would show up here first -- and in the CSV
// export as literal markup, which is what GH-1446 was.
primeGrants($db, [1], [['kind' => 'snapin', 'gid' => 1, 'n' => 3]]);
$plain = grantsFor(1);
$t->check(
    'the server composes plain text, never markup',
    ['3 snapins'] === $plain
    && false === strpos($plain[0], '<')
    && false === strpos($plain[0], '&')
);

$t->finish();
