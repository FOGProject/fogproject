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

/*
 *  Possible return codes
 *  "#!db" => Database error
 *  "#!im" => Invalid MAC Format
 *  "#!ac" => Invalid Action Command
 *  "#!nh" => Host Not Found
 *  "#!us" => Invalid User
 *  "#!er" => Other error.
 *  "#!ok" => record accepted!
 *
 */


if ( isset($_GET["mac"] ) && isset($_GET["action"] ) )
{
	$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
	if ( $conn )
	{
		if ( ! @mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "#!db" );
		
		$mac = mysql_real_escape_string( strtolower( base64_decode($_GET["mac"] ) ) );
		$action = strtolower( base64_decode($_GET["action"] ) );
		$user = trim( strtolower( base64_decode( $_GET["user"] ) ) );
		
		$arUser = explode( chr(92), $user );

		if ( count( $arUser ) == 2 )
			$user = $arUser[1];
		
		$user = mysql_real_escape_string($user);
					
		$date = mysql_real_escape_string( base64_decode( $_GET["date"] ) );
		
		if ( $action == "login" || $action == "logout" || $action == "start" )
		{
			$actionText = "";
			if ( $action == "login" )
			{
				if ( $user == null ) die( "#!us" );
				$actionText = "1";
			}
			else if ( $action == "start" )
				$actionText = "99";
			else
			{
				$actionText = "0";
			}
			
			$desc = "''";
			if ( strlen( trim($date) ) == 0 )
			{
				$date = " NOW() ";
			}
			else
			{
				$desc = " concat('Replay from journal: real insert time: ', NOW() ) ";
				$date = " '$date' ";
			}
			
			if ( isValidMACAddress( $mac ) )
			{

					$hostid = mysql_real_escape_string(getHostID( $conn, $mac ));
					if ( $hostid !== null && is_numeric( $hostid ) )
					{
						$sql = "INSERT INTO 
								userTracking(utHostID, utUserName, utAction, utDateTime, utDesc, utDate)
								values( '$hostid', '$user', '$actionText', $date,  $desc, DATE($date) )";	
								
						if ( mysql_query( $sql, $conn ) )
							echo "#!ok";
						else
							echo "#!db" ;
					}
					else
					{
						echo "#!nh";
					}	
			}
			else
				echo "#!im";
		}
		else
			echo "#!ac";
			
	}
	else
		echo "#!db" ;
}
else
	echo "#!im";
?>
