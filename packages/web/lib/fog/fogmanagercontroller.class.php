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
        $this->childClass = preg_replace(
            '#_?Manager$#',
            '',
            get_class($this)
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
        $limit = '';
        if (!isset($request['start'])
            || $request['length'] == -1
        ) {
            return $limit;
        }
        $limit = "LIMIT "
            . intval($request['start'])
            . ", "
            . intval($request['length']);
        return $limit;
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
        $dbColumns = self::pluck($columns, 'db');
        $doColumns = self::pluck($columns, 'do');
        if (!isset($request['order']) || count($request['order'] ?: []) <= 0) {
            $columnIdx = array_search($orderby, $dtColumns);
            $columnOdx = array_search($orderby, $doColumns);
            if (false === $columnIdx && false === $columnOdx) {
                $columnIdx = array_search('id', $dtColumns);
                $order = 'ORDER BY `'
                    . $dbColumns[$columnIdx]
                    . '` ASC';
            } else if (false === $columnIdx) {
                $order = 'ORDER BY `'
                    . $doColumns[$columnOdx]
                    . '` ASC';
            }
            return $order;
        }
        $orderBy = [];
        for ($i = 0, $ien = count($request['order'] ?: []); $i < $ien; $i++) {
            // Convert the column index into the column data property
            $columnIdx = intval($request['order'][$i]['column']);
            $requestColumn = $request['columns'][$columnIdx];
            $columnIdx = array_search($requestColumn['data'], $dtColumns);
            $column = $columns[$columnIdx];
            if ($requestColumn['orderable'] != 'true'
                || (
                    isset($requestColumn['removeFromQuery'])
                    && $requestColumn['removeFromQuery']
                )
            ) {
                continue;
            }
            $dir = $request['order'][$i]['dir'] === 'asc' ?
                'ASC' :
                'DESC';
            if (!isset($column['db']) && isset($column['do'])) {
                $orderCol = $column['do'];
            } else {
                $orderCol = $column['db'];
            }
            $orderBy[] = '`'.$orderCol.'` '.$dir;
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
        $dtColumns = self::pluck($columns, 'dt');
        $doColumns = self::pluck($columns, 'do');
        if (isset($request['search']) && $request['search']['value'] != '') {
            $str = $request['search']['value'];
            for ($i=0, $ien = count($request['columns'] ?: []); $i < $ien; $i++) {
                $requestColumn = $request['columns'][$i];
                $columnIdx = array_search($requestColumn['data'], $dtColumns);
                $columnOdx = array_search($requestColumn['data'], $doColumns);
                $column = $columns[$columnIdx];
                if ($requestColumn['searchable'] != 'true'
                    || (!isset($column['db']) && !isset($column['do']))
                    || (isset($column ['removeFromQuery']) && $column['removeFromQuery'])
                ) {
                    continue;
                }
                if (!isset($column['db'])) {
                    continue;
                } else {
                    $columnSrch = $column['db'];
                }
                $binding = self::bind($bindings, '%'.$str.'%', PDO::PARAM_STR);
                $globalSearch[] = "`".$columnSrch."` LIKE ".$binding;
            }
        }
        // Individual column filtering
        if (isset($request['columns'])) {
            for ($i = 0, $ien = count($request['columns'] ?: []); $i < $ien; $i++) {
                $requestColumn = $request['columns'][$i];
                $columnIdx = array_search($requestColumn['data'], $dtColumns);
                $column = $columns[$columnIdx];
                $str = $requestColumn['search']['value'];
                if ($requestColumn['searchable'] != 'true'
                    || $str == ''
                    || (!isset($column['db']) && !isset($column['do']))
                    || $column['removeFromQuery']
                ) {
                    continue;
                }
                if (!isset($column['db'])) {
                    continue;
                } else {
                    $columnSrch = $column['db'];
                }
                $binding = self::bind(
                    $bindings,
                    '%' . $str . '%',
                    PDO::PARAM_STR
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
        $db = DatabaseManager::getLink();
        $bindings = [];
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
        // Build the actual string itself.
        $sql_query = sprintf(
            $sqlstr,
            implode('`,`', self::pluck($columns, 'db')),
            $table,
            $where,
            $order,
            $limit
        );
        // Main query to actually get the data
        $data = self::sqlexec($db, $bindings, $sql_query);
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
        );
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
            'data' => self::dataOutput($columns, $data),
            //'sql_query' => $sql_query,
            //'filter_query' => $filter_query,
            //'total_query' => $total_query,
            //'request' => $request,
            //'columns' => $columns,
            //'order' => $order
        ];
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
        $orderby = 'name'
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
        // Main query to actually get the data
        $data = self::sqlexec($db, $bindings, $sql_query);
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
            'data' => self::dataOutput($columns, $data),
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
        } catch (PDOException $e) {
            self::fatal("An SQL error occurred: ".$e->getMessage() . "SQL: $sql");
        }
        // Return all
        return $stmt->fetchAll(PDO::FETCH_BOTH);
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
     * @param array $fields the fields to insert into
     * @param array $values the values to insert
     *
     * @return array
     */
    public function insertBatch($fields, $values)
    {
        $fieldlength = count($fields ?: []);
        $valuelength = count($values ?: []);
        if ($fieldlength < 1) {
            throw new Exception(_('No fields passed'));
        }
        if ($valuelength < 1) {
            throw new Exception(_('No values passed'));
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
                throw new Exception(_('No data to insert'));
            }
            $query = sprintf(
                $this->insertBatchTemplate,
                $this->databaseTable,
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
    private function perform_update($findWhere, $whereOperator, $insertData) {
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
        $useKey = 'id'
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
            Route::listem(
                $this->childClass,
                $find,
                true
            );
        } else {
            Route::listem($this->childClass, false, true);
        }
        $Items = json_decode(
            Route::getData()
        );
        foreach ($Items->data as &$Item) {
            if (isset($Item->isEnabled) && !$Item->isEnabled) {
                continue;
            }
            echo '<option value="'
                . $Item->{$useKey}
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
                . $Item->name
                . ' - (' . $Item->id . ')'
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
            . $elementName
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

        if (!in_array('name', array_keys($this->databaseFields))) {
            $idField = 'id';
        }

        $query = sprintf(
            $this->existsQueryTemplate,
            $this->databaseTable,
            $this->databaseFields[$idField],
            $this->databaseTable,
            $this->databaseTable,
            $this->databaseFields[$idField],
            sprintf(':%s', $idField),
            $this->databaseTable,
            $this->databaseFields[$idField],
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
        $countVals = $countKeys = [];
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
                    &$countKeys
                ) {
                    $field = trim($field);
                    if (is_array($value) && count($value ?: []) > 0) {
                        foreach ((array) $value as $index => &$val) {
                            $countKeys[] = sprintf(':countVal%d', $index);
                            $countVals[sprintf('countVal%d', $index)] = $val;
                            unset($val);
                        }
                        $whereArray[] = sprintf(
                            '`%s`.`%s` IN (%s)',
                            $this->databaseTable,
                            $this->databaseFields[$field],
                            implode(',', $countKeys)
                        );
                    } else {
                        if (is_array($value)) {
                            $value = '';
                        }
                        $countVals['countVal'] = $value;
                        $whereArray[] = sprintf(
                            '`%s`.`%s`%s:countVal',
                            $this->databaseTable,
                            $this->databaseFields[$field],
                            (
                                preg_match(
                                    '#%#',
                                    $value
                                ) ?
                                ' LIKE' :
                                $compare
                            )
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
                (isset($whereArray) && count($whereArray)) ?
                sprintf(
                    ' WHERE %s%s',
                    implode(
                        sprintf(
                            ' %s ',
                            $whereOperator
                        ),
                        (array) $whereArray
                    ),
                    (
                        (isset($isEnabled) && $isEnabled) ?
                        sprintf(
                            ' AND %s',
                            $isEnabled
                        ) :
                        ''
                    )
                ) :
                (
                    $isEnabled ?
                    sprintf(
                        ' WHERE %s',
                        $isEnabled
                    ) :
                    ''
                )
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
     * @return []
     */
    public function getColumns()
    {
        return $this->databaseFields;
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
