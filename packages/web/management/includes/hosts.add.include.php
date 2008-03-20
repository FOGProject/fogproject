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

if ( $_POST["add"] != null )
{
	if ( ! hostsExists( $conn, $_POST["mac"] ) )
	{
		$ip = mysql_real_escape_string( $_POST["ip"] );
		$desc = mysql_real_escape_string( $_POST["description"] ); 
		$image = mysql_real_escape_string( $_POST["image"] );
		$mac = mysql_real_escape_string( $_POST["mac"] );
		$hostname = mysql_real_escape_string( $_POST["host"] );
		$os = mysql_real_escape_string( $_POST["os"] );		
		$user = "";
		if ( $currentUser != null )
			$user = mysql_real_escape_string($currentUser->getUserName());

		$useAD = "0";
		if ( $_POST["domain"] == "on" )
			$useAD = "1";
		
		$adDomain = mysql_real_escape_string( $_POST["domainname"] );
		$adOU = mysql_real_escape_string( $_POST["ou"] );
		$adUser = mysql_real_escape_string( $_POST["domainuser"] );
		$adPass = mysql_real_escape_string( $_POST["domainpassword"] );				
			
		if ( createHost( $conn, $mac, $hostname, $ip, $desc, $user, $os, $image, $useAD, $adDomain, $adOU, $adUser, $adPass) )
		{
			msgBox( "Host Added, you may now add another." );
			lg( "New Host Added via management form: " . $hostname );
		}
		else
		{
			msgBox( "Failed to create host!" );
			lg( "Failed add add new host via management form: " . $hostname );
		}			
	}
}
echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">Add new host definition</p>" );
echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]\">" );
echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
	echo ( "<tr><td>Host Name:*</td><td><input class=\"smaller\" type=\"text\" name=\"host\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Host IP:</td><td><input class=\"smaller\" type=\"text\" name=\"ip\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Host MAC:*</td><td><input class=\"smaller\" type=\"text\" name=\"mac\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Host Description:</td><td><textarea name=\"description\" rows=\"5\" cols=\"40\"></textarea></td></tr>" );
	echo ( "<tr><td>Host Image:</td><td>" );
		echo getImageDropDown( $conn );
	echo ( "</td></tr>" );
	echo ( "<tr><td>Host OS:</td><td>" );		
		echo ( getOSDropDown( $conn ) );
	echo ( "</td></tr>" );	
	echo ( "</table>" );
	echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );

	echo ( "<p class=\"titleBottomLeft\">Active Directory</p>" );
				
	echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
		echo ( "<tr><td>Join Domain after image task:</td><td><input class=\"smaller\" type=\"checkbox\" name=\"domain\" /></td></tr>" );
		echo ( "<tr><td>Domain name:</td><td><input class=\"smaller\" type=\"text\" name=\"domainname\" /></td></tr>" );				
		echo ( "<tr><td>Organizational Unit:</td><td><input class=\"smaller\" type=\"text\" name=\"ou\" /> <span class=\"lightColor\">(Blank for default)</span></td></tr>" );				
		echo ( "<tr><td>Domain Username:</td><td><input class=\"smaller\" type=\"text\" name=\"domainuser\" /></td></tr>" );						
		echo ( "<tr><td>Domain Password:</td><td><input class=\"smaller\" type=\"text\" name=\"domainpassword\" /> <span class=\"lightColor\">(Must be encrypted)</span></td></tr>" );											
		echo ( "<tr><td colspan=2><center><br /><input type=\"hidden\" name=\"add\" value=\"1\" /><input class=\"smaller\" type=\"submit\" value=\"Add\" /></center></td></tr>" );				
	echo ( "</table>" );	
echo ( "</table></center>" );
echo ( "</form>" );
echo ( "</div>" );
?>
