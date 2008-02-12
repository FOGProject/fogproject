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
 *  "#!ih" => Invalid Host format
 *  "#!ma" => Mac address already exists.
 *  "#!er" => Other error.
 *  "#!ok" => registration successful.
 *
 */


if ( isset($_GET["mac"] ) && isset($_GET["hostname"] ) )
{
	$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
	if ( $conn )
	{
		if ( ! @mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "#!db" );
		
		$mac = mysql_real_escape_string( strtolower($_GET["mac"]) );
		$hostname = mysql_real_escape_string( $_GET["hostname"] );
		$ip = mysql_real_escape_string( $_GET["ip"] );
		$os = mysql_real_escape_string( $_GET["os"] );

		if ( ! isValidIPAddress( $ip ) )
			$ip = "";

		if ( ! is_numeric( $os ) )
			$os = "";
		
		if ( isValidMACAddress( $mac ) )
		{
			if ( isSafeHostName( $hostname ) )
			{			
				$sql = "select count(*) as cnt from hosts where hostMAC = '" . $mac . "'";
				$res = mysql_query( $sql, $conn ) or die( "#!db" );
				while( $ar = mysql_fetch_array( $res ) )
				{
					if ( $ar["cnt"] == 0 )
					{
						$desc = mysql_real_escape_string("Created by FOG Service on " . date("F j, Y, g:i a") );
						$sql = "insert into hosts(hostName, hostDesc, hostIP, hostCreateDate, hostCreateBy, hostMAC, hostOS) 
						                    values('" . $hostname . "', '" . $desc . "', '" . $ip . "', NOW(), 'FOGSERVICE', '" . $mac . "', '" . $os . "')";
						if ( mysql_query( $sql, $conn ) )
							echo "#!ok";
						else
							echo "#!db";
					}
					else
						echo "#!ma";
				}
			}
			else
				echo "#!ih";
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
