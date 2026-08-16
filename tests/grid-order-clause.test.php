<?php
/**
 * Guards the "requested sort silently dropped" bug class in grid ordering.
 *
 * Every association tab asks DataTables for "associated rows first, then
 * name". That is a server-side sort, so it only happens if
 * FOGManagerController::order() can resolve the association column to
 * something the database can ORDER BY. It could not: the association column
 * is declared with 'do' (a SELECT alias, `IF(...) AS <owner>Assoc`) and no
 * 'db', and the lookup compared that alias against the grid's OUTPUT name
 * ('association'), which never matches. order() then skipped the term with no
 * error, so every association tab came back ordered by name alone and the
 * checked rows were scattered through the list.
 *
 * The failure is invisible from either end -- the client keeps requesting the
 * sort, the server keeps answering 200 with well-formed rows -- so it needs a
 * test rather than an eyeball. Refs GH-956, which introduced the silent form
 * (before it, the same column produced `ORDER BY `` ASC`, a hard SQL error).
 *
 * Standalone by design, like the other tests here: FOGManagerController
 * extends FOGBase, but order() and its helpers are static and touch no state,
 * so a stub base class is enough to exercise them without a boot or a
 * database.
 *
 * Usage: php tests/grid-order-clause.test.php [path/to/packages/web]
 * Exit status 0 = pass, 1 = fail.
 */

$root = rtrim(
    $argv[1] ?? dirname(__DIR__) . '/packages/web',
    '/'
);

$target = $root . '/lib/fog/fogmanagercontroller.class.php';
if (!is_file($target)) {
    fwrite(STDERR, "FAIL: no such file: $target\n");
    exit(1);
}

// Stubs standing in for the pieces order() never reaches.
abstract class FOGBase
{
}
class DatabaseManager
{
}

/*
 * The target is required directly, so no autoloader is running to bridge
 * names for us. Since Phase 3 the file declares `namespace FOG;` and its
 * `extends FOGBase` therefore resolves to FOG\FOGBase -- which the stubs
 * above do not provide. Aliasing them into FOG\ is the same move the real
 * class files make in the opposite direction, and it keeps the stubs
 * readable as the global names the rest of this file uses.
 */
class_alias('FOGBase', 'FOG\\FOGBase');
class_alias('DatabaseManager', 'FOG\\DatabaseManager');

require $target;

/**
 * Exposes the protected/static order builder.
 */
class GridOrderProbe extends FOGManagerController
{
    /**
     * Builds the ORDER BY clause for a request/column pair.
     *
     * @param array  $request Simulated DataTables request
     * @param array  $columns Column definitions as getItemsList builds them
     * @param string $orderby Default order column name
     *
     * @return string
     */
    public static function build($request, $columns, $orderby = 'name')
    {
        return self::order($request, $columns, $orderby);
    }
}

/**
 * Builds a DataTables server-side request naming ordered column indexes.
 *
 * @param array $orders Pairs of [column index, direction]
 * @param array $datas  The 'data' name of each grid column, in order
 *
 * @return array
 */
function gridRequest(array $orders, array $datas)
{
    $request = ['order' => [], 'columns' => []];
    foreach ($orders as $order) {
        $request['order'][] = ['column' => $order[0], 'dir' => $order[1]];
    }
    foreach ($datas as $data) {
        $request['columns'][] = ['data' => $data, 'orderable' => 'true'];
    }
    return $request;
}

$nameFormatter = function ($d, $row) {
    return $d;
};

$cases = [];

// The plain two-column association tab (usergroup members, host groups, ...).
$cases[] = [
    'name' => 'plain association tab sorts associated-first, then name',
    'columns' => [
        ['db' => 'uId', 'dt' => 'id'],
        ['db' => 'uName', 'dt' => 'name'],
        ['db' => 'uName', 'dt' => 'mainLink', 'formatter' => $nameFormatter],
        ['do' => 'usergroupAssoc', 'dt' => 'association'],
    ],
    'request' => gridRequest(
        [[1, 'asc'], [0, 'asc']],
        ['mainLink', 'association']
    ),
    'expect' => 'ORDER BY `usergroupAssoc` ASC, `uName` ASC',
];

// The LDAP plugin tabs put a directory-server column before the association
// one, so its index is 2 -- the clause must follow the column, not an index.
$cases[] = [
    'name' => 'three-column association tab sorts on the association column',
    'columns' => [
        ['db' => 'lgID', 'dt' => 'id'],
        ['db' => 'lgName', 'dt' => 'name'],
        ['db' => 'lgName', 'dt' => 'mainLink', 'formatter' => $nameFormatter],
        ['db' => 'lgLDAPID', 'dt' => 'ldapserver'],
        ['do' => 'usergroupAssoc', 'dt' => 'association'],
    ],
    'request' => gridRequest(
        [[2, 'asc'], [0, 'asc']],
        ['mainLink', 'ldapserver', 'association']
    ),
    'expect' => 'ORDER BY `usergroupAssoc` ASC, `lgName` ASC',
];

// Group's tri-state tab labels are all/some/none, which sort alphabetically as
// all/none/some, so its column carries an ORDER BY expression instead. That
// must reach the clause unquoted -- backticking it names a column that is not
// there.
$cases[] = [
    'name' => 'tri-state association column orders by its ranking expression',
    'columns' => [
        ['db' => 'sID', 'dt' => 'id'],
        ['db' => 'sName', 'dt' => 'name'],
        ['db' => 'sName', 'dt' => 'mainLink', 'formatter' => $nameFormatter],
        [
            'do' => 'groupAssoc',
            'dt' => 'association',
            'order' => "FIELD(`groupAssoc`,'all','some','none')",
        ],
        ['do' => 'groupAssocCount', 'dt' => 'assocCount'],
        ['do' => 'groupAssocTotal', 'dt' => 'assocTotal'],
    ],
    'request' => gridRequest(
        [[1, 'asc'], [0, 'asc']],
        ['mainLink', 'association']
    ),
    'expect' => "ORDER BY FIELD(`groupAssoc`,'all','some','none') ASC, "
        . '`sName` ASC',
];

// The API path sends no order at all. GH-956 made that fall back to the
// caller's default column; it must keep doing so, or LIMIT pages over an
// unordered result and repeats or skips rows.
$cases[] = [
    'name' => 'a request with no order falls back to the default column',
    'columns' => [
        ['db' => 'uId', 'dt' => 'id'],
        ['db' => 'uName', 'dt' => 'name'],
        ['do' => 'usergroupAssoc', 'dt' => 'association'],
    ],
    'request' => ['columns' => []],
    'expect' => 'ORDER BY `uName` ASC',
];

$failures = [];
foreach ($cases as $case) {
    $got = GridOrderProbe::build($case['request'], $case['columns']);
    if ($got !== $case['expect']) {
        $failures[] = sprintf(
            "%s\n    expected: %s\n    got:      %s",
            $case['name'],
            $case['expect'],
            '' === $got ? '(empty clause)' : $got
        );
    }
}

if ($failures) {
    fwrite(
        STDERR,
        "FAIL: grid ORDER BY clause\n\n"
        . implode("\n\n", $failures)
        . "\n"
    );
    exit(1);
}

printf("PASS: %d grid ORDER BY cases\n", count($cases));
exit(0);
