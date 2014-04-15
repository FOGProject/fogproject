<?php
class OracleOLD
{
	private $conn;
	private $strUser, $strPass;
	private $strSchema;
	private $strHost;
	private $intPort;
	private $intInsertID;
	private $result;
	public $ROW_ASSOC = 1;	// OCI_ASSOC
	public $ROW_NUM = 2;	// OCI_NUM
	public $ROW_BOTH = 3;	// OCI_BOTH
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
				if ( $this->strSchema != null )
				{
					$strconn = "//" . $this->strHost .  ( ( $this->intPort != null ) ? ( ":" . $this->intPort) : "" ) . "/" . $this->strSchema;
					$this->conn = oci_new_connect( $this->strUser, $this->strPass, $strconn );
					if ( $this->conn != null )
						return true;
					else
						throw new Exception(_("Failed to connect to server").": " . $this->strHost . " "._("Server returned").": " . oci_error() );
				}
				else
					throw new Exception(_("Schema is required to connect to server.") );
			}
			else
				throw new Exception(_("Username is null"));
		}
		else
			throw new Exception(_("Hostname is null"));
	}
	public function setCredentials( $user, $pass )
	{
		$this->strUser = $user;
		$this->strPass = $pass;
	}
	public function setSchema( $schema )
	{
		$this->strSchema = $schema;
		return true;
	}
	public function setHost( $host, $port=null )
	{
		$this->strHost = $host;
		$this->intPort = $port;
	}
	public function begin()
	{
		throw Exception( _("Begin not implemented!") );
	}
	public function rollback()
	{
		throw Exception( _("Rollback not implemented!") );
	}
	public function commit()
	{
		throw Exception( _("Rollback not implemented!") );
	}	
	public function executeUpdate($sql)
	{
		throw Exception( _("executeUpdate not implemented!") );
	}
	public function executeQuery($sql)
	{
		$this->result = null;
		if ( $sql != null && strlen( $sql ) > 0 )
		{
			if ( $this->conn != null )
			{
				$this->result = oci_parse( $this->conn, $sql );
				if ( $this->result != null )
				{
					if ( oci_execute( $this->result ) === true )
						return true;
					else
						throw new Exception(_("Query Failed").": " . oci_error($this->conn) . "SQL:" . $sql );
				}
				else
					throw new Exception(_("Query Failed: parse error").":: " . oci_error($this->conn) );
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
			return oci_close( $this->conn );
		return false;
	}
	public function escape( $string )
	{
		return addslashes( $string );
	}
	public function getNext()
	{
		if ( $this->result != null )
			return oci_fetch_assoc($this->result );
		else
			throw new Exception(_("Result set is null."));
	}
	public function getNumRows()
	{
		if ( $this->result != null )
			return oci_num_rows( $this->result );
		else
			throw new Exception(_("Result set is null."));	
	}
	public function getInsertID()
	{
		$id;
		oci_bind_by_name($this->result, ":id", $id, 20, SQLT_INT);
		oci_execute($this->result);
		return $id;
	}
	// For legacy $conn connections
	public function getLink()
	{
		return $this->conn;
	}
}
