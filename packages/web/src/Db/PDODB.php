<?php
/**
 * PDODB, the database connector.
 *
 * PHP version 7.4+
 *
 * This is what communicates between FOG and the Database.
 *
 * @category PDODB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Db;

/**
 * PDODB, the database connector.
 *
 * This is what communicates between FOG and the Database.
 *
 * @category PDODB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PDODB extends DatabaseManager
{
    /**
     * If true, query() will throw on error instead of swallowing it.
     * Default false to preserve legacy behavior.
     *
     * Toggle at runtime via:
     *   PDODB::$throwOnQueryError = true;
     *
     * @var bool
     */
    public static $throwOnQueryError = false;

    /**
     * Stores last errorcode for query.
     *
     * @var bool|int
     */
    public $errorCode;

    /**
     * Stores last error for query.
     *
     * @var bool|string
     */
    public $error;

    /**
     * Stores last errorInfo() array for the most recent statement.
     *
     * @var array
     */
    public $lastErrorInfo = array();

    /**
     * Stores the last SQL query (after vsprintf substitution).
     *
     * @var string
     */
    public $lastSql = '';

    /**
     * Stores the last bound params for debugging.
     *
     * @var array
     */
    public $lastParams = array();

    /**
     * Stores the current connection
     *
     * @var resource
     */
    private static $_link;

    /**
     * Stores the query string
     *
     * @var string
     */
    private static $_query;

    /**
     * Stores the query results
     *
     * @var object
     */
    private static $_queryResult;

    /**
     * Stores the returned results
     *
     * @var mixed
     */
    private static $_result;

    /**
     * Stores the database name
     *
     * @var string
     */
    private static $_dbName;

    /**
     * Stores last affected rows (since statements may be closed/nulled later)
     *
     * @var int
     */
    private static $_lastAffectedRows = 0;

    /**
     * Stores the last connection error message (e.g. the SQLSTATE string
     * returned by PDO when the database cannot be reached). Used to surface
     * a meaningful diagnostic when no link can be established.
     *
     * @var string
     */
    private static $_connectError = '';

    /**
     * Options for the connection
     *
     * @var array
     */
    private static $_options = [
        \PDO::ATTR_PERSISTENT => false,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,

        // Keep unbuffered by default (what you had).
        // Cursor closing below prevents SQLSTATE[HY000] 2014 in most cases.
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
    ];

    /**
     * Initializes the PDODB class
     *
     * @param array $options any custom options
     *
     * @return void|PDODB
     * @throws PDOException
     */
    public function __construct($options = [])
    {
        ignore_user_abort(true);
        set_time_limit(0);
        if (self::$_link) {
            return $this;
        }
        parent::__construct();
        try {
            if (count($options ?: []) > 0) {
                self::$_options = $options;
            }
            self::$_dbName = DATABASE_NAME;
            if (!$this->_connect()) {
                throw new \PDOException(
                    _('Failed to connect')
                );
            }
        } catch (\PDOException $e) {
            $msg = sprintf(
                '%s %s: %s, %s: %s',
                _('Failed to'),
                __FUNCTION__,
                $e->getMessage(),
                _('SQL Error'),
                $this->sqlerror()
            );
            self::debug($msg);
            self::error($msg);

            if (self::$throwOnQueryError) {
                throw $e;
            }
        }
    }

    /**
     * Uninitializes the PDODB Class
     *
     * @return void
     */
    public function __destruct()
    {
        self::$_result = null;

        if (self::$_queryResult instanceof \PDOStatement) {
            try {
                self::$_queryResult->closeCursor();
            } catch (\Exception $e) {
                // ignore
            }
        }
        self::$_queryResult = null;

        if (!self::$_link) {
            return;
        }
        self::$_link = null;
    }

    /**
     * The default read deadline, in seconds, for a connection this class
     * opens.
     *
     * Five minutes: far above any query FOG issues in a request, far below
     * mysqlnd's own 86400. See _boundReadTimeout() for why it exists.
     *
     * @var int
     */
    const READ_TIMEOUT = 300;

    /**
     * Puts a ceiling on how long a read from MySQL may block.
     *
     * mysqlnd defaults net_read_timeout to 86400 -- a full day. If the server
     * stops answering on an established connection (a hang, a dropped
     * session, a restart leaving the socket half-open) PHP parks in read()
     * for that long. Neither backstop people assume covers this actually
     * does: max_execution_time does not count time in a blocking syscall
     * (measured: still blocked at 40s with the limit set to 5), and php-fpm's
     * request_terminate_timeout defaults to disabled. A stuck worker is held
     * from a finite pool, so enough of them and the UI and API stop serving
     * entirely. Refs #944.
     *
     * PDO::ATTR_TIMEOUT is deliberately not used: under mysqlnd it maps to
     * MYSQL_OPT_CONNECT_TIMEOUT and bounds only the TCP connect, which in
     * this failure succeeds. The read is what hangs.
     *
     * Only applied when the value is still mysqlnd's default, so an explicit
     * choice always wins -- the daemons set their own before bootstrapping
     * (packages/service/lib/service_lib.php), a migration lifts it while it
     * rebuilds tables (DatabaseManager::_convertEngine), and an admin's
     * php.ini is left alone.
     *
     * @return void
     */
    private static function _boundReadTimeout()
    {
        if ((int)ini_get('mysqlnd.net_read_timeout') === 86400) {
            ini_set('mysqlnd.net_read_timeout', (string)self::READ_TIMEOUT);
        }
    }

    /**
     * Connects the database as needed.
     *
     * @param bool $dbexists does db exist
     *
     * @return object
     * @throws PDOException
     */
    private function _connect($dbexists = true)
    {
        try {
            if (self::$_link) {
                return $this;
            }
            self::_boundReadTimeout();
            $type = DATABASE_TYPE;
            $host = preg_replace('#p:#i', '', DATABASE_HOST);
            $user = DATABASE_USERNAME;
            $pass = DATABASE_PASSWORD;
            $dsn = sprintf(
                '%s:host=%s;dbname=%s;charset=utf8',
                $type,
                $host,
                self::$_dbName
            );
            if (!$dbexists) {
                $dsn = sprintf(
                    '%s:host=%s;charset=utf8',
                    $type,
                    $host
                );
            }
            self::$_link = new \PDO(
                $dsn,
                $user,
                $pass,
                self::$_options
            );
            if (self::$_link && !self::currentDb($this)) {
                if (preg_match('#schema#', self::$querystring)) {
                    self::redirect('../management/index.php?node=schema');
                }
            }
            /*
             * GH-1245: no `SET SESSION sql_mode=''` here.
             *
             * That line arrived in 13661edb (May 2016) as "try to set sql_mode
             * to non-strict which should allow 5.7 mysql to operate", and it
             * shipped with a TARGETED mode commented out one line above it --
             * one that kept STRICT_TRANS_TABLES. So even then the intent was
             * not to disable validation; the blanket clear was the fallback.
             *
             * It stayed for nine years and meant every statement FOG issued
             * ran with the server's checks off: truncations, out-of-range
             * numerics and invalid enum members were all silently coerced and
             * reported only as warnings nothing reads. That is how 83 of 86
             * hosts on the maintainer's own server came to hold a zero
             * `hostLastDeploy` on a MariaDB configured with
             * STRICT_TRANS_TABLES, and how the ENUM error value got into 27
             * columns.
             *
             * What actually needed fixing was FOGController::save(), which
             * wrote '' for every unset optional field regardless of the
             * column's type. emptyValueFor() now writes the value the server
             * was coercing to anyway, so nothing here depends on the checks
             * being off. Schema steps 344 and 345 repair the rows that were
             * written while they were.
             */
        } catch (\PDOException $e) {
            if ($dbexists) {
                self::$_link = false;
                $this->_connect(false);
            } else {
                self::$_connectError = $e->getMessage();
                $msg = sprintf(
                    '%s %s: %s: %s %s: %s',
                    _('Failed to'),
                    __FUNCTION__,
                    _('Error'),
                    $e->getMessage(),
                    _('Error Message'),
                    $this->sqlerror()
                );
                self::debug($msg);
                self::error($msg);

                if (self::$throwOnQueryError) {
                    throw $e;
                }
            }
        }
        return $this;
    }

    /**
     * Gets the current database.
     *
     * @param object $main Static method so we need the main element.
     *
     * @return object
     * @throws PDOException
     */
    public static function currentDb($main)
    {
        try {
            if (!self::$_link) {
                throw new \PDOException(
                    _('No link established to the database')
                );
            }
            if (!isset(self::$_dbName) || !self::$_dbName) {
                self::$_dbName = DATABASE_NAME;
            }
            $sql = sprintf(
                'USE `%s`',
                self::$_dbName
            );
            $dbTest = self::$_link->query($sql);
            if (false === $dbTest) {
                self::$_dbName = false;
            }
        } catch (\PDOException $e) {
            $msg = sprintf(
                '%s %s: %s: %s %s: %s',
                _('Failed to'),
                __FUNCTION__,
                _('Error'),
                $e->getMessage(),
                _('Error Message'),
                $main->sqlerror()
            );
            self::$_dbName = false;
            self::debug($msg);
            self::error($msg);
        }
        return $main;
    }

    /**
     * The query method.
     *
     * @param string $sql       the sql statement to query
     * @param array  $data      the data as needed
     * @param array  $paramvals the bound param variables
     *
     * @return PDODB
     * @throws PDOException
     */
    public function query(
        $sql,
        $data = [],
        $paramvals = []
    ) {
        // Save for diagnostics even if we fail early
        $this->lastSql = $sql;
        $this->lastParams = $paramvals;
        $this->lastErrorInfo = array();
        self::$_lastAffectedRows = 0;

        try {
            if (!self::$_link) {
                throw new \PDOException($this->sqlerror());
            }

            // Prevent "Cannot execute queries while other unbuffered queries are active"
            if (self::$_queryResult instanceof \PDOStatement) {
                try {
                    self::$_queryResult->closeCursor();
                } catch (\Exception $e) {
                    // best effort
                }
            }
            self::$_queryResult = null;

            if (isset($data) && !is_array($data)) {
                $data = [$data];
            }
            if (count($data ?: [])) {
                $sql = vsprintf($sql, $data);
            }
            if (!$sql) {
                throw new \PDOException(_('No query passed'));
            }

            self::$_query = $sql;
            $this->lastSql = $sql;

            self::_prepare();

            $ok = self::_execute($paramvals);
            if ($ok === false) {
                // execute() can return false without throwing (driver-dependent edge cases)
                $info = (self::$_queryResult instanceof \PDOStatement)
                    ? self::$_queryResult->errorInfo()
                    : array(null, null, _('Unknown DB error'));

                $this->lastErrorInfo = $info;
                $this->errorCode = isset($info[1]) ? $info[1] : false;

                $msg = sprintf(
                    "Failed to %s: %s\nSQL: %s\nParams: %s\nErrorInfo: %s\nDebug: %s",
                    __FUNCTION__,
                    (isset($info[2]) ? $info[2] : _('Unknown DB error')),
                    self::$_query,
                    print_r($paramvals, true),
                    print_r($info, true),
                    self::_debugDumpParams()
                );

                throw new \PDOException($msg);
            }

            // Capture affected rows while statement is alive
            if (self::$_queryResult instanceof \PDOStatement) {
                self::$_lastAffectedRows = (int) self::$_queryResult->rowCount();
            }

            if (!self::$_dbName) {
                self::currentDb($this);
            }
            if (!self::$_dbName) {
                throw new \PDOException(
                    _('No database to work off')
                );
            }

            $this->error = false;
        } catch (\PDOException $e) {
            // Capture PDO errorInfo if possible
            if (self::$_queryResult instanceof \PDOStatement) {
                $this->lastErrorInfo = self::$_queryResult->errorInfo();
                if (isset($this->lastErrorInfo[1])) {
                    $this->errorCode = $this->lastErrorInfo[1];
                }
            }

            $msg = sprintf(
                "Failed to %s: %s: %s %s: %s\nSQL: %s\nParams: %s\nErrorInfo: %s\nDebug: %s",
                __FUNCTION__,
                _('Error'),
                $e->getMessage(),
                _('Error Message'),
                $this->sqlerror(),
                self::$_query,
                print_r($paramvals, true),
                print_r($this->lastErrorInfo, true),
                self::_debugDumpParams()
            );

            self::debug(self::$_query);
            self::error($msg);

            $this->error = $msg;

            if (self::$throwOnQueryError) {
                throw $e;
            }
        }

        return $this;
    }

    /**
     * Fetches the information into a statement object to paarse.
     *
     * @param int    $type      the type of fetching PDO int.
     * @param string $fetchType the type in function calling
     * @param mixed  $params    any additional parameters needed.
     *
     * @return object
     * @throws PDOException
     */
    public function fetch(
        $type = \PDO::FETCH_ASSOC,
        $fetchType = 'fetch_assoc',
        $params = false
    ) {
        try {
            self::$_result = [];
            if (empty($type)) {
                $type = \PDO::FETCH_ASSOC;
            }
            if (empty($fetchType)) {
                $fetchType = 'fetch_assoc';
            }
            if (is_bool(self::$_queryResult)) {
                self::$_result = self::$_queryResult;
            } elseif (empty(self::$_queryResult)) {
                throw new \PDOException(
                    _('No query result, use query() first')
                );
            } else {
                $fetchType = strtolower($fetchType);
                if ($fetchType === 'fetch_all') {
                    self::_all($type);
                } else {
                    self::_single($type);
                }
            }
        } catch (\PDOException $e) {
            $msg = sprintf(
                '%s %s: %s: %s %s: %s',
                _('Failed to'),
                __FUNCTION__,
                _('Error'),
                $e->getMessage(),
                _('Error Message'),
                $this->sqlerror()
            );
            self::$_result = false;
            /*
             * $msg used to be built here and dropped on the floor: a failed
             * fetch set no ->error, logged nothing, and left $_result false
             * -- so get() answered an empty set and the caller could not tell
             * "the read failed" from "there are no rows". That is the same
             * defect GH-1257 fixed in FOGController::save() and load(), one
             * layer down, and it is why those checks alone were not enough:
             * they sat between query() and fetch(), covering a rejected
             * STATEMENT and missing a rejected READ of its rows.
             *
             * Only ever ADDS a failure, never overwrites one. query() owns
             * clearing ->error -- it runs immediately before every fetch()
             * and always sets it to false or to a message -- so when a fetch
             * fails BECAUSE the query did ("No query result, use query()
             * first"), the guard keeps the original cause rather than
             * replacing it with the symptom.
             *
             * Not logged from here. The callers know which class and table
             * they were reading, and this does not; a line naming neither is
             * worse than the caller's, and two lines per failure is noise.
             */
            if (!$this->error) {
                $this->error = $msg;
            }

            if (self::$throwOnQueryError) {
                throw $e;
            }
        }

        // Close cursor to avoid unbuffered-query issues later.
        if (self::$_queryResult instanceof \PDOStatement) {
            try {
                self::$_queryResult->closeCursor();
            } catch (\Exception $e) {
                // ignore
            }
        }
        self::$_queryResult = null;

        return $this;
    }

    /**
     * Get's the relevante items or item as needed.
     *
     * @param string $field the field to get
     *
     * @throws PDOException
     * @return mixed
     */
    public function get($field = '')
    {
        try {
            if (!self::$_link) {
                throw new \Exception(
                    _('No connection to the database')
                );
            }
            if (self::$_result === false) {
                throw new \Exception(
                    _('No data returned')
                );
            }
            if (self::$_result === true) {
                return self::$_result;
            }
            $result = [];
            if ($field) {
                foreach ((array)$field as &$key) {
                    $key = trim($key);
                    if (array_key_exists($key, (array)self::$_result)) {
                        return self::$_result[$key];
                    }
                    foreach ((array)self::$_result as &$value) {
                        if (array_key_exists($key, (array)$value)) {
                            $result[] = $value[$key];
                        }
                    }
                }
            }
            if (count($result ?: [])) {
                return $result;
            }
        } catch (\Exception $e) {
            $msg = sprintf(
                '%s %s: %s: %s %s: %s',
                _('Failed to'),
                __FUNCTION__,
                _('Error'),
                $e->getMessage(),
                _('Error Message'),
                $this->sqlerror()
            );
        }
        return self::$_result;
    }

    /**
     * Returns error of the last sql command
     *
     * @return string
     */
    public function sqlerror()
    {
        $msg = '';
        if (isset(self::$_link) && self::$_link) {
            if (isset(self::$_queryResult)
                && self::$_queryResult instanceof \PDOStatement
                && self::$_queryResult->errorCode()
            ) {
                $errCode = self::$_queryResult->errorCode();
                $errInfo = self::$_queryResult->errorInfo();
                $this->errorCode = $errInfo[1];

                $this->lastErrorInfo = $errInfo;
            }
            if (isset($errCode) && $errCode !== '00000') {
                $msg = sprintf(
                    '%s: %s, %s: %s, %s: %s',
                    _('Error Code'),
                    json_encode($errCode),
                    _('Error Message'),
                    json_encode($errInfo),
                    _('Debug'),
                    self::_debugDumpParams()
                );
            }
        } else {
            $msg = _('Cannot connect to database');
            self::$_link = false;
        }
        self::debug($msg);
        self::error($msg);
        return $msg;
    }

    /**
     * Returns the last insert ID
     *
     * @return int
     */
    public function insertId()
    {
        if (!self::$_link) {
            $this->sqlerror();
        }

        // Do NOT run a new SQL query here; it can trigger SQLSTATE[HY000] 2014
        // when unbuffered queries are still active. Use PDO's built-in method.
        return (int) self::$_link->lastInsertId();
    }

    /**
     * Returns the field count
     *
     * @return int
     */
    public function fieldCount()
    {
        if (self::$_queryResult instanceof \PDOStatement) {
            return self::$_queryResult->columnCount();
        }
        return 0;
    }

    /**
     * Returns affected rows
     *
     * @return int
     */
    public function affectedRows()
    {
        return (int) self::$_lastAffectedRows;
    }

    /**
     * Escapes data passed
     *
     * @param mixed $data the data to escape
     *
     * @return mixed
     */
    public function escape($data)
    {
        return $this->sanitize($data);
    }

    /**
     * Cleans data passed
     *
     * @param mixed $data the data to clean
     *
     * @return mixed
     */
    private function _clean($data)
    {
        $data = trim($data);
        if (!self::$_link) {
            return $data;
        }
        return self::$_link->quote($data);
    }

    /**
     * Santizes data passed
     *
     * @param mixed $data the data to be sanitized
     *
     * @return mixed
     */
    public function sanitize($data)
    {
        if (!is_array($data)) {
            return $this->_clean($data);
        }
        foreach ($data as $key => &$val) {
            if (is_array($val)) {
                foreach ($val as $i => $v) {
                    $data[$this->_clean($key)][$i] = $this->_clean($v);
                }
            } else {
                $data[$this->_clean($key)] = $this->_clean($val);
            }
        }
        return $data;
    }

    /**
     * Returns the database name
     *
     * @return string
     */
    public function dbName()
    {
        return self::$_dbName;
    }

    /**
     * Returns the last database connection error message, sanitized for
     * display. The SQLSTATE code and human-readable reason are preserved;
     * single-quoted identifiers (database user, host and name, which PDO
     * embeds in access-denied style messages) are redacted so the message
     * is safe to surface to any caller.
     *
     * @return string
     */
    public function connectError()
    {
        if (!self::$_connectError) {
            return '';
        }
        return preg_replace(
            "/'[^']*'/",
            "'***'",
            self::$_connectError
        );
    }

    /**
     * Returns the primary link
     *
     * @return object
     */
    public function link()
    {
        $this->_connect(true);
        return self::$_link;
    }

    /**
     * Returns the DB link object
     *
     * @return boolean
     */
    public function ping()
    {
        try {
            if (self::$_link) {
                return self::$_link->query('SELECT 1') ? true : false;
            }
        } catch (\PDOException $e) {
            self::debug($e->getMessage());
            self::error($e->getMessage());
        }
        return (self::$_link = false);
    }

    /**
     * Returns the item whatever this is
     * Could be database manager or pdodb.
     *
     * @return object
     */
    public function returnThis()
    {
        return $this;
    }

    /**
     * Dump PDO specific debug information
     *
     * @return string
     */
    private static function _debugDumpParams()
    {
        if (self::$_queryResult instanceof \PDOStatement) {
            ob_start();
            self::$_queryResult->debugDumpParams();
            return ob_get_clean();
        }
        return '';
    }

    /**
     * Executes the query.
     *
     * @param array $paramvals the parameters if any
     *
     * @return bool
     */
    private static function _execute($paramvals = [])
    {
        if (count($paramvals ?: []) > 0) {
            foreach ((array)$paramvals as $param => &$value) {
                if (is_array($value)) {
                    self::_bind($param, $value[0], $value[1]);
                } else {
                    self::_bind($param, $value);
                }
                unset($value);
            }
        }
        return self::$_queryResult->execute();
    }

    /**
     * Fetch all items
     *
     * @param int $type the type to fetch
     *
     * @return void
     */
    private static function _all($type = \PDO::FETCH_ASSOC)
    {
        self::$_result = self::$_queryResult->fetchAll($type);
    }

    /**
     * Fetch single item
     *
     * @param int $type the type to fetch
     *
     * @return void
     */
    private static function _single($type = \PDO::FETCH_ASSOC)
    {
        self::$_result = self::$_queryResult->fetch($type);
    }

    /**
     * Prepare the query
     *
     * @return void
     */
    private static function _prepare()
    {
        self::$_queryResult = self::$_link->prepare(self::$_query);
    }

    /**
     * Bind the values as needed
     *
     * @param string $param the parameter
     * @param mixed  $value the value to bind
     * @param int    $type  the way to bind if needed
     *
     * @return void
     */
    private static function _bind($param, $value, $type = null)
    {
        if (is_null($type)) {
            $type = \PDO::PARAM_STR;
        }
        /*
         * A PHP boolean bound as a string is the string cast of it, and
         * (string)false is ''. Every caller reaches this method with the
         * default PDO::PARAM_STR, so `->set('shutdown', $action ==
         * 'shutdown')` -- an ordinary comparison, and how the snapin pages
         * have always spelled it -- stored '' into `snapins`.`sShutdown`,
         * an enum('0','1'). That is error 1265, "Data truncated for column
         * 'sShutdown' at row 1", on any server with STRICT_TRANS_TABLES.
         *
         * It is the same defect as GH-1245 arriving by a different door.
         * save()'s emptyValueFor() only recognises null and '' as empty, so
         * a boolean walks straight past it, and the manager UPDATE path
         * (HostManager::update() writing `hosts`.`hostInfoLock` from
         * ->set('tokenlock', false) at the end of every imaging task) never
         * went through save() at all. Normalising here is the only place
         * that covers save(), insertBatch(), the manager builders and
         * hand-written queries at once.
         *
         * '0'/'1' rather than PDO::PARAM_BOOL: bound as an integer, 0
         * against an ENUM is an *index*, and index 0 is the error value --
         * the same trap Schema::defaultLiteral() exists for. As strings
         * they are literal enum members, and a numeric column coerces them
         * to 0/1. Readers are unaffected either way; '0' is falsey in PHP
         * exactly as '' was.
         *
         * A caller that passes an explicit type is left alone -- it has
         * said what it means.
         */
        if (is_bool($value) && $type === \PDO::PARAM_STR) {
            $value = $value ? '1' : '0';
        }
        // bindValue() copies the value immediately; bindParam() would bind by
        // reference to a local variable that goes out of scope before execute().
        self::$_queryResult->bindValue($param, $value, $type);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\PDODB', 'PDODB');
