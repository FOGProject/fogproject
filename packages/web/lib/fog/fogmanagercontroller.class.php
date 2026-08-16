<?php
/**
 * FOG Manager Controller, main object mass getter.
 *
 * PHP version 5
 *
 * @category FOGManagerController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
        // hundreds -- but userTracking, imagingLog and the other append-only
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
     * @param array $request  Data sent to server by DataTables
     * @param array $columns  Column information array
     * @param array $bindings Array of values for PDO bindings, used in the
     *                        sqlexec() function
     *
     * @return string SQL where clause
     */
    public static function filter($request, $columns, &$bindings)
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
        if ($where !== '') {
            $where = 'WHERE '.$where;
        }
        return $where;
    }
    /**
     * Perform the SQL queries needed for an server-side processing requested,
     * utilising the helper functions of this class, limit(), order() and
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
        $where = self::filter($request, $columns, $bindings);
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
            'data' => $countOnly ? [] : self::dataOutput($columns, $data),
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
        $affectedRows = 0;
        $vals = [];
        $insertVals = [];
        $values = array_chunk($values, 500);
        foreach ((array) $values as $ind => &$v) {
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
                    $val = trim($val);
                    $insertVals[$key] = $val;
                    unset($val);
                }
                $vals[] = sprintf('(%s)', implode(',', (array) $insertKeys));
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
            $value = trim($value);
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
                        $val = trim($val);
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
                    $value = trim($value);
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

        return (bool) self::$DB->query($query, [], $queryVals);
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
                . Initiator::e($Item->{$useKey})
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
                . '>'
                . Initiator::e($Item->name)
                . ' - (' . Initiator::e($Item->id) . ')'
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

        return (bool)self::$DB
            ->query($query, [], $existVals)
            ->fetch()
            ->get('total') > 0;
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

        return (int)self::$DB
            ->query($query, [], $countVals)
            ->fetch()
            ->get('total');
    }
    /**
     * Uninstalls the table.
     *
     * @return bool
     */
    public function uninstall()
    {
        $sql = Schema::dropTable($this->tablename);
        return self::$DB->query($sql);
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
