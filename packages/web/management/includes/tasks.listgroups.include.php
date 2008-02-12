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

		echo ( "<center><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=100%>" );
		$cnt = 0;
		echo ( "<tr bgcolor=\"#BDBDBD\"><td><font class=\"smaller\">&nbsp;<b>Name</b></font></td><td><font class=\"smaller\"><b>Description</b></font></td><td><font class=\"smaller\"><b>Created By</b></font></td><td><font class=\"smaller\"><b>Members</b></font></td><td><font class=\"smaller\">&nbsp;<b>Send</b></font></td><td><b>&nbsp;Send (Multicast)</b></td><td><b>&nbsp;Advanced</b></td></tr>" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$bgcolor = "";
			if ( $cnt++ % 2 == 0 ) $bgcolor = "#E7E7E7";

			$count = 0;
			$members = getImageMembersByGroupID( $conn, $ar["groupID"] );
			if ( $members != null )
				$count = count( $members );
			echo ( "<tr bgcolor=\"$bgcolor\"><td><font class=\"smaller\">&nbsp;$ar[groupName]</font></td><td><font class=\"smaller\">&nbsp;$ar[groupDesc]</font></td><td><font class=\"smaller\">&nbsp;$ar[groupCreateBy]</font></td><td><font class=\"smaller\">&nbsp;$count</font></td><td><center><a href=\"?node=tasks&type=group&direction=down&noconfirm=" . $ar["groupID"] ."\"><img class=\"noBorder\" src=\"images/down.png\" /></a></center></td><td><center><a href=\"?node=tasks&type=group&direction=downmc&noconfirm=" . $ar["groupID"] ."\"><img class=\"noBorder\" src=\"images/multicast.png\" /></a></center></td><td><center><a href=\"?node=tasks&sub=advanced&groupid=" . $ar["groupID"] ."\"><img class=\"noBorder\" src=\"images/advanced.png\" /></a></center></td></tr>" );
		}
		echo ( "</table></center>" );
	} 
	else
	{
		echo ( "No Groups found" );
	}	
echo ( "</div>" );		
?>
