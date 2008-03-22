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
 
@error_reporting(0);

require_once( "../commons/config.php" );
require_once( "../commons/functions.include.php" );

if ( isset( $_GET["action"] ) )
{
	$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
	if ( $conn )
	{
		if ( ! @mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "#!db" );
		
		$file = mysql_real_escape_string( base64_decode( $_GET["file"] ) );
		$action = $_GET["action"];

		if ( $action == "ask" && isset( $_GET["file"] )  )
		{
			$sql = "SELECT 
			 		cuMD5 
			 	FROM 
					clientUpdates
				WHERE
					cuName = '$file'";
					
			$res = @mysql_query( $sql, $conn ) or die("#!db");
			if ( $ar = mysql_fetch_array( $res ) )
			{										
				echo ( $ar["cuMD5"] );
			}
			else
			{
				echo "#!nf";
			}
		}
		else if ( $action == "get" && isset( $_GET["file"] ) )
		{
			$sql = "SELECT 
			 		cuName, 
			 		cuFile 
			 	FROM 
					clientUpdates
				WHERE
					cuName = '$file'";
			
			$res = @mysql_query( $sql, $conn ) or die("#!db");	
			if ( $ar = mysql_fetch_array( $res ) )
			{					
				header ("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header ("Content-Description: File Transfer");
				header ("Content-Type: application/octet-stream");
				header("Content-Disposition: attachment; filename=" . basename($ar["cuName"]));
								
				echo ( base64_decode($ar["cuFile"]) );
			}					
		}
		else if ( $action == "list" )
		{
			$sql = "SELECT 
			 		cuName
			 	FROM 
					clientUpdates
				WHERE
					cuType = 'bin'";
			$res = @mysql_query( $sql, $conn ) or die("#!db");					
			while( $ar = mysql_fetch_array( $res ) )
			{
				echo base64_encode( $ar["cuName"] ) . "\n";
			}
		}
		else
			echo "#!er";		
	}
	else
		echo "#!er";
}
else
	echo "#!er";


?>
