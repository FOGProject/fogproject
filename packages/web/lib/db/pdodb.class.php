<?php
class PDODB extends DatabaseManager {
    private static $link;
    private static $query;
    private static $queryResult;
    private static $result;
    private static $db_name;
    public function __construct() {
        if (self::$link) return $this;
        parent::__construct();
        try {
            if (!$this->connect()) throw new Exception(_('Failed to connect'));
        } catch (PDOException $e) {
            $this->debug(sprintf('%s %s: %s, %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage(),_('SQL Error:'),$this->sqlerror()));
        }
    }
    public function __destruct() {
        self::$result = null;
        self::$queryResult = null;
        if (!self::$link) return;
        self::$link = null;
    }
    private function connect($dbexists = true) {
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        );
        try {
            if (self::$link) return $this;
            if ($dbexists) {
                self::$link = new PDO(sprintf('%s:host=%s;dbname=%s;charset=utf8',DATABASE_TYPE,preg_replace('#^p[:]#','',DATABASE_HOST),DATABASE_NAME),DATABASE_USERNAME,DATABASE_PASSWORD,$options);
            } else {
                self::$link = new PDO(sprintf('%s:host=%s;charset=utf8',DATABASE_TYPE,preg_replace('#^p[:]#','',DATABASE_HOST)),DATABASE_USERNAME,DATABASE_PASSWORD,$options);
                if (!self::current_db($this) && !preg_match('#schema#',htmlspecialchars($_SERVER['QUERY_STRING'],ENT_QUOTES,'utf-8'))) $this->redirect('?node=schema');
            }
            self::query("SET SESSION sql_mode=''");
        } catch (PDOException $e) {
            if ($dbexists) $this->connect(false);
            else {
                $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
            }
        }
        return $this;
    }
    public static function current_db(&$main) {
        try {
            if (!self::$link) throw new PDOException('');
            if (!isset(self::$db_name) || !self::$db_name) self::$db_name = (self::$link->query(sprintf('USE `%s`',DATABASE_NAME)) ? DATABASE_NAME : false);
        } catch (PDOException $e) {
            return false;
        }
        return $main;
    }
    public function query($sql, $data = array(), $paramvals = array()) {
        try {
            if (!self::$link) throw new PDOException($this->sqlerror());
            self::$queryResult = null;
            if (isset($data) && !is_array($data)) $data = array($data);
            if (count($data)) $sql = vsprintf($sql,$data);
            $this->info($sql);
            self::$query = $sql;
            if (!self::$query) throw new PDOException(_('No query sent'));
            self::prepare();
            self::execute($paramvals);
            if (!self::$db_name) self::current_db($this);
            if (!self::$db_name) throw new PDOException(_('No database to work off'));
        } catch (PDOException $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
        }
        return $this;
    }
    public function fetch($type = PDO::FETCH_ASSOC,$fetchType = 'fetch_assoc',$params = false) {
        try {
            self::$result = array();
            if (empty($type)) $type = PDO::FETCH_ASSOC;
            if (empty($fetchType)) $fetchType = 'fetch_assoc';
            if (!is_object(self::$queryResult) && in_array(self::$queryResult,array(true,false),true)) self::$result = self::$queryResult;
            else if (empty(self::$queryResult)) throw new PDOException(_('No query result, use query() first'));
            else {
                switch (strtolower($fetchType)) {
                case 'fetch_all':
                    self::all($type);
                    break;
                default:
                    self::single($type);
                    break;
                }
            }
        } catch (PDOException $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
            self::$result = false;
        }
        return $this;
    }
    public function get($field = '') {
        try {
            if (!self::$link) throw new Exception(_('No connection to the database'));
            if (self::$result === false) throw new Exception(_('No data returned'));
            if (self::$result === true) return self::$result;
            $result = array();
            if ($field) {
                foreach ((array)$field AS &$key) {
                    $key = trim($key);
                    if (array_key_exists($key, (array)self::$result)) {
                        return self::$result[$key];
                    }
                    foreach ((array)self::$result AS &$value) {
                        if (array_key_exists($key, (array)$value)) $result[] = $value[$key];
                    }
                }
            }
            if (count($result)) return $result;
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
        }
        return self::$result;
    }
    public function sqlerror() {
        $message = self::$link ? sprintf('%s: %s, %s: %s',_('Error Code'),self::$link->errorCode() ? self::$link->errorCode() : self::$queryResult->errorCode(),_('Error Message'),self::$link->errorCode() ? self::$link->errorInfo() : self::$queryResult->errorInfo()) : _('Cannot connect to database');
        return $message;
    }
    public function insert_id() {
        return self::$link->lastInsertId();
    }
    public function field_count() {
        return self::$queryResult->columnCount();
    }
    public function affected_rows() {
        return self::$queryResult->rowCount();
    }
    public function escape($data) {
        return $this->sanitize($data);
    }
    private function clean($data) {
        if (!self::$link) return trim(htmlentities($data,ENT_QUOTES,'utf-8'));
        $data = preg_replace("#^[']|[']$#",'',trim(self::$link->quote($data)));
        return $data ? $data : '';
    }
    public function sanitize($data) {
        if (!is_array($data)) return $this->clean($data);
        foreach ($data AS $key => &$val) {
            if (is_array($val)) {
                foreach ($val AS $i => $v) $data[$this->clean($key)][$i] = $this->clean($v);
            } else $data[$this->clean($key)] = $this->clean($val);
        }
        return $data;
    }
    public function db_name() {
        return self::$db_name;
    }
    public function link() {
        return self::$link;
    }
    public function returnThis() {
        return $this;
    }
    public function debugDumpParams() {
        return self::$queryResult->debugDumpParams();
    }
    private static function execute($paramvals = array()) {
        if (count($paramvals) > 0) {
            array_walk($paramvals,function(&$value,&$param) {
                is_array($value) ? self::bind($param,$value[0],$value[1]) : self::bind($param,$value);
            });
        }
        return self::$queryResult->execute();
    }
    private static function all($type = PDO::FETCH_ASSOC) {
        self::$result = self::$queryResult->fetchAll($type);
    }
    private static function single($type = PDO::FETCH_ASSOC) {
        self::$result = self::$queryResult->fetch($type);
    }
    private static function prepare() {
        self::$queryResult = self::$link->prepare(self::$query);
    }
    private static function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
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
        self::$queryResult->bindParam($param,$value,$type);
    }
}
