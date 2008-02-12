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
 *  "#!ok" => Job Exists -> GO!
 *  "#!nj" => No Job Exists
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
			if ( getCountOfActiveTasksWithMAC( $conn, $mac ) > 0 )
				echo "#!ok";
			else
				echo "#!nj";	
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
