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
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    );
    /**
     * Initializes the PDODB class
     *
     * @param array $options any custom optoins
     *
     * @throws PDOException
     * @return void
     */
    public function __construct($options = array())
    {
        if (self::$_link) {
            return $this;
        }
        parent::__construct();
        try {
            if (count($optoins) > 0) {
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
     * @param bool $dbexists does db exist
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
                '%s %s: %s: %s',
                _('Failed to'),
                __FUNCTION__,
                _('Error'),
                $e->getMessage()
            );
            self::debug($msg);
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
                throw new PDOException(_('No query result, use query() first'));
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
    public function get($field = '')
    {
        try {
            if (!self::$_link) {
                throw new Exception(_('No connection to the database'));
            }
            if (self::$_result === false) {
                throw new Exception(_('No data returned'));
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
            $this->debug(sprintf('%s %s: %s', _('Failed to'), __FUNCTION__, $e->getMessage()));
        }
        return self::$_result;
    }
    public function sqlerror()
    {
        $message = self::$_link ? sprintf('%s: %s, %s: %s, %s: %s', _('Error Code'), self::$_link->errorCode() ? self::$_link->errorCode() : self::$_queryResult->errorCode(), _('Error Message'), self::$_link->errorCode() ? self::$_link->errorInfo() : self::$_queryResult->errorInfo(), _('Debug'), self::debugDumpParams()) : _('Cannot connect to database');
        return $message;
    }
    public function insertId()
    {
        return self::$_link->lastInsertId();
    }
    public function fieldCount()
    {
        return self::$_queryResult->columnCount();
    }
    public function affectedRows()
    {
        return self::$_queryResult->rowCount();
    }
    public function escape($data)
    {
        return $this->sanitize($data);
    }
    private function _clean($data)
    {
        if (!self::$_link) {
            return trim(htmlentities($data, ENT_QUOTES, 'utf-8'));
        }
        return self::$_link->quote($data);
    }
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
    public function dbName()
    {
        return self::$_dbName;
    }
    public function link()
    {
        return self::$_link;
    }
    public function returnThis()
    {
        return $this;
    }
    private static function _debugDumpParams()
    {
        ob_start();
        self::$_queryResult->debugDumpParams();
        return ob_get_clean();
    }
    private static function _execute($paramvals = array())
    {
        if (count($paramvals) > 0) {
            array_walk($paramvals, function ($value, $param) {
                is_array($value) ? self::_bind($param, $value[0], $value[1]) : self::_bind($param, $value);
            });
        }
        return self::$_queryResult->execute();
    }
    private static function _all($type = PDO::FETCH_ASSOC)
    {
        self::$_result = self::$_queryResult->fetchAll($type);
    }
    private static function _single($type = PDO::FETCH_ASSOC)
    {
        self::$_result = self::$_queryResult->fetch($type);
    }
    private static function _prepare()
    {
        self::$_queryResult = self::$_link->prepare(self::$_query);
    }
    private static function _bind($param, $value, $type = null)
    {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
                    break;
            }
        }
        $type = PDO::PARAM_STR;
        self::$_queryResult->bindParam($param, $value, $type);
    }
}
