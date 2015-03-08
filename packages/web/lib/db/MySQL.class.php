<?php
class MySQL extends FOGBase
{
	/* host to connect to
	 * @var string
	 */
	protected $host;
	/* user to connect with
	 * @var string
	 */
	protected $user;
	/* pass to connect with
	 * @var string
	 */
	protected $pass;
	/* dbname to use
	 * @var string
	 */
	protected $dbname;
	/* link the actual connection
	 * @var resource
	 */
	private $link;
	/* query the query to use
	 * @var string
	 */
	private $query;
	/* queryResult the result of query
	 * @var resource
	 */
	private $queryResult;
	/* result the returned results
	 * @var array of info
	 */
	private $result;
	/* debug turn on or off
	 * @var boolean
	 */
	public $debug = false;
	/* info turn on or off
	 * @var boolean
	 */
	public $info = false;
	/* __construct initializes the class
	 * @param string host
	 * @param string user
	 * @param string pass
	 * @param string dbname set null
	 * @return void
	 */
	public function __construct($host, $user, $pass, $db = '')
	{
		/* Get the constructor of the main first */
		parent::__construct();
		try
		{
			$this->debug = false;
			$this->info = false;
			if (!class_exists('mysqli'))
				throw new Exception(sprintf('%s PHP extension not loaded', __CLASS__));
			$this->host = $host;
			$this->user = $user;
			$this->pass = $pass;
			$this->dbname = $db;
			if (!$this->connect())
				throw new Exception('Failed to connect');
		}
		catch (Exception $e)
		{
			$this->error(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
	}
	/* __destruct destroys the class
	 * @return main destructor
	 */
	public function __destruct()
	{
		if (!$this->link)
			return;
		unset($this->link,$this->result);
	}
	/* connect establishes the link
	 * @return the class
	 */
	public function connect()
	{
		try
		{
			if (!$this->link)
				$this->link = new mysqli($this->host, $this->user, $this->pass);
			if ($this->link->connect_error)
				throw new Exception(sprintf('Host: %s, Username: %s, Password: %s, Database: %s, Error: %s', $this->host, $this->user, '[Protected]', $this->dbname, $this->sqlerror));
			$this->link->set_charset('utf8');
			if (!$this->link->select_db($this->dbname))
				throw new Exception(_('Issue working with the current DB, maybe it has not been created yet'));
		}
		catch (Exception $e)
		{
			$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/* query performs the db query
	 * @param string sql
	 * @param array data
	 * @return this class
	 */
	public function query($sql, $data = array())
	{
		try
		{
			if (!is_array($data))
				$data = array($data);
			if (count($data))
				$sql = vsprintf($sql,$data);
			$this->info($sql);
			$this->query = $sql;
			if (!$this->query)
				throw new Exception(_('No query sent'));
			if (!$this->link->query($this->query,MYSQLI_ASYNC))
				throw new Exception(_('Error: ').$this->sqlerror());
			$all_links = array($this->link);
			$processed = 0;
			do {
				$links = $errors = $reject = array();
				foreach($all_links AS $link)
					$links[] = $errors[] = $reject[] = $link;
				if (0 == ($ready = mysqli_poll($links,$errors,$reject, 1, 0)))
					continue;
				foreach($links AS $k => $link) {
					if ($this->queryResult = $link->reap_async_query())
						$processed++;
				}
			} while ($processed < 1);
		}
		catch (Exception $e)
		{
			$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	/** fetch($type = MYSQL_ASSOC)
		fetches the information.
	*/
	public function fetch($type = MYSQLI_ASSOC,$fetchType = 'fetch_array')
	{
		try
		{
			$this->result = array();
			if (empty($type))
				$type = MYSQLI_ASSOC;
			if (empty($fetchType))
				$fetchType = 'fetch_array';
			if ($this->queryResult === false || $this->queryResult === true)
				$this->result = $this->queryResult;
			else if (!$this->queryResult)
				throw new Exception('No query result present. Use query() first');
			else
			{
				if ($fetchType == 'fetch_all')
				{
					if (method_exists('mysqli_result','fetch_all'))
						$this->result = $this->queryResult->fetch_all($type);
					else
						for($this->result = array();$tmp = $this->queryResult->fetch_array($type);) $this->result[] = $tmp;
				}
				else
					$this->result = $this->queryResult->fetch_assoc();
			}
		}
		catch (Exception $e)
		{
			$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
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
				throw new Exception(_('No data returned'));
			// Return: 'field' if requested and field exists in results, otherwise the raw result
			return ($field && array_key_exists((string)$field,(array)$this->result) ? $this->result[$field] : $this->result);
		}
		catch (Exception $e)
		{
			$this->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return false;
	}
	/** sqlerror()
		What was the error.
	*/
	public function sqlerror()
	{
		if ($this->link->connect_error)
			return $this->link->connect_error.', Message: '.'Check that database is running';
		else
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
		return ($this->queryResult->num_rows ? $this->queryResult->num_rows : null);
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
		return $this->link->real_escape_string(strip_tags($data));
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
