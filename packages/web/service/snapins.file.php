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

if ( isset($_GET["mac"] ) && isset( $_GET["taskid"] ) )
{
	$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
	if ( $conn )
	{
		if ( ! @mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "#!db" );
		
		$mac = mysql_real_escape_string( strtolower($_GET["mac"]) );
		$taskid = mysql_real_escape_string( $_GET["taskid"] );

		if ( isValidMACAddress( $mac ) )
		{
			if ( ! getCountOfActiveTasksWithMAC( $conn, $mac ) > 0 )
			{
				$hostid = mysql_real_escape_string(getHostID( $conn, $mac ));
				if ( $hostid !== null && is_numeric( $hostid ) )
				{
					$sql = "SELECT 
					 		sFilePath 
					 	FROM 
							snapinTasks
							inner join snapinJobs on ( snapinTasks.stJobID = snapinJobs.sjID )
							inner join snapins on ( snapins.sID = snapinTasks.stSnapinID )
						WHERE
							stState in ( '0', '1' ) and
							sjHostID = '$hostid' and 
							stID = '$taskid'";	
							
					$res = @mysql_query( $sql, $conn ) or die("#!db");
					if ( mysql_num_rows( $res ) > 0 )
					{			
						if ( $ar = mysql_fetch_array( $res ) )
						{
							$strFile = mysql_real_escape_string( $ar["sFilePath"] );
							if ( file_exists( $strFile ) && is_readable( $strFile ) )
							{
								header ("Cache-Control: must-revalidate, post-check=0, pre-check=0");
								header ("Content-Description: File Transfer");
								header ("Content-Type: application/octet-stream");
								header("Content-Length: " . filesize($strFile));
								header("Content-Disposition: attachment; filename=" . basename($strFile));
								@readfile($strFile); 	
								
								$sql = "update snapinTasks set stState = '2' where stID = '$taskid'";
								@mysql_query( $sql, $conn );
							}		
						}
					}
				}
			}
		}
	}
}

?>
