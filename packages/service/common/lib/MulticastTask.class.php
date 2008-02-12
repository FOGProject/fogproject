<?php

/*
 *  FOG is a computer imaging solution.
 *  Copyright (C) 2007  Chuck Syperski & Jian Zhang
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 */
 
class MulticastTask
{
	public static function getAllMulticastTasks($conn)
	{
		$arTasks = array();
		if ( $conn != null )
		{
			$sql = "SELECT
					*
				FROM
					multicastSessions
				WHERE
					msState in (0,1)
				ORDER BY 
					msID";
			$res = mysql_query( $sql, $conn );
			while( $ar = mysql_fetch_array( $res ) )
			{
				$sql = "SELECT
						count(*) as cnt
					FROM
						( SELECT * from multicastSessionsAssoc where msID = '" . mysql_real_escape_string( $ar["msID"] ) . "') multicastSessionsAssoc	
						inner join ( select * from tasks where taskState in (0,1) ) tasks on ( tID = taskID )
						inner join hosts on (taskHostID = hostID )";
				$resCnt = mysql_query( $sql, $conn );
				if ( $arCnt = mysql_fetch_array( $resCnt ) )
				{
					$count = $arCnt["cnt"];
					if ( $count > 0 )
					{
						$id = mysql_real_escape_string($ar["msID"]);
						$name = mysql_real_escape_string($ar["msName"]);
						$port = mysql_real_escape_string($ar["msBasePort"]);
						$image = mysql_real_escape_string($ar["msImage"]);
						$isDD = ( $ar["msIsDD"] == "1");
						$eth = mysql_real_escape_string($ar["msInterface"]);
						$arTasks[] = new self( $id, $name, $port, $image, $eth, $count, $isDD );
					}
				}
			}
		}
		return $arTasks;
	}

	private $intID, $strName, $intPort, $strImage, $strEth, $intClients;
	private $blIsDD;
	private $procRef, $arPipes;
	private $deathTime;

	public function __construct($id, $name, $port, $image, $eth, $clients, $blIsDD)
	{
		$this->intID = $id;
		$this->strName = $name;
		$this->intPort = $port;
		$this->strImage = $image;
		$this->strEth = $eth;
		$this->intClients = $clients;
		$this->blIsDD = $blIsDD;	
		$this->deathTime = null;									
	}

	public function getID() { return $this->intID; }
	public function getName() { return $this->strName; }
	public function getImagePath() { return $this->strImage; }
	public function getIsDD() { return $this->blIsDD; }
	public function getProcRef() { return $this->procRef; }
	public function getClientCount() { return $this->intClients; }
	public function getPortBase() { return $this->intPort; }
	public function getInterface() { return $this->strEth; }
	public function getUDPCastLogFile() { return MULTICASTLOGPATH . ".udpcast."  . $this->getID(); }
	
	public function getCMD()
	{
		$interface = "";
		if ( $this->getInterface() != null && strlen($this->getInterface()) > 0 )
		{
			$interface = "--interface " .  $this->getInterface();
		}	
	
		$cmd = "gunzip -c \"" . $this->getImagePath() . "\" | " . UPDSENDERPATH . " --min-receivers " . $this->getClientCount() . "  --portbase " . $this->getPortBase() . " " . $interface . " --half-duplex --ttl 32 --nokbd";		// --max-wait 1800
		return $cmd;
	}

	public function startTask($conn)
	{
		if( $conn != null )
		{
			// remove any old log files in the case that the service gets restarted
			@unlink( $this->getUDPCastLogFile() );
			$descriptor = array( 0 => array("pipe", "r"),  1 => array("file", $this->getUDPCastLogFile(), "w"),  2 => array("file", $this->getUDPCastLogFile(), "w") );
			$pipes;
			$this->procRef = proc_open( $this->getCMD(), $descriptor, $pipes );	
			$this->arPipes = $pipes;
			sleep( 5 );	// make sure app doesn't terminate right after starting
			$sql = "UPDATE multicastSessions set msState = '1' where msID = '" . mysql_real_escape_string($this->intID) . "'";
			mysql_query( $sql, $conn );
			return $this->isRunning();
		}
		return false;
	}

