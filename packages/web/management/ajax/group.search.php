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
session_start();
@error_reporting( 0 );

require( "../../commons/config.php" );
require( "../../commons/functions.include.php" );
require( "../lib/ImageMember.class.php" );

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
			
			$sql = "select * from groups where groupName like '%$crit%' or groupDesc like '%$crit%' order by groupName";
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
					echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;<a href=\"?node=group&sub=edit&groupid=$ar[groupID]\" class=\"plainlink\">$ar[groupName]</a></td><td>&nbsp;$ar[groupDesc]</td><td>&nbsp;$ar[groupCreateBy]</td><td>&nbsp;$count</td><td><a href=\"?node=group&sub=edit&groupid=$ar[groupID]\" class=\"plainlink\"><img src=\"images/edit.png\" class=\"link\" /></a></td></tr>" );
				}
				echo ( "</table>" );
			} 
			else
			{
				echo ( "No matches found" );
			}			
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
