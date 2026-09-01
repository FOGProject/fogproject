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
 * 4. Every binding the clause creates is referenced BY that clause. sqlexec()
 *    binds the whole array to each of complex()'s queries, so a binding the
 *    SQL never names makes PDO refuse the statement outright -- "Invalid
 *    parameter number: parameter was not defined" -- and the list answers
 *    406. This one is checked on every clause case below rather than as a
 *    case of its own, because it is a property of all of them; the first cut
 *    of this file rendered the bindings into the SQL text and so was blind
 *    to it, which shipped a 406 on 'before' and 'after'.
 *
 * 5. A relationship column negates from OUTSIDE its membership test. A
 *    column declaring 'sqlfilter' has no scalar behind it -- the host list's
 *    Groups column is a many-to-many through `groupMembers` -- so "is not
 *    lab01" cannot be one comparison. Pushed inside, it asks "is in SOME
 *    group that is not lab01", which is true of nearly every host carrying
 *    two labels; what the user picked means "is in NO group called lab01".
 *    Both forms are valid SQL, both answer 200, and the wrong one returns
 *    plausible rows -- which is why it is pinned here rather than left to a
 *    lab run.
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
        // The column a relationship filter compares. On a real server this
        // is read from the schema manifest exactly like the four above --
        // the point being that a relationship column is still typed from a
        // real column of a real table, never from what the request claims.
        'groups.groupName' => 'VARCHAR(64) NOT NULL',
        'groups.groupOrder' => 'INT(11) NOT NULL',
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
    /**
     * The zone the viewer reads times in, and the zone the column is stored
     * in. Equal by default, which is every account that has not chosen a
     * display timezone -- and in that state the conversion below must be a
     * no-op, so every existing case still states the clause it always did.
     *
     * @var string
     */
    public static $displayZone = 'UTC';
    /**
     * @var string
     */
    public static $storageZone = 'UTC';

    /**
     * Moves a bound from the viewer's zone to the storage zone.
     *
     * @param string $value 'Y-m-d H:i:s' as the viewer means it
     *
     * @return string
     */
    public static function displayToStorage($value)
    {
        if (self::$displayZone === self::$storageZone) {
            return (string)$value;
        }
        try {
            $date = new \DateTime(
                (string)$value,
                new \DateTimeZone(self::$displayZone)
            );
        } catch (\Exception $e) {
            return (string)$value;
        }

        return $date
            ->setTimezone(new \DateTimeZone(self::$storageZone))
            ->format('Y-m-d H:i:s');
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
     * The bindings a payload creates that its own clause never names.
     *
     * Anything listed here is a statement PDO will refuse to prepare.
     *
     * @param array  $request DataTables request carrying searchBuilder
     * @param array  $columns Column definitions as listem() builds them
     * @param string $table   The table being queried
     *
     * @return array
     */
    public static function unusedBindings($request, $columns, $table = 'hosts')
    {
        $bindings = [];
        $where = self::filter($request, $columns, $bindings, $table);
        $unused = [];
        foreach ($bindings as $binding) {
            if (false === strpos($where, $binding['key'])) {
                $unused[] = $binding['key'] . " => '" . $binding['val'] . "'";
            }
        }
        return $unused;
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
    // A relationship column, exactly as Route::_gridColumns() declares the
    // host list's Groups column: no 'db', so every raw-identifier path skips
    // it, and an 'sqlfilter' the criterion paths know how to read.
    [
        'dt' => 'groups',
        'sqlfilter' => [
            'table' => 'groups',
            'column' => 'groupName',
            'match' => 'EXISTS (SELECT 1 FROM `groupMembers`'
                . ' INNER JOIN `groups`'
                . ' ON `groups`.`groupID` = `groupMembers`.`gmGroupID`'
                . ' WHERE `groupMembers`.`gmHostID` = `hosts`.`hostID`'
                . ' AND (%s))'
        ]
    ],
];

/**
 * The membership test above, around one comparison.
 *
 * Written out once so a case below states the COMPARISON it expects rather
 * than a wall of join text -- the comparison and its sense are what these
 * cases are about.
 *
 * @param string $inner The comparison the criterion built
 *
 * @return string
 */
function groupsMatch($inner)
{
    return 'EXISTS (SELECT 1 FROM `groupMembers`'
        . ' INNER JOIN `groups`'
        . ' ON `groups`.`groupID` = `groupMembers`.`gmGroupID`'
        . ' WHERE `groupMembers`.`gmHostID` = `hosts`.`hostID`'
        . ' AND (' . $inner . '))';
}

/**
 * A DataTables request carrying per-column header-box searches.
 *
 * @param array $searches Column 'dt' name => the box's wire value
 * @param array $columns  The grid's column definitions
 *
 * @return array
 */
function colRequest(array $searches, array $columns)
{
    $request = ['columns' => []];
    foreach ($columns as $column) {
        $request['columns'][] = [
            'data' => $column['dt'],
            'searchable' => 'true',
            'search' => ['value' => $searches[$column['dt']] ?? ''],
        ];
    }
    return $request;
}

/**
 * The wire value a header box sends: condition, separator, then values.
 *
 * @param string $condition The condition key
 * @param array  $values    Zero, one or two values
 *
 * @return string
 */
function colValue($condition, array $values = [])
{
    return implode(
        FOGManagerController::COLUMN_SEARCH_SEPARATOR,
        array_merge([$condition], $values)
    );
}

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

/* -- the per-column header boxes -------------------------------------- */

// The header row sends its condition inline, through the SAME builder the
// Filter panel's payload uses -- so these cases are really asserting that the
// second transport is not a second filter language with its own escaping,
// date handling and guards to get wrong.

$cases[] = [
    'name' => 'a header box with no condition still means contains',
    'request' => colRequest(['name' => 'lab'], $columns),
    'expect' => "WHERE `hostName` LIKE '%lab%'",
];

$cases[] = [
    'name' => 'a header box can ask for starts-with',
    'request' => colRequest(['name' => colValue('starts', ['lab'])], $columns),
    'expect' => "WHERE `hostName` LIKE 'lab%' ESCAPE '\\\\'",
];

$cases[] = [
    'name' => 'a header box can ask for exactly',
    'request' => colRequest(['name' => colValue('=', ['lab01'])], $columns),
    'expect' => "WHERE `hostName` = 'lab01'",
];

$cases[] = [
    'name' => 'a header box on a date column gets the whole-day arithmetic',
    'request' => colRequest(
        ['deployed' => colValue('<', ['2026-08-29'])],
        $columns
    ),
    'expect' => "WHERE (`hostDeployed` >= '1000-01-01 00:00:00'"
        . " AND `hostDeployed` < '2026-08-29 00:00:00')",
];

$cases[] = [
    'name' => 'a header box cannot ask for a condition the type does not offer',
    'request' => colRequest(['name' => colValue('<', ['m'])], $columns),
    'expect' => '',
];

$cases[] = [
    'name' => 'a header box cannot match a nosearch column',
    'request' => colRequest(
        ['token' => colValue('contains', ['abc'])],
        $columns
    ),
    'expect' => '',
];

$cases[] = [
    'name' => 'a header box cannot match a computed column',
    'request' => colRequest(['members' => colValue('=', ['3'])], $columns),
    'expect' => '',
];

$cases[] = [
    'name' => 'a header box escapes a LIKE wildcard in the value',
    'request' => colRequest(
        ['name' => colValue('contains', ['100%'])],
        $columns
    ),
    'expect' => "WHERE `hostName` LIKE '%100\\%%' ESCAPE '\\\\'",
];

$cases[] = [
    'name' => 'header boxes on two columns are ANDed',
    'request' => colRequest(
        [
            'name' => colValue('starts', ['lab']),
            'id' => colValue('>', ['10']),
        ],
        $columns
    ),
    // Emitted in grid-column order (id before name), not request order.
    'expect' => "WHERE `hostID` > '10'"
        . " AND `hostName` LIKE 'lab%' ESCAPE '\\\\'",
];

/* -- relationship columns --------------------------------------------- */

$cases[] = [
    'name' => 'a relationship column filters through its membership test',
    'request' => sbRequest([sbCriterion('groups', 'contains', ['lab'])]),
    'expect' => 'WHERE ('
        . groupsMatch("`groups`.`groupName` LIKE '%lab%' ESCAPE '\\\\'")
        . ')',
];

$cases[] = [
    'name' => 'equals names the group exactly',
    'request' => sbRequest([sbCriterion('groups', '=', ['lab01'])]),
    'expect' => 'WHERE ('
        . groupsMatch("`groups`.`groupName` = 'lab01'")
        . ')',
];

$cases[] = [
    // Invariant 5. The comparison inside stays POSITIVE and the whole
    // membership test is negated, so this reads "in no group called lab01".
    // The wrong form -- NOT pushed inside -- would read "in some group that
    // is not lab01" and match nearly every host with two groups.
    'name' => 'not-equals negates the membership, not the comparison',
    'request' => sbRequest([sbCriterion('groups', '!=', ['lab01'])]),
    'expect' => 'WHERE (NOT ('
        . groupsMatch("`groups`.`groupName` = 'lab01'")
        . '))',
];

$cases[] = [
    'name' => 'not-contains negates the membership too',
    'request' => sbRequest([sbCriterion('groups', '!contains', ['lab'])]),
    'expect' => 'WHERE (NOT ('
        . groupsMatch("`groups`.`groupName` LIKE '%lab%' ESCAPE '\\\\'")
        . '))',
];

$cases[] = [
    // 'null' on a relationship means no related row AT ALL, so the
    // membership test is the whole predicate and the sense is the opposite
    // way round to every other pair: 'null' negates, '!null' does not.
    'name' => 'empty finds the hosts in no group at all',
    'request' => sbRequest([sbCriterion('groups', 'null')]),
    'expect' => 'WHERE (NOT (' . groupsMatch('1') . '))',
];

$cases[] = [
    'name' => 'not-empty finds the hosts in at least one group',
    'request' => sbRequest([sbCriterion('groups', '!null')]),
    'expect' => 'WHERE (' . groupsMatch('1') . ')',
];

$cases[] = [
    // The type comes from `groups`.`groupName`, which is text -- so a range
    // condition is not on offer for this column any more than it is for
    // hostName, even though the request says nothing about which table the
    // comparison lands on.
    'name' => 'a relationship column is typed from the column it compares',
    'request' => sbRequest([sbCriterion('groups', '>', ['m'])]),
    'expect' => '',
];

$cases[] = [
    'name' => 'an unfilled relationship criterion is dropped',
    'request' => sbRequest([sbCriterion('groups', 'contains', [''])]),
    'expect' => '',
];

$cases[] = [
    'name' => 'the header box reaches a relationship column',
    'request' => colRequest(
        ['groups' => colValue('starts', ['lab'])],
        $columns
    ),
    'expect' => 'WHERE '
        . groupsMatch("`groups`.`groupName` LIKE 'lab%' ESCAPE '\\\\'"),
];

$cases[] = [
    // The free-text box has to find a group by name too. It and the header
    // box were one copied line apart, and a global box that silently cannot
    // match this column reads as the search being broken rather than as the
    // column being special.
    'name' => 'the free-text box reaches a relationship column',
    'request' => [
        'search' => ['value' => 'lab'],
        'columns' => [
            [
                'data' => 'groups',
                'searchable' => 'true',
                'search' => ['value' => ''],
            ],
        ],
    ],
    'expect' => "WHERE (" . groupsMatch(
        "`groups`.`groupName` LIKE '%lab%'"
    ) . ")",
];

$cases[] = [
    // The template is SQL and SQL contains '%' -- a LIKE pattern written
    // into a plugin's own scope fragment is the case that turns up. sprintf
    // would read '%L' as a conversion and either mangle the clause or throw
    // a ValueError on PHP 8, so the placeholder is substituted with
    // str_replace. Nothing about the wrong version looks wrong until the
    // list answers 500.
    'name' => "a literal '%' in the membership test survives substitution",
    'assert' => function () use ($columns) {
        $percent = $columns;
        $percent[5]['sqlfilter']['match'] =
            "EXISTS (SELECT 1 FROM `t` WHERE `x` LIKE 'a%L' AND (%s))";
        $want = "WHERE (EXISTS (SELECT 1 FROM `t` WHERE `x` LIKE 'a%L'"
            . " AND (`groups`.`groupName` = 'lab01')))";
        $got = SearchBuilderProbe::build(
            sbRequest([sbCriterion('groups', '=', ['lab01'])]),
            $percent
        );

        return $got === $want
            ? ''
            : "expected: $want\n    got:      $got";
    },
];

$cases[] = [
    'name' => 'a malformed sqlfilter matches nothing rather than half of it',
    'assert' => function () use ($columns) {
        $broken = $columns;
        // Every way the contract can be wrong, one at a time. Each must
        // produce NO clause -- a template with no placeholder would filter
        // on nothing while looking like it filtered, which is the shape that
        // returns plausible wrong rows.
        $mutations = [
            'no table' => ['column' => 'groupName', 'match' => 'X (%s)'],
            'no column' => ['table' => 'groups', 'match' => 'X (%s)'],
            'no match' => ['table' => 'groups', 'column' => 'groupName'],
            'no placeholder' => [
                'table' => 'groups',
                'column' => 'groupName',
                'match' => 'X (1)',
            ],
            'two placeholders' => [
                'table' => 'groups',
                'column' => 'groupName',
                'match' => 'X (%s) AND Y (%s)',
            ],
            'quoted identifier' => [
                'table' => '`groups`',
                'column' => 'groupName',
                'match' => 'X (%s)',
            ],
            'injected identifier' => [
                'table' => 'groups',
                'column' => 'groupName` = 1 OR `1',
                'match' => 'X (%s)',
            ],
        ];
        $problems = [];
        foreach ($mutations as $why => $filter) {
            $broken[5]['sqlfilter'] = $filter;
            $got = SearchBuilderProbe::build(
                sbRequest([sbCriterion('groups', 'contains', ['lab'])]),
                $broken
            );
            if ('' !== $got) {
                $problems[] = $why . ' produced: ' . $got;
            }
            $types = SearchBuilderProbe::types('hosts', $broken);
            if (false !== ($types['groups'] ?? null)) {
                $problems[] = $why . ' was offered to the browser as '
                    . json_encode($types['groups'] ?? null);
            }
        }

        return implode("\n    ", $problems);
    },
];

/* -- the viewer's timezone -------------------------------------------- */

/*
 * A grid that SHOWS times in the viewer's zone has to FILTER in it too. The
 * user picks a day off a calendar that matches the column they are reading,
 * so "on the 29th" must mean their 29th; comparing it against the storage
 * zone's 29th silently returns a different set of rows, and near midnight a
 * different day entirely.
 *
 * Storage here is UTC and the viewer is five hours behind it, so their day
 * starts at 05:00 UTC and ends at 05:00 UTC the next morning.
 */
$cases[] = [
    'name' => "a day means the VIEWER's day, not the server's",
    'zones' => ['America/Chicago', 'UTC'],
    'request' => sbRequest([sbCriterion('deployed', '=', ['2026-08-29'])]),
    'expect' => "WHERE ((`hostDeployed` >= '2026-08-29 05:00:00'"
        . " AND `hostDeployed` < '2026-08-30 05:00:00'))",
];

$cases[] = [
    'name' => 'before, in the viewer\'s zone',
    'zones' => ['America/Chicago', 'UTC'],
    'request' => sbRequest([sbCriterion('deployed', '<', ['2026-08-29'])]),
    'expect' => "WHERE ((`hostDeployed` >= '1000-01-01 00:00:00'"
        . " AND `hostDeployed` < '2026-08-29 05:00:00'))",
];

$cases[] = [
    'name' => 'after, in the viewer\'s zone',
    'zones' => ['America/Chicago', 'UTC'],
    'request' => sbRequest([sbCriterion('deployed', '>', ['2026-08-29'])]),
    'expect' => "WHERE (`hostDeployed` >= '2026-08-30 05:00:00')",
];

$cases[] = [
    'name' => 'no preference set leaves the bounds exactly as typed',
    'zones' => ['UTC', 'UTC'],
    'request' => sbRequest([sbCriterion('deployed', '=', ['2026-08-29'])]),
    'expect' => "WHERE ((`hostDeployed` >= '2026-08-29 00:00:00'"
        . " AND `hostDeployed` < '2026-08-30 00:00:00'))",
];

$failures = [];
$checks = 0;
foreach ($cases as $case) {
    ++$checks;
    // Zones default to equal -- the state every account is in until someone
    // chooses a display timezone -- so an unmarked case states the clause it
    // always did.
    FOGBase::$displayZone = $case['zones'][0] ?? 'UTC';
    FOGBase::$storageZone = $case['zones'][1] ?? 'UTC';
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
    // Invariant 4 in the docblock: it holds for every payload, so it is
    // asserted on every case rather than being one case of its own.
    ++$checks;
    $unused = SearchBuilderProbe::unusedBindings($case['request'], $columns);
    if ($unused) {
        $failures[] = $case['name']
            . "\n    bindings the clause never names (PDO refuses these):\n    "
            . implode("\n    ", $unused);
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
    // Offered as a text filter even though there is no `hosts` column behind
    // it: reported false, the UI would leave the column out of the picker
    // entirely and the feature would be invisible.
    'groups' => 'string',
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
    . ($checks + 1)
    . " checks)\n";
exit(0);