	public function flagAsDead()
	{
		if ( $this->deathTime == null )
			$this->deathTime = time();
	}

	public function canBeSafelyKilled()
	{
		// only return true if it has been 5 minutes since deathTime
		// This allows time for the clients to update the tasks before 
		// killing this one
		return ((time() - $this->deathTime) > 300);
	}

	public function killTask($conn, $blIgnoreDB=false)
	{
		if ( $conn != null || $blIgnoreDB )
		{
			// first clean up the pipes
			for( $i = 0; $i < count( $this->arPipes ); $i++ )
			{
				@fclose( $this->arPipes[$i] );
			}
			
			// now terminate the process
			if ( $this->isRunning() )
			{
				$pid = $this->getPID();
				if ( $pid > 0 )
				{
					@posix_kill( $pid, SIGKILL );
				}
				else
				{
					@proc_terminate( $this->procRef , SIGKILL );
				}
			}
			else
			{
				@proc_close( $this->procRef );
			}
			$this->procRef = null;
			
			@unlink( $this->getUDPCastLogFile() );
			
			if ( ! $blIgnoreDB )
			{
				//update database to show that the task is complete
				$sql = "select tID from multicastSessionsAssoc where msID = '" . mysql_real_escape_string($this->intID) . "'";
				$res = mysql_query( $sql, $conn ) or die( mysql_error() );
				while ($ar = mysql_fetch_array( $res ) )
				{
					$sql = "update tasks set taskState = '2' where taskID = '" . mysql_real_escape_string( $ar["tID"] ) . "'";
					mysql_query($sql, $conn) or die( mysql_error() );
				}
				
				$sql = "UPDATE multicastSessions set msState = '2' where msID = '" . mysql_real_escape_string($this->intID) . "'";
				return mysql_query( $sql, $conn );
			}
			else 
				return true;
		}
		return false;
	}

	public function updateStats($conn)
	{
		if ( $conn != null )
		{
			if ( file_exists( $this->getUDPCastLogFile() ) )
			{
				if ( $this->getID() !== null && is_numeric( $this->getID() ) )
				{
					$fp = fopen($this->getUDPCastLogFile(), 'r');
					fseek($fp, 0, SEEK_END);				
					$max = ftell($fp);
					$amount = (1024);
					$startRead = $max - $amount;

					$file = $this->getImagePath();
					$intImageSize = trim(`stat -c%s $file 2>/dev/null`);
					if ( is_numeric( $intImageSize ) )
					{
						if ( $startRead > 0 )
						{
							fseek($fp, $startRead);
							$data = fread($fp, $amount);
							
							$arData = array();
							
							$tok = strtok($data, "\n\r");
							while ($tok !== false) 
							{
							    $arData[] = $tok;
							    $tok = strtok("\n\r");
							}							
							
							if (count($arData) > 3 ) //assume first and last are garbage
							{
								$arData[0] = null;
								$arData[(count($arData)-1)] = null;
								for($i = (count($arData) - 1); $i >= 0 ; $i--)
								{									
									if( strpos( trim($arData[$i]), "bytes=") === 0 )
									{
										$strStat = $arData[$i];
										$strStat = str_replace( "bytes=", ""  , $strStat );
										$strStat = substr( $strStat, 0, 15 );
										$strStat = str_replace( " ", ""  , $strStat );
										if ( is_numeric( $strStat ) && $strStat >= 0 )
										{
											$percent = round( (($strStat / $intImageSize) * 100) / 2, 2 );
											$sql = "UPDATE multicastSessions set msPercent = '$percent' where msID = '" .  mysql_real_escape_string( $this->getID() ) . "'";
											if ( mysql_query( $sql, $conn ) )
												return $percent;
											else 
												echo( mysql_error() );
										}
									}	
								}
							}
						}
					}
				}
			}
		}
		return -1;
	}

	public function isRunning()
	{
		if ( $this->procRef != null )
		{
			$ar = proc_get_status( $this->procRef );
			return $ar["running"];
		}
		return false;
	}

	public function getPID()
	{
		if ( $this->procRef != null )
		{
			$ar = proc_get_status( $this->procRef );
			return $ar["pid"];
		}
		return -1;	
	}

}
 
?>
