<?php
/*
 *  FOG - Free, Open-Source Ghost is a computer imaging solution.
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
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">All Current Users</p>" );
	$sql = "select * from users order by uName";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( mysql_num_rows( $res ) > 0 )
	{

		echo ( "<table cellpadding=0 cellspacing=0 border=0 width=100%>" );
		$cnt = 0;
		echo ( "<tr bgcolor=\"#BDBDBD\"><td>&nbsp;<b>Username</b></td><td><b>Modify</b></td></tr>" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$bgcolor = "";
			if ( $cnt++ % 2 == 0 ) $bgcolor = "#E7E7E7";
			echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;" . $ar["uName"] . "</td><td><a href=\"?node=$_GET[node]&sub=edit&userid=" . $ar["uId"] . "\"><img src=\"images/edit.png\" class=\"link\" /></a></td></tr>" );
		}
		echo ( "</table>" );
	} 
	else
	{
		echo ( "No users found" );
	}	
echo ( "</div>" );		

?> 
