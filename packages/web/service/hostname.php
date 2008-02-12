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
 *  "#!nf" => Mac/Hostname not found.
 *  "#!ok=[hostname]" => Hostname found
 *
 */


if ( isset($_GET["mac"] ) )
{
	$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
	if ( $conn )
	{
		if ( ! @mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "#!db" );
		if ( isValidMACAddress( $_GET["mac"] ) )
		{
			$sql = "select * from hosts where hostMAC = '" . mysql_real_escape_string( $_GET["mac"] ) . "'";
			$res = mysql_query( $sql, $conn ) or die( "#!db" );
			if( $ar = mysql_fetch_array( $res ) )
			{
				if ( isSafeHostName( $ar["hostName"] ) )
				{
					echo "#!ok=" .  $ar["hostName"] . "\n";
					echo "#AD=" . $ar["hostUseAD"] . "\n";
					echo "#ADDom=" . $ar["hostADDomain"] . "\n";					
					echo "#ADOU=" . $ar["hostADOU"] . "\n";	
					echo "#ADUser=" . $ar["hostADUser"] . "\n";					
					echo "#ADPass=" . $ar["hostADPass"] ;						
				}	
				else
					echo "#!ih";
				exit;
			}
			echo "#!nf";
		}
		else
		{
			echo "#!im";
		}
	}
	else
	{
		die( "#!db" );
	}
}
else
	echo "#!im";
?>
