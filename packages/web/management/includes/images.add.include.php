<?php
/*
 *  FOG - is a computer imaging solution.
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
		if ( ! imageDefExists( $conn, $_POST[name] ) )
		{
			$name = mysql_real_escape_string( $_POST[name] );
			$description = mysql_real_escape_string( $_POST[description] );
			$file = mysql_real_escape_string( $_POST[file] );
			$user = mysql_real_escape_string( $currentUser->getUserName() );
			$dd = "0";
			if ( is_numeric($_POST["imagetype"]) )
				$dd = mysql_real_escape_string($_POST["imagetype"]);			
			$sql = "insert into images(imageName, imageDesc, imagePath, imageDateTime, imageCreateBy, imageDD) values('$name', '$description', '$file', NOW(), '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '$dd' )";
			if ( mysql_query( $sql, $conn ) )
			{
				msgBox( "Image created.<br />You may now add another." );
				lg( "Image Added :: $name" );
			}
			else
			{
				msgBox( "Failed to add image." );
				lg( "Failed to add image :: $name " . mysql_error()  );
			}
		}
}
echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">Add new image definition</p>" );
echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]\">" );
echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
	echo ( "<tr><td>Image Name:</td><td><input class=\"smaller\" type=\"text\" name=\"name\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Image Description:</td><td><textarea class=\"smaller\" name=\"description\" rows=\"5\" cols=\"65\"></textarea></td></tr>" );
	echo ( "<tr><td>Image File:</td><td>" . STORAGE_DATADIR . "<input class=\"smaller\" type=\"text\" name=\"file\" value=\"\" /></td></tr>" );
	echo ( "<tr><td>Image Type:</td><td>" . getImageTypeDropDown(  ) . "</td></tr>" );				
	echo ( "<tr><td colspan=2><center><br /><input type=\"hidden\" name=\"add\" value=\"1\" /><input class=\"smaller\" type=\"submit\" value=\"Add\" /></center></td></tr>" );				
echo ( "</table></center>" );
echo ( "</form>" );
echo ( "</div>" );		

?>
