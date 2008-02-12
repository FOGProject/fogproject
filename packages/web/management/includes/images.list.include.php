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

echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">All Current Images</p>" );
	$sql = "select * from images order by imageName";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	if ( mysql_num_rows( $res ) > 0 )
	{

		echo ( "<table cellpadding=0 cellspacing=0 border=0 width=100%>" );
		$cnt = 0;
		echo ( "<tr bgcolor=\"#BDBDBD\"><td>&nbsp;<b>Image Title</b></td><td><b>Description</b></td><td><b>File</b></td><td><b>Edit</b></td></tr>" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$bgcolor = "";
			if ( $cnt++ % 2 == 0 ) $bgcolor = "#E7E7E7";
			echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;<a href=\"?node=$_GET[node]&sub=edit&imageid=" . $ar[imageID] . "\" class=\"plainlink\">" . $ar["imageName"] . "</a></td><td>&nbsp;" . trimString($ar["imageDesc"], 20) ."</td><td>&nbsp;" . STORAGE_DATADIR . trimString($ar["imagePath"], 25) ."</td><td><a href=\"?node=$_GET[node]&sub=edit&imageid=" . $ar[imageID] . "\" class=\"plainlink\"><img src=\"images/edit.png\" class=\"link\" /></a></td></tr>" );
		}
		echo ( "</table>" );
	} 
	else
	{
		echo ( "No images found" );
	}	
echo ( "</div>" );		

?>
