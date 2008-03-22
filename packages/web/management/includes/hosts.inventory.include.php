<?php
/*
 *  FOG is a computer imaging solution.
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

		$prim = mysql_real_escape_string( $_POST["pu"] );
		$other1 = mysql_real_escape_string( $_POST["other1"] );
		$other2 = mysql_real_escape_string( $_POST["other2"] );
		$sql = "update inventory set iPrimaryUser = '$prim', iOtherTag = '$other1', iOtherTag1 ='$other2' where iHostID = '$id'";
		if ( !mysql_query( $sql, $conn ) )
		{
			msgBox( mysql_error() );
		}
	}
	
	echo ( "<div class=\"scroll\">" );
	
	if ( is_numeric( $id ) )
	{

		echo ( "<p class=\"title\">Host Hardware Inventory</p>" );
		echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&id=$_GET[id]\">" );
		
		echo ( "<table cellpadding=0 cellspacing=0 border=0 width=100%>" );
				$sql = "SELECT 
						* 
					FROM 
						inventory
					WHERE
						iHostID = '$id'";
				$res = mysql_query( $sql, $conn ) or die( mysql_error() );
				if ( mysql_num_rows( $res ) > 0 )
				{
					while ( $ar = mysql_fetch_array( $res ) )
					{
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Primary User</td><td>&nbsp;<input type=\"text\" value=\"" . $ar["iPrimaryUser"] . "\" name=\"pu\" /></td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Other Tag #1</td><td>&nbsp;<input type=\"text\" value=\"" . $ar["iOtherTag"] . "\" name=\"other1\" /></td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Other Tag #2</td><td>&nbsp;<input type=\"text\" value=\"" . $ar["iOtherTag1"] . "\" name=\"other2\" /></td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;System Manufacturer</td><td>&nbsp;" . $ar["iSysman"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;System Product</td><td>&nbsp;" . $ar["iSysproduct"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;System Version</td><td>&nbsp;" . $ar["iSysversion"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;System Serial Number</td><td>&nbsp;" . $ar["iSysserial"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;System Type</td><td>&nbsp;" . $ar["iSystype"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;BIOS Vendor</td><td>&nbsp;" . $ar["iBiosvendor"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;BIOS Version</td><td>&nbsp;" . $ar["iBiosversion"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;BIOS Date</td><td>&nbsp;" . $ar["iBiosdate"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Motherboard Manufacturer</td><td>&nbsp;" . $ar["iMbman"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Motherboard Product Name</td><td>&nbsp;" . $ar["iMbproductname"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Motherboard Version</td><td>&nbsp;" . $ar["iMbversion"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Motherboard Serial Number</td><td>&nbsp;" . $ar["iMbserial"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Motherboard Asset Tag</td><td>&nbsp;" . $ar["iMbasset"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;CPU Manufacturer</td><td>&nbsp;" . $ar["iCpuman"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;CPU Version</td><td>&nbsp;" . $ar["iCpuversion"] . "</td></tr>" );																		
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;CPU Normal Speed</td><td>&nbsp;" . $ar["iCpucurrent"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;CPU Max Speed</td><td>&nbsp;" . $ar["iCpumax"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Memory</td><td>&nbsp;" . $ar["iMem"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Hard Disk Model</td><td>&nbsp;" . $ar["iHdmodel"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Hard Disk Firmware</td><td>&nbsp;" . $ar["iHdfirmware"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Hard Disk Serial Number</td><td>&nbsp;" . $ar["iHdserial"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Chassis Manufacturer</td><td>&nbsp;" . $ar["iCaseman"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Chassis Version</td><td>&nbsp;" . $ar["iCasever"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Chassis Serial</td><td>&nbsp;" . $ar["iCaseserial"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td>&nbsp;Chassis Asset</td><td>&nbsp;" . $ar["iCaseasset"] . "</td></tr>" );
						echo ( "<tr><td>&nbsp;</td><td colspan='2'><center><input type=\"hidden\" name=\"update\" value=\"1\" /><input type=\"submit\" value=\"Update\" /></center></td></tr>" );
					}
				}
				else
				{
					echo ( "<tr><td colspan=\"3\" class=\"centeredCell\">No Inventory found for this host</td></tr>" );
				}
		echo ( "</table>" );			
		

		
		

		echo ( "</form>" );
		
	}
	else
	{
		echo ( "<center><font class=\"smaller\">Invalid host ID Number.</font></center>" );
	}
	echo ( "</div>" );

}
?>
