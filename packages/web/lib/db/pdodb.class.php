<?php
class PDODB extends DatabaseManager {
    private static $link;
    private static $query;
    private static $queryResult;
    private static $result;
    private static $db_name;
    public function __construct() {
        if (self::$link) return $this;
        try {
            if (!$this->connect()) throw new Exception(_('Failed to connect'));
        } catch (PDOException $e) {
            $this->sqlerror();
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
    public function query($sql, $data = array()) {
        try {
            if (!self::$link) throw new PDOException($this->sqlerror());
            self::$queryResult = null;
            if (isset($data) && !is_array($data)) $data = array($data);
            if (count($data)) $sql = vsprintf($sql,$data);
            $this->info($sql);
            self::$query = $sql;
            if (!self::$query) throw new PDOException(_('No query sent'));
            if (!self::$queryResult = self::$link->prepare(self::$query)) throw new PDOException(sprintf('%s: %s',_('Error'),$this->sqlerror()));
            self::$queryResult->execute();
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
                    self::$result = self::$queryResult->fetchAll($type);
                    break;
                default:
                    self::$result = self::$queryResult->fetch($type);
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
    public function result() {
        return self::$result;
    }
    public function queryResult() {
        return self::$queryResult;
    }
    public function sqlerror() {
        $message = self::$link ? sprintf('%s: %s, %s: %s',_('Error Code'),self::$link->errorCode() ? self::$link->errorCode() : self::$queryResult->errorCode(),_('Error Message'),self::$link->errorCode() ? self::$link->errorInfo() : self::$queryResult->errorInfo()) : _('Cannot connect to database');
        return $message;
    }
    public function field_count() {
        return self::$link->columnCount();
    }
    public function insert_id() {
        return self::$link->lastInsertId();
    }
    public function affected_rows() {
        return self::$queryResult->rowCount();
    }
    public function num_rows() {
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
}
