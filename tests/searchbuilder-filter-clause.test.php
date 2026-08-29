<?php
/**
 * Guards how a DataTables SearchBuilder payload becomes SQL.
 *
 * The payload is a tree of rules built in the browser -- a column, a
 * condition, up to two values -- and FOGManagerController::filter() turns it
 * into the WHERE clause every grid, every export and every REST list runs.
 * Three things about that are worth pinning, and none of them shows up as an
 * error when it breaks:
 *
 * 1. The guards. A column the emitter strips ('nosearch') must not be
 *    matchable, or a caller can use match/no-match as an oracle to read a
 *    value the response deliberately refuses to contain. A column that is not
 *    really a column ('removeFromQuery') must not reach SQL either. Both fail
 *    SILENTLY if dropped: the query still runs and still answers 200.
 *
 * 2. The date arithmetic. SearchBuilder sends a whole day, the columns are
 *    DATETIMEs, and a never-set date sorts before every real one. Compared
 *    naively, "= today" matches nothing at all and "before today" lists every
 *    host that has never been deployed. Both look like data, not like bugs.
 *
 * 3. The type comes from the SERVER. The condition set accepted for a column
 *    is chosen from that column's real SQL type, not from the `type` the
 *    request carries, so a hand-made request cannot ask for a range on a
 *    text column.
 *
 * Standalone like its neighbors: filter() and its helpers are static and
 * touch no state, so a stub base carrying a fixed column-type map is enough
 * to exercise them with no boot and no database.
 *
 * Usage: php tests/searchbuilder-filter-clause.test.php [path/to/packages/web]
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGManagerController;

$root = rtrim(
    $argv[1] ?? dirname(__DIR__) . '/packages/web',
    '/'
);

$target = $root . '/src/Base/FOGManagerController.php';
if (!is_file($target)) {
    fwrite(STDERR, "FAIL: no such file: $target\n");
    exit(1);
}

/**
 * Stands in for the pieces filter() never reaches, plus the one it does:
 * columnType(), which on a live server answers from the schema manifest or
 * the database catalog. Here it answers from a fixture, which is what lets
 * the cases below state a column's type outright.
 */
abstract class FOGBase
{
    /**
     * Column type fixture, keyed 'table.column'.
     *
     * @var array
     */
    public static $types = [
        'hosts.hostName' => 'VARCHAR(250) NOT NULL',
        'hosts.hostDeployed' => 'DATETIME',
        'hosts.hostID' => 'INT(11) NOT NULL',
        'hosts.hostSecToken' => 'LONGTEXT NOT NULL',
        'hosts.hostMembers' => 'INT(11)',
    ];

    /**
     * The declared SQL type of a column.
     *
     * @param string $table  the database table
     * @param string $column the database column
     *
     * @return string
     */
    protected static function columnType($table, $column)
    {
        return self::$types[$table . '.' . $column] ?? '';
    }
}
class DatabaseManager
{
}

class_alias('FOGBase', 'FOG\\Base\\FOGBase');
class_alias('DatabaseManager', 'FOG\\Db\\DatabaseManager');

require $target;

/**
 * Exposes filter() and renders its bindings inline so a case can state the
 * whole clause it expects in one readable string.
 */
class SearchBuilderProbe extends FOGManagerController
{
    /**
     * Builds the WHERE clause for a payload, with bindings substituted.
     *
     * @param array  $request DataTables request carrying searchBuilder
     * @param array  $columns Column definitions as listem() builds them
     * @param string $table   The table being queried
     *
     * @return string
     */
    public static function build($request, $columns, $table = 'hosts')
    {
        $bindings = [];
        $where = self::filter($request, $columns, $bindings, $table);
        // Substituted longest-key-first: :binding_1 must not be rewritten by
        // the pattern for :binding_10.
        usort(
            $bindings,
            function ($a, $b) {
                return strlen($b['key']) - strlen($a['key']);
            }
        );
        foreach ($bindings as $binding) {
            $where = str_replace(
                $binding['key'],
                "'" . $binding['val'] . "'",
                $where
            );
        }
        return $where;
    }

