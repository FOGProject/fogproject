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

if ( $_GET["rmsnapinid"] != null && is_numeric($_GET["rmsnapinid"]) && $_GET["hostid"] != null && is_numeric($_GET["hostid"]) )
{
	if ( cancelSnapinsForHost( $conn, $_GET["hostid"], $_GET["rmsnapinid"] ) )
	{
		msgBox( "Snapin removed!" );
		lg( "Snapin Task deleted :: " . $_GET["rmtasksnap"] );
	}
	else
		msgBox( "Failed to remove snapin" );
}


echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">All Active Snapins</p>" );

	$sql = "SELECT 
	 		* 
	 	FROM 
			snapinTasks
			inner join snapinJobs on ( snapinTasks.stJobID = snapinJobs.sjID )
			inner join hosts on ( snapinJobs.sjHostID = hosts.hostID )
			inner join snapins on ( snapins.sID = snapinTasks.stSnapinID )
		WHERE
			stState in ( '0', '1' )";
			
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( mysql_num_rows( $res ) > 0 )
	{

		echo ( "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=100%>" );
		$cnt = 0;
		echo ( "<tr bgcolor=\"#BDBDBD\"><td>&nbsp;<b>Host Name</b></td><td><b>Snapin</b></td><td>&nbsp;<b>Start Time</b></td><td>&nbsp;<b>State</b></td><td>&nbsp;<b>Kill</b></td></tr>" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$bgcolor = "";
			if ( $cnt++ % 2 == 0 ) $bgcolor = "#E7E7E7";
			if ( $ar[iState] > 0 )
				$bgcolor = "#B8E2B6";

			$state = "N/A";
			if ( $ar["stState"] == 0 )
				$state = "Queued";
			else if ( $ar["stState"] == 1 )
			{	
				$state = "In Progress";
				$bgcolor = "#B8E2B6";				
			}
			
			$hname = $ar["hostName"];

			echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;" . $hname . "</td><td>" . $ar["sName"] . "</td><td>" . $ar["sjCreateTime"] . "</td><td>" . $state . "</td><td>&nbsp;&nbsp;<a href=\"?node=$_GET[node]&sub=$_GET[sub]&rmsnapinid=" . $ar["sID"] . "&hostid=" . $ar["hostID"] . "\"><img src=\"images/kill.png\" border=0></a></td></tr>" );
		}
		echo ( "</table>" );
	} 
	else
	{
		echo ( "<b>No Active Snapins found</b>" );
	}	
echo ( "</div>" );		
?>
