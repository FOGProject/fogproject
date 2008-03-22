<?php
/*
 *  FOG  is a computer imaging solution.
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
 *  "#!np" => No Printers found.
 *  "#!mg=x" => management level = x where x is 0, 1, or 2
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
			$hostid = mysql_real_escape_string(getHostID( $conn, $mac ));
			$level = null;
			if ( $hostid !== null && is_numeric( $hostid ) )
			{
				$sql = "SELECT 
						hostPrinterLevel
					FROM
						hosts
					WHERE
						hostID = '$hostid'";
				$res = mysql_query( $sql, $conn ) or die( "#!db" );
				while( $ar = mysql_fetch_array( $res ) )
				{
					$level = $ar["hostPrinterLevel"];
				}
				
				if ( $level == null )
					$level = "0";
			
				if ($level != null)
				{
					echo ( base64_encode("#!mg=" . $level ) . "\n" );
					
					if ( $level > 0 )
					{
						$sql = "SELECT 
						 		* 
						 	FROM 
								printerAssoc
								inner join printers on ( printerAssoc.paPrinterID = printers.pID )
							WHERE
								paHostID = '$hostid'";	
								
								
						$res = @mysql_query( $sql, $conn ) or die(base64_encode("#!db"));
						if ( mysql_num_rows( $res ) > 0 )
						{						
							while( $ar = mysql_fetch_array( $res ) )
							{
								echo base64_encode($ar["pPort"] . "|" .$ar["pDefFile"] . "|" .$ar["pModel"] . "|".$ar["pAlias"] . "|".$ar["pIP"] . "|" .$ar["paIsDefault"]);
								echo ( "\n" );
							}						
						}
					}
				}
			}
			else
			{
				echo base64_encode("#!er");
			}
	
		}
		else
			echo base64_encode("#!im");
	}
	else
		echo base64_encode("#!db") ;
}
else
	echo base64_encode("#!im");
?>