    /**
     * Exposes the per-column search type map sent to the browser.
     *
     * @param string $table   The table being queried
     * @param array  $columns Column definitions
     *
     * @return array
     */
    public static function types($table, $columns)
    {
        return self::searchTypes($table, $columns);
    }
}

/**
 * One leaf criterion in SearchBuilder's own wire shape.
 *
 * @param string $column    The column's output ('dt') name
 * @param string $condition The condition key
 * @param array  $values    Zero, one or two values
 *
 * @return array
 */
function sbCriterion($column, $condition, array $values = [])
{
    return [
        'condition' => $condition,
        'data' => $column,
        'origData' => $column,
        // Deliberately a lie in some cases below: the server must decide the
        // type from the column, not from this.
        'type' => 'string',
        'value' => $values,
    ];
}

/**
 * A whole payload around one or more criteria.
 *
 * @param array  $criteria The criteria (leaves or nested groups)
 * @param string $logic    'AND' or 'OR'
 *
 * @return array
 */
function sbRequest(array $criteria, $logic = 'AND')
{
    return [
        'searchBuilder' => [
            'criteria' => $criteria,
            'logic' => $logic,
        ],
    ];
}

// The grid columns as Route::listem() builds them for the host list: a real
// text column, a real datetime, a real int, a secret the emitter strips, and
// a count the query computes rather than selects.
$columns = [
    ['db' => 'hostID', 'dt' => 'id'],
    ['db' => 'hostName', 'dt' => 'name'],
    ['db' => 'hostDeployed', 'dt' => 'deployed'],
    ['db' => 'hostSecToken', 'dt' => 'token', 'nosearch' => true],
    ['db' => 'hostMembers', 'dt' => 'members', 'removeFromQuery' => true],
];

$cases = [];

/* -- the guards ------------------------------------------------------- */

$cases[] = [
    'name' => 'a nosearch column cannot be matched on',
    'request' => sbRequest([sbCriterion('token', 'contains', ['abc'])]),
    'expect' => '',
];

$cases[] = [
    'name' => 'a nosearch column cannot be probed with an emptiness test',
    'request' => sbRequest([sbCriterion('token', '!null')]),
    'expect' => '',
];

$cases[] = [
    'name' => 'a computed column never reaches SQL',
    'request' => sbRequest([sbCriterion('members', '=', ['3'])]),
    'expect' => '',
];

$cases[] = [
    'name' => 'a column the grid does not carry is ignored',
    'request' => sbRequest([sbCriterion('hostPassword', '=', ['x'])]),
    'expect' => '',
];

$cases[] = [
    'name' => 'a condition the column type does not offer is ignored',
    // '<' is a number/date condition. The request says type string and the
    // column IS text; either way there is no ordering to ask for.
    'request' => sbRequest([sbCriterion('name', '<', ['m'])]),
    'expect' => '',
];

$cases[] = [
    'name' => 'an unknown condition is ignored',
    'request' => sbRequest([sbCriterion('name', 'regex', ['^a'])]),
    'expect' => '',
];

/* -- dates ------------------------------------------------------------ */

$cases[] = [
    'name' => 'on a day is the whole of that day, not the bare date',
    'request' => sbRequest([sbCriterion('deployed', '=', ['2026-08-29'])]),
    'expect' => "WHERE ((`hostDeployed` >= '2026-08-29 00:00:00'"
        . " AND `hostDeployed` < '2026-08-30 00:00:00'))",
];

$cases[] = [
    'name' => 'before a day excludes the never-deployed',
    'request' => sbRequest([sbCriterion('deployed', '<', ['2026-08-29'])]),
    'expect' => "WHERE ((`hostDeployed` >= '1000-01-01 00:00:00'"
        . " AND `hostDeployed` < '2026-08-29 00:00:00'))",
];

$cases[] = [
    'name' => 'after a day starts at the next midnight',
    'request' => sbRequest([sbCriterion('deployed', '>', ['2026-08-29'])]),
    'expect' => "WHERE (`hostDeployed` >= '2026-08-30 00:00:00')",
];

$cases[] = [
    'name' => 'between is inclusive of both whole days',
    'request' => sbRequest(
        [sbCriterion('deployed', 'between', ['2026-08-01', '2026-08-31'])]
    ),
    'expect' => "WHERE ((`hostDeployed` >= '2026-08-01 00:00:00'"
        . " AND `hostDeployed` < '2026-09-01 00:00:00'))",
];

