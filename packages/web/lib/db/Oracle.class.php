<?php
class Oracle extends DatabaseManager {
	/** @var $link the link after connected */
	private $link;
	/** @var $query the query to call */
	private $query;
	/** @var $queryResult the result of the query */
	private $queryResult;
	/** @var $result the result set */
	private $result;
	/** __construct initializes the class
	  * @return void
	  */
	public function __construct() {
		parent::__construct();
		try {
			$this->debug = false;
			$this->info = false;
			if (!function_exists('oci_new_connect')) throw new Exception(sprintf('%s PHP extension not loaded', __CLASS__));
			if (!$this->connect()) throw new Exception('Failed to connect');
		} catch (Exception $e) {
			$this->error(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
	}
	/** __destruct destroys the class
	  * @return void
	  */
	public function __destruct() {
		if (!$this->link) return;
		unset($this->link,$this->result);
	}
	/** connect establishes the link
	  * @return the class
	  */
	public function connect() {
		try {
			if (!$this->link = @oci_new_connect(DATABASE_USERNAME,DATABASE_PASSWORD,'//'.DATABASE_HOST.'/'.DATABASE_NAME)) throw new Exception(sprintf('Host: %s, Username: %s, Database: %s, Error: %s',DATABASE_HOST,DATABASE_USERNAME,DATABASE_NAME,@oci_error()));
			if (!$this->link->select_db(DATABASE_NAME)) throw new Exception(_('Issue working with the current DB, maybe it has not been created yet'));
		} catch (Exception $e) {
			$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/** query perfoms the db query
	  * @param string sql
	  * @param array data
	  * @return this class
	  */
	public function query($sql, $data = array()) {
		try {
			if (!is_array($data)) $data = array($data);
			if (count($data)) $sql = vsprintf($sql,$data);
			$this->info($sql);
			$this->query = $sql;
			if (!$this->query) throw new Exception(_('No query sent'));
			$this->queryResult = @oci_parse($sql,$this->link) or $this->debug($this->sqlerror(),$sql);
			if (!@oci_execute($this->queryResult)) throw new Exception('Query failed');
		} catch (Exception $e) {
			$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/** fetch() fetches the data
	  * @param $type what type of data to fetch in
	  * @return the class as is
	  */
	public function fetch($type = OCI_ASSOC) {
		try {
			$this->result = array();
			if (empty($type)) $type = OCI_ASSOC;
			if ($this->queryResult === false || $this->queryResult === true) $this->result = $this->queryResult;
			else if (!$this->queryResult) throw new Exception('No query result present. Use query() first');
			else $this->result = @oci_fetch_assoc($this->queryResult, $type);
		} catch (Exception $e) {
			$this->debug(sprintf('Failed to %s: %s',__FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/** get() get the information as called
	  * @param $field the field to get or all
	  * @return the requested data or all
	  */
	public function get($field = '') {
		try {
			if ($this->result === false) throw new Exception(_('No data returned'));
			return ($field && array_key_exists((string)$field,(array)$this->result) ? $this->result[$field] : $this->result);
		} catch (Exception $e) {
			$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return false;
	}
	/** result() result of the query
	  * @return the result
	  */
	public function result() {return $this->result;}
	/** queryResult() queryResult of the sql query
	  * @return the queryResult
	  */
	public function queryResult() {return $this->queryResult;}
	/** sqlerror() the error if there is one
	  * @return the connection or sql error
	  */
	public function sqlerror() {return @oci_error();}
	/** insert_id() the last insert id
	  * @return the value of the id
	  */
	public function insert_id() {return (int)@oci_insert_id($this->link);}
	/** affected_rows() the number of rows.
	  * @return the number
	  */
	public function affected_rows() {return (int)@oci_affected_rows($this->lilnk);}
	/** num_rows() the number of rows.
	  * @return the number
	  */
	public function num_rows() {return (int)@oci_num_rows($this->queryResult);}
	/** escape() escape/clean the data
	  * @param $data the data to be cleaned
	  * @return the sanitized data
	  */
	public function escape($data) {return $this->sanitize($data);}
	/** clean() escape/clean the data
	  * @param $data the data to be cleaned
	  * @return the sanitized data
	  */
	private function clean($data) {return mysql_real_escape_string(strip_tags($data));}
	/** sanitize() escape/clean the data
	  * @param $data the data to be cleaned
	  * @return the sanitized data
	  */
	public function sanitize($data) {
		if (!is_array($data)) return $this->clean($data);
		foreach ($data AS $key => $val) {
			if (is_array($val)) $data[$this->clean($key)] = $this->escape($val);
			else $data[$this->clean($key)] = $this->clean($val);
		}
		return $data;
	}
	/** select_db() select the database
	  * @param $db the database to connect to
	  * @return the class
	  */
	public function select_db($db) {
		try {
			$this->link->close();
			$this->connect();
		} catch (Exception $e) {
			$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
}
