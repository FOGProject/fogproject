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

//@ini_set( "max_execution_time", 120 );
 
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

require_once( "./lib/ReportMaker.class.php" );

echo ( "<div class=\"scroll\">" );
if ( $_GET["userid"] != null )
{
	if ( $_POST["date1"] != null && $_POST["date2"] != null )
	{
		echo ( "<p class=\"title\">FOG User Login History Summary <a href=\"export.php?type=csv\" target=\"_blank\"><img class=\"noBorder\" src=\"images/csv.png\" /></a> <a href=\"export.php?type=pdf\" target=\"_blank\"><img class=\"noBorder\" src=\"images/pdf.png\" /></a></p>" );
		echo ( "<div>" );
			$report = new ReportMaker();
			
			$user = mysql_real_escape_string( base64_decode($_GET["userid"] ) );
			$dte1 = mysql_real_escape_string( $_POST["date1"] );
			$dte2 = mysql_real_escape_string( $_POST["date2"] );
			
			$sql = "SELECT 
					* 
				FROM 
					( SELECT *, utDateTime as tme FROM userTracking WHERE utUserName = '" . $user . "' and utDate between DATE('" . $dte1 . "') and DATE('" . $dte2 . "') ) userTracking
				ORDER BY
					utDateTime";			
			$report->appendHTML("<table cellpadding=0 cellspacing=0 border=0 width=100%>");
			$report->appendHTML( "<tr bgcolor=\"#BDBDBD\"><td><b>&nbsp;Action</b></td><td><b>&nbsp;Username</b></font></td><td><b>&nbsp;Time</b></td><td><b>&nbsp;Description</b></td></tr>");		
				
			$report->addCSVCell("Action");
			$report->addCSVCell("Username");
			$report->addCSVCell("Time");
			$report->addCSVCell("Description");
			$report->endCSVLine();												
				

			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			if ( mysql_num_rows( $res ) > 0 )
			{
				$cnt = 0;
				while( $ar = mysql_fetch_array( $res ) )
				{					
					$bg = "";
					if ( $cnt++ % 2 == 0 ) $bg = "#E7E7E7";
					$report->appendHTML("<tr bgcolor=\"$bg\"><td>&nbsp;" . userTrackerActionToString( $ar["utAction"] ) . "</td><td>&nbsp;" . $ar["utUserName"] . "</td><td>&nbsp;" . $ar["tme"] . "</td><td>&nbsp;" . trimString( $ar["utDesc"], 60 ) . "</td></tr>"  );
					$report->addCSVCell(userTrackerActionToString( $ar["utAction"] ));
					$report->addCSVCell($ar["utUserName"]);
					$report->addCSVCell($ar["tme"]);
					$report->addCSVCell($ar["utDesc"]);
					$report->endCSVLine();						
				}
			}
			else
			{
				$report->appendHTML("<tr><td colspan=\"5\" class=\"centeredCell\">No Entries Found.</td></tr>" );
				$report->addCSVCell("No Entries Found.");
				$report->endCSVLine();						
			}
			
			$report->appendHTML( "</table>" );
			$report->outputReport(ReportMaker::FOG_REPORT_HTML);
			$_SESSION["foglastreport"] = serialize( $report );
		echo ( "</div>" );	
	}
	else
	{
		echo ( "<p class=\"title\">FOG User Login History Summary - Select Date Range</p>" );
		$user = mysql_real_escape_string( base64_decode($_GET["userid"] ) );
		$sql = "SELECT 
					utDate as dte 
				FROM 
					userTracking 
				WHERE 
					utUserName = '" . $user . "' 
				GROUP BY 
					utDate 
				ORDER BY 
					utDate desc";
		$res = mysql_query( $sql, $conn ) or die( mysql_error());	
		echo ( "<form method=\"POST\" name=\"hosts\" action=\"?node=$_GET[node]&sub=$_GET[sub]&f=$_GET[f]&userid=$_GET[userid]\">" );						
			echo ( "<p>Select Start Date<p>" );
			echo ( "<p>" );
			echo ( "<select name=\"date1\" size=\"1\">" );	
				while( $ar = mysql_fetch_array( $res ) )
				{	
					echo ( "<option value=\"" . $ar["dte"] . "\" $sel>" . $ar["dte"] . "</option>" );
				}
			echo ( "</select>" );		
			echo ( "</p>" );
			
			echo ( "<p>Select End Date<p>" );
			echo ( "<p>" );
			echo ( "<select name=\"date2\" size=\"1\">" );	
				mysql_data_seek($res, 0);
				while( $ar = mysql_fetch_array( $res ) )
				{	
					echo ( "<option value=\"" . $ar["dte"] . "\" $sel>" . $ar["dte"] . "</option>" );
				}
			echo ( "</select>" );		
			echo ( "</p>" );
			
			echo ( "<input type=\"submit\" value=\"Search for Entries\" />" );
		echo ( "</form>" );
	}
}
else if ( $_POST["usersearch"] != null )
{
	echo ( "<p class=\"title\">FOG User Login History Summary - Select User</p>" );
	$user = mysql_real_escape_string( $_POST["usersearch"] );
	$user = str_replace( "*", "%", $user );
	$sql = "select utUserName from userTracking where utUserName like '$user' group by utUserName order by utUserName limit 100";
	$res = mysql_query( $sql, $conn ) or die( mysql_error());
	echo ( "<table cellpadding=0 cellspacing=0 border=0 width=50%>");
	echo ( "<tr bgcolor=\"#BDBDBD\"><td>&nbsp;<b>Username</b></td></tr>");		
	$i = 0;
	if ( mysql_num_rows( $res ) > 0 )
	{
		while( $ar =mysql_fetch_array( $res ) )
		{
			$bgcolor = "";
			if ( $i++ % 2 == 0 ) $bgcolor = "#E7E7E7";
			echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;<a href=\"?node=$_GET[node]&sub=$_GET[sub]&f=$_GET[f]&userid=" . base64_encode($ar["utUserName"]) . "\">" . $ar["utUserName"] . "</td></tr>" );		
		}
	}
	else
	{
		echo ( "<tr><td>No Results found!</td></tr>" );
	}
	echo ( "</table>" );
}
else
{
	echo ( "<p class=\"title\">FOG User Login History Summary - Search</p>" );
	echo ( "<form method=\"POST\" name=\"hosts\" action=\"?node=$_GET[node]&sub=$_GET[sub]&f=$_GET[f]\">" );
		echo ( "<p>" );
			echo ( "Enter a username to search for:" );
			echo ( "<input type=\"text\" name=\"usersearch\" />" );
		echo ( "</p>" );
		echo ( "<input type=\"submit\" value=\"Search for User\" />" );
	echo ( "</form>" );
		
}	
echo ( "</div>" );	
?>