$cases[] = [
    'name' => 'between bounds sent the wrong way round still match',
    'request' => sbRequest(
        [sbCriterion('deployed', 'between', ['2026-08-31', '2026-08-01'])]
    ),
    'expect' => "WHERE ((`hostDeployed` >= '2026-08-01 00:00:00'"
        . " AND `hostDeployed` < '2026-09-01 00:00:00'))",
];

$cases[] = [
    'name' => 'empty finds the never-deployed, zero date included',
    'request' => sbRequest([sbCriterion('deployed', 'null')]),
    'expect' => "WHERE ((`hostDeployed` IS NULL"
        . " OR `hostDeployed` < '1000-01-01 00:00:00'))",
];

$cases[] = [
    'name' => 'a date that is not a date is dropped, not passed through',
    'request' => sbRequest([sbCriterion('deployed', '=', ['2026-02-30'])]),
    'expect' => '',
];

/* -- numbers ---------------------------------------------------------- */

$cases[] = [
    'name' => 'a numeric comparison reaches SQL as one',
    'request' => sbRequest([sbCriterion('id', '>=', ['42'])]),
    'expect' => "WHERE (`hostID` >= '42')",
];

$cases[] = [
    'name' => 'a non-numeric value on a numeric column is dropped',
    'request' => sbRequest([sbCriterion('id', '>=', ['DROP'])]),
    'expect' => '',
];

/* -- strings ---------------------------------------------------------- */

$cases[] = [
    'name' => 'contains is anchored nowhere',
    'request' => sbRequest([sbCriterion('name', 'contains', ['lab'])]),
    'expect' => "WHERE (`hostName` LIKE '%lab%' ESCAPE '\\\\')",
];

$cases[] = [
    'name' => 'starts is anchored at the front',
    'request' => sbRequest([sbCriterion('name', 'starts', ['lab'])]),
    'expect' => "WHERE (`hostName` LIKE 'lab%' ESCAPE '\\\\')",
];

$cases[] = [
    'name' => 'a typed % is a percent sign, not a second wildcard',
    'request' => sbRequest([sbCriterion('name', 'contains', ['50%'])]),
    'expect' => "WHERE (`hostName` LIKE '%50\\%%' ESCAPE '\\\\')",
];

$cases[] = [
    'name' => 'a typed _ is an underscore, not any-character',
    'request' => sbRequest([sbCriterion('name', 'starts', ['lab_'])]),
    'expect' => "WHERE (`hostName` LIKE 'lab\\_%' ESCAPE '\\\\')",
];

$cases[] = [
    'name' => 'not-equals keeps the rows whose cell is empty',
    'request' => sbRequest([sbCriterion('name', '!=', ['lab1'])]),
    'expect' => "WHERE ((`hostName` IS NULL OR `hostName` <> 'lab1'))",
];

$cases[] = [
    'name' => 'an unfilled rule is dropped rather than matching everything',
    'request' => sbRequest([sbCriterion('name', 'contains', [''])]),
    'expect' => '',
];

/* -- logic and shape -------------------------------------------------- */

$cases[] = [
    'name' => 'two rules are joined by the group logic',
    'request' => sbRequest(
        [
            sbCriterion('name', 'contains', ['lab']),
            sbCriterion('id', '>', ['10']),
        ],
        'OR'
    ),
    'expect' => "WHERE (`hostName` LIKE '%lab%' ESCAPE '\\\\'"
        . " OR `hostID` > '10')",
];

$cases[] = [
    'name' => 'a nested group is parenthesised, so the client sets precedence',
    'request' => sbRequest(
        [
            sbCriterion('name', 'contains', ['lab']),
            [
                'logic' => 'OR',
                'criteria' => [
                    sbCriterion('id', '=', ['1']),
                    sbCriterion('id', '=', ['2']),
                ],
            ],
        ]
    ),
    'expect' => "WHERE (`hostName` LIKE '%lab%' ESCAPE '\\\\'"
        . " AND (`hostID` = '1' OR `hostID` = '2'))",
];

$cases[] = [
    'name' => 'a group whose every rule was dropped adds no clause',
    'request' => sbRequest([sbCriterion('token', 'contains', ['abc'])], 'OR'),
    'expect' => '',
];

