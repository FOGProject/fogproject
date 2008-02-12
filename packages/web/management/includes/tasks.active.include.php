<?php
/*
 *  FOG is a computer imaging solution.
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

if ( $_GET[rmtask] != null && is_numeric($_GET[rmtask]) )
{
	if ( ! ftpDelete( TFTP_PXE_CONFIG_DIR . "01-" . str_replace ( ":", "-", strtolower($_GET[mac]) ) ) )
	{
		msgBox( "Unable to delete PXE file" );
	}
	
	$sql = "delete from tasks where taskID = '" . mysql_real_escape_string( $_GET[rmtask] ) . "' limit 1";
	if ( mysql_query( $sql, $conn ) )
	{
		msgBox( "Task removed, but if the task was in progress or the computer already booted to the Linux Image you will need to reboot it!" );
		lg( "Task deleted :: $_GET[rmtask]" );
	}
	else
		msgBox( mysql_error());
}

if ( $_GET["forcetask"] != null && is_numeric($_GET["forcetask"]) )
{
	$sql = "update imageJobs set iForce = '1' where iID = '" . mysql_real_escape_string( $_GET[forcetask] ) . "'";
	if ( mysql_query( $sql, $conn ) )
	{
		msgBox( "Task updated to force!" );
		lg( "Task set to Force :: $_GET[forcetask]" );	
	}
}

echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">All Active Tasks</p>" );

	$sql = "select 
	 		* 
	 		from tasks 
	 		inner join hosts on (taskHostID = hostID)
	 		left outer join images on (hostImage = imageID )
	 		where taskState in (0,1) order by taskCreateTime, taskName";	
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( mysql_num_rows( $res ) > 0 )
	{

		echo ( "<center><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=100%>" );
		$cnt = 0;
		echo ( "<tr bgcolor=\"#BDBDBD\"><td><font class=\"smaller\">&nbsp;<b>Force</b></font></td><td><font class=\"smaller\">&nbsp;<b>Task Name</b></font></td><td><font class=\"smaller\"><b>Hostname</b></font></td><td><font class=\"smaller\"><b>IP</b></font></td><td><font class=\"smaller\"><b>MAC</b></font></td><td><font class=\"smaller\">&nbsp;<b>Start Time</b></font></td><td><font class=\"smaller\">&nbsp;<b>Type</b></font></td><td><font class=\"smaller\">&nbsp;<b>State</b></font></td><td><font class=\"smaller\">&nbsp;<b>Kill</b></font></td></tr>" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$bgcolor = "";
			if ( $cnt++ % 2 == 0 ) $bgcolor = "#E7E7E7";
			if ( $ar[iState] > 0 )
				$bgcolor = "#B8E2B6";

			$state = state2text($ar["taskState"]);
			if ( $ar["taskState"] == 0 && hasCheckedIn( $conn, $ar["taskID"] ) )
				$state = "In Line";			

			$hname = $ar["hostName"];
			
			if ( $ar["taskForce"] == "1" )
				$hname = "* " . $hname;
				
			$blAllowForce = false;	
			if ( strtolower($ar["taskType"]) == "d" || strtolower($ar["taskType"]) == "u" )
				$blAllowForce = true;
			echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;" );
			if ( $blAllowForce )
				echo ( "<a href=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&forcetask=" . $ar["taskID"] . "&mac=" . $ar["hostMAC"] ."\"><img src=\"images/force.png\" border=0 /></a>" );
				
			echo ( "</td><td><font class=\"smaller\">&nbsp;" . $ar["taskName"] . "</font></td><td><font class=\"smaller\">" . $hname . "</font></td><td><font class=\"smaller\">" . $ar["hostIP"] . "</font></td><td><font class=\"smaller\">" . $ar["hostMAC"] . "</font></td><td><font class=\"smaller\">" . $ar["taskCreateTime"] . "</font></td><td><font class=\"smaller\">" . getImageAction( $ar["taskType"] ) . "</font></td><td><font class=\"smaller\">" . $state . "</font></td><td>&nbsp;&nbsp;<a href=\"?node=$_GET[node]&sub=$_GET[sub]&rmtask=$ar[taskID]&mac=$ar[hostMAC]\"><img src=\"images/kill.png\" border=0></a></td></tr>" );
		}
		echo ( "</table></center>" );
	} 
	else
	{
		echo ( "<center><b>No Active Tasks found</b></center>" );
	}	
echo ( "</div>" );		
?>
