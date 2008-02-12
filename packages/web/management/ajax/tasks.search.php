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

require_once( "../../commons/config.php" );
require_once( "../../commons/functions.include.php" );
require_once( "../lib/ImageMember.class.php" );
$conn = mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
if ( $conn )
{
	if ( ! mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "Unable to select database" );
}
else
{
	die( "Unable to connect to Database" );
}

if ( $_SESSION["allow_ajax_task"] )
{
	if ( $_POST["crit"] != null )
	{
		$crit = mysql_real_escape_string( trim($_POST["crit"]) );
		if ( strlen( $crit ) > 1 || $crit == "%" || $crit == "*" )
		{
			if ( $crit == "*" ) $crit = "%";
			
			$blHost = false;
			$blGroup = false;
			
			$sql = "select * from hosts where hostName like '%$crit%' or hostDesc like '%$crit%' or hostIP like '%$crit%' or hostMAC like '%$crit%'";
			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			$numRows = mysql_num_rows( $res );
			if ( $numRows > 0 )
			{
				echo ( "<p class=\"titleBottomLeft\">Host Results</p>" );
			}			
			
			echo ( "<table width=\"100%\" cellpadding=0 cellspacing=0>" );
			if ( $numRows > 0 )
				echo ( "<tr bgcolor=\"#BDBDBD\"><td><b>&nbsp;Host Name</b></td><td><b>&nbsp;IP Address</b></td><td><b>&nbsp;MAC</b></td><td><b>&nbsp;Send</b></td><td><b>&nbsp;Upload</b></td><td><b>&nbsp;Advanced</b></td></tr>" );
			$cnt = 0;
			while( $ar = mysql_fetch_array( $res ) )
			{	
				$blHost = true;
				$bg = "";
				if ( $cnt++ % 2 == 0 ) $bg = "#E7E7E7";
				echo ( "<tr bgcolor=\"$bg\"><td>&nbsp;" . $ar["hostName"] . "</td><td>" . $ar["hostIP"] . "</td><td>" . $ar["hostMAC"] . "</td><td><center><a href=\"?node=tasks&type=host&direction=down&noconfirm=" . $ar["hostID"] ."\"><img class=\"noBorder\" src=\"images/down.png\" /></a></center></td><td><center><a href=\"?node=tasks&type=host&direction=up&noconfirm=" . $ar["hostID"] ."\"><img class=\"noBorder\" src=\"images/up.png\" /></a></center></td><td><center><a href=\"?node=tasks&sub=advanced&hostid=" . $ar["hostID"] ."\"><img class=\"noBorder\" src=\"images/advanced.png\" /></a></center></td></tr>"  );
			}
			echo ( "</table>" );
			
			$sql = "select * from groups where groupName like '%$crit%'";
			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			$numRows = mysql_num_rows( $res );
			if ( $numRows > 0 )
			{
				echo ( "<p class=\"titleBottomLeft\">Group Results</p>" );
			}	
	
			echo ( "<table width=\"100%\" cellpadding=0 cellspacing=0>" );
			if ( $numRows > 0 )
				echo ( "<tr bgcolor=\"#BDBDBD\"><td><b>&nbsp;Group Name</b></td><td><b>&nbsp;Group Description</b></td><td><b>Members</b></td><td><b>&nbsp;Send</b></td><td><b>&nbsp;Send (Multicast)</b></td><td><b>&nbsp;Advanced</b></td></tr>" );
			$cnt = 0;
			while( $ar = mysql_fetch_array( $res ) )
			{
				$blGroup = true;
				$bg = "";
				if ( $cnt++ % 2 == 0 ) $bg = "#E7E7E7";
				
				$members = getImageMembersByGroupID( $conn, $ar["groupID"] );
				if ( $members != null )
					$count = count( $members );				
				
				echo ( "<tr bgcolor=\"$bg\"><td>&nbsp;" . $ar["groupName"] . "</td><td>" . $ar["groupDesc"] . "</td><td>" . $count . "</td><td><center><a href=\"?node=tasks&type=group&direction=down&noconfirm=" . $ar["groupID"] ."\"><img class=\"noBorder\" src=\"images/down.png\" /></a></center></td><td><center><a href=\"?node=tasks&type=group&direction=downmc&noconfirm=" . $ar["groupID"] ."\"><img class=\"noBorder\" src=\"images/multicast.png\" /></a></center></td><td><center><a href=\"?node=tasks&sub=advanced&groupid=" . $ar["groupID"] ."\"><img class=\"noBorder\" src=\"images/advanced.png\" /></a></center></td></tr>"  );
			}				
			echo ( "</table>" );

			
			if ( ! $blHost && ! $blGroup )
				echo ( "<center><b>No results found for <u>$crit</u></b></center>" );
		}
		else
		{
			echo "<b><center>Search criteria must be longer than 1 character.</center></b>";
		}
	}
	else
	{
		echo "<b><center>Please enter a search criteria.</center></b>";
	}
}
else
{
	echo "<b><center>This page can only be viewed via the FOG Management portal</center></b>";
}
?>
