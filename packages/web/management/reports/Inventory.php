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

echo ( "<div class=\"scroll\">" );
echo ( "<p class=\"title\">Full Inventory Export  <a href=\"export.php?type=csv\" target=\"_blank\"><img class=\"noBorder\" src=\"images/csv.png\" /></a></p>" );
	
	echo ( "<div>" );
		$report = new ReportMaker();
																																																																																																																																																																																																																												
		$report->addCSVCell("Host Name");
		$report->addCSVCell("Host IP");
		$report->addCSVCell("Host MAC");		
		$report->addCSVCell("Host Description");		
		$report->addCSVCell("Image ID");
		$report->addCSVCell("Image Name");
		$report->addCSVCell("Image Desc");
		$report->addCSVCell("OS Name");
		$report->addCSVCell("Inventory ID");
		$report->addCSVCell("Primary User");
		$report->addCSVCell("Other Tag 1");
		$report->addCSVCell("Other Tag 2");		
		$report->addCSVCell("Iventory create date");
		$report->addCSVCell("System Man");
		$report->addCSVCell("System Product");
		$report->addCSVCell("System Version");
		$report->addCSVCell("System Serial");
		$report->addCSVCell("System Type");
		$report->addCSVCell("BIOS Version");
		$report->addCSVCell("BIOS Vendor");
		$report->addCSVCell("BIOS Date");
		$report->addCSVCell("MB Man");
		$report->addCSVCell("MB name");
		$report->addCSVCell("MB Ver");
		$report->addCSVCell("MB Serial");
		$report->addCSVCell("MB Asset");
		$report->addCSVCell("CPU Man");
		$report->addCSVCell("CPU Version");
		$report->addCSVCell("CPU Speed");
		$report->addCSVCell("CPU Max Speed");
		$report->addCSVCell("Memory");
		$report->addCSVCell("HD Model");
		$report->addCSVCell("HD Firmware");
		$report->addCSVCell("HD Serial");
		$report->addCSVCell("Chassis Man");
		$report->addCSVCell("Chassis Version");
		$report->addCSVCell("Chassis Serial");
		$report->addCSVCell("Chassis Asset");
		$report->endCSVLine();												
			
				$sql = "SELECT 
						* 
					FROM 
						hosts  
						inner join inventory on ( hosts.hostID = inventory.iHostID )
						left outer join images on ( hostImage = imageID )
						left outer join supportedOS on ( hostOS = osID )";
				$res = mysql_query( $sql, $conn ) or die( mysql_error() );

				while ( $ar = mysql_fetch_array( $res ) )
				{
					// 																																																																																																																																																																																																																																				

					$report->addCSVCell($ar["hostName"]);
					$report->addCSVCell($ar["hostIP"]);
					$report->addCSVCell($ar["hostMAC"]);		
					$report->addCSVCell($ar["hostDesc"]);		
					$report->addCSVCell($ar["imageID"]);
					$report->addCSVCell($ar["imageName"]);
					$report->addCSVCell($ar["imageDesc"]);					
					$report->addCSVCell($ar["osName"]);
					$report->addCSVCell($ar["iID"]);
					$report->addCSVCell($ar["iPrimaryUser"]);
					$report->addCSVCell($ar["iOtherTag"]);
					$report->addCSVCell($ar["iOtherTag1"]);		
					$report->addCSVCell($ar["iCreateDate"]);
					$report->addCSVCell($ar["iSysman"]);
					$report->addCSVCell($ar["iSysproduct"]);
					$report->addCSVCell($ar["iSysversion"]);
					$report->addCSVCell($ar["iSysserial"]);
					$report->addCSVCell($ar["iSystype"]);
					$report->addCSVCell($ar["iBiosversion"]);
					$report->addCSVCell($ar["iBiosvendor"]);
					$report->addCSVCell($ar["iBiosdate"]);
					$report->addCSVCell($ar["iMbman"]);
					$report->addCSVCell($ar["iMbproductname"]);
					$report->addCSVCell($ar["iMbversion"]);
					$report->addCSVCell($ar["iMbserial"]);
					$report->addCSVCell($ar["iMbasset"]);
					$report->addCSVCell($ar["iCpuman"]);
					$report->addCSVCell($ar["iCpuversion"]);
					$report->addCSVCell($ar["iCpucurrent"]);
					$report->addCSVCell($ar["iCpumax"]);
					$report->addCSVCell($ar["iMem"]);
					$report->addCSVCell($ar["iHdmodel"]);
					$report->addCSVCell($ar["iHdfirmware"]);
					$report->addCSVCell($ar["iHdserial"]);
					$report->addCSVCell($ar["iCaseman"]);
					$report->addCSVCell($ar["iCasever"]);
					$report->addCSVCell($ar["iCaseserial"]);
					$report->addCSVCell($ar["iCaseasset"]);
					$report->endCSVLine();						
				}
		echo ( "<p>Reporting Complete!</p>" );

		$_SESSION["foglastreport"] = serialize( $report );
	echo ( "</div>" );		

echo ( "</div>" );	
		
?>
