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

$uploadErrors = "";
$numSuccess = 0;
$numFailed = 0;
$numAlreadyExist = 0;
$totalRows = 0;

if ($_FILES["file"] != null  )
{
	if ( $_FILES["file"]["error"] > 0 )
	{
		msgBox( "Error: " . $_FILES["file"]["error"] );
	}
	else
	{
		if ( file_exists($_FILES["file"]["tmp_name"]) )
		{
			$handle = fopen($_FILES["file"]["tmp_name"], "r");
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) 
			{
				$totalRows++;
				if ( count( $data ) < 7 && count( $data ) >= 2 )
				{
					$mac = mysql_real_escape_string( $data[0] );
					$hostname = mysql_real_escape_string( $data[1] );
					$ip = mysql_real_escape_string( $data[2] );
					$desc = mysql_real_escape_string( $data[3] . "  Uploaded by batch import on " . date("F j, Y, g:i a") ); 
					$os = mysql_real_escape_string( $data[4] );	
					$image = mysql_real_escape_string( $data[5] );
				
					$user = "";
					if ( $currentUser != null )
						$user = mysql_real_escape_string($currentUser->getUserName());				
					if ( ! hostsExists( $conn, $mac ) )
					{

						if ( createHost( $conn, $mac, $hostname, $ip, $desc, $user, $os, $image) )
						{
							$numSuccess++;
						}
						else
						{
							$numFailed++;
							$uploadErrors .= "Row: " . $totalRows . "- General error.<br />";
						}			
					}
					else
						$numAlreadyExist++;
										
				}
				else
				{
					$uploadErrors .= "Row: " . $totalRows . "- Invalid number of cells.<br />";
				}
			}
			fclose($handle);	
		}
	}
	
	echo ( "<div id=\"pageContent\" class=\"scroll\">" );
	echo ( "<p class=\"title\">Upload Results</p>" );
	echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
		echo ( "<tr><td>Total Rows</font></td><td>$totalRows</td></tr>" );
		echo ( "<tr><td>Successful Hosts</td><td>$numSuccess</td></tr>" );				
		echo ( "<tr><td>Existing Hosts</td><td>$numAlreadyExist</td></tr>" );		
		echo ( "<tr><td>Failed Hosts</td><td>$numFailed</td></tr>" );				
		echo ( "<tr><td>Errors</td><td>$uploadErrors</td></tr>" );						
	echo ( "</table></center>" );
	echo ( "</div>" );	
}
else
{
	echo ( "<div id=\"pageContent\" class=\"scroll\">" );
	echo ( "<p class=\"title\">Upload Host List</p>" );
	echo ( "<form enctype=\"multipart/form-data\" method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]\">" );
	echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
		echo ( "<tr><td>CSV File:</font></td><td><input class=\"smaller\" type=\"file\" name=\"file\" value=\"\" /></td></tr>" );
		echo ( "<tr><td colspan=2><font><center><br /><input class=\"smaller\" type=\"submit\" value=\"Upload CSV\" /></center></font></td></tr>" );				
	echo ( "</table></center>" );
	echo ( "</form>" );
	echo ( "<p class=\"titleBottom\">" );
		echo ("This page allows you to upload a CSV file of hosts into FOG to ease migration.  Right click <a href=\"./other/hostimport.csv\">here</a> and select <strong>Save target as...</strong> or <strong>Save link as...</strong>  to download a template file.  The only fields that are required are hostname and MAC address.  Do <strong>NOT</strong> include a header row, and make sure you resave the file as a CSV file and not XLS!");
	echo ( "</p>" );
	echo ( "</div>" );
}
?>
