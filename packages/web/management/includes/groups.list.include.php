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
echo ( "<p class=\"title\">All Current Groups</p>" );
	$sql = "select * from groups order by groupName";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( mysql_num_rows( $res ) > 0 )
	{

		echo ( "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=100%>" );
		$cnt = 0;
		echo ( "<tr bgcolor=\"#BDBDBD\"><td>&nbsp;<b>Name</b></td><td><b>Description</b></td><td><b>Created By</b></td><td><b>Members</b></td><td><b>Edit</b></td></tr>" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$bgcolor = "";
			if ( $cnt++ % 2 == 0 ) $bgcolor = "#E7E7E7";

			$count = 0;
			$members = getImageMembersByGroupID( $conn, $ar["groupID"] );
			if ( $members != null )
				$count = count( $members );
			echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;<a href=\"?node=$_GET[node]&sub=edit&groupid=$ar[groupID]\" class=\"plainlink\">$ar[groupName]</a></td><td>&nbsp;$ar[groupDesc]</td><td>&nbsp;$ar[groupCreateBy]</td><td>&nbsp;$count</td><td><a href=\"?node=$_GET[node]&sub=edit&groupid=$ar[groupID]\" class=\"plainlink\"><img src=\"images/edit.png\" class=\"link\" /></a></td></tr>" );
		}
		echo ( "</table>" );
	} 
	else
	{
		echo ( "No Groups found" );
	}	
echo ( "</div>" );		
?>
