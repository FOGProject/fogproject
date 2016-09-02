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
 * @category PDODB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PDODB extends DatabaseManager
{
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
     * Stores the query result
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
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    );
    /**
     * Initializes the PDODB class
     *
     * @param array $options any custom options you want passed
     *
     * @throws PDOException
     * @return void
     */
    public function __construct($options = array())
    {
        parent::__construct();
        if (self::$_link) {
            return $this;
        }
        try {
            if (count($options) > 0) {
                self::$_options = $options;
            }
            self::$_dbName = DATABASE_NAME;
            if (!$this->_connect()) {
                throw new PDOException(_('Failed to connect'));
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
            $this->debug($msg);
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
     * @param bool $dbexists tests existence of DB
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
            $host = str_replace(array('p:', 'P:'), '', DATABASE_HOST);
            $user = DATABASE_USERNAME;
            $pass = DATABASE_PASSWORD;
            $dsn = sprintf(
                '%s:host=%s;dbname=%s;charset=utf8',
                $type,
                $host,
                self::$_dbName
            );
            if (!$dbexists) {
                $dsn = preg_replace('#dbname=.*[;]#', '', $dsn);
            }
            self::$_link = new PDO(
                $dsn,
                $user,
                $pass,
                self::$_options
            );
            if (!self::currentDb($this)) {
                if (preg_match('#schema#', self::$querystring)) {
                    self::redirect('?node=schema');
                }
            }
            self::query("SET SESSION sql_mode=''");
        } catch (PDOException $e) {
            if ($dbexists) {
                $this->_connect(false);
            } else {
                $msg = sprintf(
                    '%s %s: %s: %s',
                    _('Failed to'),
                    __FUNCTION__,
                    _('Error'),
                    $e->getMessage()
                );
                $this->debug($msg);
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
                throw new PDOException(_('No link established to the database'));
            }
            if (!isset(self::$_dbName) || !self::$_dbName) {
                $sql = sprintf(
                    'USE `%s`',
                    self::$_dbName
                );
                $dbTest = self::$_link->query($sql);
                if (!$test) {
                    self::$_dbName = false;
                } elseif (self::$_dbName === false) {
                    self::$_dbName = DATABASE_NAME;
                }
            }
        } catch (PDOException $e) {
            return false;
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
     * @return object|bool
     */
    public function query($sql, $data = array(), $paramvals = array())
    {
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
                throw new PDOException(_('No query passed'));
            }
            self::$_query = $sql;
            self::_prepare();
            self::_execute($paramvals);
            $this->info($sql);
            if (!self::$_dbName) {
                self::currentDb($this);
            }
            if (!self::$_dbName) {
                throw new PDOException(_('No database to work off'));
            }
        } catch (PDOException $e) {
            $msg = sprintf(
                '%s %s: %s: %s',
                _('Failed to'),
                __FUNCTION__,
                _('Error'),
                $e->getMessage()
            );
            if (stripos($e->getMessage(), _('no database to'))) {
                $msg = sprintf(
                    '%s %s',
                    $msg,
                    self::_debugDumpParams()
                );
            }
            $this->debug($msg);
        }
        return $this;
    }
    /**
     * Fetchs the information into a statement object to parse.
     *
     * @param int    $type      the type of fetching PDO int.
     * @param string $fetchType the type in function calling
     * @param mixed  $params    any additional parameters needed.
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
            if (is_null(self::$_queryResult)) {
                throw new PDOException(_('Query method must be run first'));
            } elseif (is_bool(self::$_queryResult)) {
                self::$_result = self::$_queryResult;
            } else {
                $fetchType = strtolower($fetchType);
                switch ($fetchType) {
                    case 'fetch_all':
                        self::_all($type);
                        break;
                    default:
                        self::_single($type);
                        break;
                }
            }
        } catch (PDOException $e) {
            $msg = sprintf(
                '%s %s: %s: %s',
                _('Failed to'),
                __FUNCTION__,
                _('Error'),
                $e->getMessage()
            );
            $this->debug($msg);
            self::$_result = false;
        }
        return $this;
    }
    /**
     * Gets the field requested or all fields
     *
     * @param array|string $field the field to get
     *
     * @throws PDOException
     * @return mixed
     */
    public function get($field = '')
    {
        try {
            if (!self::$_link) {
                throw new PDOException(_('No connection to the database'));
            }
            if (is_bool(self::$_result)) {
                if (self::$_result === false) {
                    throw new PDOException(_('No data returned'));
                }
                return self::$_result;
            }
            $result = array();
            if ($field || count($field) > 0) {
                if (is_array($field)) {
                    foreach ((array)$field as &$key) {
                        $key = trim($key);
                        if (array_key_exists($key, (array)self::$_result)) {
                            return self::$_result[$key];
                        }
                        foreach ((array)self::$_result as &$value) {
                            if (array_key_exists($key, (array)$value)) {
                                $result[] = $value[$key];
                            }
                            unset($value);
                        }
                        unset($key);
                    }
                } else {
                    $key = trim($field);
                    if (array_key_exists($key, (array)self::$_result)) {
                        return self::$_result[$key];
                    }
                    foreach ((array)self::$_result as &$value) {
                        if (array_key_exists($key, (array)$value)) {
                            $result[] = $value[$key];
                        }
                        unset($value);
                    }
                    unset($key);
                }
            }
            if (count($result)) {
                return $result;
            }
        } catch (Exception $e) {
            $msg = sprintf(
                '%s %s: %s: %s',
                _('Failed to'),
                __FUNCTION__,
                _('Error'),
                $e->getMessage()
            );
            $this->debug($msg);
            throw new Exception($msg);
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
        if (self::$_link) {
            if (self::$_link->errorCode()) {
                $errCode = self::$_link->errorCode();
                $errInfo = self::$_link->errorInfo();
            } else {
                $errCode = self::$_queryResult->errorCode();
                $errInfo = self::$_queryResult->errorInfo();
            }
            $msg = sprintf(
                '%s: %s, %s: %s, %s: %s',
                _('Error Code'),
                $errCode,
                _('Error Message'),
                $errInfo,
                _('Debug'),
                self::_debugDumpParams()
            );
        } else {
            $msg = _('Cannot connect to database');
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
        return self::$_link->lastInsertId();
    }
    /**
     * Returns the field count
     *
     * @return int
     */
    public function fieldCount()
    {
        return self::$_queryResult->columnCount();
    }
    /**
     * Returns the number of affected rows
     *
     * @return int
     */
    public function affectedRows()
    {
        return self::$_queryResult->rowCount();
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
        $eData = htmlentities(
            $data,
            ENT_QUOTES,
            'utf-8'
        );
        if (!self::$_link) {
            return $eData;
        }
        return self::$_link->quote($data);
    }
    /**
     * Sanitizes data passed
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
        return self::$_link;
    }
    /**
     * Returns this item whatever this is
     * Could be database manager or pdodb.
     *
     * @return $this
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
        ob_start();
        self::$_queryResult->debugDumpParams();
        return ob_get_clean();
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
        if (count($paramvals) > 0) {
            foreach ((array)$paramvals as $param => &$value) {
                if (is_array($value)) {
                    self::_bind($param, $value[0], $value[1]);
                } else {
                    self::_bind($param, $value);
                }
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
            if (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }
        }
        self::$_queryResult->bindParam($param, $value, $type);
    }
}
