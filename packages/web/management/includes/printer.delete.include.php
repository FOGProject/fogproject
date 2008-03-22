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
echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">Delete printer definition</p>" );
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );
if ( $_GET["id"] != null && is_numeric( $_GET["id"] ) )
{
	$id = mysql_real_escape_string( $_GET["id"] );

	if ( $_GET["rm"] == "1" )
	{
		if ( $id !== null  )
		{
			$sql = "DELETE FROM 
					printers
				WHERE
					pID = '$id'";

			if ( mysql_query( $sql, $conn ) )
			{
				echo ( "Printer has been deleted!" );
			}
			else
			{
				echo( "Failed to delete printer!" );
			}
		}			
		else
			echo( "A required field is null, unable to delete printer!" );
	}
	else
	{	
		$sql = "select * from printers where pID = '$id'";
		$res = mysql_query( $sql, $conn ) or die( mysql_error() );
		if ( $ar = mysql_fetch_array( $res ) )
		{
	

			echo ( "<p>Click on the icon below to delete this printer from the FOG database.</p>" );
			echo ( "<p ><a href=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&rm=1&id=" . $id . "\"><img class=\"link\" src=\"images/delete.png\"></a></p>" );

		}
	}
	
}
echo ( "</div>" );

?>
