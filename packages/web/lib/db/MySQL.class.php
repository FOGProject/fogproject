<?php
class MySQL extends DatabaseManager {
    private $link;
    private $query;
    private $queryResult;
    private $result;
    private $execute = false;
    public $db_name;
    public function __construct() {
        parent::__construct();
        try {
            if (!class_exists('mysqli')) throw new Exception(sprintf('%s PHP extension not loaded', __CLASS__));
            if (!$this->connect()) throw new Exception('Failed to connect');
        } catch (Exception $e) {
            $this->error(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
        }
    }
    public function __destruct() {
        unset($this->result,$this->queryResult);
        if (!$this->link) return;
        unset($this->link);
        return;
    }
    public function connect() {
        try {
            if (!$this->link) {
                $this->link = mysqli_init();
                if (!$this->link) die(_('Could not initialize MySQL session'));
                $this->link->real_connect(DATABASE_HOST,DATABASE_USERNAME,DATABASE_PASSWORD);
                if ($this->link->connect_error) {
                    usleep(5000000);
                    unset($this->link);
                    die(_('Could not connect to the MySQL Server'));
                }
            }
            $this->current_db();
            $this->link->set_charset('utf8');
        } catch (Exception $e) {
            $this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
        }
        return $this;
    }
    public function current_db() {
        if (!isset($this->db_name) || !$this->db_name) $this->db_name = $this->link->select_db(DATABASE_NAME);
        return;
    }
    public function query($sql, $data = array()) {
        try {
            $this->queryResult = null;
            if (isset($data) && !is_array($data)) $data = array($data);
            if (count($data)) $sql = vsprintf($sql,$data);
            $this->info($sql);
            $this->query = $sql;
            $this->current_db();
            if (!$this->query) throw new Exception(_('No query sent'));
            else if (!$queryResult = $this->link->prepare($this->query)) throw new Exception(_('Error: ').$this->sqlerror());
            else {
                if (!$queryResult->execute()) throw new Exception(_('Error: ').$this->sqlerror());
                $this->queryResult = $queryResult->get_result();
                if (!$this->queryResult) $this->queryResult = $queryResult->store_result();
                if (!$this->db_name) $this->current_db();
            }
            if (!$this->db_name) throw new Exception(_('No database to work off'));
        } catch (Exception $e) {
            $this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
        }
        return $this;
    }
    public function fetch($type = MYSQLI_ASSOC,$fetchType = 'fetch_assoc',$params = false) {
        try {
            $this->result = array();
            if (empty($type)) $type = MYSQLI_ASSOC;
            if (empty($fetchType)) $fetchType = 'fetch_assoc';
            if (!is_object($this->queryResult) && in_array($this->queryResult,array(true,false),true)) $this->result = $this->queryResult;
            else if (empty($this->queryResult)) throw new Exception(_('No query result, use query() first'));
            else {
                switch (strtolower($fetchType)) {
                case 'fetch_all':
                    if (method_exists('mysqli_result','fetch_all')) {
                        $this->result = $this->queryResult->fetch_all($type);
                        $this->queryResult->close();
                    } else {
                        for ($this->result=array();$tmp = $this->queryResult->fetch_array($type);) $this->result[] = $tmp;
                        $this->queryResult->close();
                    }
                    break;
                case 'fetch_assoc':
                case 'fetch_row':
                case 'fetch_field':
                case 'fetch_fields':
                case 'free':
                    $this->result = $this->queryResult->$fetchType();
                    break;
                case 'fetch_object':
                    if (isset($type) && !class_exists($type)) throw new Exception(_('No valid class sent'));
                    else $this->result = $this->queryResult->$fetchType();
                    if (isset($type) && count($params) && !is_array($params)) $this->result = $this->queryResult->$fetchType($type,array($params));
                    else if (isset($type) && $params == false) $this->result = $this->queryResult->$fetchType($type,array(null));
                    else $this->result = $this->queryResult->$fetchType($type,$params);
                    break;
                case 'data_seek':
                case 'fetch_field_direct':
                case 'field_seek':
                    if (!is_numeric($type)) throw new Exception(_('Row number not set properly'));
                default:
                    $this->result = $this->queryResult->$fetchType($type);
                    break;
                }
            }
        } catch (Exception $e) {
            $this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
        }
        return $this;
    }
    public function get($field = '') {
        try {
            if ($this->result === false) throw new Exception(_('No data returned'));
            if ($this->result === true) return $this->result;
            $result = array();
            if ($field) {
                foreach ((array)$field AS $i => &$key) {
                    $key = trim($key);
                    if (array_key_exists($key, (array)$this->result)) {
                        $this->queryResult->close();
                        return $this->result[$key];
                    }
                    foreach ((array)$this->result AS $i => &$value) {
                        if (array_key_exists($key, (array)$value)) $result[] = $value[$key];
                    }
                }
            }
            if (count($result)) return $result;
        } catch (Exception $e) {
            $this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
            return false;
        }
        return $this->result;
    }
    public function result() {
        return $this->result;
    }
    public function queryResult() {
        return $this->queryResult;
    }
    public function sqlerror() {
        return $this->link->connect_error ? $this->link->connect_error.', Message: '.'Check that database is running' : $this->link->error;
    }
    public function field_count() {
        return $this->link->field_count;
    }
    public function insert_id() {
		return $this->link->insert_id;
    }
    public function affected_rows() {
        return $this->link->affected_rows;
    }
    public function num_rows() {
        $this->link->num_rows;
    }
    public function escape($data) {
        return $this->sanitize($data);
    }
    private function clean(&$data) {
        return stripslashes(addslashes($this->link->real_escape_string(mb_convert_encoding(trim($data),'UTF-8','UTF-8'))));
    }
    public function sanitize($data) {
        if (!is_array($data)) return $this->clean($data);
        foreach ($data AS $key => &$val) {
            if (is_array($val)) $data[$this->clean($key)] = $this->escape($val);
            else $data[$this->clean($key)] = $this->clean($val);
        }
        return $data;
    }
    public function link() {
        return $this->link;
    }
}
