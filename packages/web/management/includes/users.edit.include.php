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

if ( $_GET["rmuserid"] != null && is_numeric( $_GET["rmuserid"] ) )
{
	$rmid = mysql_real_escape_string( $_GET["rmuserid"] );
	if ( $_GET["confirm"] != "1" )
	{
		$sql = "select * from users where uId = '$rmid'";
		$res = mysql_query( $sql, $conn ) or die( mysql_error() );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			echo ( "<div class=\"scroll\">" );
			echo ( "<p class=\"title\">Confirm User Removal</p>" );
			echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&rmuserid=$rmid&confirm=1\">" );
			echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
				echo ( "<tr><td><font class=\"smaller\">User Name:</font></td><td><font class=\"smaller\">" . $ar["uName"] . "</font></td></tr>" );
				echo ( "<tr><td colspan=2><font class=\"smaller\"><center><br /><input class=\"smaller\" type=\"submit\" value=\"Yes, delete this user\" /></center></font></td></tr>" );				
			echo ( "</table></center>" );
			echo ( "</form>" );
			echo ( "</div>" );		
		}
	}
	else
	{

		$sql = "delete from users where uId = '" . $rmid . "'";
		if ( mysql_query( $sql, $conn ) )
		{
			echo ( "<div class=\"scroll\">" );
			echo ( "<p class=\"title\">User Removal Complete</p>" );		
			echo ( "User has been deleted" );
			echo ( "</div>" );
			lg( "user deleted :: $rmid" );				
		}
		else
			echo ( mysql_error() );
	}	
}
else
{
	echo ( "<div class=\"scroll\">" );
	echo ( "<p class=\"title\">Edit User Information</p>" );
	
	if ( $_POST["update"] != null && is_numeric( $_POST["update"] ) )
	{
		$uId = mysql_real_escape_string( $_POST["update"] );
		$name = mysql_real_escape_string( $_POST["name"] );
		if ( ! userExists( $conn, $name, $uId ) )
		{
			if ( $_POST["p1"] != null )
			{
				// update username and password
				if ( isValidPassword( $_POST["p1"], $_POST["p2"] ) )
				{
					$password = mysql_real_escape_string( $_POST["p1"] );
					$sql = "update users set uName = '$name', uPass = MD5('$password') where uId = $uId";
					if ( mysql_query( $sql, $conn ) )
					{
						msgbox( "Username and password have been updated!" );
						lg( "user updated :: $uId" );				
					}
					else
						echo ( mysql_error() );					
					
				}
				else
				{
					msgBox( "Invalid Password!" );
				}
			}
			else
			{
				// update username only
				$sql = "update users set uName = '$name' where uId = $uId";
				if ( mysql_query( $sql, $conn ) )
				{
					msgbox( "User Updated!" );
					lg( "user updated :: $uId" );				
				}
				else
					echo ( mysql_error() );					
			}	
		}
		else
		{
			msgBox( "Another user exists with this username." );
		}
	}
	
	$sql = "select * from users where uId = '" . mysql_real_escape_string( $_GET["userid"] ) . "'";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( $ar = mysql_fetch_array( $res ) )
	{
	
		echo ( "<center>" );
		if ( $_GET["tab"] == "gen" || $_GET["tab"] == "" )
		{
			echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&userid=$_GET[userid]\">" );
			echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
				echo ( "<tr><td><font class=\"smaller\">User Name:</font></td><td><input class=\"smaller\" type=\"text\" name=\"name\" value=\"" . $ar["uName"] . "\" /></td></tr>" );
				echo ( "<tr><td><font class=\"smaller\">New Password:</font></td><td><input type=\"password\" name=\"p1\" value=\"\" /></td></tr>" );
				echo ( "<tr><td><font class=\"smaller\">New Password (confirm):</font></td><td><input type=\"password\" name=\"p2\" value=\"\" /></td></tr>" );
				echo ( "<tr><td colspan=2><font class=\"smaller\"><center><br /><input type=\"hidden\" name=\"update\" value=\"" . $ar["uId"] . "\" /><input class=\"smaller\" type=\"submit\" value=\"Update\" /></center></font></td></tr>" );				
			echo ( "</table>" );
			echo ( "</form>" );
		}
		else if ( $_GET["tab"] == "delete" )
		{
			echo ( "<p>Are you sure you wish to remove this user?</p>" );
			echo ( "<p><a href=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&rmuserid=" . $ar["uId"] . "\"><img class=\"link\" src=\"images/delete.png\"></a></p>" );
		}
		echo ( "</center>" );
	}
	echo ( "</div>" );	
}	
?>
