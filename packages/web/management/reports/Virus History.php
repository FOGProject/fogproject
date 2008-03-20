<?php
/*
 *  FOG is a computer imaging solution.
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

//@ini_set( "max_execution_time", 120 );
 
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

require_once( "./lib/ReportMaker.class.php" );

if ( $_GET["delvid"] !== null && is_numeric( $_GET["delvid"] ) )
{
	$vid = mysql_real_escape_string( $_GET["delvid"] );
	clearAVRecord( $conn, $vid );
}	

if ( $_GET["delvid"] == "all"  )
{
	clearAllAVRecords( $conn );
}

echo ( "<div class=\"scroll\">" );
echo ( "<p class=\"title\">FOG Virus Summary (<a href=\"?node=$_GET[node]&sub=$_GET[sub]&f=$_GET[f]&delvid=all\">clear all history</a>) <a href=\"export.php?type=csv\" target=\"_blank\"><img class=\"noBorder\" src=\"images/csv.png\" /></a> <a href=\"export.php?type=pdf\" target=\"_blank\"><img class=\"noBorder\" src=\"images/pdf.png\" /></a></p>" );
	
	echo ( "<div>" );
		$report = new ReportMaker();
		$report->appendHTML("<table cellpadding=0 cellspacing=0 border=0 width=100%>");
			$report->appendHTML("<tr bgcolor=\"#BDBDBD\"><td>&nbsp;<b>Host Name</b></td><td>&nbsp;<b>Virus Name</b></td><td><b>File</b></td><td><b>Mode</b></td><td><b>Date</b></td><td><b>Clear</b></td></tr>");		
			
			$report->addCSVCell("Host Name");
			$report->addCSVCell("Virus Name");
			$report->addCSVCell("File");
			$report->addCSVCell("Mode");
			$report->addCSVCell("Date");
			$report->endCSVLine();												
			
				$sql = "SELECT 
						* 
					FROM 
						virus 
						inner join hosts on ( virus.vHostMAC = hosts.hostMAC )
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
						$report->appendHTML("<tr bgcolor=\"$bgcolor\"><td>&nbsp;" . $arSp["hostName"] . "</td><td><a href=\"http://www.google.com/search?q=" .  $arSp["vName"] . "\" target=\"_blank\">" . $arSp["vName"] . "</a></td><td>" . $arSp["vOrigFile"] . "</td><td>" . avModeToString( $arSp["vMode"] ) . "</td><td>" . $arSp["vDateTime"] . "</td><td><a href=\"?node=$_GET[node]&sub=$_GET[sub]&hid=" . $arSp["hostID"] . "&f=$_GET[f]&delvid=" . $arSp["vID"] . "\"><img src=\"images/deleteSmall.png\" class=\"link\" /></a></td></tr>" );
						$report->addCSVCell($arSp["hostName"]);
						$report->addCSVCell($arSp["vName"]);
						$report->addCSVCell($arSp["vOrigFile"]);
						$report->addCSVCell(avModeToString( $arSp["vMode"] ) );
						$report->addCSVCell($arSp["vDateTime"]);
						$report->endCSVLine();						
					}
				}
				else
				{
					$report->appendHTML("<tr><td colspan=\"5\" class=\"centeredCell\">No Virus Information Reported.</td></tr>" );
					$report->addCSVCell("No Virus Information Reported.");
					$report->endCSVLine();						
				}
		$report->appendHTML( "</table>" );
		$report->outputReport(ReportMaker::FOG_REPORT_HTML);
		$_SESSION["foglastreport"] = serialize( $report );
	echo ( "</div>" );		

echo ( "</div>" );	
		
?>
