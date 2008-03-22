<?php
/*
 *  FOG a computer imaging solution.
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

echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">All Current Printers</p>" );
	$sql = "select * from printers order by pAlias";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );
	echo ( "<form method=\"POST\" action=\"?node=$_GET[node]\">" );
	echo ( "<table width=\"100%\" cellpadding=0 cellspacing=0>" );
	echo ( "<tr bgcolor=\"#BDBDBD\"><td><b>&nbsp;Model</b></td><td><b>&nbsp;Alias</b></td><td><b>&nbsp;Port</b></td><td><b>&nbsp;INF</b></td><td><b>&nbsp;IP</b></td><td><b>&nbsp;Edit</b></td></tr>" );
	$cnt = 0;
	while( $ar = mysql_fetch_array( $res ) )
	{
		$bg = "";
		if ( $cnt++ % 2 == 0 ) $bg = "#E7E7E7";
		echo ( "<tr bgcolor=\"$bg\"><td>&nbsp;" . trimString( $ar["pModel"], 35 ) . "</td><td>" . trimString( $ar["pAlias"], 35 ) . "</td><td>" . trimString( $ar["pPort"], 35 ) . "</td><td>" . trimString( $ar["pDefFile"], 35 ) . "</td><td>" .  $ar["pIP"] . "</td><td>&nbsp;<a href=\"?node=$_GET[node]&sub=edit&id=" . $ar["pID"] ."\"><img class=\"noBorder\" src=\"images/edit.png\" /></a></td></tr>"  );
	}
	echo ( "</table>" );
	echo ( "</form>" );
echo ( "</div>" );
?>
