<?php
class DatabaseManager extends FOGBase {
	/** @var $valid to know if things are valid or not */
	private $valid = false;
	/** @var $DB the Connection as established */
	public $DB;
	/** __construct() initiates the database class
	  * @return if the class is valid or not
	  */
	public function __construct() {
		try {
			parent::__construct();
			if (!DATABASE_TYPE || !DATABASE_HOST || !DATABASE_USERNAME || !DATABASE_NAME)
				throw new Exception('Configuration is missing an item');
			$this->valid = $this;
		}
		catch (Exception $e) {
			$this->error('Failed: %s->%s(): Error: %s', array(get_class($this), __FUNCTION__, $e->getMessage()));
		}
	}
	/** connect()
	  * @return returns the class as established.
	  */
	public function connect() {
		try {
			// Error checking
			if (!$this->valid)
				throw new Exception('Class not constructed correctly');
			// Determine database host type
			switch(DATABASE_TYPE) {
				case 'mysql':
					$this->DB = $this->getClass('MySQL');
					break;
				case 'mssql':
					break;
				case 'oracle':
					$db = new OracleOLD();
					$db->setCredentials(DATABASE_USERNAME,DATABASE_PASSWORD);
					$db->setHost(DATABASE_HOST);
					$db->setSchema($this->DB);
					if ($db->connect())
						$this->DB = $db;
					break;
				default:
					throw new Exception(sprintf('Unknown database type. Check that DATABASE_TYPE is being set in "%s/lib/fog/Config.class.php"', rtrim($_SERVER['DOCUMENT_ROOT'], '/') . dirname($_SERVER['PHP_SELF'])));
			}
			// Database Schema version check
			if ($this->getVersion() < FOG_SCHEMA && !preg_match('#schemaupdater#i', $_SERVER['PHP_SELF']) && !preg_match('#schemaupdater#i',$_SERVER['QUERY_STRING']))
				$this->FOGCore->redirect('?node=schemaupdater');
		}
		catch (Exception $e) {$this->error('Failed: %s->%s(): Error: %s', array(get_class($this), __FUNCTION__, $e->getMessage()));}
		return $this;
	}
	/** getVersion() get the version of the schema
	  * @return the version or false (0)
	  */
	public function getVersion() {return (int)$this->DB->query('SELECT vValue FROM schemaVersion')->fetch()->get('vValue');}
}
