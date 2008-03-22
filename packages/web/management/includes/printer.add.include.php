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

if ( $_POST["add"] != null )
{
	$model = mysql_real_escape_string( $_POST["model"] );
	$alias = mysql_real_escape_string( $_POST["alias"] ); 
	$port = mysql_real_escape_string( $_POST["port"] );
	$inf = mysql_real_escape_string( $_POST["inf"] );
	$ip = mysql_real_escape_string( $_POST["ip"] );
		
	if ( $model != null && $alias != null && $port != null && $inf != null )
	{
		$sql = "INSERT INTO 
				printers( pPort, pDefFile, pModel, pAlias, pIP )
				values( '$port', '$inf', '$model', '$alias', '$ip' )";
		if ( mysql_query( $sql, $conn ) )
		{
			msgBox( "Printer Added, you may now add another." );
		}
		else
		{
			msgBox( "Failed to create printer!" );
		}
	}			
	else
		msgBox( "A required field is null, unable to create printer!" );
}
echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">Add new printer definition</p>" );
echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]\">" );
echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
	echo ( "<tr><td>Printer Model:</td><td><input type=\"text\" name=\"model\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Printer Alias:</td><td><input type=\"text\" name=\"alias\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Printer Port:</td><td><input type=\"text\" name=\"port\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Print INF File:</td><td><input type=\"text\" name=\"inf\" value=\"\" /></td></tr>" );	
	echo ( "<tr><td>Print IP (optional):</td><td><input type=\"text\" name=\"ip\" value=\"\" /></td></tr>" );	
	echo ( "<tr><td colspan=2><center><br /><input type=\"hidden\" name=\"add\" value=\"1\" /><input type=\"submit\" value=\"Add Printer\" /></center></td></tr>" );
echo ( "</table></center>" );
echo ( "</form>" );
echo ( "</div>" );
?>
