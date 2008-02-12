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

if ( $_POST["add"] != null )
{
		if ( ! snapinExists( $conn, $_POST["name"] ) )
		{
			if ( $_FILES["snapin"] != null  )
			{
				$uploadfile = SNAPINDIR . basename($_FILES['snapin']['name']);
				if ( file_exists( SNAPINDIR ) )
				{
					if ( is_writable( SNAPINDIR ) )
					{
						if ( ! file_exists( $uploadfile ) )
						{
							if (move_uploaded_file($_FILES['snapin']['tmp_name'], $uploadfile))					
							{
								$name = mysql_real_escape_string( $_POST["name"] );
								$description = mysql_real_escape_string( $_POST["description"] );
								$args = mysql_real_escape_string( $_POST["args"] );
								$file = mysql_real_escape_string(  $uploadfile );
								$blReboot = "0";
								if ( $_POST["reboot"] == "on" )
								{
									$blReboot = "1";
								}
								
								$user = mysql_real_escape_string( $currentUser->getUserName() );
								$sql = "insert into snapins(sName, sDesc, sFilePath, sArgs, sCreateDate, sCreator, sReboot) values('$name', '$description', '$file', '$args', NOW(), '$user', '$blReboot' )";
								if ( mysql_query( $sql, $conn ) )
								{
									msgBox( "Snapin Added, you may now add another." );
									lg( "Snapin Added :: $name" );
								}
								else
								{
									msgBox( "Failed to add snapin." );
									lg( "Failed to add snapin :: $name " . mysql_error()  );
								}
							}
							else
							{
								msgBox( "Failed to add snapin, file upload failed." );
								lg( "Failed to add snapin, file upload failed."  );							
							}
						}
						else
						{
							msgBox( "Failed to add snapin, file already exists." );
							lg( "Failed to add snapin, file already exists."  );				
						}
					}
					else
					{
						msgBox( "Failed to add snapin, snapin directory exists, but isn't writable." );
						lg( "Failed to add snapin, snapin directory exists, but isn't writable."  );					
					}
				}
				else
				{
					msgBox( "Failed to add snapin, unable to locate snapin directory." );
					lg( "Failed to add snapin, unable to locate snapin directory"  );				
				}
			}
			else
			{
				msgBox( "Failed to add snapin, no file was uploaded." );
				lg( "Failed to add snapin, no file was uploaded." );			
			}
		}
}

echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">Add new Snapin definition</p>" );
echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]\" enctype=\"multipart/form-data\">" );
echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
	echo ( "<tr><td>Snapin Name:</td><td><input class=\"smaller\" type=\"text\" name=\"name\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Snapin Description:</td><td><textarea class=\"smaller\" name=\"description\" rows=\"5\" cols=\"65\"></textarea></td></tr>" );
	echo ( "<tr><td>Snapin File:</td><td><input class=\"smaller\" type=\"file\" name=\"snapin\" value=\"\" /> <span class=\"lightColor\"> Max Size: " . ini_get("post_max_size") .  "</span></td></tr>" );
	echo ( "<tr><td>Snapin Arguments:</td><td><input class=\"smaller\" type=\"text\" name=\"args\" value=\"\" /></td></tr>" );	
	echo ( "<tr><td>Reboot after install:</td><td><input type=\"checkbox\" name=\"reboot\" /></td></tr>" );		
	echo ( "<tr><td colspan=2><center><br /><input type=\"hidden\" name=\"add\" value=\"1\" /><input class=\"smaller\" type=\"submit\" value=\"Add\" /></center></td></tr>" );				
echo ( "</table></center>" );
echo ( "</form>" );
echo ( "</div>" );		

?>
