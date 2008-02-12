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

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	$id = mysql_real_escape_string( $_GET["id"] );
	
	if ( $_POST["update"] == "1" )
	{
		if ( ! hostsExists( $conn, $_POST[mac], $id ) )
		{
			if ( isValidMACAddress( $_POST["mac"] ) )
			{
				$ip = mysql_real_escape_string( $_POST["ip"] );
				$desc = mysql_real_escape_string( $_POST["description"] ); 
				$image = mysql_real_escape_string( $_POST["image"] );
				$mac = mysql_real_escape_string( $_POST["mac"] );
				$hostname = mysql_real_escape_string( $_POST["host"] );
				$os = mysql_real_escape_string( $_POST["os"] );
				
				$useAD = "0";
				if ( $_POST["domain"] == "on" )
					$useAD = "1";
				
				$adDomain = mysql_real_escape_string( $_POST["domainname"] );
				$adOU = mysql_real_escape_string( $_POST["ou"] );
				$adUser = mysql_real_escape_string( $_POST["domainuser"] );
				$adPass = mysql_real_escape_string( $_POST["domainpassword"] );				
				
				if ( $mac != null && $hostname != null && $os != null && $os != "-1")
				{
					$sql = "update hosts set hostUseAD = '$useAD', hostADDomain = '$adDomain', hostADOU = '$adOU', hostADUser = '$adUser', hostADPass = '$adPass', hostMAC = '$mac', hostIP = '$ip', hostOS = '$os', hostName = '$hostname', hostDesc = '$desc', hostImage = '$image' where hostID = '$id'";
					if ( mysql_query( $sql, $conn ) )
					{
						msgBox( "Host $hostname has been updated." );
						lg( "Host added with MAC address :: $mac" );
					}
					else
					{
						msgBox( mysql_error() );
						lg( "Host add failed :: " . mysql_error() );
					}			
				}
				else
				{
					if ( $mac == null )
					{
						msgBox( "Please enter a valid MAC address." );
					}
					else if ( $hostname == null )
					{
						msgBox( "Please enter a valid Hostname." );
					}
					else if ( $os == null || $os == "-1" )
					{
						msgBox( "Please enter an operating system." );
					}				
				}	
			}
			else
				msgBox( "Invalid MAC address: Must is the the format of 00:00:00:00:00:00" );
					
		}		
	}
	
	if ( $_POST["snap"] !== null && is_numeric( $_POST["snap"] ) && $_POST["snap"] >= 0 )
	{
		$snap = mysql_real_escape_string( $_POST["snap"] );
		$ret = "";
		if ( ! addSnapinToHost( $conn, $id, $snap, $ret ) )
		{
			msgBox($ret);
		}
	}
	
	if ( $_GET["delsnaplinkid"] !== null && is_numeric( $_GET["delsnaplinkid"] ) )
	{
		$snap = mysql_real_escape_string( $_GET["delsnaplinkid"] );
		$ret = "";
		if ( ! deleteSnapinFromHost( $conn, $id, $snap, $ret ) )
		{
			msgBox($ret);
		}
	}
	
	if ( $_GET["delvid"] !== null && is_numeric( $_GET["delvid"] ) )
	{
		$vid = mysql_real_escape_string( $_GET["delvid"] );
		clearAVRecord( $conn, $vid );
	}	
	
	if ( $_GET["delvid"] == "all"  )
	{
		$member = getImageMemberFromHostID( $conn, $id );
		if ( $member != null )
		{
			clearAVRecordsForHost( $conn, $member->getMACColon() );
		}
	}	
	
	echo ( "<div class=\"scroll\">" );
	echo ( "<p class=\"title\">Modify Host</p>" );
	if ( is_numeric( $id ) )
	{
		$sql = "select * from hosts where hostID = '" . $id . "'";
		$res = mysql_query( $sql, $conn ) or die( mysql_error() );
		if ( mysql_num_rows( $res ) == 1 )
		{
			while( $ar = mysql_fetch_array( $res ) )
			{
				echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&id=$_GET[id]\">" );
				echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
					echo ( "<tr><td>Host Name:</td><td><input class=\"smaller\" type=\"text\" name=\"host\" value=\"" . $ar["hostName"] . "\" /></td></tr>" );
					echo ( "<tr><td>Host IP:</td><td><input class=\"smaller\" type=\"text\" name=\"ip\" value=\"" . $ar["hostIP"] . "\" /></td></tr>" );
					echo ( "<tr><td>Host MAC:</td><td><font class=\"smaller\"></font><input class=\"smaller\" type=\"text\" name=\"mac\" value=\"" . $ar["hostMAC"] . "\" /></td></tr>" );
					echo ( "<tr><td>Host Description:</td><td><textarea name=\"description\" rows=\"5\" cols=\"40\">" . $ar["hostDesc"] . "</textarea></td></tr>" );
					echo ( "<tr><td>Host Image:</td><td>" );

					$sql = "select * from images order by imageName";
					$res = mysql_query( $sql, $conn ) or die( mysql_error() );
					echo ( "<select name=\"image\" size=\"1\">" );
					echo ( "<option value=\"\"></option>" );	
					while( $ar1 = mysql_fetch_array( $res ) )
					{
						$selected = "";
						if ( $ar["hostImage"] == $ar1["imageID"] )
							$selected = "selected=\"selected\"";
						echo ( "<option value=\"" . $ar1["imageID"] . "\" $selected>" . $ar1["imageName"] . "</option>" );
					}
					echo ( "</select>" );
					
					echo ( "<tr><td>Host OS:</td><td>" );		
						echo ( getOSDropDown( $conn, $name="os", $ar["hostOS"] ) );
					echo ( "</td></tr>" );
					echo ( "<tr><td colspan=2><font class=\"smaller\"><center><br /><input type=\"hidden\" name=\"update\" value=\"1\" /><input class=\"smaller\" type=\"submit\" value=\"Update\" /></center></font></td></tr>" );				
				echo ( "</table>" );
				
				echo ( "<p class=\"titleBottomLeft\">Active Directory</p>" );
				
				echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
					$usedomain = "";
					if ( $ar["hostUseAD"] == "1" )
						$usedomain = " checked=\"checked\" ";
					echo ( "<tr><td>Join Domain after image task:</td><td><input class=\"smaller\" type=\"checkbox\" name=\"domain\" $usedomain /></td></tr>" );
					echo ( "<tr><td>Domain name:</td><td><input class=\"smaller\" type=\"text\" name=\"domainname\" value=\"" . $ar["hostADDomain"] . "\" /></td></tr>" );				
					echo ( "<tr><td>Organizational Unit:</td><td><input class=\"smaller\" type=\"text\" name=\"ou\" value=\"" . $ar["hostADOU"] . "\" /> <span class=\"lightColor\">(Blank for default)</span></td></tr>" );				
					echo ( "<tr><td>Domain Username:</td><td><input class=\"smaller\" type=\"text\" name=\"domainuser\" value=\"" . $ar["hostADUser"] . "\" /></td></tr>" );						
					echo ( "<tr><td>Domain Password:</td><td><input class=\"smaller\" type=\"text\" name=\"domainpassword\" value=\"" . $ar["hostADPass"] . "\" /> <span class=\"lightColor\">(Must be encrypted)</span></td></tr>" );											
					echo ( "<tr><td colspan=2><center><br /><input class=\"smaller\" type=\"submit\" value=\"Update\" /></center></td></tr>" );					
				echo ( "</table>" );
				
				echo ( "</form>" );
				
				echo ( "<p class=\"titleBottomLeft\">Snapins</p>" );
				echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
						echo ( "<tr bgcolor=\"#BDBDBD\"><td><font class=\"smaller\">&nbsp;<b>Snapin Name</b></font></td><td><font class=\"smaller\"><b>Remove</b></font></td></tr>" );
						$sql = "SELECT 
								* 
							FROM 
								snapinAssoc 
								inner join snapins on ( snapinAssoc.saSnapinID = snapins.sID )
							WHERE
								snapinAssoc.saHostID = '$id'
							ORDER BY
								snapins.sName";
						$resSnap = mysql_query( $sql, $conn ) or die( mysql_error() );
						if ( mysql_num_rows( $resSnap ) > 0 )
						{
							$i = 0;
							while ( $arSp = mysql_fetch_array( $resSnap ) )
							{
								$bgcolor = "";
								if ( $i++ % 2 == 0 ) $bgcolor = "#E7E7E7";
								echo ( "<tr bgcolor=\"$bgcolor\"><td>" . $arSp["sName"] . "</td><td><a href=\"?node=$_GET[node]&sub=$_GET[sub]&id=" . $id . "&delsnaplinkid=" . $arSp["sID"] . "\"><img src=\"images/deleteSmall.png\" class=\"link\" /></a></td></tr>" );
							}
						}
						else
						{
							echo ( "<tr><td colspan=\"2\" class=\"centeredCell\">No snapins linked to this host.</td></tr>" );
						}
				echo ( "</table>" );

				
				

				echo ( "<div class=\"hostgroup\">" );
					echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&id=$_GET[id]\">" );
					echo("<p>Add new snapin package.</p>");
					echo ( getSnapinDropDown( $conn ) );
					echo( "<p><input type=\"submit\" value=\"Add Snapin\" /></p>" );
					echo ( "</form>" );
				echo ( "</div>" );
						
						
				echo ( "<p class=\"titleBottomLeft\">Virus History (<a href=\"?node=$_GET[node]&sub=$_GET[sub]&id=" . $id . "&delvid=all\">clear all history</a>)</p>" );
				echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
						echo ( "<tr bgcolor=\"#BDBDBD\"><td>&nbsp;<b>Virus Name</b></td><td><b>File</b></td><td><b>Mode</b></td><td><b>Date</b></td><td><b>Clear</b></td></tr>" );
						$sql = "SELECT 
								* 
							FROM 
								virus 
							WHERE
								vHostMAC = '" . mysql_real_escape_string(  $ar["hostMAC"] ) . "'
							ORDER BY
								vDateTime, vName";
						$resSnap = mysql_query( $sql, $conn ) or die( mysql_error() );
						if ( mysql_num_rows( $resSnap ) > 0 )
						{
							$i = 0;
							while ( $arSp = mysql_fetch_array( $resSnap ) )
							{
								$bgcolor = "";
								if ( $i++ % 2 == 0 ) $bgcolor = "#E7E7E7";
								echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;<a href=\"http://www.google.com/search?q=" .  $arSp["vName"] . "\" target=\"_blank\">" . $arSp["vName"] . "</a></td><td>" . $arSp["vOrigFile"] . "</td><td>" . avModeToString( $arSp["vMode"] ) . "</td><td>" . $arSp["vDateTime"] . "</td><td><a href=\"?node=$_GET[node]&sub=$_GET[sub]&id=" . $id . "&delvid=" . $arSp["vID"] . "\"><img src=\"images/deleteSmall.png\" class=\"link\" /></a></td></tr>" );
							}
						}
						else
						{
							echo ( "<tr><td colspan=\"5\" class=\"centeredCell\">No Virus Information Reported for this host.</td></tr>" );
						}
				echo ( "</table>" );						
							
				echo ( "<p class=\"titleBottom\"><a href=\"?node=" . $_GET["node"] . "&rmhostid=" . $id . "\"><img class=\"link\" src=\"images/delete.png\"></a></p>" );

			}
		}
	}
	else
	{
		echo ( "<center><font class=\"smaller\">Invalid host ID Number.</font></center>" );
	}
	echo ( "</div>" );

}
?>
