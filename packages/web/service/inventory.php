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
@error_reporting(0);
require_once( "../commons/config.php" );
require_once( "../commons/functions.include.php" );

/*
 *  Possible return codes
 *  "#!db" => Database error
 *  "#!im" => Invalid MAC Format
 *  "#!ih" => Invalid Host format
 *  "#!ma" => Mac address already exists.
 *  "#!er" => Other error.
 *  "#!ok" => registration successful.
 *
 */


if ( isset( $_POST["mac"] ) )
{
	$ifconfig = base64_decode( $_POST["mac"] );
	if ( $ifconfig != null )
	{
		$arIfconfig = explode( "HWaddr", $ifconfig  );
		if ( count( $arIfconfig ) == 2 )
		{
			$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
			$mac =  mysql_real_escape_string( strtolower( trim($arIfconfig[1]) ) );
			if ( strlen( trim($mac) ) == 17 )
			{
				if ( isValidMACAddress( $mac ) )
				{			
					if ( $conn )
					{	
						if ( ! @mysql_select_db( MYSQL_DATABASE, $conn ) ) die( mysql_error() );
						
						$sysman=mysql_real_escape_string(trim(base64_decode( $_POST["sysman"] )));
						$sysproduct=mysql_real_escape_string(trim(base64_decode( $_POST["sysproduct"] )));
						$sysversion=mysql_real_escape_string(trim(base64_decode( $_POST["sysversion"] )));
						$sysserial=mysql_real_escape_string(trim(base64_decode( $_POST["sysserial"] )));
						$systype=mysql_real_escape_string(trim(base64_decode( $_POST["systype"] )));
						$biosversion=mysql_real_escape_string(trim(base64_decode( $_POST["biosversion"] )));
						$biosvendor=mysql_real_escape_string(trim(base64_decode( $_POST["biosvendor"] )));
						$biosdate=mysql_real_escape_string(trim(base64_decode( $_POST["biosdate"] )));
						$mbman=mysql_real_escape_string(trim(base64_decode( $_POST["mbman"] )));
						$mbproductname=mysql_real_escape_string(trim(base64_decode( $_POST["mbproductname"] )));
						$mbversion=mysql_real_escape_string(trim(base64_decode( $_POST["mbversion"] )));
						$mbserial=mysql_real_escape_string(trim(base64_decode( $_POST["mbserial"] )));
						$mbasset=mysql_real_escape_string(trim(base64_decode( $_POST["mbasset"] )));
						$cpuman=mysql_real_escape_string(trim(base64_decode( $_POST["cpuman"] )));
						$cpuversion=mysql_real_escape_string(trim(base64_decode( $_POST["cpuversion"] )));
						$cpucurrent=mysql_real_escape_string(trim(base64_decode( $_POST["cpucurrent"] )));
						$cpumax=mysql_real_escape_string(trim(base64_decode( $_POST["cpumax"] )));
						$mem=mysql_real_escape_string(trim(base64_decode( $_POST["mem"] )));
						$hdinfo=mysql_real_escape_string(trim(base64_decode( $_POST["hdinfo"] )));
						$caseman=mysql_real_escape_string(trim(base64_decode( $_POST["caseman"] )));
						$casever=mysql_real_escape_string(trim(base64_decode( $_POST["casever"] )));
						$caseserial=mysql_real_escape_string(trim(base64_decode( $_POST["caseserial"] )));
						$casesasset=mysql_real_escape_string(trim(base64_decode( $_POST["casesasset"] )));						

						$sql = "select hostID from hosts where hostMAC = '" . $mac . "'";
						$res = mysql_query( $sql, $conn ) or die( mysql_error() );
						if ( mysql_num_rows( $res ) == 1 )
						{
							while( $ar = mysql_fetch_array( $res ) )
							{
								if ( $ar["hostID"] !== null && is_numeric( $ar["hostID"] ) )
								{	
									$hid = mysql_real_escape_string( $ar["hostID"] );
									
						
									$hdmodel = "";
									$hdfirmware = "";
									$hdserial = "";
									
									if ( $hdinfo != null )
									{
										$arHd = explode( ",", $hdinfo );
										if ( count( $arHd ) == 3 )
										{
											$hdmodel = mysql_real_escape_string( trim(str_replace( "Model=", "", trim( $arHd[0] ) ) ) );
											$hdfirmware = mysql_real_escape_string( trim(str_replace( "FwRev=", "", trim( $arHd[1] ) ) ) );												
											$hdserial = mysql_real_escape_string( trim(str_replace( "SerialNo=", "", trim( $arHd[2] ) ) ) );												
										}
									}
								
									$mem = trim(str_replace( "MemTotal:", "", $mem ));
									$cpumax = trim(str_replace( "Max Speed:", "", $cpumax ));
									$cpucurrent = trim(str_replace( "Current Speed:", "", $cpucurrent ));										
									$systype = trim(str_replace( "Type:", "", $systype ));										
							
									$sql = "select count(*) as cnt from inventory where iHostID = '" . $hid . "'";
									$res = mysql_query( $sql, $conn ) or die( mysql_error() );
									if ( $ar = mysql_fetch_array( $res ) )
									{	
										if ( $ar["cnt"] == 0 )
										{								
											$sql = "INSERT INTO
													inventory(iHostID, iCreateDate, iSysman  , iSysproduct  , iSysversion, iSysserial, iSystype   , iBiosversion , iBiosvendor, iBiosdate , iMbman  , iMbproductname, iMbversion, iMbserial   , iMbasset, iCpuman , iCpuversion , iCpucurrent, iCpumax, iMem, iHdmodel, iHdfirmware, iHdserial, iCaseman, iCasever, iCaseserial, iCaseasset )
													values(   '$hid'  , NOW()     , '$sysman', '$sysproduct','$sysversion','$sysserial','$systype','$biosversion','$biosvendor','$biosdate','$mbman','$mbproductname','$mbversion','$mbserial','$mbasset','$cpuman','$cpuversion','$cpucurrent','$cpumax','$mem','$hdmodel','$hdfirmware','$hdserial','$caseman','$casever','$caseserial','$casesasset')";
											if ( mysql_query( $sql, $conn ) )
												echo "Done";
											else
											{
												echo "FAILED";										
												echo ( "\n" );
												echo mysql_error();
											}
										}
										else
										{
											$sql = "UPDATE
													inventory
												SET
													iSysman = '$sysman', 
													iSysproduct ='$sysproduct', 
													iSysversion ='$sysversion', 
													iSysserial = '$sysserial', 
													iSystype = '$systype', 
													iBiosversion ='$biosversion',
													iBiosvendor = '$biosvendor', 
													iBiosdate ='$biosdate', 
													iMbman ='$mbman', 
													iMbproductname ='$mbproductname', 
													iMbversion = '$mbversion', 
													iMbserial = '$mbserial', 
													iMbasset ='$mbasset', 
													iCpuman ='$cpuman', 
													iCpuversion = '$cpuversion', 
													iCpucurrent = '$cpucurrent', 
													iCpumax = '$cpumax', 
													iMem ='$mem', 
													iHdmodel = '$hdmodel', 
													iHdfirmware = '$hdfirmware', 
													iHdserial = '$hdserial', 
													iCaseman ='$caseman', 
													iCasever = '$casever', 
													iCaseserial = '$caseserial', 
													iCaseasset = '$casesasset'
												WHERE 
													iHostID = '$hid'";
											if ( mysql_query( $sql, $conn ) )
												echo "Done";
											else
											{
												echo "FAILED";										
												echo ( "\n" );
												echo mysql_error();
											}										
										}
									}
									else
										echo ( "Failed to remove old inventory" );					                    		
								}
								else
									echo "Host is already registered";
							}								
						}
					}
					else
						echo " Unable to connect to database, host not imported!";
				}
				else
					echo ( " Invalid MAC Address format!" );
			}
			else
				echo " Invalid MAC address (3)";			
		}
		else
			echo " Invalid MAC address (2)";					
	}
	else
		echo " Invalid MAC address (1)";				
}
else
	echo " Invalid MAC address (0)";
?>
