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

if ( $_GET["rmsnapinid"] != null && is_numeric( $_GET["rmsnapinid"] ) )
{
	echo ( "<div class=\"scroll\">" );
	$rmid = mysql_real_escape_string( $_GET["rmsnapinid"] );
	if ( $_GET["confirm"] != "1" )
	{
		$sql = "select * from snapins where sID = '$rmid'";
		$res = mysql_query( $sql, $conn ) or die( mysql_error() );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			echo ( "<p class=\"title\">Confirm Snapin Removal</p>" );
			
				echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
				echo ( "<tr><td>Snapin Name:</td><td>" . $ar["sName"] . "</td></tr>" );
				echo ( "<tr><td>Snapin Description:</td><td>" . $ar["sDesc"] . "</td></tr>" );
				echo ( "<tr><td>Snapin File:</td><td>" . $ar["sFilePath"] . "</td></tr>" );
				echo ( "<tr><td>Snapin Arguments:</td><td>" . $ar["sArgs"] . "</td></tr>" );	
				$checked = "No";
				if ( $ar["sReboot"] == "1" )
					$checked = "Yes";
				echo ( "<tr><td>Reboot after install:</td><td>$checked</td></tr>" );				
				echo ( "<tr><td colspan=2><center><br /><form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&rmsnapinid=$_GET[rmsnapinid]&confirm=1&killfile=1\"><input class=\"smaller\" type=\"submit\" value=\"Delete snapin definition, and snapin file.\" /></form></center></td></tr>" );				
			echo ( "</table></center>" );		
		}
	}
	else
	{
		$output = "";
		echo ( "<p class=\"title\">Snapin Removal Results</p>" );
		if ( $_GET["killfile"] == "1" )
		{
			$sql = "select sFilePath from snapins where sID = '" . $rmid . "'";
			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			$file = null;
			while( $ar = mysql_fetch_array( $res ) )
			{
				$file = $ar["sFilePath"];
			}
			
			if ( file_exists( $file ) )
			{
				if ( unlink( $file ) )
				{
					$output .= "snapin file has been deleted.<br />";
				}
				else
				{	
					$output .= "Failed to delete snapin file.<br />";
				}
			}
			else
				$output .= "Failed to locate snapin file.<br />";
		}
		$sql = "delete from snapins where sID = '" . $rmid . "'";
		if ( mysql_query( $sql, $conn ) )
		{
			$output .= "Snapin definition has been removed.<br />";
			lg( "Snapin deleted :: $_GET[delid]" );				
		}
		else
			$output .= mysql_error();
			
		echo $output;
	}
	echo ( "</div>" );	
}
else
{
	if ( $_POST["update"] == "1" && is_numeric( $_POST["snapinid"] ) )
	{
		
		if ( ! snapinExists( $conn, $_POST["name"], $_POST["snapinid"] ) )
		{
		
			$snap = mysql_real_escape_string( $_POST["snapinid"] );
			$name = mysql_real_escape_string( $_POST["name"] );
			$description = mysql_real_escape_string( $_POST["description"] );
			$args = mysql_real_escape_string( $_POST["args"] );
			$blReboot = "0";
			if ( $_POST["reboot"] == "on" )
			{
				$blReboot = "1";
			}
			
			$sql = "update snapins set sName = '$name', sDesc = '$description', sArgs = '$args', sReboot = '$blReboot' where sID = '$snap'";
			if ( mysql_query( $sql, $conn ) )
			{
				lg( "Snapin updated :: $name" );
			}
			else
			{
				msgBox( "Failed to update Snapin." );
				lg( "Failed to update Snapin :: $name " . mysql_error()  );
			}
		}	
	}

	echo ( "<div class=\"scroll\">" );
	echo ( "<p class=\"title\">Edit Snapin definition</p>" );
	
	$snapinid = $_POST["snapinid"];
	if ( $snapinid === null )
		$snapinid = $_GET["snapinid"];
	
	$sql = "select * from snapins where sID = '" . mysql_real_escape_string( $snapinid ) . "'";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( $ar = mysql_fetch_array( $res ) )
	{

		echo ( "<center>" );
		if ( $_GET["tab"] == "gen" || $_GET["tab"] == "" )
		{
			echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&snapinid=$_GET[snapinid]\">" );
			echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
				echo ( "<tr><td>Snapin Name:</td><td><input class=\"smaller\" type=\"text\" name=\"name\" value=\"" . $ar["sName"] . "\" /></td></tr>" );
				echo ( "<tr><td>Snapin Description:</td><td><textarea class=\"smaller\" name=\"description\" rows=\"5\" cols=\"65\">" . $ar["sDesc"] . "</textarea></td></tr>" );
				echo ( "<tr><td>Snapin File:</td><td>" . $ar["sFilePath"] . "</td></tr>" );
				echo ( "<tr><td>Snapin Arguments:</td><td><input class=\"smaller\" type=\"text\" name=\"args\" value=\"" . $ar["sArgs"] . "\" /></td></tr>" );	
				$checked = "";
				if ( $ar["sReboot"] == "1" )
					$checked = "checked=\"checked\"";
				echo ( "<tr><td>Reboot after install:</td><td><input type=\"checkbox\" name=\"reboot\" $checked /></td></tr>" );				
			
				echo ( "<tr><td colspan=2><font><center><br /><input type=\"hidden\" name=\"update\" value=\"1\" /><input type=\"hidden\" name=\"snapinid\" value=\"" . $ar["sID"] . "\" /><input class=\"smaller\" type=\"submit\" value=\"Update\" /></center></font></td></tr>" );				
			echo ( "</table>" );
			echo ( "</form>" );
		}
		else if ( $_GET["tab"] == "delete" )
		{
			echo ( "<p>Are you sure you wish to remove this snapin?</p>" );
			echo ( "<p><a href=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&rmsnapinid=" . $ar["sID"] . "\"><img class=\"link\" src=\"images/delete.png\"></a></p>" );
		}
		echo ( "</center>" );
	}
	echo ( "</div>" );	
}	
?>