$cases[] = [
    'name' => 'the builder ANDs onto the free-text search rather than replacing it',
    'request' => array_merge(
        sbRequest([sbCriterion('id', '=', ['7'])]),
        [
            'search' => ['value' => 'lab'],
            'columns' => [
                ['data' => 'name', 'searchable' => 'true',
                    'search' => ['value' => '']],
            ],
        ]
    ),
    'expect' => "WHERE (`hostName` LIKE '%lab%') AND (`hostID` = '7')",
];

$cases[] = [
    'name' => 'a request with no builder is untouched',
    'request' => ['columns' => []],
    'expect' => '',
];

/* -- bounds ----------------------------------------------------------- */

// The bound is on the depth a group sits at, so the outermost group is
// depth 0 and MAX_DEPTH + 1 levels of group are walked. Both sides of that
// edge are pinned: one level in from it still builds a clause, one level past
// it builds nothing at all rather than recursing on whatever it was sent.
$atBound = sbCriterion('id', '=', ['1']);
for ($i = 0; $i <= FOGManagerController::SEARCHBUILDER_MAX_DEPTH; $i++) {
    $atBound = ['logic' => 'AND', 'criteria' => [$atBound]];
}
$cases[] = [
    'name' => 'nesting up to the depth bound is walked',
    'request' => ['searchBuilder' => $atBound],
    'expect' => "WHERE " . str_repeat('(', 6) . "`hostID` = '1'"
        . str_repeat(')', 6),
];

$tooDeep = ['logic' => 'AND', 'criteria' => [$atBound]];
$cases[] = [
    'name' => 'nesting past the depth bound is dropped, not recursed',
    'request' => ['searchBuilder' => $tooDeep],
    'expect' => '',
];

$many = [];
for ($i = 0; $i < FOGManagerController::SEARCHBUILDER_MAX_CRITERIA + 20; $i++) {
    $many[] = sbCriterion('id', '=', [(string)$i]);
}
$manyClause = SearchBuilderProbe::build(sbRequest($many), $columns);
$cases[] = [
    'name' => 'criteria past the budget are dropped',
    'request' => sbRequest($many),
    'expect' => $manyClause,
    'assert' => function () use ($manyClause) {
        $count = substr_count($manyClause, '`hostID` =');
        return $count === FOGManagerController::SEARCHBUILDER_MAX_CRITERIA
            ? ''
            : "expected " . FOGManagerController::SEARCHBUILDER_MAX_CRITERIA
                . " terms, got $count";
    },
];

$failures = [];
foreach ($cases as $case) {
    if (isset($case['assert'])) {
        $problem = $case['assert']();
        if ('' !== $problem) {
            $failures[] = $case['name'] . "\n    " . $problem;
        }
        continue;
    }
    $got = SearchBuilderProbe::build($case['request'], $columns);
    if ($got !== $case['expect']) {
        $failures[] = sprintf(
            "%s\n    expected: %s\n    got:      %s",
            $case['name'],
            '' === $case['expect'] ? '(no clause)' : $case['expect'],
            '' === $got ? '(no clause)' : $got
        );
    }
}

// The types the browser is sent decide which columns it offers to filter on.
// A column the server refuses to match must be reported false, or the UI
// offers a rule that is silently dropped here -- which reads to a user as the
// filter not working rather than as the column not being filterable.
$expectTypes = [
    'id' => 'num',
    'name' => 'string',
    'deployed' => 'date',
    'token' => false,
    'members' => false,
];
$gotTypes = SearchBuilderProbe::types('hosts', $columns);
if ($gotTypes !== $expectTypes) {
    $failures[] = "per-column search types sent to the browser\n"
        . '    expected: ' . json_encode($expectTypes) . "\n"
        . '    got:      ' . json_encode($gotTypes);
}

if ($failures) {
    fwrite(
        STDERR,
        "FAIL: SearchBuilder WHERE clause\n\n"
        . implode("\n\n", $failures)
        . "\n"
    );
    exit(1);
}

echo "PASS: SearchBuilder WHERE clause ("
    . (count($cases) + 1)
    . " checks)\n";
exit(0);
