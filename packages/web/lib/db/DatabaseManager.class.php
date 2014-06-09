<?php
/**	Class Name: DatabaseManager
	Class controls the connection to the database.
*/
class DatabaseManager extends FOGBase
{
	public $type, $host, $user, $pass, $database;
	public $DB;
	private $valid = false;
	/** __construct($type,$host,$user,$pass,$database)
		Constructs the connection variables for connecting to the database.
	*/
	public function __construct($type = DATABASE_TYPE, $host = DATABASE_HOST, $user = DATABASE_USERNAME, $pass = DATABASE_PASSWORD, $database = DATABASE_NAME) 
	{
		try
		{
			parent::__construct();
			if (!$type)
				throw new Exception('Type not set');
			if (!$host)
				throw new Exception('Host not set');
			if (!$user)
				throw new Exception('User not set');
			if (!$database)
				throw new Exception('Database not set');
			$this->type = $type;
			$this->host = $host;
			$this->user = $user;
			$this->pass = $pass;
			$this->database = $database;
			$this->valid = true;
		}
		catch (Exception $e)
		{
			$this->valid = false;
			$this->FOGCore->error('Failed: %s->%s(): Error: %s', array(get_class($this), __FUNCTION__, $e->getMessage()));
		}
		
		return false;
	}
	/** connect()
		Connects the system to the database.
	*/
	public function connect()
	{
		try
		{
			// Error checking
			if (!$this->valid)
				throw new Exception('Class not constructed correctly');
			// Determine database host type
			switch($this->type)
			{
				case 'mysql':
					$this->DB = new MySQL($this->host, $this->user, $this->pass, $this->database);
					break;
				case 'mssql':
					break;
				case 'oracle':
					$db = new OracleOLD();
					$db->setCredentials( $this->user, $this->pass );
					$db->setHost( $this->host );
					$db->setSchema( $this->DB );
					if ( $db->connect() )
						$this->DB = $db;			
					break;								
				default:
					throw new Exception(sprintf('Unknown database type. Check that DATABASE_TYPE is being set in "%s/commons/config.php"', rtrim($_SERVER['DOCUMENT_ROOT'], '/') . dirname($_SERVER['PHP_SELF'])));
			}
			// Database Schema version check
			if ($this->getVersion() < FOG_SCHEMA && !preg_match('#schemaupdater#i', $_SERVER['PHP_SELF']))
				$this->FOGCore->redirect('../commons/schemaupdater/index.php?redir=1');
		}
		catch (Exception $e)
		{
			$this->FOGCore->error('Failed: %s->%s(): Error: %s', array(get_class($this), __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	
	/** close()
		Closes the database connection.
	*/
	public function close()
	{
		if ($this->DB)
			$this->DB->close();
		return $this;
	}
	
	/** getVersion()
		Gets the version stored in the database.  Sets
		up for if there's a need to update or not.
	*/
	public function getVersion()
	{
		try
		{
			// Error checking
			if (!$this->DB)
				throw new Exception('Database not connected');
			// Get version
			$version = $this->DB->query('SELECT vValue FROM schemaVersion LIMIT 1')->fetch()->get('vValue');
			// Return version OR 0 (for new install) if query failed
			return ($version ? $version : 0);
		}
		catch (Exception $e)
		{
			$this->FOGCore->error('Failed: %s->%s(): Error: %s', array(get_class($this), __FUNCTION__, $e->getMessage()));
		}
		return false;
	}
}
