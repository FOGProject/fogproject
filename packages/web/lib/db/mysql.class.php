<?php
class MySQL extends DatabaseManager {
    private static $link;
    private static $query;
    private static $queryResult;
    private static $result;
    private static $db_name;
    public function __construct() {
        if (static::$link) return $this;
        try {
            if (!$this->connect()) throw new Exception(_('Failed to connect'));
        } catch (Exception $e) {
            $this->sqlerror(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
        }
    }
    public function __destruct() {
        static::$result = null;
        static::$queryResult = null;
        if (!static::$link) return;
        static::$link = null;
    }
    private function connect() {
        try {
            if (static::$link) return $this;
            static::$link = new mysqli(DATABASE_HOST,DATABASE_USERNAME,DATABASE_PASSWORD);
            static::$link->set_charset('utf8');
            static::current_db();
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
            if (static::$link->connect_error) die($e->getMessage());
        }
        return $this;
    }
    public static function current_db() {
        if (!isset(static::$db_name) || !static::$db_name) static::$db_name = static::$link->select_db(DATABASE_NAME);
        return $this;
    }
    public function query($sql, $data = array()) {
        try {
            static::$queryResult = null;
            if (isset($data) && !is_array($data)) $data = array($data);
            if (count($data)) $sql = vsprintf($sql,$data);
            $this->info($sql);
            static::$query = sprintf('/*qc=on*/%s',$sql);
            static::current_db();
            if (!static::$query) throw new Exception(_('No query sent'));
            else if (!static::$queryResult = static::$link->query(static::$query)) throw new Exception(sprintf('%s: %s',_('Error'),$this->sqlerror()));
            if (!static::$db_name) static::current_db();
            if (!static::$db_name) throw new Exception(_('No database to work off'));
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
        }
        return $this;
    }
    public function fetch($type = MYSQLI_ASSOC,$fetchType = 'fetch_assoc',$params = false) {
        try {
            static::$result = array();
            if (empty($type)) $type = MYSQLI_ASSOC;
            if (empty($fetchType)) $fetchType = 'fetch_assoc';
            if (!is_object(static::$queryResult) && in_array(static::$queryResult,array(true,false),true)) static::$result = static::$queryResult;
            else if (empty(static::$queryResult)) throw new Exception(_('No query result, use query() first'));
            else {
                switch (strtolower($fetchType)) {
                case 'fetch_all':
                    if (method_exists('mysqli_result','fetch_all')) {
                        static::$result = static::$queryResult->fetch_all($type);
                    } else {
                        for (static::$result=array();$tmp = static::$queryResult->fetch_array($type);) static::$result[] = $tmp;
                    }
                    break;
                case 'fetch_assoc':
                case 'fetch_row':
                case 'fetch_field':
                case 'fetch_fields':
                case 'free':
                    static::$result = static::$queryResult->$fetchType();
                    break;
                case 'fetch_object':
                    if (isset($type) && !class_exists($type)) throw new Exception(_('No valid class sent'));
                    else static::$result = static::$queryResult->$fetchType();
                    if (isset($type) && count($params) && !is_array($params)) static::$result = static::$queryResult->$fetchType($type,array($params));
                    else if (isset($type) && $params == false) static::$result = static::$queryResult->$fetchType($type,array(null));
                    else static::$result = static::$queryResult->$fetchType($type,$params);
                    break;
                case 'data_seek':
                case 'fetch_field_direct':
                case 'field_seek':
                    if (!is_numeric($type)) throw new Exception(_('Row number not set properly'));
                default:
                    static::$result = static::$queryResult->$fetchType($type);
                    break;
                }
            }
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
        }
        return $this;
    }
    public function get($field = '') {
        try {
            if (static::$result === false) throw new Exception(_('No data returned'));
            if (static::$result === true) return static::$result;
            $result = array();
            if ($field) {
                foreach ((array)$field AS &$key) {
                    $key = trim($key);
                    if (array_key_exists($key, (array)static::$result)) {
                        return static::$result[$key];
                    }
                    foreach ((array)static::$result AS &$value) {
                        if (array_key_exists($key, (array)$value)) $result[] = $value[$key];
                    }
                }
            }
            if (count($result)) return $result;
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
            return false;
        }
        return static::$result;
    }
    public function result() {
        return static::$result;
    }
    public function queryResult() {
        return static::$queryResult;
    }
    public function sqlerror() {
        return static::$link->connect_error ? sprintf('%s, %s: %s',static::$link->connect_error,_('Message'),_('Check that database is running')) : static::$link->error;
    }
    public function field_count() {
        return static::$link->field_count;
    }
    public function insert_id() {
        return static::$link->insert_id;
    }
    public function affected_rows() {
        return static::$link->affected_rows;
    }
    public function num_rows() {
        return static::$link->num_rows;
    }
    public function escape($data) {
        return $this->sanitize($data);
    }
    private function clean($data) {
        return trim(static::$link->real_escape_string(htmlentities(html_entity_decode(mb_convert_encoding($data,'UTF-8'),ENT_QUOTES,'UTF-8'),ENT_QUOTES,'UTF-8')));
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
        return static::$db_name;
    }
    public function link() {
        return static::$link;
    }
}
