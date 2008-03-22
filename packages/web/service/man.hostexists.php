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
 *  "#!ih" => Invalid Host format
 *  "#!ma" => Mac address already exists.
 *  "#!er" => Other error.
 *  "#!ok" => registration successful.
 *
 */


if ( isset( $_POST["mac"] ) )
{
	$ifconfig = base64_decode( $_POST["mac"] );

	if ( $ifconfig != null )
	{
		$arIfconfig = explode( "HWaddr", $ifconfig  );
		if ( count( $arIfconfig ) == 2 )
		{
			$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
			$mac =  mysql_real_escape_string( strtolower( trim($arIfconfig[1]) ) );
			if ( strlen( trim($mac) ) == 17 )
			{
				if ( isValidMACAddress( $mac ) )
				{			
					if ( $conn )
					{	
						if ( ! @mysql_select_db( MYSQL_DATABASE, $conn ) ) die( mysql_error() );
						

						$sql = "select count(*) as cnt from hosts where hostMAC = '" . $mac . "'";
						$res = mysql_query( $sql, $conn ) or die( mysql_error() );
						while( $ar = mysql_fetch_array( $res ) )
						{
							if ( $ar["cnt"] == 0 )
							{	
								echo "#!ok";
							}
							else
								echo "Host already exists in FOG database!";
						}								

					}
					else
						echo " Unable to connect to database, host not imported!";
				}
				else
					echo ( " Invalid MAC Address format!" );
			}
			else
				echo " Invalid MAC address (3)";				
		}
		else
			echo " Invalid MAC address (2)";			
	}
	else
		echo " Invalid MAC address (1)";		
}
else
	echo " Invalid MAC address (0)";
?>
