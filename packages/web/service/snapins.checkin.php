<?php
/*
 *  FOG - Free, Open-Source Ghost is a computer imaging solution.
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
@error_reporting(0);
require_once( "../commons/config.php" );
require_once( "../commons/functions.include.php" );

/*
 *  Possible return codes
 *  "#!db" => Database error
 *  "#!im" => Invalid MAC Format
 *  "#!er" => Other error.
 *  "#!it" => Image task exists -> no snapins to be installed
 *  "#!ns" => No Snapins found
 *  "#!ok" => Job Exists -> GO!
 *
 */


if ( isset($_GET["mac"] ) )
{
	$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
	if ( $conn )
	{
		if ( ! @mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "#!db" );
		
		$mac = mysql_real_escape_string( strtolower($_GET["mac"]) );

		if ( isValidMACAddress( $mac ) )
		{
			if ( ! getCountOfActiveTasksWithMAC( $conn, $mac ) > 0 )
			{
				$hostid = mysql_real_escape_string(getHostID( $conn, $mac ));
				if ( $hostid !== null && is_numeric( $hostid ) )
				{
					$sql = "SELECT 
					 		* 
					 	FROM 
							snapinTasks
							inner join snapinJobs on ( snapinTasks.stJobID = snapinJobs.sjID )
							inner join hosts on ( snapinJobs.sjHostID = hosts.hostID )
							inner join snapins on ( snapins.sID = snapinTasks.stSnapinID )
						WHERE
							stState in ( '0', '1' ) and
							sjHostID = '$hostid'";	
							
					$res = @mysql_query( $sql, $conn ) or die("#!db");
					if ( mysql_num_rows( $res ) > 0 )
					{			
						if ( $ar = mysql_fetch_array( $res ) )
						{
							// we only want to first record
							
							$stID = mysql_real_escape_string( $ar["stID"] );
							
							// Do a checkin
							$sql = "update snapinTasks set stState = '1', stCheckinDate = NOW() where stID = '" . $stID . "'";
							if( mysql_query( $sql, $conn ) )
							{							
								echo "#!ok\n";
								echo "JOBTASKID=" . trim($ar["stID"]) . "\n" ;
								echo "JOBCREATION=" . trim($ar["sjCreateTime"]) . "\n";
								echo "SNAPINNAME=" . trim($ar["sName"]) . "\n";
								echo "SNAPINARGS=" . trim( $ar["sArgs"] ) . "\n";
								echo "SNAPINBOUNCE=" . trim( $ar["sReboot"] ) . "\n";
								echo "SNAPINFILENAME=" . trim( basename($ar["sFilePath"]) );
							}
							else
								echo "#!db" ;
							
						}
					}
					else
					{
						echo "#!ns";
					}
				}
				else
				{
					echo "#!er";
				}
			}
			else
				echo "#!it";	
		}
		else
			echo "#!im";
	}
	else
		echo "#!db" ;
}
else
	echo "#!im";
?>
