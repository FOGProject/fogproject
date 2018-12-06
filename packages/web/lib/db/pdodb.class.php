<?php
/**
 * PDODB, the database connector.
 *
 * PHP version 5
 *
 * This is what communicates between FOG and the Database.
 *
 * @category PDODB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
     * Options for the connection
     *
     * @var array
     */
    private static $_options = array(
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
    );
    /**
     * Initializes the PDODB class
     *
     * @param array $options any custom options
     *
     * @throws PDOException
     * @return void
     */
    public function __construct($options = array())
    {
        ignore_user_abort(true);
        set_time_limit(0);
        if (self::$_link) {
            return $this;
        }
        parent::__construct();
        try {
            if (count($options) > 0) {
                self::$_options = $options;
            }
            self::$_dbName = DATABASE_NAME;
            if (!$this->_connect()) {
                throw new PDOException(
                    _('Failed to connect')
                );
            }
        } catch (PDOException $e) {
            $msg = sprintf(
                '%s %s: %s, %s: %s',
                _('Failed to'),
                __FUNCTION__,
                $e->getMessage(),
                _('SQL Error'),
                $this->sqlerror()
            );
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
        self::$_queryResult = null;
        if (!self::$_link) {
            return;
        }
        self::$_link = null;
    }
    /**
     * Connects the database as needed.
     *
     * @param bool $dbexists does db exist
     * @param bool $always   always test connection.
     *
     * @throws PDOException
     * @return object
     */
    private function _connect($dbexists = true)
    {
        try {
            if (self::$_link) {
                return $this;
            }
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
            self::$_link = new PDO(
                $dsn,
                $user,
                $pass,
                self::$_options
            );
            if (self::$_link && !self::currentDb($this)) {
                if (preg_match('#schema#', self::$querystring)) {
                    self::redirect('?node=schema');
                }
            }
            self::query("SET SESSION sql_mode=''");
        } catch (PDOException $e) {
            if ($dbexists) {
                self::$_link = false;
                $this->_connect(false);
            } else {
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
        }
        return $this;
    }
    /**
     * Gets the current database.
     *
     * @param object $main Static method so we need the main element.
     *
     * @throws PDOException
     * @return bool|object
     */
    public static function currentDb(&$main)
    {
        try {
            if (!self::$_link) {
                throw new PDOException(
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
        } catch (PDOException $e) {
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
     * @throws PDOException
     * @return object|bool
     */
    public function query(
        $sql,
        $data = array(),
        $paramvals = array()
    ) {
        try {
            if (!self::$_link) {
                throw new PDOException($this->sqlerror());
            }
            self::$_queryResult = null;
            if (isset($data) && !is_array($data)) {
                $data = array($data);
            }
            if (count($data)) {
                $sql = vsprintf($sql, $data);
            }
            if (!$sql) {
                throw new PDOException(
                    _('No query passed')
                );
            }
            self::$_query = $sql;
            self::_prepare();
            self::_execute($paramvals);
            $this->info($sql);
            if (!self::$_dbName) {
                self::currentDb($this);
            }
            if (!self::$_dbName) {
                throw new PDOException(
                    _('No database to work off')
                );
            }
            $this->error = false;
        } catch (PDOException $e) {
            $msg = sprintf(
                '%s %s: %s: %s %s: %s',
                _('Failed to'),
                __FUNCTION__,
                _('Error'),
                $e->getMessage(),
                _('Error Message'),
                $this->sqlerror()
            );
            if (stripos($e->getMessage(), _('no database to'))) {
                $msg = sprintf(
                    '%s %s',
                    $msg,
                    self::_debugDumpParams()
                );
            }
            $this->error = $msg;
        }
        return $this;
    }
    /**
     * Fetches the information into a statement object to paarse.
     *
     * @param int    $type      the type of fetching PDO int.
     * @param string $fetchType the type in function calling
     * @param mixed  $params    any additional parameteres needed.
     *
     * @throws PDOException
     * @return object
     */
    public function fetch(
        $type = PDO::FETCH_ASSOC,
        $fetchType = 'fetch_assoc',
        $params = false
    ) {
        try {
            self::$_result = array();
            if (empty($type)) {
                $type = PDO::FETCH_ASSOC;
            }
            if (empty($fetchType)) {
                $fetchType = 'fetch_assoc';
            }
            if (is_bool(self::$_queryResult)) {
                self::$_result = self::$_queryResult;
            } elseif (empty(self::$_queryResult)) {
                throw new PDOException(
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
        } catch (PDOException $e) {
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
                throw new Exception(
                    _('No connection to the database')
                );
            }
            if (self::$_result === false) {
                throw new Exception(
                    _('No data returned')
                );
            }
            if (self::$_result === true) {
                return self::$_result;
            }
            $result = array();
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
            if (count($result)) {
                return $result;
            }
        } catch (Exception $e) {
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
        if (self::$_link) {
            if (self::$_queryResult instanceof PDOStatement
                && self::$_queryResult->errorCode()
            ) {
                $errCode = self::$_queryResult->errorCode();
                $errInfo = self::$_queryResult->errorInfo();
                $this->errorCode = $errInfo[1];
            }
            if ($errCode !== '00000') {
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
        return $msg;
    }
    /**
     * Returns the last insert ID
     *
     * @return int
     */
    public function insertId()
    {
        if (is_bool(self::$_link)) {
            if (!self::$_link) {
                die(_('Database connection unavailable'));
            }
        }
        return self::$_link->lastInsertId();
    }
    /**
     * Returns the field count
     *
     * @return int
     */
    public function fieldCount()
    {
        if (self::$_queryResult instanceof PDOStatement) {
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
        if (self::$_queryResult instanceof PDOStatement) {
            return self::$_queryResult->rowCount();
        }
        return 0;
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
        $eData = Initiator::sanitizeItems(
            $data
        );
        if (!self::$_link) {
            return $eData;
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
     * Returns the primary link
     *
     * @return object
     */
    public function link()
    {
        if ($this->ping()) {
            return self::$_link;
        }
        $this->_connect(true);
        return self::$_link;
    }
    /**
     * Returns the DB Link object
     *
     * @return boolean
     */
    public function ping()
    {
        try {
            if (self::$_link) {
                if (self::$_link->query('SELECT 1')) {
                    return true;
                }
            }
        } catch (PDOException $e) {
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
        if (self::$_queryResult instanceof PDOStatement) {
            ob_start();
            self::$_queryResult->debugDumpParams();
            return ob_get_clean();
        }
    }
    /**
     * Executes the query.
     *
     * @param array $paramvals the parameters if any
     *
     * @return bool
     */
    private static function _execute($paramvals = array())
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
    private static function _all($type = PDO::FETCH_ASSOC)
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
    private static function _single($type = PDO::FETCH_ASSOC)
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
            $type = PDO::PARAM_STR;
        }
        self::$_queryResult->bindParam($param, $value, $type);
    }
}
