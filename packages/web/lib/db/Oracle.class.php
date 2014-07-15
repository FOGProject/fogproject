<?php
class Oracle
{
	private $host, $user, $pass, $db, $startTime, $result, $queryResult, $link, $query;
	private $port = null;
	public $ROW_ASSOC = 1;	// OCI_ASSOC
	public $ROW_NUM = 2;	// OCI_NUM
	public $ROW_BOTH = 3;	// OCI_BOTH
	function __construct($host, $user, $pass, $db = '')
	{
		try
		{
			if (!function_exists('oci_new_connect'))
				throw new Exception(sprintf('%s PHP extension not loaded', __CLASS__));
			$this->host = $host;
			$this->user = $user;
			$this->pass = $pass;
			$this->db = $db;
			if (!$this->connect())
				throw new Exception('Failed to connect');
			$this->startTime = $this->now();
		}
		catch (Exception $e)
		{
			$FOGCore->error(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
	}
	function __destruct()
	{
		try
		{
			if (!$this->link)
				return;
			if ($this->link && !oci_close($this->link))
				throw new Exception('Could not disconnect');
		}
		catch (Exception $e)
		{
			$FOGCore->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
	}
	public function close()
	{
		$this->__destruct();
	}
	public function connect()
	{
		try
		{
			if ($this->link)
				$this->close();
			$strconn = "//" . $this->host .  ( ( $this->port != null ) ? ( ":" . $this->port) : "" ) . "/" . $this->db;
			if (!$this->link = @oci_new_connect($this->user, $this->pass, $strconn))
				throw new Exception(sprintf('Host: %s, Username: %s, Password: %s, Database: %s', $this->host, $this->user, $this->pass, $this->db));
			if ($this->db)
				$this->select_db($this->db);
		}
		catch (Exception $e)
		{
			$FOGCore->error(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	public function query($sql, $data = array())
	{
		try
		{
			$this->query = $sql;
			if (count($data))
				$this->queryResult = oci_parse(vsprintf($this->query, $data), $this->link) or $FOGCore->debug($this->error(), $this->query);
			else
				$this->queryResult = oci_parse($this->query, $this->link) or $FOGCore->debug($this->error(), $this->query);
			if (!oci_execute($this->queryResult))
				throw new Exception('Query failed');
		}
		catch (Exception $e)
		{
			$FOGCore->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	public function fetch($type = OCI_ASSOC)
	{
		try
		{
			if (!$this->queryResult)
				throw new Exception('No result present. Use query() first');
			$this->result = oci_fetch_assoc($this->queryResult, $type);
			//return $this->queryResult;
		}
		catch (Exception $e)
		{
			$FOGCore->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		//return false;
		return $this;
	}
	public function result()
	{
		return $this->result;
	}
	public function queryResult()
	{
		return $this->queryResult;
	}
	public function get($field = '')
	{
		try
		{
			if ($this->result === false)
				return false;
			if ($field && !$this->result[$field])
				throw new Exception(sprintf('No field found in results: Field: %s', $field));
			return ($field ? $this->result[$field] : $this->result);
		}
		catch (Exception $e)
		{
			$FOGCore->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return false;
	}
	public function select_db($db)
	{
		try
		{
			$this->db = $db;
			$this->close();
			$this->connect();
		}
		catch (Exception $e)
		{
			$FOGCore->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	public function error()
	{
		return oci_error();
	}
	public function insert_id()
	{
		$id = oci_insert_id($this->link);
		return ($id ? $id : 0);
	}
	public function affected_rows()
	{
		$count = oci_affected_rows($this->link);
		return ($count ? $count : 0);
	}
	public function num_rows()
	{
		try
		{
			if (!$this->queryResult)
				throw new Exception('No result present. Use query() first');
			return oci_num_rows($this->queryResult);
		}
		catch (Exception $e)
		{
			$FOGCore->debug(sprintf('Failed to %s: %s', __FUNCTION__, $e->getMessage()));
		}
		return 0;
	}
	public function age()
	{
		return ($this->now() - $this->startTime);
	}
	private function now()
	{
		return microtime(true);
	}
	public function escape($data)
	{
		return $this->sanitize($data);
	}
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
	private function clean($data)
	{
		if (function_exists('mysql_real_escape_string'))
			return (get_magic_quotes_gpc() ? mysql_real_escape_string(stripslashes($data)) : mysql_real_escape_string($data));
		else
			return (get_magic_quotes_gpc() ? addslashes(stripslashes($data)) : addslashes($data));
	}
	public function getLink()
	{
		return $this->link;
	}
}
