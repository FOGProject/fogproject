<?php
/*
 *  FOG a computer imaging solution.
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

echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">All Current Hosts</p>" );
	$sql = "select * from hosts order by hostName";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	echo ( "<form method=\"POST\" name=\"hosts\" action=\"?node=host\">" );
	echo ( "<input type=\"hidden\" name=\"frmSub\" value=\"1\" />" );
	echo ( "<table width=\"100%\" cellpadding=0 cellspacing=0>" );
	echo ( "<tr bgcolor=\"#BDBDBD\"><td><input type=\"checkbox\" name=\"no\" onClick=\"if ( this.checked!=true ) uncheckAll(document.hosts.elements); else checkAll(document.hosts.elements);\" checked=\"checked\" /></td><td><font class=\"smaller\"><b>&nbsp;Host Name</b></font></td><td><font class=\"smaller\"><b>&nbsp;IP Address</b></font></td><td><font class=\"smaller\"><b>&nbsp;MAC</b></font></td><td><font class=\"smaller\"><b>&nbsp;Edit</b></font></td></tr>" );
	$cnt = 0;
	while( $ar = mysql_fetch_array( $res ) )
	{
		$bg = "";
		if ( $cnt++ % 2 == 0 ) $bg = "#E7E7E7";
		echo ( "<tr bgcolor=\"$bg\"><td><input type=\"checkbox\" name=\"HID" . $ar["hostID"] . "\" checked=\"checked\" /></td><td><font class=\"smaller\">" . $ar["hostName"] . "</font></td><td><font class=\"smaller\">" . $ar["hostIP"] . "</font></td><td><font class=\"smaller\">" . $ar["hostMAC"] . "</font></td><td><font class=\"smaller\">&nbsp;<a href=\"?node=host&sub=edit&id=" . $ar["hostID"] ."\"><img class=\"noBorder\" src=\"images/edit.png\" /></a></font></td></tr>"  );
	}
	echo ( "</table>" );
	
	if ( mysql_num_rows( $res ) > 0 )
	{
		echo ( "<div class=\"hostgroup\">" );
			echo ( "Create new group: " );
			echo ( "<input type=\"text\" name=\"newgroup\" class=\"smaller\" />" );
			echo ( "<br />or<br />" );
			echo ( "Add to group " );
			$sql = "SELECT groupName from groups order by groupName";
			$res_gr = mysql_query( $sql, $conn ) or die( mysql_error() );
			echo ( "<select name=\"grp\" size=\"1\">" );
			echo ( "<option value=\"-1\">Select a group</option>" );
			while( $ar_gr = mysql_fetch_array( $res_gr ) )
			{
				echo ( "<option value=\"$ar_gr[groupName]\">$ar_gr[groupName]</option>" );
			}
			echo ( "</select>" );
			
			echo ( "<br /><br /><input type=\"submit\" value=\"Process Group Changes\" />" );
		echo ( "</div>" );
	}
	echo ( "</form>" );
echo ( "</div>" );
?>
