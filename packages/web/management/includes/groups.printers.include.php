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

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	echo ( "<div id=\"pageContent\" class=\"scroll\">" );
	echo ( "<p class=\"title\">Group Printer Configuration</p>" );
	
	$groupid = mysql_real_escape_string( $_GET["groupid"] );
	
	if ( $_POST["update"] == "1" )
	{
		$members = getImageMembersByGroupID( $conn, $groupid );
		if ( $members != null )
		{
			$updated_level = 0;
			$updated_add = 0;
			$updated_del = 0;
			
			$level = mysql_real_escape_string( $_POST["level"] );
			$padd = mysql_real_escape_string( $_POST["prntadd"] );
			$pdel = mysql_real_escape_string( $_POST["prntdel"] );
			if ( $level !== null && is_numeric($level) && $level >= 0 )
			{
				for( $i = 0; $i < count( $members ); $i++ )
				{
					if ( $members[$i] != null )
					{	
						$sql = "UPDATE 
								hosts
							SET 
								hostPrinterLevel = '$level'
							WHERE 
								hostID = '" . $members[$i]->getID() . "'";
						if ( mysql_query( $sql, $conn ) )
							$updated_level++;
					}			
				}
			}
			
			if ( $padd !== null && is_numeric($padd) && $padd >= 0 )
			{
				for( $i = 0; $i < count( $members ); $i++ )
				{
					if ( $members[$i] != null )
					{
						if ( addPrinter( $conn, $members[$i]->getID(), $padd ) )
						{
							$updated_add++;
						}
					}
				}			
			}
			
			if ( $pdel !== null && is_numeric($pdel) && $pdel >= 0 )
			{
				for( $i = 0; $i < count( $members ); $i++ )
				{
					if ( $members[$i] != null )
					{
						if ( deletePrinterByHost( $conn, $pdel, $members[$i]->getID() ) )
						{
							$updated_del++;
						}
					}
				}			
			}
			
			msgBox( "Management Level Changed on $updated_level hosts<br />Printer added on $updated_add hosts<br />Printer removed on $updated_del hosts<br />" );	
		}				
	}
	
	
	if ( is_numeric( $groupid ) )
	{
		echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&groupid=$_GET[groupid]\">" );
		echo ( "<p>Select Management Level for all Hosts in this group:</p>" );
		echo ( "<p class=\"alignLeft\">" );
				
		echo ( "<input type=\"radio\" name=\"level\" value=\"0\" />No Printer Management<br/>" );
		echo ( "<input type=\"radio\" name=\"level\" value=\"1\" />Add Only<br/>" );
		echo ( "<input type=\"radio\" name=\"level\" value=\"2\" />Add and Remove<br/>" );
		echo ( "</p>" );
					
		echo ( "<div class=\"hostgroup\">" );
			echo("<p>Add new printer to all hosts in this group.</p>");
			echo ( getPrinterDropDown( $conn, "prntadd" ) );
			echo ( "<br /><br /></form>" );
		echo ( "</div>" );
		
		echo ( "<div class=\"hostgroup\">" );
			echo("<p>Remove printer from all hosts in this group.</p>");
			echo ( getPrinterDropDown( $conn, "prntdel" ) );
			echo ( "<br /><br /></form>" );
		echo ( "</div>" );
		
		
		echo ( "<input type=\"hidden\" name=\"update\" value=\"1\" /><input type=\"submit\" value=\"Update\" />" );
		echo ( "</form>" );
		
	}
	else
	{
		echo ( "<center><font class=\"smaller\">Invalid host ID Number.</font></center>" );
	}	
	
	echo ( "</div>" );
}		
?>
