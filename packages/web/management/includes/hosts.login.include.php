<?php
/*
 *  FOG - Free, Open-Source Ghost is a computer imaging solution.
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

require_once( "./lib/UserLoginEntry.class.php" );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	$id = mysql_real_escape_string( $_GET["id"] );
	
	echo ( "<div class=\"scroll\">" );
	
	if ( is_numeric( $id ) )
	{
		
		echo ( "<p class=\"title\">Host Login History</p>" );
		
		$dte = mysql_real_escape_string($_POST["dte"]);
		
		echo ( "<p>View History for " );
			
			$sql = "SELECT 
					utDate as dte 
				FROM 
					userTracking 
				WHERE 
					utHostID = '" . $id . "' 
				GROUP BY 
					utDate 
				ORDER BY 
					utDate desc";
			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			echo ( "<form class=\"noMargin\" id=\"dte\" method=\"post\" action=\"?node=$_GET[node]&sub=$_GET[sub]&id=$_GET[id]\">" );
			echo ( "<select name=\"dte\" size=\"1\">" );	
				$blFirst = true;			
				while( $ar = mysql_fetch_array( $res ) )
				{
					if ( $blFirst )
					{
						if ( $dte == null )
							$dte = $ar["dte"];
					}
					
					$sel = "";
					if ( $dte == $ar["dte"] )
						$sel = " selected=\"selected\" "; 
					echo ( "<option value=\"" . $ar["dte"] . "\" $sel>" . $ar["dte"] . "</option>" );
				}
			echo ( "</select> <a href=\"#\" onclick=\"document.getElementById('dte').submit();\"><img src=\"./images/go.png\" class=\"noBorder\" /></a>" );
			echo ( "</form>" );
		echo ( "</p>" );
		$sql = "SELECT 
				* 
			FROM 
				( SELECT *, TIME(utDateTime) as tme FROM userTracking WHERE utHostID = '" . $id . "' and utDate = DATE('" . $dte . "') ) userTracking
			ORDER BY
				utDateTime";
		$res = mysql_query( $sql, $conn ) or die( mysql_error() );
		echo ( "<table cellpadding=0 cellspacing=0 border=0 width=100%>" );
		echo ( "<tr bgcolor=\"#BDBDBD\"><td><b>&nbsp;Action</b></td><td><b>&nbsp;Username</b></font></td><td><b>&nbsp;Time</b></td><td><b>&nbsp;Description</b></td></tr>" );		
		$cnt = 0;
		$arAllUsers = array();
		while( $ar = mysql_fetch_array( $res ) )
		{
			if ( ! in_array( $ar["utUserName"], $arAllUsers ) )
				$arAllUsers[] = $ar["utUserName"];
				
			$bg = "";
			if ( $cnt++ % 2 == 0 ) $bg = "#E7E7E7";
			echo ( "<tr bgcolor=\"$bg\"><td>&nbsp;" . userTrackerActionToString( $ar["utAction"] ) . "</td><td>&nbsp;" . $ar["utUserName"] . "</td><td>&nbsp;" . $ar["tme"] . "</td><td>&nbsp;" . trimString( $ar["utDesc"], 60 ) . "</td></tr>"  );
		}
		echo ( "</table>" );
		
		$_SESSION["fog_logins"] = array();

		for( $i = 0; $i < count( $arAllUsers ); $i++ )
		{
			$sql = "SELECT 
					utDateTime, utAction
				FROM 
					( SELECT *, TIME(utDateTime) as tme FROM userTracking WHERE utUserName = '" . mysql_real_escape_string( $arAllUsers[$i] ) . "' and utHostID = '" . $id . "' and utDate = DATE('" . $dte . "') ) userTracking
				ORDER BY
					utDateTime";	
			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			$tmpUserLogin = null;
			while( $ar = mysql_fetch_array( $res ) )
			{			
				if ( $ar["utAction"] == "1" || $ar["utAction"] == "99" )
				{
					$tmpUserLogin = new UserLoginEntry( $arAllUsers[$i] );					
					$tmpUserLogin->setLogInTime( $ar["utDateTime"] );
					$tmpUserLogin->setClean( ($ar["utAction"] == "1") );
				}
				else if ( $ar["utAction"] == "0" )
				{
					if ( $tmpUserLogin != null )
						$tmpUserLogin->setLogOutTime( $ar["utDateTime"] );


					$_SESSION["fog_logins"][] = serialize( $tmpUserLogin );
					$tmpUserLogin = null;
				}
			}				
		}
		
		if ( count( $_SESSION["fog_logins"] ) > 0 )
			echo ( "<p><img src=\"./phpimages/hostloginhistory.phpgraph.php\" /></p>" );
	}
	else
	{
		echo ( "<center><font class=\"smaller\">Invalid host ID Number.</font></center>" );
	}
	echo ( "</div>" );

}
?>
