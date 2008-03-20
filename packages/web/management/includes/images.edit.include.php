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

if ( $_GET["rmimageid"] != null && is_numeric( $_GET["rmimageid"] ) )
{
	echo ( "<div class=\"scroll\">" );
	$rmid = mysql_real_escape_string( $_GET["rmimageid"] );
	if ( $_GET["confirm"] != "1" )
	{
		$sql = "select * from images where imageID = '$rmid'";
		$res = mysql_query( $sql, $conn ) or die( mysql_error() );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			echo ( "<p class=\"title\">Confirm Image Removal</p>" );
			echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
				echo ( "<tr><td><font class=\"smaller\">Image Name:</font></td><td><font class=\"smaller\">" . $ar["imageName"] . "</font></td></tr>" );
				echo ( "<tr><td><font class=\"smaller\">Image Description:</font></td><td><font class=\"smaller\">" . $ar["imageDesc"] . "</font></td></tr>" );
				echo ( "<tr><td><font class=\"smaller\">Image File:</font></td><td><font class=\"smaller\">" . STORAGE_DATADIR . $ar["imagePath"] . "</font></td></tr>" );
				echo ( "<tr><td colspan=2><center><br /><form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&rmimageid=$_GET[rmimageid]&confirm=1\"><input class=\"smaller\" type=\"submit\" value=\"Delete only the image definition.\" /></form><br /><form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&rmimageid=$_GET[rmimageid]&confirm=1&killfile=1\"><input class=\"smaller\" type=\"submit\" value=\"Delete image definition, and image file.\" /></form></center></td></tr>" );				
			echo ( "</table></center>" );		
		}
	}
	else
	{
		$output = "";
		echo ( "<p class=\"title\">Image Removal Results</p>" );
		if ( $_GET["killfile"] == "1" )
		{
			$sql = "select imagePath from images where imageID = '" . $rmid . "'";
			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			$file = null;
			while( $ar = mysql_fetch_array( $res ) )
			{
				$file = $ar["imagePath"];
			}
			
			if ( $file !== null )
			{
				if ( ftpDelete( STORAGE_DATADIR . $file ) )
				{
					$output .= "Image file has been deleted.<br />";
				}
				else
				{	
					$output .= "Failed to delete image file.<br />";
				}
			}
			else
				$output .= "Failed to locate image file.<br />";
		}
		$sql = "delete from images where imageID = '" . $rmid . "'";
		if ( mysql_query( $sql, $conn ) )
		{
			$output .= "Image definition has been removed.<br />";
			lg( "image deleted :: $_GET[delid]" );				
		}
		else
			$output .= mysql_error();
			
		echo $output;
	}
	echo ( "</div>" );	
}
else
{
	if ( $_POST["update"] == "1" && is_numeric( $_POST["imgid"] ) )
	{
		if ( ! imageDefExists( $conn, $_POST["name"], $_POST["imgid"] ) )
		{
			$name = mysql_real_escape_string( $_POST["name"] );
			$description = mysql_real_escape_string( $_POST["description"] );
			$file = mysql_real_escape_string( $_POST["file"] );
			$imgid = mysql_real_escape_string( $_POST["imgid"] );
			$dd = "0";
			if ( $_POST["dd"] == "on" )
				$dd = "1";
			$sql = "update images set imageName = '$name', imageDesc = '$description', imagePath ='$file', imageDD = '$dd' where imageID = '$imgid'";
			if ( mysql_query( $sql, $conn ) )
			{
				lg( "Image updated :: $name" );
			}
			else
			{
				msgBox( "Failed to update image." );
				lg( "Failed to update image :: $name " . mysql_error()  );
			}
		}	
	}

	echo ( "<div class=\"scroll\">" );
	echo ( "<p class=\"title\">Edit image definition</p>" );
	$sql = "select * from images where imageID = '" . mysql_real_escape_string( $_GET["imageid"] ) . "'";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( $ar = mysql_fetch_array( $res ) )
	{
		echo ( "<center>" );
		if ( $_GET["tab"] == "gen" || $_GET["tab"] == "" )
		{
			echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&imageid=$_GET[imageid]\">" );
			echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
				echo ( "<tr><td><font class=\"smaller\">Image Name:</font></td><td><input class=\"smaller\" type=\"text\" name=\"name\" value=\"" . $ar["imageName"] . "\" /></td></tr>" );
				echo ( "<tr><td><font class=\"smaller\">Image Description:</font></td><td><textarea class=\"smaller\" name=\"description\" rows=\"5\" cols=\"65\">" . $ar["imageDesc"] . "</textarea></td></tr>" );
				echo ( "<tr><td><font class=\"smaller\">Image File:</font></td><td><font class=\"smaller\">" . STORAGE_DATADIR . "</font><input class=\"smaller\" type=\"text\" name=\"file\" value=\"" . $ar["imagePath"] . "\" /></td></tr>" );
				$checked = "";
				if ( $ar["imageDD"] == "1" )
					$checked="checked=\"checked\"";
				echo ( "<tr><td>Disk Image:</td><td><input type=\"checkbox\" name=\"dd\" $checked /></td></tr>" );			
				echo ( "<tr><td colspan=2><font><center><br /><input type=\"hidden\" name=\"update\" value=\"1\" /><input type=\"hidden\" name=\"imgid\" value=\"" . $ar["imageID"] . "\" /><input class=\"smaller\" type=\"submit\" value=\"Update\" /></center></font></td></tr>" );				
			echo ( "</table>" );
			echo ( "</form>" );
		}
		else if ( $_GET["tab"] == "delete" )
		{
			echo ( "<p>Are you sure you would like to remove this image?</p>" );
			echo ( "<p><a href=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&rmimageid=" . $ar["imageID"] . "\"><img class=\"link\" src=\"images/delete.png\"></a></p>" );
		}
		echo ( "</center>" );
	}
	echo ( "</div>" );	
}	
?>
