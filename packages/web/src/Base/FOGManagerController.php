<?php
/**
 * FOG Manager Controller, main object mass getter.
 *
 * PHP version 7.4+
 *
 * @category FOGManagerController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

use FOG\Db\DatabaseManager;
use FOG\Items\Schema;
use FOG\Router\Route;

/**
 * FOG Manager Controller, main object mass getter.
 *
 * @category FOGManagerController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGManagerController extends FOGBase
{
    /**
     * Hard cap on the rows one grid query will fetch when the request asks for
     * all of them. Bounds the memory a single server-side-processing response
     * can cost; see limit() for why "all rows" is no longer taken literally.
     *
     * It is a ceiling, not a guarantee: a wide grid whose formatters
     * materialize a related object per row costs far more per row than a log
     * table does, so this stops the failure that is reachable from the UI
     * rather than proving every grid safe at the cap.
     */
    const MAX_ROWS = 10000;
    /**
     * Deepest SearchBuilder group nesting this server will walk.
     *
     * The payload is recursive -- a criterion may itself be a group -- and it
     * arrives from a browser, so the recursion needs a bound that does not
     * depend on the client being the one we shipped. Five is far past
     * anything the UI builds by hand.
     */
    const SEARCHBUILDER_MAX_DEPTH = 5;
    /**
     * Most SearchBuilder criteria this server will turn into SQL.
     *
     * Every criterion is one more predicate in the WHERE clause, so an
     * unbounded payload is an unbounded query. Criteria past the budget are
     * dropped, which can only narrow the result, never widen it.
     */
    const SEARCHBUILDER_MAX_CRITERIA = 50;
    /**
     * The SearchBuilder conditions implemented here, per resolved column type.
     *
     * These are SearchBuilder's own condition keys, but the CLIENT does not
     * decide which set applies: the type is re-derived from the column's real
     * SQL type in searchBuilderType(), so a hand-made request cannot ask for
     * day-boundary arithmetic on a text column or a range on a blob. Anything
     * not listed here is dropped rather than guessed at.
     *
     * @var array
     */
    private static $_sbConditions = [
        'date' => [
            '=', '!=', '<', '>', 'between', '!between', 'null', '!null'
        ],
        'num' => [
            '=', '!=', '<', '<=', '>=', '>', 'between', '!between',
            'null', '!null'
        ],
        'string' => [
            '=', '!=', 'starts', '!starts', 'contains', '!contains',
            'ends', '!ends', 'null', '!null'
        ]
    ];
    /**
     * The oldest datetime treated as a real one.
     *
     * A never-set date stores as NULL on a column added since schema step 353
     * and as the zero date on an older one, and the zero date sorts BEFORE
     * every real date -- so "deployed before today" would otherwise list every
     * host that has never been deployed at all. A plain comparison against
     * this floor excludes it while staying sargable; YEAR(col) = 0 would say
     * the same thing and cost the index on a fleet-sized table.
     */
    const SEARCHBUILDER_DATE_FLOOR = '1000-01-01 00:00:00';
    /**
     * Separates the condition from its values in a per-column search box.
     *
     * ASCII unit separator: it cannot be typed into the box, so a value a
     * user actually entered can never be mistaken for this wire form and
     * fall through to the criterion path by accident.
     *
     * @var string
     */
    const COLUMN_SEARCH_SEPARATOR = "\x1f";
    /**
     * Whether the last limit() call had to impose MAX_ROWS because the request
     * did not bound itself. Read by complex() so the payload can say it is a
     * page rather than the whole answer.
     *
     * A caller that sends its own start/length already knows it is paging and
     * is not flagged; this is only ever set when the server chose the bound.
     *
     * @var bool
     */
    private static $_capped = false;
    /**
     * The main class for the object.
     *
     * @var string
     */
    protected $childClass;
    /**
     * The table name for the object.
     *
     * @var string
     */
    protected $databaseTable;
    /**
     * The common names and fields.
     *
     * @var array
     */
    protected $databaseFields = [];
    /**
     * The Flipped fields.
     *
     * @var array
     */
    protected $databaseFieldsFlipped = [];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [];
    /**
     * Required keys whose name ends in ID but which are NOT foreign keys.
     *
     * Mirrored from the model for the same reason isValid() carries its own
     * copy of the rule save() uses: both write paths infer "ends in id, so it
     * is a foreign key", so both need the model's opt-out or one of them
     * refuses a string identifier the other accepts.
     *
     * @var array
     */
    protected $databaseFieldsNotInt = [];
    /**
     * The Class relationships.
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [];
    /**
     * The additional fields.
     *
     * @var array
     */
    protected $additionalFields = [];
    /**
     * The sql query string
     *
     * @var string
     */
    protected $sqlQueryStr = '';
    /**
     * The sql filter string
     *
     * @var string
     */
    protected $sqlFilterStr = '';
    /**
     * The load template.
     *
     * SELECT <field(s)> FROM `<table>` <join> <where>
     *
     * @var string
     */
    protected $loadQueryTemplate = 'SELECT %s FROM `%s` %s %s %s %s %s';
    /**
     * The count template.
     *
     * @var string
     */
    protected $countQueryTemplate = 'SELECT COUNT(`%s`.`%s`)
        AS `total`
        FROM `%s`%s
        LIMIT 1';
    /**
     * The update template.
     *
     * @var string
     */
    protected $updateQueryTemplate = 'UPDATE `%s` SET %s %s';
    /**
     * The exists template.
     *
     * @var string
     */
    protected $existsQueryTemplate = 'SELECT COUNT(`%s`.`%s`)
        AS `total`
        FROM `%s`
        WHERE `%s`.`%s`=%s
        AND `%s`.`%s` <> %s';
    /**
     * The insert batch template.
     *
     * @var string
     */
    protected $insertBatchTemplate = 'INSERT INTO `%s` (`%s`)
        VALUES %s
        ON DUPLICATE KEY UPDATE %s';
    /**
     * The distinct template.
     *
     * @var string
     */
    protected $distinctTemplate = 'SELECT COUNT(DISTINCT `%s`.`%s`)
        AS `total`
        FROM `%s`%s
        LIMIT 1';
    /**
     * Initializes the manager class.
     */
    public function __construct()
    {
        parent::__construct();
        // Short name: $childClass is not only instantiated, it is lowercased
        // into an HTML name=/id= attribute and passed to Route::listem(),
        // which validates it against Route::$validClasses -- a list of bare
        // lowercase names that 'fog\host' is not a member of.
        $this->childClass = preg_replace(
            '#_?Manager$#',
            '',
            self::shortName($this)
        );
        $classVars = self::getClass(
            $this->childClass,
            '',
            true
        );
        $classGet = [
            'databaseTable',
            'databaseFields',
            'additionalFields',
            'databaseFieldsRequired',
            'databaseFieldClassRelationships',
            'sqlQueryStr',
            'sqlFilterStr',
            'sqlTotalStr',
            'databaseFieldsNotInt',
        ];
        $this->databaseTable = &$classVars[$classGet[0]];
        $this->databaseFields = &$classVars[$classGet[1]];
        $this->additionalFields = &$classVars[$classGet[2]];
        $this->databaseFieldsRequired = &$classVars[$classGet[3]];
        $this->databaseFieldClassRelationships = &$classVars[$classGet[4]];
        $this->databaseFieldsFlipped = array_flip($this->databaseFields);
        $this->sqlQueryStr = &$classVars[$classGet[5]];
        $this->sqlFilterStr = &$classVars[$classGet[6]];
        $this->sqlTotalStr = &$classVars[$classGet[7]];
        $this->databaseFieldsNotInt = &$classVars[$classGet[8]];
        unset($classGet);
    }
    /**
     * Create the data output array for the DataTables rows
     *
     * @param array $columns Column information array
     * @param array $data    Data from the SQL get
     *
     * @return array Formatted data in a row based format
     */
    /**
     * Re-labels a grid's date cells in the zone the viewer reads in.
     *
     * Grid cells are raw stored values -- dataOutput() copies them straight
     * out of the row unless the column declares a formatter -- so without this
     * a user's chosen timezone would apply on every form and detail page and
     * nowhere on the lists, which is where the timestamps people actually read
     * live. The column types are the ones already computed for the client's
     * search UI, so nothing extra is derived to do it.
     *
     * NOT applied to REST responses. A script reading hostLastDeploy wants one
     * stable answer, not one that depends on whose token it used; per-caller
     * values would break consumers silently, and the values carry no zone
     * marker to disambiguate them. The UI is the human surface and this is a
     * human preference.
     *
     * A no-op whenever the display and storage zones agree, which is every
     * account that has not chosen a preference.
     *
     * @param array  $rows    Rows as dataOutput() built them.
     * @param string $table   The table being queried.
     * @param array  $columns Column information array.
     *
     * @return array
     */
    /**
     * Appended to a date column's output key to flag that row's value as
     * predating the UTC boundary.
     *
     * Two underscores and a word no column is called, because this shares a
     * namespace with every column's own output key -- fog.common.js reads it
     * back by suffix, so a collision would put a marker on a real column.
     *
     * @var string
     */
    const UNADJUSTED_SUFFIX = '__unadjusted';
    public static function displayDates($rows, $table, $columns)
    {
        if (class_exists('\\FOG\\Router\\Route')
            && \FOG\Router\Route::$apiRequest
        ) {
            return $rows;
        }
        // Which output keys are dates, and -- separately -- which of those
        // came from a DATETIME column rather than a TIMESTAMP one. The
        // second question is the one that decides whether a value can be
        // pre-boundary at all: a TIMESTAMP has always held a UTC instant and
        // hands one back however old the row is. Taken from columnType(),
        // which answers from the manifest for core and from the server's own
        // catalog for a plugin's table, so a plugin grid is typed correctly
        // without the plugin doing anything.
        $dateKeys = [];
        foreach ((array)$columns as $column) {
            if (!isset($column['dt'], $column['db'])) {
                continue;
            }
            if (self::searchBuilderType($table, $column['db']) !== 'date') {
                continue;
            }
            $type = strtolower(trim((string)self::columnType($table, $column['db'])));
            $dateKeys[$column['dt']] = 0 !== strpos($type, 'timestamp');
        }
        if (!$dateKeys) {
            return $rows;
        }
        // No early return on the two zones matching any more. They match for
        // a viewer with no preference on an install that has crossed the
        // boundary -- and that is exactly the reader a pre-boundary value
        // has to be un-converted for.
        foreach ($rows as &$row) {
            foreach ($dateKeys as $key => $isDatetime) {
                if (!isset($row[$key]) || '' === trim((string)$row[$key])) {
                    continue;
                }
                // validDate() keeps the zero date -- "this never happened" --
                // out of it. Shifting one moves it across a day boundary and
                // changes how the cell renders for no reason.
                if (!self::validDate($row[$key])) {
                    continue;
                }
                // The flag goes in BEFORE the value is rewritten, because
                // isPreBoundary() classifies the STORED value and the next
                // line replaces it with a display one.
                //
                // A sibling key rather than decoration on the value itself.
                // A grid cell's data is not an HTML context: it is escaped
                // into the cell, it is what sorting and filtering see, and it
                // is what the CSV/Excel buttons export. Markup smuggled
                // through it prints as tags and lands in the download
                // (GH-1245, GH-1446). The browser reads this flag and adds
                // the glyph to the cell's DOM instead, so the value stays a
                // value everywhere it is used as one.
                //
                // Only written when true. An absent key is falsy in JS, so
                // "false" costs a key on every date of every row for nothing.
                if (\FOG\Base\StorageEpoch::isPreBoundary(
                    (string)$row[$key],
                    $isDatetime
                )) {
                    $row[$key . self::UNADJUSTED_SUFFIX] = true;
                }
                $row[$key] = self::toDisplayStored(
                    (string)$row[$key],
                    $isDatetime
                )->format('Y-m-d H:i:s');
            }
        }
        unset($row);

        return $rows;
    }
    /**
     * The sentence explaining an unadjusted marker, or '' when none applies.
     *
     * Carries displayDates()'s guard for the same reason displayDates() has
     * it: a REST consumer gets raw stored values and no display conversion,
     * so a note about a conversion that did not happen would be describing
     * something it cannot see.
     *
     * @return string
     */
    public static function unadjustedNote()
    {
        if (class_exists('\\FOG\\Router\\Route')
            && \FOG\Router\Route::$apiRequest
        ) {
            return '';
        }
        if (!\FOG\Base\StorageEpoch::active()) {
            return '';
        }

        return \FOG\Base\StorageEpoch::note();
    }
    public static function dataOutput($columns, $data)
    {
        $out = [];
        // Let a column bulk-load anything it needs for the whole result set
        // before the per-row formatters run (avoids per-row N+1 queries).
        foreach (($columns ?: []) as $column) {
            if (isset($column['prime']) && is_callable($column['prime'])) {
                $column['prime']($data);
            }
        }
        for ($i = 0, $ien=count($data ?: []); $i<$ien; $i++) {
            $row = [];
            for ($j=0, $jen=count($columns ?: []); $j < $jen; $j++) {
                $column = $columns[$j];
                // Is there a formatter?
                if (isset($column['formatter'])) {
                    if (!isset($column['extra'])) {
                        $row[$column['dt']] = $column['formatter'](
                            (
                                isset($column['db']) ?
                                $data[$i][$column['db']] :
                                (
                                    isset($column['do']) ?
                                    $data[$i][$column['do']] :
                                    ''
                                )
                            ),
                            $data[$i]
                        );
                    } else {
                        $row[$column['dt']] = $column['formatter'](
                            $data[$i][$column['extra']],
                            $data[$i]
                        );
                    }
                } else {
                    $row[$column['dt']] = (
                        isset($columns[$j]['db']) && isset($data[$i][$columns[$j]['db']]) ?
                        $data[$i][$columns[$j]['db']] :
                        (
                            isset($columns[$j]['do']) && isset($data[$i][$columns[$j]['do']]) ?
                            $data[$i][$columns[$j]['do']] :
                            ''
                        )
                    );
                    if (!isset($column['extra'])) {
                        $row[$column['dt']] = (
                            isset($columns[$j]['db']) && isset($data[$i][$columns[$j]['db']]) ?
                            $data[$i][$columns[$j]['db']] :
                            (
                                isset($columns[$j]['do']) && isset($data[$i][$columns[$j]['do']]) ?
                                $data[$i][$columns[$j]['do']] :
                                ''
                            )
                        );
                    } else {
                        $row[$column['dt']] = $data[$i][$columns[$j]['extra']];
                    }
                }
            }
            $out[] = $row;
        }
        return $out;
    }
    /**
     * Paging
     *
     * Construct the LIMIT clause for server-side processing SQL Query.
     *
     * @param array $request Data sent to the server.
     * @param array $columns Column information array.
     *
     * @return string SQL limit clause.
     */
    public static function limit($request, $columns)
    {
        self::$_capped = false;
        // A request carrying no `start` at all is a REST list: /api/usertracking
        // with no pagination. That used to mean no LIMIT, which is the same
        // uncatchable memory fatal described below reached through the API
        // instead of the UI, and on exactly the tables a reporting script is
        // most likely to ask for. It takes the same cap; `truncated` in the
        // envelope, and the nextUrl Route::paginate() builds from it, are how a
        // consumer sees that it got a page and walks the rest.
        if (!isset($request['start'])) {
            self::$_capped = true;
            return 'LIMIT 0, ' . self::MAX_ROWS;
        }
        // "All" (-1) is a page size the browser can ask for from the length
        // menu, and an admin can make it the default for every grid through
        // FOG_VIEW_DEFAULT_SCREEN. It used to mean no LIMIT at all, so the row
        // query fetched the entire table into PHP in one fetchAll(). That is
        // survivable on an entity grid -- hosts, images, snapins are counted in
        // hundreds -- but userTracking, taskLog and the other append-only
        // logs are not bounded by anything: a host's Login History tab on a
        // long-lived server asks for every login and logout ever recorded
        // against it. At around 100k rows that exhausts PHP's memory_limit
        // mid-fetch, and a memory fatal cannot be caught or rendered, so the
        // grid's AJAX call answers 500 with a zero-byte body. There is nothing
        // in that for the admin to act on and nothing in it for a bug report
        // either.
        //
        // So "all rows" is bounded rather than unbounded. MAX_ROWS sits above
        // any plausible entity grid, which keeps "All" behaving exactly as it
        // does today everywhere it works today, and below the size at which the
        // log tables take the request down. recordsFiltered still reports the
        // true total, so the table's info line says how many rows there really
        // are rather than pretending the capped page is all of them.
        if ($request['length'] == -1) {
            self::$_capped = true;
            return 'LIMIT 0, ' . self::MAX_ROWS;
        }
        // Both values are clamped, because neither is ours: they come straight
        // off a DataTables POST. Scroller derives `start` from the scroll
        // position of the virtual viewport, and on a table it cannot measure --
        // one with no rows, or still hidden inside an unopened tab -- that
        // arithmetic goes negative. It arrives here as `LIMIT -1, 50`, which is
        // a SQL syntax error, so the grid's own AJAX call answers 406 with a
        // raw SQLSTATE body and DataTables reports the only thing visible from
        // the browser: "Ajax error". That is the whole of the message an admin
        // gets, on a tab whose only distinguishing feature is that it is empty.
        //
        // A negative start means the first page. A negative length is the same
        // defect with the "all rows" sentinel already handled above, so
        // anything else negative is nonsense; it is read as "all rows" and
        // takes the same cap rather than being built into `LIMIT 0, -5`.
        $start = intval($request['start']);
        $length = intval($request['length']);
        if ($length < 0) {
            self::$_capped = true;
            return 'LIMIT 0, ' . self::MAX_ROWS;
        }
        $limit = "LIMIT "
            . max(0, $start)
            . ", "
            . $length;
        return $limit;
    }
    /**
     * Finds a grid column by its output name.
     *
     * The column definitions are searched directly rather than by offsetting
     * into a pluck()ed list: pluck() skips entries and reindexes, so its
     * offsets do not correspond to $columns. Refs GH-956.
     *
     * @param array  $columns Column information array
     * @param string $dt      The output ('dt') name to find
     *
     * @return array|null The column definition, or null if there is none
     */
    protected static function columnFor($columns, $dt)
    {
        foreach ((array)$columns as $column) {
            if (isset($column['dt']) && $column['dt'] === $dt) {
                return $column;
            }
        }
        return null;
    }
    /**
     * Resolves the real column to order a grid by.
     *
     * Matches on the column's output name ('dt') first and falls back to a
     * 'do' column, mirroring the precedence order() has always used. A
     * column that is computed by a formatter, or excluded from the query,
     * has nothing the database could sort on, so it is skipped. Refs GH-956.
     *
     * @param array  $columns Column information array
     * @param string $orderby The name to order by
     *
     * @return string|null The database column, or null if there is none
     */
    protected static function orderColumn($columns, $orderby)
    {
        $column = self::columnFor($columns, $orderby);
        if (null !== $column
            && !(isset($column['removeFromQuery']) && $column['removeFromQuery'])
        ) {
            // 'order' names a different alias to sort this column by, for the
            // case where the displayed value does not sort the way it reads.
            // Group's tri-state association is the one that needs it: its
            // labels are all/some/none, which alphabetically come out
            // all/none/some, so it sorts on a numeric rank alias instead.
            if (isset($column['order'])) {
                return $column['order'];
            }
            if (isset($column['db'])) {
                return $column['db'];
            }
            // A column carrying only 'do' is a SELECT alias rather than a real
            // table column -- the association tabs' `IF(... ) AS <owner>Assoc`
            // is the case that matters. MySQL lets ORDER BY name a select
            // alias, so it is sortable; it just is not findable by its alias,
            // because the grid asks for it by its OUTPUT name ('association')
            // and the alias is built from the owning class. Without this the
            // lookup fell through to the loop below, which compares 'do'
            // against the requested name and so could never match -- the sort
            // was dropped silently and every association tab came back ordered
            // by name alone, ignoring the requested "associated first".
            if (isset($column['do'])) {
                return $column['do'];
            }
        }
        foreach ((array)$columns as $column) {
            if (isset($column['removeFromQuery']) && $column['removeFromQuery']) {
                continue;
            }
            if (isset($column['do']) && $column['do'] === $orderby) {
                return $column['do'];
            }
        }
        return null;
    }
    /**
     * Renders a resolved order target for the ORDER BY clause.
     *
     * A plain column or select alias is quoted as an identifier, as it always
     * was. A column's 'order' key may instead give a whole SQL expression --
     * group's tri-state ranking is the one that does -- and backticking that
     * would turn it into a nonexistent column name, so it is emitted as-is.
     * Nothing user-supplied reaches here: 'order' is only ever set from a
     * column definition written in PHP, and everything else is matched
     * against those definitions before it gets this far.
     *
     * @param string $ref The resolved column, alias, or expression
     *
     * @return string The ORDER BY target
     */
    protected static function orderRef($ref)
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $ref)) {
            return '`' . $ref . '`';
        }
        return $ref;
    }
    /**
     * Ordering
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     * @param array $request Data sent to server by DataTables
     * @param array $columns Column information array
     * @param array $orderby set order value
     *
     * @return string SQL order by clause
     */
    public static function order($request, $columns, $orderby = 'name')
    {
        $order = '';
        $dtColumns = self::pluck($columns, 'dt');
        if (!isset($request['order']) || count($request['order'] ?: []) <= 0) {
            // GH-956: the branch this replaced had no arm for the case that
            // actually happens. It ordered by id when $orderby matched
            // nothing, and by the 'do' column when it matched only that --
            // but when $orderby named one of the grid's own 'dt' columns,
            // which is the normal case, it fell through and returned an empty
            // string. The one column a caller could never order by was the
            // one it asked for, so every list came back in whatever order the
            // storage engine happened to hand the rows over. The UI hid it
            // (DataTables re-sorts client-side once it has the rows) but the
            // API did not: LIMIT over an unordered result means paging can
            // repeat or skip rows.
            //
            // The lookup also no longer indexes one pluck()ed list with an
            // offset from another. pluck() drops entries that lack the
            // property or are removeFromQuery and then reindexes, so the two
            // lists do not line up -- the old id fallback only worked because
            // 'id' happens to be the first column of both for most classes.
            $orderCol = self::orderColumn($columns, $orderby);
            if (null === $orderCol) {
                $orderCol = self::orderColumn($columns, 'id');
            }
            if (null !== $orderCol) {
                $order = 'ORDER BY ' . self::orderRef($orderCol) . ' ASC';
            }
            return $order;
        }
        $orderBy = [];
        for ($i = 0, $ien = count($request['order'] ?: []); $i < $ien; $i++) {
            // Convert the column index into the column data property
            $columnIdx = intval($request['order'][$i]['column']);
            $requestColumn = $request['columns'][$columnIdx];
            if ($requestColumn['orderable'] != 'true'
                || (
                    isset($requestColumn['removeFromQuery'])
                    && $requestColumn['removeFromQuery']
                )
            ) {
                continue;
            }
            // Resolved against $columns itself rather than by using an offset
            // into the pluck()ed 'dt' list: pluck() drops entries and
            // reindexes, so the two lists only line up while no column ahead
            // of this one is removeFromQuery. Sorting by a header could
            // therefore sort by a different column. Refs GH-956.
            $orderCol = self::orderColumn($columns, $requestColumn['data']);
            if (null === $orderCol) {
                continue;
            }
            $dir = $request['order'][$i]['dir'] === 'asc' ?
                'ASC' :
                'DESC';
            $orderBy[] = self::orderRef($orderCol).' '.$dir;
        }
        if (count($orderBy ?: []) > 0) {
            $order = 'ORDER BY '.implode(', ', $orderBy);
        }
        return $order;
    }
    /**
     * Searching / Filtering
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here performance on large
     * databases would be very poor
     *
     * Two column flags suppress a column here, and they mean different
     * things. 'removeFromQuery' says the column is not a real column of the
     * table -- an aggregate or a formatter's invention -- so naming it in
     * SQL is an error. 'nosearch' says the column IS real and IS selected,
     * but must never be matched against: Route::listem() sets it on every
     * field the emitter strips, so a caller cannot use match/no-match to
     * learn a value the response refuses to contain. The searchable flag in
     * the request cannot serve for this -- the client sends it.
     *
     * A SearchBuilder payload, when the request carries one, is ANDed onto
     * whatever the two boxes above produced -- the same relationship the
     * per-column boxes already have to the global one. See
     * _searchBuilderWhere().
     *
     * @param array  $request  Data sent to server by DataTables
     * @param array  $columns  Column information array
     * @param array  $bindings Array of values for PDO bindings, used in the
     *                         sqlexec() function
     * @param string $table    The table being queried, for column typing
     *
     * @return string SQL where clause
     */
    public static function filter($request, $columns, &$bindings, $table = '')
    {
        $globalSearch = [];
        $columnSearch = [];
        // Both loops used to locate the column by offsetting into the
        // pluck()ed 'dt' list, which does not line up with $columns -- the
        // same mismatch fixed in order(). Worse here: array_search() returns
        // false when the column is not found, and $columns[false] is
        // $columns[0], so an unmatched column silently searched the grid's
        // FIRST column instead of being skipped. Resolve against $columns
        // directly. Refs GH-956.
        if (isset($request['search']) && $request['search']['value'] != '') {
            $str = $request['search']['value'];
            for ($i=0, $ien = count($request['columns'] ?: []); $i < $ien; $i++) {
                $requestColumn = $request['columns'][$i];
                $column = self::columnFor($columns, $requestColumn['data']);
                if (null === $column
                    || $requestColumn['searchable'] != 'true'
                    || !isset($column['db'])
                    || (isset($column['removeFromQuery']) && $column['removeFromQuery'])
                    || (isset($column['nosearch']) && $column['nosearch'])
                ) {
                    continue;
                }
                $columnSrch = $column['db'];
                $binding = self::bind($bindings, '%'.$str.'%', \PDO::PARAM_STR);
                $globalSearch[] = "`".$columnSrch."` LIKE ".$binding;
            }
        }
        // Individual column filtering
        if (isset($request['columns'])) {
            for ($i = 0, $ien = count($request['columns'] ?: []); $i < $ien; $i++) {
                $requestColumn = $request['columns'][$i];
                $column = self::columnFor($columns, $requestColumn['data']);
                $str = $requestColumn['search']['value'];
                if (null === $column
                    || $requestColumn['searchable'] != 'true'
                    || $str == ''
                    || !isset($column['db'])
                    || (isset($column['removeFromQuery']) && $column['removeFromQuery'])
                    || (isset($column['nosearch']) && $column['nosearch'])
                ) {
                    continue;
                }
                // A per-column box in the header row can carry a condition,
                // so that "starts with", "is exactly" and a date's "before"
                // are the SAME code path -- and the same guards, escaping and
                // whole-day arithmetic -- as the Filter panel's equivalent.
                // The wire form is the condition, a unit separator, then up
                // to two values. A plain string with no separator keeps the
                // historical contains-anywhere behavior, which is what every
                // other caller of column().search() in FOG sends.
                if (false !== strpos($str, self::COLUMN_SEARCH_SEPARATOR)) {
                    $parts = explode(self::COLUMN_SEARCH_SEPARATOR, $str);
                    $clause = self::_sbCriterion(
                        [
                            'origData' => $requestColumn['data'],
                            'condition' => array_shift($parts),
                            'value' => $parts,
                        ],
                        $columns,
                        $bindings,
                        $table
                    );
                    if ($clause !== '') {
                        $columnSearch[] = $clause;
                    }
                    continue;
                }
                $columnSrch = $column['db'];
                $binding = self::bind(
                    $bindings,
                    '%' . $str . '%',
                    \PDO::PARAM_STR
                );
                $columnSearch[] = "`".$columnSrch."` LIKE ".$binding;
            }
        }
        // Combine the filters into a single string
        $where = '';
        if (count($globalSearch ?: [])) {
            $where = '('.implode(' OR ', $globalSearch).')';
        }
        if (count($columnSearch ?: [])) {
            $where = $where === '' ?
                implode(' AND ', $columnSearch) :
                $where .' AND '. implode(' AND ', $columnSearch);
        }
        $builder = self::_searchBuilderWhere(
            $request,
            $columns,
            $bindings,
            $table
        );
        if ($builder !== '') {
            $where = $where === '' ?
                $builder :
                $where . ' AND ' . $builder;
        }
        if ($where !== '') {
            $where = 'WHERE '.$where;
        }
        return $where;
    }
    /**
     * The search type a column is treated as: 'date', 'num' or 'string'.
     *
     * Read from the column's real SQL type rather than from anything the
     * client said, so a request cannot talk this server into date arithmetic
     * on a text column. columnType() answers from commons/schema-expected.php
     * for core and from the server's own catalog for anything the manifest
     * does not cover, which is what makes a plugin's grid type its columns
     * correctly without the plugin doing anything.
     *
     * TIME and TIMESTAMP part company deliberately: a TIMESTAMP names a point
     * on the calendar and gets the day-boundary handling below, a TIME does
     * not and stays a string. An unknown or unreadable type is a string,
     * which is the behavior every column had before this existed.
     *
     * @param string $table  the database table
     * @param string $column the database column
     *
     * @return string
     */
    public static function searchBuilderType($table, $column)
    {
        $type = strtolower(trim((string)self::columnType($table, $column)));
        if (!preg_match('/^([a-z]+)/', $type, $match)) {
            return 'string';
        }
        switch ($match[1]) {
            case 'date':
            case 'datetime':
            case 'timestamp':
                return 'date';
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'integer':
            case 'bigint':
            case 'decimal':
            case 'dec':
            case 'numeric':
            case 'float':
            case 'double':
            case 'real':
            case 'bit':
            case 'year':
                return 'num';
        }
        return 'string';
    }
    /**
     * The per-column search types the browser needs to build its filter UI.
     *
     * Sent with every grid payload as '_searchtypes', keyed by the column's
     * output name. Server-derived rather than sniffed from the rows, because
     * a server-side grid only ever hands the client ONE PAGE: a column that
     * happens to be empty on page one would be typed as text and lose its
     * date picker, and which columns those are changes with the sort.
     *
     * A column that may not be searched is reported as false rather than
     * omitted, so the client can leave it out of the picker entirely instead
     * of offering a filter that would be silently dropped here.
     *
     * @param string $table   the table being queried
     * @param array  $columns Column information array
     *
     * @return array
     */
    public static function searchTypes($table, $columns)
    {
        $types = [];
        foreach ((array)$columns as $column) {
            if (!isset($column['dt'])) {
                continue;
            }
            if (!isset($column['db'])
                || (isset($column['removeFromQuery']) && $column['removeFromQuery'])
                || (isset($column['nosearch']) && $column['nosearch'])
            ) {
                $types[$column['dt']] = false;
                continue;
            }
            $types[$column['dt']] = self::searchBuilderType($table, $column['db']);
        }
        return $types;
    }
    /**
     * Turns a DataTables SearchBuilder payload into a WHERE fragment.
     *
     * The payload is a tree: a group carries a `logic` ("AND"/"OR") and a
     * list of `criteria`, each of which is either a leaf (a column, a
     * condition and up to two values) or another group. Groups become
     * parenthesised, so the client's own nesting is what decides precedence
     * rather than operator precedence in SQL.
     *
     * Nothing here trusts the request. The column is resolved against the
     * server's own column definitions, the condition must be one this server
     * implements for that column's real type, and every value is bound.
     * A criterion that fails any of those is dropped -- and dropping is the
     * safe direction: within an AND it removes a restriction the UI was
     * showing, within an OR it removes an alternative, so the result can
     * narrow but can never contain a row the user was not entitled to.
     *
     * @param array  $request  Data sent to server by DataTables
     * @param array  $columns  Column information array
     * @param array  $bindings Array of values for PDO bindings
     * @param string $table    The table being queried
     *
     * @return string
     */
    private static function _searchBuilderWhere($request, $columns, &$bindings, $table)
    {
        if (!isset($request['searchBuilder'])
            || !is_array($request['searchBuilder'])
        ) {
            return '';
        }
        $budget = self::SEARCHBUILDER_MAX_CRITERIA;
        return self::_sbGroup(
            $request['searchBuilder'],
            $columns,
            $bindings,
            $table,
            0,
            $budget
        );
    }
    /**
     * One SearchBuilder group, recursively.
     *
     * @param array  $group    The group node
     * @param array  $columns  Column information array
     * @param array  $bindings Array of values for PDO bindings
     * @param string $table    The table being queried
     * @param int    $depth    How deep this group sits
     * @param int    $budget   Criteria left to spend, decremented in place
     *
     * @return string
     */
    private static function _sbGroup(
        $group,
        $columns,
        &$bindings,
        $table,
        $depth,
        &$budget
    ) {
        if ($depth > self::SEARCHBUILDER_MAX_DEPTH
            || !isset($group['criteria'])
            || !is_array($group['criteria'])
        ) {
            return '';
        }
        $logic = isset($group['logic'])
            && strtoupper((string)$group['logic']) === 'OR'
            ? 'OR'
            : 'AND';
        $parts = [];
        foreach ($group['criteria'] as $criterion) {
            if (!is_array($criterion) || $budget < 1) {
                continue;
            }
            $budget--;
            $sql = isset($criterion['criteria'])
                ? self::_sbGroup(
                    $criterion,
                    $columns,
                    $bindings,
                    $table,
                    $depth + 1,
                    $budget
                )
                : self::_sbCriterion($criterion, $columns, $bindings, $table);
            if ($sql !== '') {
                $parts[] = $sql;
            }
        }
        if (count($parts ?: []) < 1) {
            return '';
        }
        return '(' . implode(' ' . $logic . ' ', $parts) . ')';
    }
    /**
     * One SearchBuilder leaf criterion.
     *
     * @param array  $criterion The criterion node
     * @param array  $columns   Column information array
     * @param array  $bindings  Array of values for PDO bindings
     * @param string $table     The table being queried
     *
     * @return string
     */
    private static function _sbCriterion($criterion, $columns, &$bindings, $table)
    {
        $column = self::columnFor(
            $columns,
            isset($criterion['origData']) ? (string)$criterion['origData'] : ''
        );
        // The same three guards a per-column search gets in filter(), for the
        // same reasons. 'removeFromQuery' means there is no column to name;
        // 'nosearch' means the column IS real but the emitter strips its
        // value, so matching on it would let a caller use match/no-match to
        // read what the response refuses to contain.
        if (null === $column
            || !isset($column['db'])
            || (isset($column['removeFromQuery']) && $column['removeFromQuery'])
            || (isset($column['nosearch']) && $column['nosearch'])
        ) {
            return '';
        }
        $type = self::searchBuilderType($table, $column['db']);
        $condition = isset($criterion['condition'])
            ? (string)$criterion['condition']
            : '';
        if (!in_array($condition, self::$_sbConditions[$type], true)) {
            return '';
        }
        $col = '`' . $column['db'] . '`';
        $values = self::_sbValues($criterion);
        switch ($type) {
            case 'date':
                return self::_sbDate($col, $condition, $values, $bindings);
            case 'num':
                return self::_sbNum($col, $condition, $values, $bindings);
        }
        return self::_sbString($col, $condition, $values, $bindings);
    }
    /**
     * The values off a criterion, in the order the client sorted them.
     *
     * SearchBuilder sends both shapes: `value` as an array, and value1/value2
     * flattened out of it for servers that find nested form fields awkward.
     * They carry the same content, so read the array and fall back.
     *
     * @param array $criterion The criterion node
     *
     * @return array
     */
    private static function _sbValues($criterion)
    {
        $values = [];
        if (isset($criterion['value']) && is_array($criterion['value'])) {
            foreach ($criterion['value'] as $value) {
                if (is_scalar($value)) {
                    $values[] = (string)$value;
                }
            }
            return $values;
        }
        foreach (['value1', 'value2'] as $key) {
            if (isset($criterion[$key]) && is_scalar($criterion[$key])) {
                $values[] = (string)$criterion[$key];
            }
        }
        return $values;
    }
    /**
     * A text criterion.
     *
     * @param string $col       The quoted column
     * @param string $condition The whitelisted condition key
     * @param array  $values    The criterion's values
     * @param array  $bindings  Array of values for PDO bindings
     *
     * @return string
     */
    private static function _sbString($col, $condition, $values, &$bindings)
    {
        if ($condition === 'null') {
            return "($col IS NULL OR $col = '')";
        }
        if ($condition === '!null') {
            return "($col IS NOT NULL AND $col <> '')";
        }
        // An unfilled criterion is dropped. The client treats one as matching
        // every row, so dropping it agrees with the UI inside an AND and is
        // strictly narrower inside an OR -- never wider.
        if (!isset($values[0]) || $values[0] === '') {
            return '';
        }
        if ($condition === '=') {
            return $col . ' = '
                . self::bind($bindings, $values[0], \PDO::PARAM_STR);
        }
        if ($condition === '!=') {
            // A NULL cell reads as an empty string in the grid, so it is
            // "not equal to" anything the user typed and belongs in the
            // result. Without the IS NULL arm SQL's three-valued logic would
            // silently drop those rows.
            return '(' . $col . ' IS NULL OR ' . $col . ' <> '
                . self::bind($bindings, $values[0], \PDO::PARAM_STR) . ')';
        }
        // LIKE metacharacters in the VALUE are escaped, because the condition
        // the user picked is what says where the wildcards go -- "contains"
        // already means "anywhere". A '%' they typed is a percent sign they
        // are looking for. The free-text box above the table is deliberately
        // left as it was: there the loose behavior is the feature.
        $escaped = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $values[0]
        );
        $patterns = [
            'starts' => $escaped . '%',
            '!starts' => $escaped . '%',
            'contains' => '%' . $escaped . '%',
            '!contains' => '%' . $escaped . '%',
            'ends' => '%' . $escaped,
            '!ends' => '%' . $escaped
        ];
        $binding = self::bind($bindings, $patterns[$condition], \PDO::PARAM_STR);
        if ($condition[0] === '!') {
            return "($col IS NULL OR $col NOT LIKE $binding ESCAPE '\\\\')";
        }
        return "$col LIKE $binding ESCAPE '\\\\'";
    }
    /**
     * A numeric criterion.
     *
     * @param string $col       The quoted column
     * @param string $condition The whitelisted condition key
     * @param array  $values    The criterion's values
     * @param array  $bindings  Array of values for PDO bindings
     *
     * @return string
     */
    private static function _sbNum($col, $condition, $values, &$bindings)
    {
        if ($condition === 'null') {
            return $col . ' IS NULL';
        }
        if ($condition === '!null') {
            return $col . ' IS NOT NULL';
        }
        $numeric = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $numeric[] = $value + 0;
            }
        }
        $between = ($condition === 'between' || $condition === '!between');
        if (count($numeric ?: []) < ($between ? 2 : 1)) {
            return '';
        }
        if ($between) {
            // The client sorts a between pair before sending it, but the
            // request is not what this depends on: BETWEEN with the bounds
            // the wrong way round matches nothing at all, silently.
            sort($numeric);
            $low = self::bind($bindings, $numeric[0], \PDO::PARAM_STR);
            $high = self::bind($bindings, $numeric[1], \PDO::PARAM_STR);
            if ($condition === '!between') {
                return "($col IS NULL OR $col NOT BETWEEN $low AND $high)";
            }
            return "$col BETWEEN $low AND $high";
        }
        $binding = self::bind($bindings, $numeric[0], \PDO::PARAM_STR);
        if ($condition === '!=') {
            return "($col IS NULL OR $col <> $binding)";
        }
        // Interpolated, not bound: $condition has already been matched
        // against $_sbConditions, so it is one of '=', '<', '<=', '>=', '>'.
        return "$col $condition $binding";
    }
    /**
     * A date criterion.
     *
     * SearchBuilder sends a whole day ('YYYY-MM-DD'), and the columns are
     * DATETIMEs, so every condition is expressed as a half-open range over
     * day boundaries rather than a comparison against the bare date. Compared
     * literally, "= 2026-08-29" would match nothing on a DATETIME column and
     * "after 2026-08-29" would still include that afternoon.
     *
     * @param string $col       The quoted column
     * @param string $condition The whitelisted condition key
     * @param array  $values    The criterion's values
     * @param array  $bindings  Array of values for PDO bindings
     *
     * @return string
     */
    private static function _sbDate($col, $condition, $values, &$bindings)
    {
        $floor = self::SEARCHBUILDER_DATE_FLOOR;
        if ($condition === 'null') {
            return "($col IS NULL OR $col < '$floor')";
        }
        if ($condition === '!null') {
            return "($col >= '$floor')";
        }
        $dates = [];
        foreach ($values as $value) {
            if (self::_sbIsDay($value)) {
                $dates[] = (string)$value;
            }
        }
        $between = ($condition === 'between' || $condition === '!between');
        if (count($dates ?: []) < ($between ? 2 : 1)) {
            return '';
        }
        sort($dates);
        // BIND ONLY WHAT THE RETURNED CLAUSE NAMES. Every binding created here
        // is handed to PDO for each of complex()'s three queries, so one the
        // clause does not mention is not merely wasted -- PDO refuses the
        // statement with "Invalid parameter number: parameter was not defined"
        // and the whole list answers 406. 'before' names only the lower bound
        // and 'after' only the upper, so binding both up front broke exactly
        // the two conditions this feature exists for, while '=' and 'between'
        // (which use both) worked. Found against the running server; the first
        // cut of the gate rendered the bindings into the SQL text and so could
        // not see it. The gate now also asserts every binding is referenced.
        // Both bounds are converted from the zone the VIEWER is reading in to
        // the zone the column is stored in. The user picked a day off a
        // calendar that matches what the grid showed them, so "on 29 August"
        // has to mean their 29th -- not the server's, which near midnight is a
        // different day and quietly returns the wrong rows. A no-op for every
        // account that has not chosen a display zone.
        $lower = self::displayToStorage($dates[0] . ' 00:00:00');
        // The exclusive upper bound: midnight starting the day AFTER the last
        // date named. One date makes it that date's own midnight, so "=" is
        // the whole of one day and "after" starts at the next.
        $upper = self::displayToStorage(
            self::_sbNextDay($between ? $dates[1] : $dates[0])
        );
        switch ($condition) {
            case '=':
            case 'between':
                $from = self::bind($bindings, $lower, \PDO::PARAM_STR);
                $until = self::bind($bindings, $upper, \PDO::PARAM_STR);
                return "($col >= $from AND $col < $until)";
            case '!=':
            case '!between':
                // A never-set date is not the date asked about, so it belongs
                // in the result -- matching what the grid shows, where the
                // cell is blank rather than absent.
                $from = self::bind($bindings, $lower, \PDO::PARAM_STR);
                $until = self::bind($bindings, $upper, \PDO::PARAM_STR);
                return "($col IS NULL OR $col < $from OR $col >= $until)";
            case '<':
                $from = self::bind($bindings, $lower, \PDO::PARAM_STR);
                return "($col >= '$floor' AND $col < $from)";
        }
        // '>', the only condition left: strictly after the whole of that day.
        return $col . ' >= ' . self::bind($bindings, $upper, \PDO::PARAM_STR);
    }
    /**
     * Is this one of SearchBuilder's 'YYYY-MM-DD' day values?
     *
     * @param string $value The value to test
     *
     * @return bool
     */
    private static function _sbIsDay($value)
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)
            && checkdate(
                (int)substr((string)$value, 5, 2),
                (int)substr((string)$value, 8, 2),
                (int)substr((string)$value, 0, 4)
            );
    }
    /**
     * Midnight starting the day after the one given.
     *
     * @param string $day A validated 'YYYY-MM-DD' value
     *
     * @return string
     */
    private static function _sbNextDay($day)
    {
        $date = new \DateTime($day . ' 00:00:00');
        $date->modify('+1 day');
        return $date->format('Y-m-d H:i:s');
    }
    /**
     * Perform the SQL queries needed for an server-side processing requested,
     * utilizing the helper functions of this class, limit(), order() and
     * filter() among others. The returned array is ready to be encoded as JSON
     * in response to an SSP request, or can be modified if needed before
     * sending back to the client.
     *
     * @param array  $request    Data sent to server by DataTables
     * @param string $table      SQL table to query
     * @param string $primaryKey Primary key of the table
     * @param array  $columns    Column information array
     * @param string $sqlstr     The sql query to use.
     * @param string $fltrstr    The Filter query to use.
     * @param string $ttlstr     The total query to use.
     * @param string $orderby    How to order the values.
     *
     * @return array Server-side processing response array
     */
    public static function simple(
        $request,
        $table,
        $primaryKey,
        $columns,
        $sqlstr,
        $fltrstr,
        $ttlstr,
        $orderby = 'name'
    ) {
        // `simple` is `complex` with no extra WHERE conditions; delegate so the
        // server-side processing logic lives in exactly one place.
        return self::complex(
            $request,
            $table,
            $primaryKey,
            $columns,
            $sqlstr,
            $fltrstr,
            $ttlstr,
            null,
            null,
            $orderby
        );
    }
    /**
     * The difference between this method and the `simple` one, is that you can
     * apply additional `where` conditions to the SQL queries. These can be in
     * one of two forms:
     *
     * * 'Result condition' - This is applied to the result set, but not the
     *   overall paging information query - i.e. it will not effect the number
     *   of records that a user sees they can have access to. This should be
     *   used when you want apply a filtering condition that the user has sent.
     * * 'All condition' - This is applied to all queries that are made and
     *   reduces the number of records that the user can access. This should be
     *   used in conditions where you don't want the user to ever have access to
     *   particular records (for example, restricting by a login id).
     *
     * @param array  $request     Data sent to server by DataTables
     * @param string $table       SQL table to query
     * @param string $primaryKey  Primary key of the table
     * @param array  $columns     Column information array
     * @param string $sqlstr      The sql query to use.
     * @param string $fltrstr     The Filter query to use.
     * @param string $ttlstr      The total query to use.
     * @param string $whereResult WHERE condition to apply to the result set
     * @param string $whereAll    WHERE condition to apply to all queries
     * @param string $orderby     How to order the query
     * @param bool   $countOnly   Only the record counts are wanted, so skip
     *                            the row query and the formatters entirely.
     *
     * @return array          Server-side processing response array
     */
    public static function complex(
        $request,
        $table,
        $primaryKey,
        $columns,
        $sqlstr,
        $fltrstr,
        $ttlstr,
        $whereResult = null,
        $whereAll = null,
        $orderby = 'name',
        $countOnly = false
    ) {
        $bindings = [];
        $db = DatabaseManager::getLink();
        $localWhereResult = [];
        $localWhereAll = [];
        $whereAllSql = '';
        if ($primaryKey == 'id') {
            foreach ($columns as $item) {
                if ($item['dt'] == 'id') {
                    $primaryKey = $item['db'];
                }
                unset($item);
            }
        }
        // Build the SQL query string from the request
        $limit = self::limit($request, $columns);
        $order = self::order($request, $columns, $orderby);
        $where = self::filter($request, $columns, $bindings, $table);
        $whereResult = self::_flatten($whereResult);
        $whereAll = self::_flatten($whereAll);
        if ($whereResult) {
            $where = $where ?
                $where .' AND '.$whereResult :
                'WHERE '.$whereResult;
        }
        if ($whereAll) {
            $where = $where ?
                $where .' AND '.$whereAll :
                'WHERE '.$whereAll;
            $whereAllSql = 'WHERE '.$whereAll;
        }
        // Build the actual string itself.
        $sql_query = sprintf(
            $sqlstr,
            implode('`,`', self::pluck($columns, 'db')),
            $table,
            $where,
            $order,
            $limit
        );
        // Main query to actually get the data.
        //
        // GH-707: Route::count() reaches this through listem() purely for
        // recordsFiltered, which the filter query below answers on its own.
        // Running the row query anyway meant a count of a 1000-host group
        // fetched all 1000 rows and then ran every per-row formatter, each of
        // which loads a related object -- roughly a thousand extra queries to
        // produce a single integer. Group::loadHosts() calls getHostCount(),
        // so that price was paid on merely touching a group.
        $data = $countOnly ? [] : self::sqlexec($db, $bindings, $sql_query);
        // Data set length after filtering
        $filter_query = sprintf(
            $fltrstr,
            $primaryKey,
            $table,
            $where
        );
        $resFilterLength = self::sqlexec($db, $bindings, $filter_query);
        $recordsFiltered = $resFilterLength[0][0];
        // Total data set length
        $total_query = sprintf(
            $ttlstr,
            $primaryKey,
            $table
        ).$whereAllSql;
        // Total data set length
        $resTotalLength = self::sqlexec($db, $total_query);
        $recordsTotal = $resTotalLength[0][0];
        /*
         * Output
         */
        return [
            'draw' => (
                isset($request['draw']) ?
                intval($request['draw']) :
                0
            ),
            'recordsTotal' => intval($recordsTotal),
            'recordsFiltered' => intval($recordsFiltered),
            // True when the caller did not bound the request and MAX_ROWS did
            // it for them AND there was more behind the cap. Without this a
            // capped response is indistinguishable from a complete one, which
            // is how a reporting script silently loses rows. countOnly fetches
            // no rows at all, so nothing was truncated there.
            'truncated' => !$countOnly
                && self::$_capped
                && intval($recordsFiltered) > self::MAX_ROWS,
            'data' => $countOnly
                ? []
                : self::displayDates(
                    self::dataOutput($columns, $data),
                    $table,
                    $columns
                ),
            // How each column may be filtered, for the client's search UI.
            // Server-derived because a server-side grid hands the browser one
            // page, and page one is not enough to tell a date column from a
            // text one -- see searchTypes().
            '_searchtypes' => self::searchTypes($table, $columns),
            // The tooltip text for any cell flagged UNADJUSTED_SUFFIX above.
            // Once per response, not once per row: it is the same sentence
            // every time, and it is a TRANSLATED one -- assembling it in JS
            // would put a msgid somewhere xgettext never looks. Empty on an
            // install that has not crossed the boundary, which is also every
            // install where no row can carry the flag.
            '_unadjustednote' => self::unadjustedNote(),
            //'sql_query' => $sql_query,
            //'filter_query' => $filter_query,
            //'total_query' => $total_query,
            //'request' => $request,
            //'columns' => $columns,
            //'order' => $order
        ];
    }
    /**
     * Execute an SQL query on the database
     *
     * @param resource $db       Database handler
     * @param array    $bindings Array of PDO binding values from bind() to be
     *                           used for safely escaping strings.
     *                           Note that this can be given as the
     *                           SQL query string if no bindings are required.
     * @param string   $sql      SQL query to execute.
     *
     * @return array         Result from the query (all rows)
     */
    public static function sqlexec($db, $bindings, $sql = null)
    {
        // Argument shifting
        if ($sql === null) {
            $sql = $bindings;
        }
        $stmt = $db->prepare($sql);
        //echo $sql;
        // Bind parameters
        if (is_array($bindings)) {
            for ($i = 0,$ien = count($bindings ?: []); $i < $ien; $i++) {
                $binding = $bindings[$i];
                $stmt->bindValue($binding['key'], $binding['val'], $binding['type']);
            }
        }
        // Execute
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            self::fatal(_("An SQL error occurred").": ".$e->getMessage() . "SQL: $sql");
        }
        // Return all
        return $stmt->fetchAll(\PDO::FETCH_BOTH);
    }
    /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
     * Internal methods
     */

    /**
     * Throw a fatal error.
     *
     * This writes out an error message in a JSON string which DataTables will
     * see and show to the user in the browser.
     *
     * @param string $msg Message to send to the client
     *
     * @return void
     */
    public static function fatal($msg)
    {
        echo json_encode(
            ['error' => $msg]
        );
        exit(0);
    }
    /**
     * Create a PDO binding key which can be used for escaping variables safely
     * when executing a query with sqlexec()
     *
     * @param array $a    Array of bindings
     * @param *     $val  Value to bind
     * @param int   $type PDO field type
     *
     * @return string       Bound key to be used in the SQL where this parameter
     *   would be used.
     */
    public static function bind(&$a, $val, $type)
    {
        $key = ':binding_'.count($a ?: []);
        $a[] = [
            'key' => $key,
            'val' => $val,
            'type' => $type
        ];
        return $key;
    }
    /**
     * Pull a particular property from each assoc. array in a numeric array,
     * returning and array of the property values from each item.
     *
     * @param array  $a    Array to get data from
     * @param string $prop Property to read
     *
     * @return array        Array of property values
     */
    public static function pluck($a, $prop)
    {
        $out = [];
        for ($i = 0, $len = count($a ?: []); $i < $len; $i++) {
            if (!isset($a[$i][$prop])
                || (isset($a[$i]['removeFromQuery']) && $a[$i]['removeFromQuery'])
            ) {
                continue;
            }
            $out[] = $a[$i][$prop];
        }
        return $out;
    }
    /**
     * Return a string from an array or a string
     *
     * @param array|string $a    Array to join
     * @param string       $join Glue for the concatenation
     *
     * @return string Joined string
     */
    private static function _flatten($a, $join = ' AND ')
    {
        if (!$a) {
            return '';
        } elseif ($a && is_array($a)) {
            return implode($join, $a);
        }
        return $a;
    }
    /**
     * Trims a value on its way into a bound parameter, and leaves anything
     * that is not a string alone.
     *
     * Trimming is a string operation, but `trim()` casts first, and the cast
     * is where the information goes. trim(null) is '' -- and a PHP 8.1
     * deprecation -- which would put the zero date back into a column being
     * cleared. trim(false) is also '', which is not how any column in the
     * schema spells false: enum('0','1') rejects it outright under
     * STRICT_TRANS_TABLES, and so does the tinyint(1) `hosts`.`hostInfoLock`
     * that ends every imaging task via ->set('tokenlock', false). And an
     * array -- a nested IN () list is one -- is a TypeError on PHP 8.
     *
     * A boolean is left as a boolean and normalized once, in PDODB::_bind(),
     * so save() and the two builders here cannot disagree about what false
     * stores. See GH-1245 and forum topic 18227.
     *
     * @param mixed $value the value being bound
     *
     * @return mixed
     */
    private static function _trimValue($value)
    {
        return is_string($value) ? trim($value) : $value;
    }
    /**
     * Refuses a batch row whose required foreign key points at nothing.
     *
     * FOGController::save() will not write a row whose required *ID field is
     * not an integer >= 1 -- see its "Required *id must be integer >= 1"
     * branch. insertBatch() enforced nothing, so the same model validated
     * itself when written one row at a time and did not when written a
     * hundred at a time.
     *
     * What gets through is a 0. Since GH-1245 the server's own sql_mode
     * rejects a null or an '' bound into a NOT NULL int, but 0 is a perfectly
     * legal integer and no layer has an opinion about it -- and a caller
     * indexing a positional list past its end, or reading an id off an object
     * that did not load, produces exactly that.
     *
     * In `tasks` such a row is permanent and invisible at the same time.
     * taskStateID still resolves, so the task counts as active for every
     * "is this live" test in the tree; taskHostID/taskImageID/taskTypeID
     * match nothing, and because the Active Tasks list renders from
     * buildQuery()'s LEFT OUTER JOINs those columns come back NULL. The row
     * shows as "() -" for host and image with no type icon, cannot be
     * completed by any host, and nothing ever reaps it. Reported as "null
     * tasks" in forum topics 18228 and 18230.
     *
     * Deliberately narrow, because this is a write path with 26 call sites:
     *
     *  - Only columns the caller NAMED. A required column the batch is silent
     *    about is left to columnsRequiringValue() below, which is the
     *    behavior every one of those call sites already relies on; turning
     *    silence into an error is a different change with a different blast
     *    radius.
     *  - Only *ID columns. They are the ones whose zero is indistinguishable
     *    from a value; a required string is caught by the server or by the
     *    reader either way.
     *  - Never the model's own primary key. No batch caller supplies it, and
     *    save() skips it for the same reason.
     *  - Never a key the model has declared is a string via
     *    $databaseFieldsNotInt.
     *
     * @param array $fields the friendly field names the caller named
     * @param array $values the rows, positional against $fields
     *
     * @throws \Exception
     *
     * @return void
     */
    private function _assertBatchForeignKeys($fields, $values)
    {
        $notInt = array_map(
            'strtolower',
            (array)$this->databaseFieldsNotInt
        );
        $positions = [];
        foreach ((array)$this->databaseFieldsRequired as $friendly) {
            $lower = strtolower($friendly);
            if ('id' === $lower
                || 'id' !== substr($lower, -2)
                || in_array($lower, $notInt, true)
            ) {
                continue;
            }
            foreach ((array)$fields as $i => $named) {
                if (strtolower($named) === $lower) {
                    $positions[$i] = $friendly;
                }
            }
        }
        if (count($positions ?: []) < 1) {
            return;
        }
        foreach ((array)$values as $rowIndex => $row) {
            foreach ($positions as $i => $friendly) {
                $val = isset($row[$i]) ? $row[$i] : null;
                $valid = filter_var(
                    $val,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                if (false !== $valid) {
                    continue;
                }
                throw new \Exception(
                    sprintf(
                        '%s: `%s`.%s %s %d, %s: %s',
                        self::$foglang['RequiredDB'],
                        $this->databaseTable,
                        $friendly,
                        _('in batch row'),
                        $rowIndex,
                        _('got'),
                        var_export($val, true)
                    )
                );
            }
        }
    }
    /**
     * Inserts data in mass to the database.
     *
     * @param array  $fields the fields to insert into
     * @param array  $values the values to insert
     * @param string $table  optional table override (defaults to this
     *                        manager's table); lets callers stage a bulk
     *                        load into a side table for an atomic swap
     *
     * @return array
     */
    public function insertBatch($fields, $values, $table = null)
    {
        $fieldlength = count($fields ?: []);
        $valuelength = count($values ?: []);
        if ($fieldlength < 1) {
            throw new \Exception(_('No fields passed'));
        }
        if ($valuelength < 1) {
            throw new \Exception(_('No values passed'));
        }
        // Before the loop below, which rewrites $fields from friendly names
        // to column names in place.
        $this->_assertBatchForeignKeys($fields, $values);
        $keys = [];
        foreach ((array) $fields as &$key) {
            $key = $this->databaseFields[$key];
            $keys[] = $key;
            $dups[] = sprintf(
                '`%s`=VALUES(`%s`)',
                $key,
                $key
            );
            unset($key);
        }
        /*
         * GH-1245 again, on the other write path.
         *
         * A caller names the columns it has a value for, which is not the
         * same set as the columns the server will accept an INSERT without.
         * Under a strict sql_mode a NOT NULL column with no DEFAULT that the
         * statement does not name is error 1364 and the whole batch is
         * rejected; without one the server invents a zero value and says
         * nothing. PDODB cleared sql_mode until GH-1245, so every such call
         * site had been relying on the second behavior without knowing it --
         * saving FOG settings omits settingDesc and settingCategory, and
         * tasking a group's snapins omits stReturnCode and stReturnDetails.
         *
         * So write the coercion down instead of relying on it, exactly as
         * FOGController::save() now does for a single row. The values come
         * from the same emptyValueFor(), which is why it moved to FOGBase.
         *
         * They are deliberately NOT added to the ON DUPLICATE KEY UPDATE
         * list: this fills a column the caller had nothing to say about, so
         * on a row that already exists the stored value must stand. Filling
         * settingDesc into the update list would blank the description of
         * every setting on the page the moment anyone pressed save.
         *
         * The filled columns and their values are worked out ONCE here; the
         * placeholders that carry them are named PER ROW, down in the loop.
         * They were named once too, and the single `:_fill_0` was then
         * repeated in every VALUES tuple -- which one row survives and two do
         * not: PDODB sets PDO::ATTR_EMULATE_PREPARES => false, and a real
         * server-side prepare answers SQLSTATE[HY093] "Invalid parameter
         * number" to a named parameter used twice. So every batch of two or
         * more rows into a table with an unnamed NOT NULL column failed
         * outright, silently to the user: tasking a GROUP of two hosts with
         * anything that is not a deploy or a multicast (wipe, hardware
         * inventory, password reset, snapins) names none of
         * `tasks`' four NFS/image columns and so hit this every time.
         * See background_scripts/prove_batch_fill_duplicate_bind.php.
         */
        $fillCols = [];
        foreach ((array) self::columnsRequiringValue(
            $table ?: $this->databaseTable
        ) as $column => $type) {
            if (in_array(strtolower($column), array_map('strtolower', $keys), true)) {
                continue;
            }
            $keys[] = $column;
            $fillCols[] = self::emptyValueFor(
                $table ?: $this->databaseTable,
                $column
            );
        }
        $affectedRows = 0;
        $vals = [];
        $values = array_chunk($values, 500);
        foreach ((array) $values as $ind => &$v) {
            $insertVals = [];
            foreach ((array) $v as $index => &$value) {
                $insertKeys = [];
                foreach ((array) $value as $i => &$val) {
                    $key = sprintf(
                        '%s_%d',
                        $fields[$i],
                        $index
                    );
                    $insertKeys[] = sprintf(
                        ':%s',
                        $key
                    );
                    $val = self::_trimValue($val);
                    $insertVals[$key] = $val;
                    unset($val);
                }
                foreach ($fillCols as $fillIndex => $fillVal) {
                    $key = sprintf(
                        '_fill_%d_%d',
                        $fillIndex,
                        $index
                    );
                    $insertKeys[] = sprintf(
                        ':%s',
                        $key
                    );
                    $insertVals[$key] = $fillVal;
                }
                $vals[] = sprintf(
                    '(%s)',
                    implode(',', (array) $insertKeys)
                );
                unset($value);
            }
            if (count($vals ?: []) < 1) {
                throw new \Exception(_('No data to insert'));
            }
            $query = sprintf(
                $this->insertBatchTemplate,
                $table ?: $this->databaseTable,
                implode('`,`', $keys),
                implode(',', $vals),
                implode(',', $dups)
            );
            self::$DB->query($query, [], $insertVals);
            // Same swallowed-error seam as FOGController::save(): without
            // this the loop went on to report affectedRows for a batch the
            // server rejected. Throws rather than returning a count, because
            // the caller's two return values describe a write that happened.
            if (self::$DB->error) {
                throw new \Exception((string) self::$DB->error);
            }
            if ($ind === 0) {
                $insertID = (int) self::$DB->insertId();
            }
            $affectedRows += (int) self::$DB->affectedRows();
            unset($v, $vals, $insertVals);
        }
        return [
            $insertID,
            $affectedRows,
        ];
    }
    /**
     * Function deals with enmass updating.
     *
     * @param array  $findWhere     what specific to update
     * @param string $whereOperator what to join where with
     * @param array  $insertData    the data to update
     *
     * @return bool
     */
    public function update(
        $findWhere = [],
        $whereOperator = 'AND',
        $insertData = []
    ) {
        if (empty($findWhere)) {
            $findWhere = [];
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }

        if (self::is_assoc_array($insertData)) {
            // Handle single associative array case
            return $this->perform_update($findWhere, $whereOperator, $insertData);
        } elseif (self::is_array_of_assoc_arrays($insertData)) {
            // Handle array of associative arrays case
            foreach ($insertData as $data) {
                if (!$this->perform_update($findWhere, $whereOperator, $data)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }
    /**
     * Works to perform the actual actions.
     *
     * @param $findWhere     What are we adjusting
     * @param $whereOperator How are we doing the where/filter lookups
     * @param $insertData    What we're actually updating.
     * @return bool
     */
    private function perform_update($findWhere, $whereOperator, $insertData)
    {
        $insertArray = [];
        $whereArray = [];
        $updateVals = [];
        foreach ((array) $insertData as $field => &$value) {
            $field = trim($field);
            // GH-1245: null is a value to write, not a string to trim.
            // trim(null) is '' -- and a PHP 8.1 deprecation -- which would
            // put the zero date back into a column being cleared.
            $value = self::_trimValue($value);
            $updateKey = sprintf(
                ':update_%s',
                $field
            );
            $updateVals[sprintf('update_%s', $field)] = $value;
            $key = sprintf(
                '`%s`.`%s`',
                $this->databaseTable,
                $this->databaseFields[$field]
            );
            $insertArray[] = sprintf(
                '%s=%s',
                $key,
                $updateKey
            );
            unset($value);
        }
        unset($updateKey);
        $findVals = [];
        if (count($findWhere ?: []) > 0) {
            foreach ($findWhere as $field => &$value) {
                $key = trim($field);
                if (is_array($value) && count($value ?: []) > 0) {
                    foreach ($value as $i => &$val) {
                        $val = self::_trimValue($val);
                        // Define the key
                        $k = sprintf(
                            '%s_%d',
                            $key,
                            $i
                        );
                        // Define param keys
                        $findKeys[] = sprintf(
                            ':%s',
                            $k
                        );
                        // Define the param array
                        $findVals[$k] = $val;
                        unset($val);
                    }
                    $whereArray[] = sprintf(
                        '`%s`.`%s` IN (%s)',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        implode(',', $findKeys)
                    );
                    unset($findKeys);
                } else {
                    if (is_array($value)) {
                        $value = '';
                    }
                    // Read side, same rule as the write side above: a filter
                    // holding false has to bind the same literal the column
                    // now stores, or it silently matches nothing.
                    $value = self::_trimValue($value);
                    $k = sprintf(
                        '%s',
                        $key
                    );
                    // Define the param keys
                    $findKey = sprintf(
                        ':%s',
                        $key
                    );
                    // Define the param array
                    $findVals[$k] = $value;
                    $whereArray[] = sprintf(
                        '`%s`.`%s`%s%s',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        (
                            preg_match('#%#', (string) $value) ?
                            ' LIKE' :
                            '='
                        ),
                        $findKey
                    );
                }
                unset($value);
            }
        }
        unset($findKeys, $findKey);
        $query = sprintf(
            $this->updateQueryTemplate,
            $this->databaseTable,
            implode(',', (array) $insertArray),
            (
                count($whereArray ?: []) ?
                sprintf(
                    ' WHERE %s',
                    implode(" $whereOperator ", (array) $whereArray)
                ) :
                ''
            )
        );
        $queryVals = self::fastmerge(
            (array) $updateVals,
            (array) $findVals
        );

        self::$DB->query($query, [], $queryVals);
        /*
         * `(bool) self::$DB->query(...)` was ALWAYS true: query() returns
         * $this, and an object casts to true whatever the server said. So
         * this reported success for every rejected mass update -- including
         * the API's bulk edit, which is update()'s busiest caller.
         *
         * Faulted here rather than thrown: this method has no catch and its
         * callers expect a bool, so throwing would turn a silently-failed
         * bulk edit into an uncaught 500. False is the honest answer they
         * were already written to read.
         */
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s',
                    _('Mass update failed'),
                    _('Table'),
                    $this->databaseTable,
                    _('Error'),
                    self::$DB->error
                )
            );

            return false;
        }

        return true;
    }
    /**
     * Builds a select box/option box from the elements.
     *
     * @param mixed  $matchID     select the matching id
     * @param string $elementName the name for the select box
     * @param string $orderBy     how to order
     * @param string $filter      should we filter existing
     * @param mixed  $template    should we include a template element
     * @param string $useKey      id for storage.
     *
     * @return string
     */
    public function buildSelectBox(
        $matchID = '',
        $elementName = '',
        $orderBy = 'name',
        $filter = '',
        $template = false,
        $useKey = 'id',
        $elementId = ''
    ) {
        if (empty($useKey)) {
            $useKey = 'id';
        }
        global $node;
        global $sub;
        if ($node === 'image' && $sub === 'add') {
            $waszero = false;
            if ($matchID === 0) {
                $waszero = true;
                $matchID = 9;
            }
        }
        $elementName = trim($elementName);
        if (empty($elementName)) {
            $elementName = strtolower($this->childClass);
        }
        $elementId = trim($elementId);
        if (empty($elementId)) {
            $elementId = $elementName;
        }
        ob_start();
        self::$HookManager->processEvent(
            'SELECT_BUILD',
            [
                'matchID' => &$matchID,
                'elementName' => &$elementName,
                'orderBy' => &$orderBy,
                'filter' => &$filter,
                'template' => &$template,
                'waszero' => &$waszero,
                'obj' => $this
            ]
        );
        if ($filter) {
            $find = ['id' => $filter];
            $Items = Route::getList(
                $this->childClass,
                $find
            );
        } else {
            $Items = Route::getList($this->childClass);
        }
        foreach ($Items as &$Item) {
            if (isset($Item->isEnabled) && !$Item->isEnabled) {
                continue;
            }
            echo '<option value="'
                . \Initiator::e($Item->{$useKey})
                . '"'
                . (
                    $matchID == $Item->{$useKey} ?
                    ' selected' :
                    (
                        $template ?
                        '${selected_item' . $Item->id . '}' :
                        ''
                    )
                )
                // The visible text carries the id so two same-named items
                // can be told apart in the picker; data-label is the bare
                // name, for anything that needs to ECHO the choice rather
                // than offer it (the edit page's info card).
                . ' data-label="' . \Initiator::e($Item->name) . '"'
                . '>'
                . \Initiator::e($Item->name)
                . ' - (' . \Initiator::e($Item->id) . ')'
                . '</option>';
            unset($Item);
        }
        $objOpts = ob_get_clean();
        $objOpts = trim($objOpts);
        if (empty($objOpts)) {
            return _('No items found');
        }
        $tmpStr = '<select class="form-control input-group fog-select2" name="'
            . (
                $template ?
                '${select_name}' :
                $elementName
            )
            . '" id="'
            . $elementId
            . '" autocomplete="off">';
        $tmpStr .= '<option value="">- ';
        $tmpStr .= self::$foglang['PleaseSelect'];
        $tmpStr .= ' -</option>';
        $tmpStr .= $objOpts;
        $tmpStr .= '</select>';
        return $tmpStr;
    }
    /**
     * Checks if item already exists or not.
     *
     * @param string $val     the value to test
     * @param string $id      an ID if already exists
     * @param string $idField the id field to scan
     *
     * @return bool
     */
    public function exists(
        $val,
        $id = 0,
        $idField = 'name'
    ) {
        $idSpecField = 'id';
        if (empty($id)) {
            $id = 0;
        }
        if (empty($idField)) {
            $idField = 'name';
        }
        $existVals = [
            $idField => $val,
            'id' => $id,
        ];

        $query = sprintf(
            $this->existsQueryTemplate,
            $this->databaseTable,
            $this->databaseFields[$idField],
            $this->databaseTable,
            $this->databaseTable,
            $this->databaseFields[$idField],
            sprintf(':%s', $idField),
            $this->databaseTable,
            $this->databaseFields[$idSpecField],
            ':id'
        );

        self::$DB->query($query, [], $existVals);
        $total = self::$DB->fetch()->get('total');
        /*
         * After the fetch, so one check covers both halves of the read --
         * fetch() records its own failure on ->error and never clears one.
         *
         * A rejected read here answers "no, it does not exist", which is the
         * most expensive wrong answer this class can give: callers use
         * exists() to decide whether to CREATE, so an unreadable database
         * turns into a duplicate rather than an error.
         *
         * The contract is left alone -- callers expect a bool and there is no
         * catch here -- so the fault line is the whole of the fix. Making
         * this throw is a real change to a read contract and belongs in its
         * own decision, not smuggled into a logging fix.
         */
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s, %s',
                    _('Existence check failed'),
                    _('Table'),
                    $this->databaseTable,
                    _('Error'),
                    self::$DB->error,
                    _('answering "does not exist" for a read that never ran')
                )
            );
        }

        return (bool)$total > 0;
    }
    /**
     * Returns the distinct (all matching).
     *
     * @param string $field         the field to be distinct
     * @param array  $findWhere     what to find
     * @param string $whereOperator how to scan for where multiples
     * @param string $compare       comparitor
     *
     * @return int
     */
    public function distinct(
        $field = '',
        $findWhere = [],
        $whereOperator = 'AND',
        $compare = '='
    ) {
        if (empty($findWhere)) {
            $findWhere = [];
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        if (empty($compare)) {
            $compare = '=';
        }
        $whereArray = [];
        $countVals = [];
        // Monotonic index so every bound value gets a unique placeholder.
        // Reusing one shared :countVal collided across conditions and built
        // a malformed query for multi-condition lookups.
        $phIndex = 0;
        if (count($findWhere ?: []) > 0) {
            array_walk(
                $findWhere,
                function (
                    &$value,
                    $field
                ) use (
                    &$whereArray,
                    $compare,
                    &$countVals,
                    &$phIndex
                ) {
                    $field = trim($field);
                    if (is_array($value) && count($value ?: []) > 0) {
                        $inKeys = [];
                        foreach ((array) $value as &$val) {
                            $ph = sprintf('countVal%d', $phIndex++);
                            $inKeys[] = ':' . $ph;
                            $countVals[$ph] = $val;
                            unset($val);
                        }
                        $whereArray[] = sprintf(
                            '`%s`.`%s` IN (%s)',
                            $this->databaseTable,
                            $this->databaseFields[$field],
                            implode(',', $inKeys)
                        );
                    } elseif (null === $value) {
                        // GH-1245: a null filter asks for rows where the
                        // column holds nothing. Bound as a placeholder it
                        // becomes `col = NULL`, which is never true, so the
                        // query silently returns nothing -- and it now has
                        // callers, because the date columns that used to
                        // carry '0000-00-00 00:00:00' as their "not yet"
                        // sentinel hold NULL from schema step 344 on.
                        $whereArray[] = sprintf(
                            '`%s`.`%s` IS NULL',
                            $this->databaseTable,
                            $this->databaseFields[$field]
                        );
                    } else {
                        if (is_array($value)) {
                            $value = '';
                        }
                        $ph = sprintf('countVal%d', $phIndex++);
                        $countVals[$ph] = $value;
                        $whereArray[] = sprintf(
                            '`%s`.`%s`%s:%s',
                            $this->databaseTable,
                            $this->databaseFields[$field],
                            (
                                preg_match(
                                    '#%#',
                                    $value
                                ) ?
                                ' LIKE' :
                                $compare
                            ),
                            $ph
                        );
                    }
                    unset($value, $field);
                }
            );
        }
        $query = sprintf(
            $this->distinctTemplate,
            $this->databaseTable,
            $this->databaseFields[$field],
            $this->databaseTable,
            (
                count($whereArray) ?
                sprintf(
                    ' WHERE %s',
                    implode(
                        sprintf(
                            ' %s ',
                            $whereOperator
                        ),
                        $whereArray
                    )
                ) :
                ''
            )
        );

        self::$DB->query($query, [], $countVals);
        $total = self::$DB->fetch()->get('total');
        // Same as exists(): a rejected count answers 0, which reads as "there
        // are none" rather than "nobody asked". After the fetch, so one check
        // covers both halves. Contract unchanged.
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s, %s',
                    _('Count failed'),
                    _('Table'),
                    $this->databaseTable,
                    _('Error'),
                    self::$DB->error,
                    _('answering 0 for a read that never ran')
                )
            );
        }

        return (int)$total;
    }
    /**
     * Builds the CREATE TABLE for this manager's table, with a default on
     * every column that is optional.
     *
     * GH-1245. Schema::createTable() emits `NOT NULL` with no DEFAULT for
     * almost everything a caller does not spell out, which is the same defect
     * schema step 348 repairs on an existing install -- except that the step
     * can only repair a table that is already there. A plugin's createSql()
     * runs as step 0 of its own schema(), so a plugin installed AFTER the
     * migration gets a table built the old way: every optional column
     * mandatory, and under the server's own sql_mode any INSERT omitting one
     * fails with error 1364.
     *
     * WHICH COLUMNS KEEP THEIR TEETH. Not a judgment call, and not a list
     * kept by hand -- FOG already states it, and this class already holds the
     * statement: $databaseFieldsRequired, resolved up the model's inheritance
     * chain by the constructor. Three kinds of column are left bare:
     *
     *   - the primary key and the auto-increment column;
     *   - anything the model declares required;
     *   - anything whose name ends in ID, because an INSERT that forgets the
     *     row it hangs off should fail rather than make a silent orphan.
     *     Deliberately not gated on an integer type: a foreign key stored as
     *     text is no less a foreign key.
     *
     * That is deliberately the SAME rule schema step 348 applies, so a table
     * a plugin creates and a table the step migrated say the same thing. Two
     * installs of the same FOG should not have two different schemas.
     *
     * A default the caller passed explicitly always wins; this only fills in
     * where there was nothing.
     *
     * The signature mirrors Schema::createTable() exactly so a call site
     * changes by one token.
     *
     * @param string $name    What are we calling the table?
     * @param bool   $exists  If not exists?
     * @param array  $fields  The fields and names.
     * @param array  $types   The types for the fields.
     * @param array  $nulls   Which fields to have null or not.
     * @param array  $default Default values for field(s).
     * @param array  $unique  The unique fields.
     * @param string $engine  The db engine for the table.
     * @param string $charset The charset to use for the table.
     * @param string $prime   The primary field, if one.
     * @param string $autoin  The auto increment field.
     *
     * @return string
     */
    public function createTableSql(
        $name,
        $exists,
        $fields,
        $types,
        $nulls,
        $default,
        $unique,
        $engine = 'InnoDB',
        $charset = 'utf8',
        $prime = '',
        $autoin = ''
    ) {
        $keep = [];
        foreach ((array)$this->databaseFieldsRequired as $friendly) {
            if (isset($this->databaseFields[$friendly])) {
                $keep[strtolower($this->databaseFields[$friendly])] = true;
            }
        }
        if ($prime) {
            $keep[strtolower($prime)] = true;
        }
        if ($autoin) {
            $keep[strtolower($autoin)] = true;
        }
        foreach ((array)$fields as $i => $field) {
            $notNull = isset($nulls[$i]) && $nulls[$i] === false;
            $hasDefault = isset($default[$i])
                && false !== $default[$i]
                && null !== $default[$i]
                && '' !== $default[$i];
            if (!$notNull
                || $hasDefault
                || isset($keep[strtolower($field)])
                || preg_match('/ID$/', $field)
            ) {
                continue;
            }
            $fill = Schema::emptyDefaultFor($types[$i]);
            if (null === $fill) {
                // A TEXT or BLOB column on a server too old to carry a
                // default for one. Nothing to do and nothing broken by
                // leaving it: save() writes the column explicitly and
                // insertBatch() backfills it.
                continue;
            }
            $default[$i] = $fill;
        }

        return Schema::createTable(
            $name,
            $exists,
            $fields,
            $types,
            $nulls,
            $default,
            $unique,
            $engine,
            $charset,
            $prime,
            $autoin
        );
    }
    /**
     * Uninstalls the table.
     *
     * @return bool
     */
    public function uninstall()
    {
        $sql = Schema::dropTable($this->tablename);
        self::$DB->query($sql);
        // Declared @return bool and returned the PDODB object, which is
        // truthy however the DROP went. A plugin uninstall that left its
        // table in place reported success.
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s',
                    _('Table uninstall failed'),
                    _('Table'),
                    $this->tablename,
                    _('Error'),
                    self::$DB->error
                )
            );

            return false;
        }

        return true;
    }
    /**
     * Gets the columns for this item.
     *
     * Drops any field whose column is not actually on the table. A model can
     * name a column that a *later* schema migration creates, and every read
     * built from this list is an explicit column list -- so on a database
     * that has not been migrated yet the whole query dies with
     * "Unknown column" rather than merely missing a value. That is how a
     * pre-275 database took the storage node grid down over `ngmGraphColor`
     * (#927/#928), and how a plugin whose pSchema has fallen behind the core
     * schema takes its own pages down (#737) -- note the second case happens
     * with the *core* schema perfectly current, so this cannot be gated on
     * "is an upgrade pending".
     *
     * Reads tolerate drift; writes deliberately do not. save()/insertBatch()
     * still name every declared column, because silently dropping a column
     * from an INSERT or UPDATE is data loss, and the correct answer there is
     * to run the migration, not to write a short row.
     *
     * tableColumns() memoises per request, and returns an empty list when the
     * table is absent or information_schema is unreadable -- which must mean
     * "don't know", never "no columns", or this would strip the model bare.
     *
     * @return []
     */
    public function getColumns()
    {
        $have = DatabaseManager::tableColumns($this->databaseTable);
        if (!count($have)) {
            return $this->databaseFields;
        }
        return array_filter(
            $this->databaseFields,
            function ($column) use ($have) {
                return in_array(strtolower(trim($column)), $have, true);
            }
        );
    }
    /**
     * Gets the table for this item.
     *
     * @return []
     */
    public function getTable()
    {
        return $this->databaseTable;
    }
    /**
     * Gets the query string for this item.
     *
     * @return string
     */
    public function getQueryStr()
    {
        return $this->sqlQueryStr;
    }
    /**
     * Gets the Filter string for this item.
     *
     * @return string
     */
    public function getFilterStr()
    {
        return $this->sqlFilterStr;
    }
    /**
     * Gets the Total string for this item.
     *
     * @return string
     */
    public function getTotalStr()
    {
        return $this->sqlTotalStr;
    }
}
