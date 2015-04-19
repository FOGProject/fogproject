<?php
class MySQL extends FOGBase {
	private $link;
	private $query;
	private $queryResult;
	private $result;
	/** __construct initializes the class
	  * @return void
	  */
	public function __construct()
	{
		parent::__construct();
		try {
			$this->debug = false;
			$this->info = false;
			if (!class_exists('mysqli')) throw new Exception(sprintf('%s PHP extension not loaded', __CLASS__));
			if (!$this->connect()) throw new Exception('Failed to connect');
		}
		catch (Exception $e) {$this->error(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));}
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
			if (!$this->link) $this->link = new mysqli(DATABASE_HOST, DATABASE_USERNAME, DATABASE_PASSWORD);
			if ($this->link->connect_error) throw new Exception(sprintf('Host: %s, Username: %s, Database: %s, Error: %s', DATABASE_HOST, DATABASE_USERNAME, DATABASE_NAME, $this->sqlerror));
			$this->link->set_charset('utf8');
			if (!$this->link->select_db(DATABASE_NAME)) throw new Exception(_('Issue working with the current DB, maybe it has not been created yet'));
		}
		catch (Exception $e) {$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));}
		return $this;
	}
	/** query performs the db query
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
			$this->queryResult = $this->link->query($this->query,DATABASE_CONNTYPE);
			if (!$this->queryResult) throw new Exception(_('Error: ').$this->sqlerror());
			if (!$this->queryResult instanceof mysqli_result) {
				$all_links[] = $this->link;
				$processed = 0;
				do {
					$links = $errors = $reject = array();
					foreach($all_links AS $link)
						$links[] = $errors[] = $reject[] = $link;
					if (!mysqli_poll($links,$errors,$reject,1))
						continue;
					foreach($links AS $k => $link) {
						$this->queryResult = $link->reap_async_query();
						$processed++;
					}
				} while ($processed < 1);
			}
		}
		catch (Exception $e) {$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));}
		return $this;
	}
	/** fetch() fetches the data
	  * @param $type what type of data to fetch in
	  * @return the class as is
	  */
	public function fetch($type = MYSQLI_ASSOC,$fetchType = 'fetch_array') {
		try {
			$this->result = array();
			if (empty($type)) $type = MYSQLI_ASSOC;
			if (empty($fetchType)) $fetchType = 'fetch_array';
			if ($this->queryResult === false || $this->queryResult === true) $this->result = $this->queryResult;
			else if (!$this->queryResult) throw new Exception('No query result present. Use query() first');
			else {
				if ($fetchType == 'fetch_all') {
					if (method_exists('mysqli_result','fetch_all')) $this->result = $this->queryResult->fetch_all($type);
					else for($this->result = array();$tmp = $this->queryResult->fetch_array($type);) $this->result[] = $tmp;
				}
				else $this->result = $this->queryResult->fetch_assoc();
			}
		}
		catch (Exception $e) {$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));}
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
		}
		catch (Exception $e) {$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));}
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
	public function sqlerror() {return $this->link->connect_error ? $this->link->connect_error.', Message: '.'Check that database is running' : $this->link->error;}
	/** insert_id() the last insert id
	  * @return the value of the id
	  */
	public function insert_id() {return ($this->link->insert_id ? $this->link->insert_id : 0);}
	/** affected_rows() the number of affected rows
	  * @return the number
	  */
	public function affected_rows() {return ($this->link->affected_rows ? $this->link->affected_rows : 0);}
	/** num_rows() the number of rows.
	  * @return the number
	  */
	public function num_rows() {return ($this->link->num_rows ? $this->link->num_rows : 0);}
	/** escape() escape/clean the data
	  * @param $data the data to be cleaned
	  * @return the sanitized data
	  */
	public function escape($data) {return $this->sanitize($data);}
	/** clean() escape/clean the data
	  * @param $data the data to be cleaned
	  * @return the sanitized data
	  */
	private function clean($data) {return $this->link->real_escape_string(strip_tags($data));}
	/** sanitize() escape/clean the data
	  * @param $data the data to be cleaned
	  * @return the sanitized data
	  */
	public function sanitize($data) {
		if (!is_array($data))
			return $this->clean($data);
		foreach ($data AS $key => $val) {
			if (is_array($val))
				$data[$this->clean($key)] = $this->escape($val);
			else
				$data[$this->clean($key)] = $this->clean($val);
		}
		return $data;
	}
}
