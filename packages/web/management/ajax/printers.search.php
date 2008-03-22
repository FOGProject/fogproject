<?php
/*
 *  FOG is a computer imaging solution.
 *  Copyright (C) 2007  Chuck Syperski & Jian Zhang
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
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
session_start();
@error_reporting( 0 );

require( "../../commons/config.php" );
require( "../../commons/functions.include.php" );
$conn = mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
if ( $conn )
{
	if ( ! mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "Unable to select database" );
}
else
{
	die( "Unable to connect to Database" );
}

if ( $_SESSION["allow_ajax_host"] )
{
	if ( $_POST["crit"] != null )
	{
		$crit = mysql_real_escape_string( trim($_POST["crit"]) );
		if ( strlen( $crit ) > 1 || $crit == "%" || $crit == "*" )
		{
			if ( $crit == "*" ) $crit = "%";
			
			$sql = "select * from printers where pModel like '%$crit%' or pAlias like '%$crit%' or pIP like '%$crit%' or pPort like '%$crit%'";
			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			echo ( "<center>" );
			echo ( "<table width=\"100%\" cellpadding=0 cellspacing=0>" );
			echo ( "<tr bgcolor=\"#BDBDBD\"><td><b>&nbsp;Model</b></td><td><b>&nbsp;Alias</b></td><td><b>&nbsp;Port</b></td><td><b>&nbsp;INF</b></td><td><b>&nbsp;IP</b></td><td><b>&nbsp;Edit</b></td></tr>" );
			$cnt = 0;
			while( $ar = mysql_fetch_array( $res ) )
			{
				$bg = "";
				if ( $cnt++ % 2 == 0 ) $bg = "#E7E7E7";
				echo ( "<tr bgcolor=\"$bg\"><td>&nbsp;" . trimString( $ar["pModel"], 35 ) . "</td><td>" . trimString( $ar["pAlias"], 35 ) . "</td><td>" . trimString( $ar["pPort"], 35 ) . "</td><td>" . trimString( $ar["pDefFile"], 35 ) . "</td><td>" .  $ar["pIP"] . "</td><td>&nbsp;<a href=\"?node=print&sub=edit&id=" . $ar["pID"] ."\"><img class=\"noBorder\" src=\"images/edit.png\" /></a></td></tr>"  );
			}
			echo ( "</table>" );
			echo ( "</center>" );
		}
		else
		{
			echo "<b><center><font class=\"smaller\">Search criteria must be longer than 1 character.</font></center></b>";
		}
	}
	else
	{
		echo "<b><center><font class=\"smaller\">Please enter a search criteria.</font></center></b>";
	}
}
else
{
	echo "<b><center><font class=\"smaller\">This page can only be viewed via the FOG Management portal</font></center></b>";
}
?>
