<?php
class MySqlOLD
{
	private $conn;
	private $strUser, $strPass;
	private $strSchema;
	private $strHost;
	private $intPort;
	private $intInsertID;
	private $result;
	public function __construct( ) 
	{
		$this->conn 		= null;
		$this->setCredentials(null, null);
		$this->setSchema( null );
		$this->setHost( null, null );
		$this->intInsertID 	= -1;
		$this->result 		= null;
	}
	// return boolean
	public function connect()
	{
		$this->conn = null;
		$this->intInsertID = -1;
		if ( $this->strHost != null )
		{
			if ( $this->strUser != null )
			{
				$this->conn = mysql_connect( $this->strHost . ( ( $this->intPort != null ) ? (":" . $this->intPort) : "" ), $this->strUser, $this->strPass, true );
				if ( $this->conn != null )
				{
					if ( $this->strSchema != null && strlen( $this->strSchema ) > 0 )
						return mysql_select_db( $this->strSchema, $this->conn );
					else 
						return true;
				}
				else
					throw new Exception(_("Failed to connect to server").": " . $this->strHost . " "._("Server returned").": " . mysql_error() );
			}
			else
				throw new Exception(_("Username is null"));
		}
		else
			throw new Exception(_("Hostname is null"));
	}
	/* For good old Legacy Reasons :(    */
	public function getNativeConnection()
	{
		return $this->conn;
	}
	public function setCredentials( $user, $pass )
	{
		$this->strUser = $user;
		$this->strPass = $pass;
	}
	public function setSchema( $schema )
	{
		$this->strSchema = $schema;
		if ( $this->conn != null && $this->strSchema != null && strlen( $this->strSchema ) > 0 )
			return mysql_select_db( $this->strSchema, $this->conn );
		else
			return false;
	}
	public function setHost( $host, $port=null )
	{
		$this->strHost = $host;
		$this->intPort = $port;
	}
	public function begin()
	{
		$this->executeQuery( "BEGIN" );
	}
	public function rollback()
	{
		$this->executeQuery( "ROLLBACK" );
	}
	public function commit()
	{
		$this->executeQuery( "COMMIT" );
	}	
	public function executeUpdate($sql)
	{
		$this->result = null;
		if ( $sql != null && strlen( $sql ) > 0 )
		{
			if ( $this->conn != null )
			{
				if ( mysql_query( $sql, $this->conn ) === TRUE )
					return mysql_affected_rows( $this->conn );
				else
					throw new Exception(_("Query Failed").": " . mysql_error($this->conn) );
			}
			else
				throw new Exception(_("database connection is null."));
		}
		else
			throw new Exception(_("SQL query is null"));
	}
	public function executeQuery($sql)
	{
		$this->result = null;
		if ( $sql != null && strlen( $sql ) > 0 )
		{
			if ( $this->conn != null )
			{
				$this->result = mysql_query( $sql, $this->conn );
				
				if ( $this->result != null )
					return true;
				else
					throw new Exception(_("Query Failed").": " . mysql_error($this->conn) );
			}
			else
				throw new Exception(_("database connection is null."));
		}
		else
			throw new Exception(_("SQL query is null"));	
	}
	public function close()
	{
		if ( $this->conn != null )
			return mysql_close( $this->conn );
		return false;
	}
	public function escape( $string )
	{
		return mysql_real_escape_string( $string );
	}
	public function getNext()
	{
		if ( $this->result != null )
			return mysql_fetch_array( $this->result, MYSQL_ASSOC );
		else
			throw new Exception(_("Result set is null."));
	}
	public function getNumRows()
	{
		if ( $this->result != null )
			return mysql_num_rows( $this->result );
		else
			throw new Exception(_("Result set is null."));	
	}
	public function getInsertID()
	{
		return mysql_insert_id( $this->conn );
	}
	public function getLink()
	{
		return $this->conn;
	}
}
