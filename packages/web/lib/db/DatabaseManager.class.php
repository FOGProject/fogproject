<?php
/**	Class Name: DatabaseManager
	Class controls the connection to the database.
*/
class DatabaseManager extends FOGBase
{
	/** $type the type of connection to establish */
	public $type;
	/** $host the host to connect to */
	public $host;
	/** $user the user to connect as */
	public $user;
	/** $pass the pass to connect with */
	public $pass;
	/** $database the database to use */
	public $database;
	/** $DB the Connection as established */
	public $DB;
	/** $valid if the item is valid or not */
	private $valid = false;
	/** __construct() initiates the database class
	  * @return if the class is valid or not
	  */
	public function __construct()
	{
		try
		{
			parent::__construct();
			if (!DATABASE_TYPE)
				throw new Exception('Type not set');
			if (!DATABASE_HOST)
				throw new Exception('Host not set');
			if (!DATABASE_USERNAME)
				throw new Exception('User not set');
			if (!DATABASE_NAME)
				throw new Exception('Database not set');
			$this->type = DATABASE_TYPE;
			$this->host = DATABASE_HOST;
			$this->user = DATABASE_USERNAME;
			$this->pass = DATABASE_PASSWORD;
			$this->database = DATABASE_NAME;
			$this->valid = $this;
		}
		catch (Exception $e)
		{
			$this->valid = false;
			$this->FOGCore->error('Failed: %s->%s(): Error: %s', array(get_class($this), __FUNCTION__, $e->getMessage()));
		}
		return $this->valid;
	}
	/** connect()
	  * @return returns the class as established.
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
					throw new Exception(sprintf('Unknown database type. Check that DATABASE_TYPE is being set in "%s/lib/fog/Config.class.php"', rtrim($_SERVER['DOCUMENT_ROOT'], '/') . dirname($_SERVER['PHP_SELF'])));
			}
			// Database Schema version check
			if ($this->getVersion() < FOG_SCHEMA && !preg_match('#schemaupdater#i', $_SERVER['PHP_SELF']) && !preg_match('#schemaupdater#i',$_SERVER['QUERY_STRING']))
				$this->FOGCore->redirect('?node=schemaupdater');
		}
		catch (Exception $e)
		{
			$this->FOGCore->error('Failed: %s->%s(): Error: %s', array(get_class($this), __FUNCTION__, $e->getMessage()));
		}
		return $this;
	}
	
	/** getVersion() get the version of the schema
	  * @return the version or false (0)
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
		}
		catch (Exception $e)
		{
			$this->FOGCore->error('Failed: %s->%s(): Error: %s', array(get_class($this), __FUNCTION__, $e->getMessage()));
		}
		// Return version OR 0 (for new install) if query failed
		return ($version ? $version : false);
	}
}
