<?php
/**	Class Name: MySQL
	For mysql connections specifically.
*/
class MySQL extends FOGBase
{
	private $host, $user, $pass, $dbname, $startTime, $result, $queryResult, $queryHandle, $link, $query;
	// Cannot use constants as you cannot access constants from $this->dbname::ROW_ASSOC
	public $ROW_ASSOC = 1;	// MYSQL_ASSOC
	public $ROW_NUM = 2;	// MYSQL_NUM
	public $ROW_BOTH = 3;	// MYSQL_BOTH
	public $debug = false;
	public $info = false;
	/** __construct($host,$user,$pass,$db = '')
		Constructs the connections to the database.
	*/
	function __construct($host, $user, $pass, $db = '')
	{
		parent::__construct();
		try
		{
			if (!class_exists('mysqli'))
				throw new Exception(sprintf('%s PHP extension not loaded', __CLASS__));
			$this->host = $host;
			$this->user = $user;
			$this->pass = $pass;
			$this->dbname = $db;
			if (!$this->connect())
				throw new Exception('Failed to connect');
			$this->startTime = $this->now();
		}
		catch (Exception $e)
		{
			$GLOBALS['FOGCore']->error(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
	}
	/** __destruct()
		Disconnect the connection.
	*/
	function __destruct()
	{
		if (!$this->link)
			return;
		else
			$this->link = null;
		return;
	}
	/** close()
		Close the connection.
	*/
	public function close()
	{
		$this->__destruct();
	}
	/** connect()
		Connects the database.
	*/
	public function connect()
	{
		try
		{
			if ($this->link)
				$this->close();
			if (!$this->link = new mysqli($this->host, $this->user, $this->pass))
				throw new Exception(sprintf('Host: %s, Username: %s, Password: %s, Database: %s', $this->host, $this->user, '[Protected]', $this->dbname));
			if ($this->dbname)
				$this->select_db($this->dbname);
		}
		catch (Exception $e)
		{
			$GLOBALS['FOGCore']->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/** query($sql,$data = array())
		Allows the sending of specific sql settings.
	*/
	public function query($sql, $data = array())
	{
		try
		{
			// printf
			if (!is_array($data))
				$data = array($data);
			if (count($data))
				$sql = vsprintf($sql, $data);
			// Query
			$this->query = $sql;
			$this->queryResult = $this->link->query($this->query) or $GLOBALS['FOGCore']->debug($this->sqlerror(),$this->query);
			// INFO
			$GLOBALS['FOGCore']->info($this->query);
		}
		catch (Exception $e)
		{
			$GLOBALS['FOGCore']->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/** fetch($type = MYSQL_ASSOC)
		fetches the information.
	*/
	public function fetch($type = MYSQLI_ASSOC)
	{
		try
		{
			if (!$this->queryResult)
				throw new Exception('No query result present. Use query() first');
			if ($this->queryResult === false)
				$this->result = false;
			elseif ($this->queryResult === true)
				$this->result = true;
			else
				$this->result = $this->queryResult->fetch_array($type);
			//return $this->result;
		}
		catch (Exception $e)
		{
			$GLOBALS['FOGCore']->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/** result()
		The result of the query.
	*/
	public function result()
	{
		return $this->result;
	}
	/** queryResult()
		The result of the sql query.
	*/
	public function queryResult()
	{
		return $this->queryResult;
	}
	/** get($field = '')
		Get the information.  Can specify the database field as well.
	*/
	public function get($field = '')
	{
		try
		{
			// Result finished
			if ($this->result === false)
				return false;
			// Query failed
			if ($this->queryResult === false)
				return false;
			// Return: 'field' if requested and field exists in results, otherwise the raw result
			return ($field && array_key_exists($field, $this->result) ? $this->result[$field] : $this->result);
		}
		catch (Exception $e)
		{
			$GLOBALS['FOGCore']->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return false;
	}
	/** select_db($db)
		Selects the sent database.
	*/
	public function select_db($db)
	{
		try
		{
			if (!$this->link->select_db($db))
				throw new Exception("$db");
			$this->dbname = $db;
		}
		catch (Exception $e)
		{
			$GLOBALS['FOGCore']->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/** sqlerror()
		What was the error.
	*/
	public function sqlerror()
	{
		return $this->link->error;
	}
	/** insert_id()
		Return the id of the inserted element.
	*/
	public function insert_id()
	{
		$id = $this->link->insert_id;
		return ($id ? $id : 0);
	}
	/** affected_rows()
		Return the affected rows.
	*/
	public function affected_rows()
	{
		$count = $this->link->affected_rows;
		return ($count ? $count : 0);
	}
	/** num_rows()
		Return the number of rows.
	*/
	public function num_rows()
	{
		return ($this->link->num_rows ? $this->link->num_rows : null);
	}
	/** age()
		Return the age of the information.
	*/
	public function age()
	{
		return ($this->now() - $this->startTime);
	}
	/** now()
		Return the current time.
	*/
	private function now()
	{
		return microtime(true);
	}
	/** escape($data)
		Make sure the data is clean to pass to the database.
	*/
	public function escape($data)
	{
		return $this->sanitize($data);
	}
	/** sanitize($data)
		Sanatizes the entry.
	*/
	public function sanitize($data)
	{
		if (!is_array($data))
			return $this->clean($data);
		foreach ($data AS $key => $val)
		{
			if (is_array($val))
				$data[$this->clean($key)] = $this->escape($val);
			else
				$data[$this->clean($key)] = $this->clean($val);
		}
		return $data;
	}
	/** clean($data)
		Clean the information.
	*/
	private function clean($data)
	{
		return (get_magic_quotes_gpc() ? $this->link->escape_string(stripslashes($data)) : $this->link->escape_string($data));;
	}
	// For legacy $conn connections
	/** getLink()
		Make sure the link works.
	*/
	public function getLink()
	{
		return $this->link;
	}
	/** dump($exit = false)
		Dumps the data.
	*/
	public function dump($exit = false)
	{
		printf('<p>Last Error: %s</p><p>Last Query: %s</p><p>Last Query Result: %s</p><p>Last Num Rows: %s</p><p>Last Affected Rows: %s</p><p>Last Result: %s</p>',
			$this->sqlerror(),
			$this->query,
			(is_bool($this->queryResult) === true ? ($this->queryResult == true ? 'true' : 'false') : $this->queryResult),
			$this->num_rows(),
			$this->affected_rows(),
			(is_array($this->result) ? '<pre>' . print_r($this->result, 1) . '</pre>' : (is_bool($this->result) === true ? ($this->result == true ? 'true' : 'false') : $this->result))
		);
		if ($exit)
			exit;
		return $this;
	}
}
