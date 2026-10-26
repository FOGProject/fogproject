<?php
/**
 * A grid or list row keeps a NULL column as null.
 *
 * WHAT BROKE (GH-1953). FOGManagerController::dataOutput() builds every row
 * that Route::listem() and the other complex() callers send: the REST lists
 * and every DataTables grid. For a column with no formatter it copied the
 * value only when isset($row[$col]) was true. isset() is false for NULL, so
 * a NULL column fell through to the '' default. `GET /fog/host/list` then
 * said "imageID": "" for a host with no image, while `GET /fog/host/{id}`
 * said null and the OpenAPI document said `integer, nullable`. A client got
 * two answers for one stored value, and a string where the schema promises
 * an integer or null.
 *
 * WHAT IS PINNED.
 *
 *   - A key the row carries with a NULL value is emitted as null, for both
 *     the `db` and the `do` lookups.
 *   - A key the row does not carry at all is still emitted as '', which is
 *     what it always was.
 *   - A present non-NULL value is copied unchanged, including the falsy
 *     ones ('0', 0 and '').
 *   - A formatter still receives the raw value, so nothing changes there.
 *   - The same holds through complex(), the path every grid takes, so a
 *     later rewrite of dataOutput()'s call site cannot quietly undo it.
 *
 * Usage: php tests/list-rows-keep-null.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGManagerController;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('list-rows-keep-null');

$db = FogTestHarness::fakeDb();
$t = new FogChecks();

// ------------------------------------------------------- dataOutput() alone

$seen = 'not called';
$columns = [
    ['db' => 'hostID', 'dt' => 'id'],
    ['db' => 'hostImage', 'dt' => 'imageID'],
    ['db' => 'hostArchID', 'dt' => 'archID'],
    ['db' => 'hostName', 'dt' => 'name'],
    ['db' => 'hostPending', 'dt' => 'pending'],
    ['db' => 'hostDesc', 'dt' => 'description'],
    // Selected by nobody: the row has no such key.
    ['db' => 'notInTheRow', 'dt' => 'absent'],
    // The association tabs' shape: no `db`, a `do` alias instead.
    ['do' => 'hostAssoc', 'dt' => 'association'],
    ['do' => 'missingAssoc', 'dt' => 'noassoc'],
    [
        'db' => 'hostImage',
        'dt' => 'formatted',
        'formatter' => function ($d, $row) use (&$seen) {
            $seen = $d;
            return 'f';
        }
    ],
];
$rows = FOGManagerController::dataOutput(
    $columns,
    [
        [
            'hostID' => 7,
            'hostImage' => null,
            'hostArchID' => null,
            'hostName' => 'lab-07',
            'hostPending' => '0',
            'hostDesc' => '',
            'hostAssoc' => null,
        ]
    ]
);
$row = $rows[0] ?? [];

$t->check(
    'a NULL column is emitted as null, not "" (got '
        . var_export($row['imageID'] ?? 'MISSING', true) . ')',
    array_key_exists('imageID', $row) && null === $row['imageID']
);
$t->check(
    'a second NULL column is emitted as null too (got '
        . var_export($row['archID'] ?? 'MISSING', true) . ')',
    array_key_exists('archID', $row) && null === $row['archID']
);
$t->check(
    'a NULL `do` alias is emitted as null (got '
        . var_export($row['association'] ?? 'MISSING', true) . ')',
    array_key_exists('association', $row) && null === $row['association']
);
$t->check(
    'a `db` key the row does not carry is still "" (got '
        . var_export($row['absent'] ?? 'MISSING', true) . ')',
    array_key_exists('absent', $row) && '' === $row['absent']
);
$t->check(
    'a `do` key the row does not carry is still "" (got '
        . var_export($row['noassoc'] ?? 'MISSING', true) . ')',
    array_key_exists('noassoc', $row) && '' === $row['noassoc']
);
$t->check(
    'a present value is copied unchanged',
    7 === ($row['id'] ?? null) && 'lab-07' === ($row['name'] ?? null)
);
$t->check(
    'a falsy present value is copied unchanged, not turned into null',
    '0' === ($row['pending'] ?? null) && '' === ($row['description'] ?? null)
);
$t->check(
    'a formatter still receives the raw NULL (got '
        . var_export($seen, true) . ')',
    null === $seen && 'f' === ($row['formatted'] ?? null)
);

// ------------------------------------------------------ through complex()

// Every row the fake database hands back carries hostImage = NULL, keyed by
// name and by position as PDO::FETCH_BOTH does.
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;
$db->pdo->rowFactory = function ($cols, $n) {
    $row = [];
    foreach ($cols as $i => $col) {
        $value = 'hostImage' === $col ? null : ('hostID' === $col ? $n : $col);
        $row[$col] = $value;
        $row[$i] = $value;
    }
    return $row;
};
$out = FOGManagerController::complex(
    ['draw' => 1, 'start' => 0, 'length' => 10],
    'hosts',
    'hostID',
    [
        ['db' => 'hostID', 'dt' => 'id'],
        ['db' => 'hostImage', 'dt' => 'imageID'],
        ['db' => 'hostName', 'dt' => 'name'],
    ],
    'SELECT `%s` FROM `%s` %s %s %s',
    'SELECT COUNT(`%s`) FROM `%s` %s',
    'SELECT COUNT(`%s`) FROM `%s`'
);
$first = $out['data'][0] ?? [];
$t->check(
    'complex() returns the row (got ' . count($out['data'] ?? []) . ' rows)',
    1 === count($out['data'] ?? [])
);
$t->check(
    'complex() keeps a NULL column as null (got '
        . var_export($first['imageID'] ?? 'MISSING', true) . ')',
    array_key_exists('imageID', $first) && null === $first['imageID']
);
$t->check(
    'and JSON-encodes it as null, which is what a REST client reads',
    false !== strpos(json_encode($first), '"imageID":null')
);

$t->finish();
