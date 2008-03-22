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

if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );
if ( $_GET["id"] != null && is_numeric( $_GET["id"] ) )
{
	$id = mysql_real_escape_string( $_GET["id"] );

	if ( $_POST["update"] != null )
	{
		$model = mysql_real_escape_string( $_POST["model"] );
		$alias = mysql_real_escape_string( $_POST["alias"] ); 
		$port = mysql_real_escape_string( $_POST["port"] );
		$inf = mysql_real_escape_string( $_POST["inf"] );
		$ip = mysql_real_escape_string( $_POST["ip"] );
			
		if ( $id !== null && $model != null && $alias != null && $port != null && $inf != null )
		{
			$sql = "UPDATE 
					printers
				SET 
					pPort = '$port', pDefFile = '$inf', pModel ='$model', pAlias ='$alias', pIP = '$ip'
				WHERE
					pID = '$id'";

			if ( ! mysql_query( $sql, $conn ) )
			{
				msgBox( "Failed to create printer!" );
			}
		}			
		else
			msgBox( "A required field is null, unable to update printer!" );
	}

	echo ( "<div id=\"pageContent\" class=\"scroll\">" );


	
	$sql = "select * from printers where pID = '$id'";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( $ar = mysql_fetch_array( $res ) )
	{
		$model =  $ar["pModel"];
		$alias =  $ar["pAlias"]; 
		$port =  $ar["pPort"];
		$inf = $ar["pDefFile"];
		$ip =  $ar["pIP"];
	
		echo ( "<p class=\"title\">Update printer definition</p>" );
		echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&id=$_GET[id]\">" );
		echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
			echo ( "<tr><td>Printer Model:</td><td><input type=\"text\" name=\"model\" value=\"$model\" /></td></tr>" );
			echo ( "<tr><td>Printer Alias:</td><td><input type=\"text\" name=\"alias\" value=\"$alias\" /></td></tr>" );
			echo ( "<tr><td>Printer Port:</td><td><input type=\"text\" name=\"port\" value=\"$port\" /></td></tr>" );
			echo ( "<tr><td>Print INF File:</td><td><input type=\"text\" name=\"inf\" value=\"$inf\" /></td></tr>" );	
			echo ( "<tr><td>Print IP (optional):</td><td><input type=\"text\" name=\"ip\" value=\"$ip\" /></td></tr>" );	
			echo ( "<tr><td colspan=2><center><br /><input type=\"hidden\" name=\"update\" value=\"1\" /><input type=\"submit\" value=\"Update Printer\" /></center></td></tr>" );
		echo ( "</table></center>" );
		echo ( "</form>" );
	}
	echo ( "</div>" );
}


?>
