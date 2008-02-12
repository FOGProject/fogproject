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

define( "WIPE_FAST", 1 );
define( "WIPE_NORMAL", 2 );
define( "WIPE_FULL", 3 );

define( "FOG_AV_SCANONLY", 1 );
define( "FOG_AV_SCANQUARANTINE", 2 );

function criticalError( $description, $title="FOG :: Critical Error!" )
{
	echo "<div class=\"errorBox\">";
		echo "<h2>";
			echo $title;
		echo "</h2>";
		echo "<b>Description:</b> " . $description;
	echo "</div>";
	exit;
}

function isSafeHostName( $hostname )
{

	return ( ereg( "^[0-9a-zA-Z_\-]*$", $hostname ) && strlen($hostname ) > 0 && strlen( $hostname ) <= 15  );
} 
 
function isValidIPAddress( $ip )
{
	$ar = explode( ".", $ip );
	
	if (count($ar) != 4 ) return false;
	
	for($i=0;$i<count($ar);$i++)
	{
		if ( $ar[$i] === null || ! is_numeric( $ar[$i] ) || $ar[$i] < 0 || $ar[$i] > 255 ) return false;
	}
	return true;
}
 
function isValidMACAddress( $mac )
{
	return ereg( "^([0-9a-fA-F][0-9a-fA-F]:){5}([0-9a-fA-F][0-9a-fA-F])$", $mac );
}
 
function doAllMembersHaveSameImage( $members )
{
	if ( $members !== null )
	{
		$firstImageDef = null;
		for( $i = 0; $i < count( $members ); $i++ )
		{
			$currentImageDef = $members[$i]->getImageID();
			if( $i == 0 )
				$firstImageDef = $currentImageDef;
				
			if ( $currentImageDef === null || $currentImageDef != $firstImageDef || $currentImageDef < 0 || ! is_numeric( $currentImageDef ) )
				return false;
		}	
		
		if ( $firstImageDef !== null ) return true;
	}
	return false;
}
 
function getCurrentDBVersion($conn)
{
	if ( $conn )
	{
		@mysql_select_db( MYSQL_DATABASE );
		$sql_version = "select vValue FROM schemaVersion";
		$res_version = mysql_query( $sql_version, $conn );
		if ( $ar = @mysql_fetch_array( $res_version ) )
		{
			return $ar[0];
		}
	}
	return 0;
}
 
function getOSDropDown( $conn, $name="os", $selected=null )
{
	if ( $conn != null )
	{
		$sql = "select * from supportedOS";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		$buffer = "<select name=\"$name\" size=\"1\">\n";
		$buffer .= "<option value=\"-1\" label=\"Select One\">Select One</option>\n";
		while( $ar = mysql_fetch_array( $res ) )
		{
			$sel = "";
			if ( $selected == $ar["osValue"] )
				$sel = "selected=\"selected\"";
			$buffer .= "<option value=\"" . $ar["osValue"] . "\" label=\"" . $ar["osName"] . "\" $sel>" . $ar["osName"] . "</option>\n";
		}
		$buffer .= "</select>\n";
		return $buffer;
	}
	return null;
}

function getSnapinDropDown( $conn, $name="snap", $selected=null )
{
	if ( $conn != null )
	{
		$sql = "select * from snapins order by sName";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		$buffer = "<select name=\"$name\" size=\"1\">\n";
		$buffer .= "<option value=\"-1\" label=\"Select One\">Select One</option>\n";
		while( $ar = mysql_fetch_array( $res ) )
		{
			$sel = "";
			if ( $selected == $ar["sName"] )
				$sel = "selected=\"selected\"";
			$buffer .= "<option value=\"" . $ar["sID"] . "\" label=\"" . $ar["sName"] . "\" $sel>" . $ar["sName"] . "</option>\n";
		}
		$buffer .= "</select>\n";
		return $buffer;
	}
	return null;
}

function isHostAssociatedWithSnapin( $conn, $hostid, $snapinid )
{
	if ( $conn != null && $hostid !== null && $snapinid !== null && is_numeric( $hostid ) && is_numeric( $snapinid ) )
	{
		$hostid = mysql_real_escape_string( $hostid );
		$snapinid = mysql_real_escape_string( $snapinid );
		$sql = "select count(*) as cnt from snapinAssoc where saHostID = '" . $hostid . "' and saSnapinID = '" . $snapinid . "'"; 
		$res = mysql_query($sql, $conn) or criticalError( mysql_error(), "FOG :: Database error!" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			return ($ar["cnt"] > 0);
		
		}
		return false;
	}
	return true;
}

function addSnapinToHost( $conn, $hostid, $snapinid, &$reason )
{
	if ( $conn != null && $hostid !== null && $snapinid !== null && is_numeric( $hostid ) && is_numeric( $snapinid ) )
	{
		if ( ! isHostAssociatedWithSnapin( $conn, $hostid, $snapinid ) )
		{
			$hostid = mysql_real_escape_string( $hostid );
			$snapinid = mysql_real_escape_string( $snapinid );		
			$sql = "insert into snapinAssoc(saHostID, saSnapinID) values('$hostid','$snapinid')";
			if( mysql_query( $sql, $conn ) )
			{
				$reason = "Snapin added to host.";
				return true;
			}
			else
				$reason = "Database error: " . mysql_error() ;
		}
		else
			$reason = "Snapin is already linked with this host.";
	}
	else
		$reason = "Either the database connection, snapid ID, or host ID was null.";
		
	return false;
}

function deleteSnapinFromHost( $conn, $hostid, $snapinid, &$reason )
{
	if ( $conn != null && $hostid !== null && $snapinid !== null && is_numeric( $hostid ) && is_numeric( $snapinid ) )
	{
		if ( isHostAssociatedWithSnapin( $conn, $hostid, $snapinid ) )
		{
			$hostid = mysql_real_escape_string( $hostid );
			$snapinid = mysql_real_escape_string( $snapinid );		
			$sql = "delete from snapinAssoc where saHostID = '$hostid' and saSnapinID = '$snapinid'";
			if( mysql_query( $sql, $conn ) )
			{
				$reason = "Snapin removed from host.";
				return true;
			}
			else
				$reason = "Database error: " . mysql_error() ;		
		}
		else
			$reason = "Snapin is not linked with this host.";
	}
	else
		$reason = "Either the database connection, snapid ID, or host ID was null.";
	
	return false;
}

function cancelSnapinsForHost( $conn, $hostid, $snapID = -1 )
{
	if ( $conn != null && $hostid !== null && is_numeric( $hostid )  )
	{
		$hostid = mysql_real_escape_string( $hostid );
		$snapID = mysql_real_escape_string( $snapID );
		$where = "";
		if ( $snapID != -1 )
		{
			$where = " and stSnapinID = '$snapID' ";
		}
		
		$sql = "SELECT 
				stID 
			FROM 
				snapinTasks
				inner join snapinJobs on ( snapinTasks.stJobID = snapinJobs.sjID )
			WHERE
				sjHostID = '$hostid' and
				stState in ( '0', '1' )
				$where";
				
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$sql = "update snapinTasks set stState = '-1' where stID = '" . $ar["stID"] . "'";
			if (!mysql_query( $sql, $conn ) )
				die( mysql_error() );
		}
		return true;
	}
	return false;
}

function deploySnapinsForHost( $conn, $hostid, $snapID = -1 )
{
	if ( $conn != null && $hostid !== null && is_numeric( $hostid )  )
	{
		$hostid = mysql_real_escape_string( $hostid );
		$snap = mysql_real_escape_string( $snapID );
		$where = "";
		if ( $snapID != -1 )
		{
			$where = " and sID = '$snap' ";
		}
		$sql = "SELECT 
				count(*) as cnt 
			FROM 
				snapinAssoc 
				inner join snapins on ( snapinAssoc.saSnapinID = snapins.sID )
			WHERE
				snapinAssoc.saHostID = '$hostid' 
				$where";
		
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array($res) )
		{
			if($ar["cnt"] > 0 )
			{
				// create job record
				// todo: make transactional 
				$sql = "insert into snapinJobs(sjHostID, sjCreateTime) values( '$hostid', NOW())";
				if ( mysql_query( $sql, $conn ) )
				{
					$insertedID = mysql_insert_id( $conn );
					if ( $insertedID !== false )
					{
						// create job items
						$suc = 0;
						$sql = "SELECT 
								sID 
							FROM 
								snapinAssoc 
								inner join snapins on ( snapinAssoc.saSnapinID = snapins.sID )
							WHERE
								snapinAssoc.saHostID = '$hostid'
							ORDER BY
								snapins.sName";	

						$resS = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
						while( $arS = mysql_fetch_array( $resS ) )
						{

							if ( $snap == -1 || $arS["sID"] == $snap )
							{
								$sql = "insert into 
										snapinTasks(stJobID, stState, stSnapinID) 
										values('$insertedID', '0', '" . $arS["sID"] . "')";
								
								if ( mysql_query( $sql, $conn ) )
									$suc++;
							}
						}
						return $suc;
					}
				}
			}
			else 
				return 0;
		}
							
	}
	return -1;
}

function getImageAction( $char )
{
	if ( strtolower( $char ) == "u" )
		return "Upload";
	else if ( strtolower( $char ) == "d" )
		return "Download";
	else if ( strtolower( $char ) == "w" )
		return "Wipe";		
	else if ( strtolower( $char ) == "x" )
		return "Debug";			
	else if ( strtolower( $char ) == "m" )
		return "Memtest";			
	else if ( strtolower( $char ) == "t" )
		return "Testdisk";
	else if ( strtolower( $char ) == "c" )
		return "Multicast";					
	else if ( strtolower( $char ) == "v" )
		return "Virus Scan";			
	else
		return "N/A";
}

function ftpDelete( $remotefile )
{
	$ftp = ftp_connect(TFTP_HOST); 
	$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
	if ($ftp && $ftp_loginres ) 
	{
		return ftp_delete( $ftp, $remotefile ); 
	}
	@ftp_close($ftp); 
	return false;	
}

function hasCheckedIn( $conn, $jobid )
{
	if ( $conn && $jobid )
	{
		$sql = "select (UNIX_TIMESTAMP(taskCheckIn) - UNIX_TIMESTAMP(taskCreateTime) ) as diff from tasks where taskID = '" . mysql_real_escape_string( $jobid ) . "'";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			if ( $ar["diff"] > 2 ) return true;
		}
	}
	return false;
}

function state2text( $intState )
{
	if ( $intState == 0 )
		return "Queued";
	else if ( $intState == 1 )
		return "In progress";
	else if ( $intState == 2 )
		return "complete";
	else
		return "unknown";
}

function getNumberOfTasks($conn, $intState )
{
	if ( $conn != null )
	{
		$sql = "select 
				count(*) as cnt 
			from 
				(select * from tasks where taskState = '" . mysql_real_escape_string( $intState ) . "' and taskType in ('U', 'D') ) tasks 
				inner join hosts on ( tasks.taskHostID = hosts.hostID )";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			return $ar["cnt"];
		}		
	}
	return 0;
}

function isValidPassword( $password1, $password2 )
{
	if ( $password1 == $password2 )
	{
		if ( strlen($password1) >= USER_MINPASSLENGTH )
		{
			$passChars = USER_VALIDPASSCHARS;
			for( $i = 0; $i < strlen( $password1 ); $i ++ )
			{
				$blFound = false;
				for( $z = 0; $z < strlen( USER_VALIDPASSCHARS ); $z++ )
				{
					if ( $passChars[$z] == $password1[$i] )
					{
						$blFound = true;
						break;
					}
				}
				
				if ( ! $blFound ) return false;
			}
			return true;
		}
	}
	return false;
}

function userExists( $conn, $username, $except=-1 )
{
	if ( $conn != null && $username != null )
	{
		$sql = "select count(*) as cnt from users where uName = '" . mysql_real_escape_string( $username ) . "' and uId <> $except";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			if ( $ar["cnt"] > 0 )
				return true;
		}		
	}
	return false;
}

function snapinExists( $conn, $name, $id=-1 )
{
	if ( $conn != null && $name != null )
	{
		$sql = "select count(*) as cnt from snapins where sName = '" . mysql_real_escape_string( $name ) . "' and sID <> $id";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			if ( $ar["cnt"] == 0 )
				return false;
		}	
	}
}

function imageDefExists( $conn, $name, $id=-1 )
{
	if ( $conn != null && $name != null )
	{
		$sql = "select count(*) as cnt from images where imageName = '" . mysql_real_escape_string( $name ) . "' and imageID <> $id";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			if ( $ar["cnt"] == 0 )
				return false;
		}
	}
	return true;
}

function msgBox( $msg )
{
	echo ( "<div class=\"msgbox\" id=\"msgbox\">" );
		echo ( "<p class=\"msgbox\">FOG Message</p>" );
		echo ( $msg );
		echo ( "<p><input class=\"smaller\" type=\"button\" value=\"   OK    \" onClick=\"document.getElementById('msgbox').className='hide'\" /></p>" );
	echo ( "</div>" );
}

function lg( $string )
{
	global $conn, $currentUser;
	$sql = "insert into history( hText, hUser, hTime, hIP ) values( '" . mysql_real_escape_string( $string ) . "', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', NOW(), '" . $_SERVER[REMOTE_ADDR] . "')";
	@mysql_query( $sql, $conn );
}

function groupExists( $conn, $groupName, $id=-1 )
{
	if ( $conn != null && $groupName != null )
	{
		$sql = "select count(*) as cnt from groups where groupName = '" . mysql_real_escape_string( $groupName ) . "' and groupID <> $id";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			if ( $ar["cnt"] == 0 )
				return false;
		}
	}
	return true;
}

function hostsExists( $conn, $mac, $id=-1 )
{
	if ( $conn != null && $mac != null )
	{
		$sql = "select count(*) as cnt from hosts where hostMAC = '" . mysql_real_escape_string( $mac ) . "' and hostID <> $id";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			if ( $ar["cnt"] == 0 )
				return false;
		}
	}
	return true;
}

function createHost( $conn, $mac, $hostname, $ip="" , $desc="", $createby=null, $osid=null, $imageid=null, $binUseAD=0, $adDomain=null, $adOU=null, $adUser=null, $adPass=null)
{
	if ( ! hostsExists( $conn, $mac ) )
	{
		if ( isValidMACAddress( $mac ) )
		{
			$ip = mysql_real_escape_string( $ip );
			$desc = mysql_real_escape_string( $desc ); 
			$imageid = mysql_real_escape_string( $imageid );
			$mac = mysql_real_escape_string( $mac );
			$hostname = mysql_real_escape_string( $hostname );
			$osid = mysql_real_escape_string( $osid );	
			$createby = mysql_real_escape_string( $createby );	
			
			$useAD = "0";
			if ( $binUseAD == "1" )
				$useAD = "1";
			
			$adDomain = mysql_real_escape_string( $adDomain );
			$adOU = mysql_real_escape_string( $adOU	);
			$adUser = mysql_real_escape_string( $adUser );
			$adPass = mysql_real_escape_string( $adPass );
			
			if ( $mac != null && $hostname != null && $os != "-1" )
			{
				$sql = "insert into hosts(hostMAC, hostIP, hostName, hostDesc, hostCreateDate, hostImage, hostCreateBy, hostOS, hostUseAD, hostADDomain, hostADOU, hostADUser, hostADPass) 
				                   values('$mac','$ip', '$hostname', '$desc', NOW(), '$imageid', '$createby', '$osid', '$useAD', '$adDomain', '$adOU', '$adUser', '$adPass')";
				if ( mysql_query( $sql, $conn ) )
					return true;
			}				
		}	
	}
	return false;
}

function trimString( $string, $len )
{
	if ( strlen($string) > $len )
	{
		return substr( trim($string), 0, $len ) . "...";
	}
	
	return $string;
}

function getCheckedItems( $post )
{
	$retAr = array();
	foreach ($post as $key => $value)
	{
   		if ( substr( trim($key), 0, 3 ) == "HID" && $value == "on" )
   		{
   			$retAr[] = substr(trim($key),3 );
   		}
   	}
   	return $retAr;
	
}

function addMemebersToGroup( $conn, $grpID, $arMembers )
{
	 if ( $conn != null && $grpID != null && $arMembers != null )
	 {
	 	for( $i = 0; $i < count( $arMembers ); $i++ )
	 	{
	 		if ( $arMembers[$i] != null && is_numeric($arMembers[$i]) )
	 		{
	 			$sql = "select count(*) from groupMembers where gmGroupID = '$grpID' and gmHostID = '$arMembers[$i]'";
	 			$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
	 			$num = -1;
	 			while( $ar = mysql_fetch_array( $res ) )
	 			{
	 				$num = $ar[0];
	 			}
	 			
	 			if ( $num == 0 )
	 			{
	 				$sql = "insert into groupMembers(gmGroupID, gmHostID) values('$grpID', '$arMembers[$i]')";
	 				mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
	 				lg( "Host [$arMembers[$i]] added to group [$grpID] " );
	 			}
	 		}  
	 	}
	 	return true;
	 }
	 return false;
}

function getImageMembersByGroupID( $conn, $groupID )
{
	$arM = array();
	if ( $conn != null && $groupID != null )
	{
		$sql = "select 
				* 
			from groups
			inner join groupMembers on ( groups.groupID = groupMembers.gmGroupID )
			inner join hosts on ( groupMembers.gmHostID = hosts.hostID )
			left outer join images on ( hostImage = imageID )
			where groupID = $groupID";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$imgType = ImageMember::IMAGETYPE_PARTITION;
			if ( $ar["imageDD"] == "1" )
				$imgType = ImageMember::IMAGETYPE_DISKIMAGE;
			$arM[] = new ImageMember( $ar["hostName"],  $ar["hostIP"], $ar["hostMAC"], $ar["imagePath"], $ar["hostID"], null, $ar["imageID"], false, $ar["hostOS"], $imgType );
		}
	}
	return $arM;
}

function getGroupNameByID( $conn, $id )
{
	if ( $conn != null && $id != null && is_numeric( $id ) )
	{
		$id = mysql_real_escape_string( $id );
		$sql = "select * from groups where groupID = '$id'";

		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( mysql_num_rows( $res ) == 1 )
		{
			if( $ar = mysql_fetch_array( $res ) )
			{
				return $ar["groupName"];
			}
		}	
	}
	return null;
}

function getGroupIDByName( $conn, $name )
{
	if ( $conn != null && $name != null )
	{
		$name = mysql_real_escape_string( $name );
		$sql = "select groupID from groups where groupName = '$name'";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( mysql_num_rows( $res ) == 1 )
		{
			if( $ar = mysql_fetch_array( $res ) )
			{
				return $ar["groupID"];
			}
		}	
	}
	return -1;
}

function getImageMemberFromHostID( $conn, $hostid )
{
	$member = null;
	if ( $conn != null && $hostid != null )
	{
		$hostid = mysql_real_escape_string( $hostid );
		$sql = "select 
				* 
			from hosts
			left outer join images on ( hostImage = imageID )
			where hostID = '$hostid'";

		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$imgType = ImageMember::IMAGETYPE_PARTITION;
			if ( $ar["imageDD"] == "1" )
				$imgType = ImageMember::IMAGETYPE_DISKIMAGE;		
			$member = new ImageMember( $ar["hostName"], $ar["hostIP"], $ar["hostMAC"], $ar["imagePath"], $ar["hostID"], 0, $ar["imageID"], false, $ar["hostOS"], $imgType );
		}
	}
	return $member;
}

function wakeUp( $mac )
{
	if ( $mac != null )
	{	
		$ch = curl_init();	
		curl_setopt($ch, CURLOPT_URL, "http://" . WOL_HOST . WOL_PATH . "?wakeonlan=$mac");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		curl_close($ch);	
	}
}

function createGroup( $conn, $name )
{
	global $currentUser;
	
	if ( $conn != null && $name != null )
	{
		$name = mysql_real_escape_string( $name );
		$sql = "select * from groups where groupName = '$name'";
		$res = mysql_query( $sql, $conn );
		if ( mysql_num_rows( $res ) == 0 )
		{
			$sql = "insert into groups(groupName, groupCreateBy, groupDateTime) values( '$name', '" . mysql_real_escape_string($currentUser->getUserName()) . "', NOW() )";
			if( mysql_query( $sql, $conn ) )
			{
				lg( "Group created :: $name" );			
				return true;
			}
		}
	}
	return false;
}

function createPXEFile( $contents )
{
	$tmp = "/tmp/fog-" . rand ( 0, 999999999 ) . ".pxe";
	$hndl = fopen( $tmp, "w" );	
	if( $hndl )
	{	
		if ( fwrite( $hndl, $contents ) )
		{
			return $tmp;
		}
		fclose( $hndl );	
	}
	return null;
}

/*
 *  Until we move to a busy box based client image
 *  that can handle dns lookups, this is the poor mans
 *  name resolution.
 */

function sloppyNameLookup( $host )
{
	if ( USE_SLOPPY_NAME_LOOKUPS )
		return gethostbyname( $host );
	
	return $host;
}

function createDiskSufaceTestPackage( $conn, $member, &$reason )
{
	global $currentUser;
	if ( $conn != null && $member != null )
	{
		if (  $member->getMACDash() != null  )
		{

			$mac = strtolower( $member->getMACImageReady() );			
			$output = "# Created by FOG Imaging System\n\n
						  DEFAULT send\n
						  LABEL send\n
						  kernel " . PXE_KERNEL . "\n
						  append initrd=" . PXE_IMAGE . "  root=/dev/ram0 rw ramdisk_size=" . PXE_KERNEL_RAMDISK . " ip=dhcp dns=" . PXE_IMAGE_DNSADDRESS . " mac=" . $member->getMACColon() . " web=" . sloppyNameLookup(WEB_HOST) . WEB_ROOT . " mode=badblocks quiet";
			$tmp = createPXEFile( $output );
			if( $tmp !== null )
			{
				$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
					
				if ( $num == 0 )
				{	
				
					$ftp = ftp_connect(TFTP_HOST); 
					$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
					if ($ftp && $ftp_loginres ) 
					{
						if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
						{		
							$sql = "insert into 
									tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
									values('" . mysql_real_escape_string($member->getHostName() . " Testdisk") . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'T' )";
							if ( mysql_query( $sql, $conn ) )
							{
								wakeUp( $member->getMACColon() );																			
								lg( "Testdisk package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );										
								@ftp_close($ftp);
								@unlink( $tmp );
								return true;								
							}
							else
							{
								ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
								$reason = mysql_error();								
							}							
						}  
 						else
							$reason = "Unable to upload file."; 											
 					}	
 					else
						$reason = "Unable to connect to tftp server."; 				
					
					@ftp_close($ftp); 					
					@unlink( $tmp );		
				}	
			}
			else
				$reason = "Failed to open tmp file.";
			
		} 
		else
			$reason = "MAC is null.";
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return false;	
}

function createTestDiskPackage( $conn, $member, &$reason )
{
	global $currentUser;
	if ( $conn != null && $member != null )
	{
		if (  $member->getMACDash() != null  )
		{

			$mac = strtolower( $member->getMACImageReady() );			
			$output = "# Created by FOG Imaging System\n\n
						  DEFAULT send\n
						  LABEL send\n
						  kernel " . PXE_KERNEL . "\n
						  append initrd=" . PXE_IMAGE . "  root=/dev/ram0 rw ramdisk_size=" . PXE_KERNEL_RAMDISK . " ip=dhcp dns=" . PXE_IMAGE_DNSADDRESS . " mac=" . $member->getMACColon() . " web=" . sloppyNameLookup(WEB_HOST) . WEB_ROOT . " mode=checkdisk quiet";
			$tmp = createPXEFile( $output );
			if( $tmp !== null )
			{
				$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
					
				if ( $num == 0 )
				{	
				
					$ftp = ftp_connect(TFTP_HOST); 
					$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
					if ($ftp && $ftp_loginres ) 
					{
						if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
						{		
							$sql = "insert into 
									tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
									values('" . mysql_real_escape_string($member->getHostName() . " Testdisk") . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'T' )";
							if ( mysql_query( $sql, $conn ) )
							{
								wakeUp( $member->getMACColon() );																			
								lg( "Testdisk package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );
								@ftp_close($ftp); 					
								@unlink( $tmp );								
								return true;								
							}
							else
							{
								ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
								$reason = mysql_error();								
							}							
						}  
 						else
							$reason = "Unable to upload file."; 											
 					}	
 					else
						$reason = "Unable to connect to tftp server."; 				
					
					@ftp_close($ftp); 					
					@unlink( $tmp );					
									
				}	
			}
			else
				$reason = "Failed to open tmp file.";
			
		} 
		else
			$reason = "MAC is null.";
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return false;	
}

function createWipePackage( $conn, $member, &$reason, $mode=WIPE_NORMAL, $blFast=false )
{
	global $currentUser;
	if ( $conn != null && $member != null )
	{
		if ( $member->getMACDash() != null  )
		{

			$mac = strtolower( $member->getMACImageReady() );
						
			$wipemode="wipemode=full";
			if ( $mode ==  WIPE_FAST )
				$wipemode="wipemode=fast";
			else if ( $mode ==  WIPE_NORMAL )
				$wipemode="wipemode=normal";
			else if ( $mode ==  WIPE_FULL )	
				$wipemode="wipemode=full";		
				
			$output = "# Created by FOG Imaging System\n\n
						  DEFAULT send\n
						  LABEL send\n
						  kernel " . PXE_KERNEL . "\n
						  append initrd=" . PXE_IMAGE . "  root=/dev/ram0 rw ramdisk_size=" . PXE_KERNEL_RAMDISK . " ip=dhcp dns=" . PXE_IMAGE_DNSADDRESS . " mac=" . $member->getMACColon() . " web=" . sloppyNameLookup(WEB_HOST) . WEB_ROOT . " osid=" . $member->getOSID() . " $wipemode mode=wipe quiet";
			$tmp = createPXEFile( $output );
			if( $tmp !== null )
			{
				$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
					
				if ( $num == 0 )
				{	
				
					$ftp = ftp_connect(TFTP_HOST); 
					$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
					if ($ftp && $ftp_loginres ) 
					{
						if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
						{		
							$sql = "insert into 
									tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
									values('" . mysql_real_escape_string($member->getHostName() . " Wipe") . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'W' )";
							if ( mysql_query( $sql, $conn ) )
							{
								wakeUp( $member->getMACColon() );																			
								lg( "Wipe package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );										
								@ftp_close($ftp); 					
								@unlink( $tmp );								
								return true;								
							}
							else
							{
								ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
								$reason = mysql_error();								
							}							
						}  
 						else
							$reason = "Unable to upload file."; 											
 					}	
 					else
						$reason = "Unable to connect to tftp server."; 				
					
					@ftp_close($ftp); 					
					@unlink( $tmp ); 					
									
				}	
			}
			else
				$reason = "Failed to open tmp file.";
			
		} 
		else
			$reason = "MAC is null.";
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return false;	
}

function avModeToString( $avMode )
{
	if ( $avMode == "q" )
		return "Quarantine";
	else if ( $avMode == "s" )
		return "Report";	
}

function clearAVRecord( $conn, $avID )
{
	if ( $conn != null && $avID != null && is_numeric( $avID ) )
	{
		$vid = mysql_real_escape_string( $avID );
		$sql = "delete from virus where vID = '$vid'";
		return mysql_query( $sql, $conn );
	}
	return false;
}

function clearAVRecordsForHost( $conn, $mac )
{
	if ( $conn != null && $mac != null  )
	{
		$mac = mysql_real_escape_string( $mac );
		$sql = "delete from virus where vHostMAC = '$mac'";
		return mysql_query( $sql, $conn );
	}
	return false;
}

function clearAllAVRecords( $conn )
{
	if ( $conn != null  )
	{
		$sql = "delete from virus";
		return mysql_query( $sql, $conn );
	}
	return false;
}

function createAVPackage( $conn, $member, &$reason, $mode=FOG_AV_SCANONLY )
{
	global $currentUser;
	if ( $conn != null && $member != null )
	{
		if ( $member->getMACDash() != null  )
		{

			$mac = strtolower( $member->getMACImageReady() );
						
			$scanmode="avmode=s";
			if ( $mode ==  FOG_AV_SCANQUARANTINE )
				$scanmode="avmode=q";
		
				
			$output = "# Created by FOG Imaging System\n\n
						  DEFAULT send\n
						  LABEL send\n
						  kernel " . PXE_KERNEL . "\n
						  append initrd=" . PXE_IMAGE . "  root=/dev/ram0 rw ramdisk_size=" . PXE_KERNEL_RAMDISK . " ip=dhcp dns=" . PXE_IMAGE_DNSADDRESS . " mac=" . $member->getMACColon() . " web=" . sloppyNameLookup(WEB_HOST) . WEB_ROOT . " osid=" . $member->getOSID() . " $scanmode mode=clamav quiet";
			$tmp = createPXEFile( $output );
			if( $tmp !== null )
			{
				$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
					
				if ( $num == 0 )
				{	
				
					$ftp = ftp_connect(TFTP_HOST); 
					$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
					if ($ftp && $ftp_loginres ) 
					{
						if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
						{		
							$sql = "insert into 
									tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
									values('" . mysql_real_escape_string($member->getHostName() . " ClamScan") . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'v' )";
							if ( mysql_query( $sql, $conn ) )
							{
								wakeUp( $member->getMACColon() );																			
								lg( "ClamAV package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );										
								@ftp_close($ftp); 					
								@unlink( $tmp );								
								return true;								
							}
							else
							{
								ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
								$reason = mysql_error();								
							}							
						}  
 						else
							$reason = "Unable to upload file."; 											
 					}	
 					else
						$reason = "Unable to connect to tftp server."; 				
					
					@ftp_close($ftp); 					
					@unlink( $tmp ); 					
									
				}	
				else
					$reason = "Host is already a member of an active task!";
			}
			else
				$reason = "Failed to open tmp file.";
			
		} 
		else
			$reason = "MAC is null.";
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return false;	
}


function createUploadImagePackage( $conn, $member, &$reason, $debug=false )
{
	global $currentUser;
	if ( $conn != null && $member != null )
	{
		if (  $member->getImage() != null && $member->getMACDash() != null  )
		{

			$mac = strtolower( $member->getMACImageReady() );

			$image = $member->getImage();
			$imageid = $member->getImageID();
			$building = $member->getBuilding();

			$mode = "";
			if ($debug)
				$mode = "mode=debug";		
				
			$imgType = "imgType=n";
			if ( $member->getImageType() == ImageMember::IMAGETYPE_DISKIMAGE )
				$imgType = "imgType=dd";
			
			$pct = "pct=5"; // default percentage
			
			if ( is_numeric(UPLOADRESIZEPCT) && UPLOADRESIZEPCT >= 5 && UPLOADRESIZEPCT < 100 )
				$pct = "pct=" . UPLOADRESIZEPCT;
			
			$output = "# Created by FOG Imaging System\n\n
						  DEFAULT send\n
						  LABEL send\n
						  kernel " . PXE_KERNEL . "\n
						  append initrd=" . PXE_IMAGE . "  root=/dev/ram0 rw ramdisk_size=" . PXE_KERNEL_RAMDISK . " ip=dhcp dns=" . PXE_IMAGE_DNSADDRESS . " type=up img=$image imgid=$imageid mac=" . $member->getMACColon() . " storage=" . sloppyNameLookup(STORAGE_HOST) . ":" . STORAGE_DATADIR_UPLOAD . " web=" . sloppyNameLookup(WEB_HOST) . WEB_ROOT . " osid=" . $member->getOSID() . " $mode $pct $imgType quiet";
			$tmp = createPXEFile( $output );
			if( $tmp !== null )
			{
				$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
					
				if ( $num == 0 )
				{	
				
					$ftp = ftp_connect(TFTP_HOST); 
					$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
					if ($ftp && $ftp_loginres ) 
					{
						if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
						{		
							$sql = "insert into 
									tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
									values('" . mysql_real_escape_string($taskName) . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'U' )";
							if ( mysql_query( $sql, $conn ) )
							{
								wakeUp( $member->getMACColon() );																			
								lg( "Image upload package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );
								@ftp_close($ftp); 					
								@unlink( $tmp );								
								return true;								
							}
							else
							{
								ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
								$reason = mysql_error();								
							}							
						}  
 						else
							$reason = "Unable to upload file."; 											
 					}	
 					else
						$reason = "Unable to connect to tftp server."; 				
					
					@ftp_close($ftp); 					
					@unlink( $tmp );					
									
				}	
			}
			else
				$reason = "Failed to open tmp file.";
			
		} 
		else
			$reason = "Either image, or MAC address is null.";
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return false;
}

function getCountOfActiveTasksWithMAC( $conn, $mac )
{
	if ( $conn != null && $mac != null )
	{
		$sql = "select count(*) as cnt 
			from tasks 
			inner join hosts on ( tasks.taskHostID = hostID ) 
			where hostMAC = '" . mysql_real_escape_string($mac) . "' and tasks.taskState in (0,1)";	
		$res = mysql_query( $sql, $conn );
		if ( $res )
		{			
			if ( $ar = mysql_fetch_array( $res ) )
			{
				return $ar["cnt"];
			}		
		}
	}
	return -1;
}

function createMemTestPackage($conn, $member, &$reason)
{
	// Load memtest86+
	global $currentUser;
	if ( $conn != null && $member != null )
	{
		if ( $member->getMACDash() != null )
		{
			$mac = strtolower( $member->getMACImageReady() );

			$output = "# Created by FOG Imaging System\n\n
						  DEFAULT fog\n
						  LABEL fog\n
						  kernel " . MEMTEST_KERNEL . "\n";
						  	  
			$tmp = createPXEFile( $output );

			if( $tmp !== null )
			{
				// make sure there is no active task for this mac address
				$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
				
				if ( $num == 0 )
				{
					// attempt to ftp file
										
					$ftp = ftp_connect(TFTP_HOST); 
					$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
					if ($ftp && $ftp_loginres ) 
					{
						if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
						{						
							$sql = "insert into 
									tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
									values('" . mysql_real_escape_string('MEMTEST') . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'M' )";
							if ( mysql_query( $sql, $conn ) )
							{
								// lets try to wake the computer up!
								wakeUp( $member->getMACColon() );																			
								lg( "memtest package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );
								@ftp_close($ftp); 					
								@unlink( $tmp );								
								return true;
							}
							else
							{
								ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
								$reason = mysql_error();
							}
						}  
						else
							$reason = "Unable to upload file."; 											
					}	
					else
						$reason = "Unable to connect to tftp server."; 	
						
					@ftp_close($ftp); 					
					@unlink( $tmp );							
				}
				else
					$reason = "Host is already a member of a active task.";
			}
			else
				$reason = "Failed to open tmp file.";
			
		} 
		else
		{
			if ( $member->getMACDash() == null )
				$reason = "MAC Address is null";
		}
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return false;
}


function createDebugPackage($conn, $member, &$reason)
{
	// Just load image
	global $currentUser;
	if ( $conn != null && $member != null )
	{
		if ( $member->getMACDash() != null )
		{
			$mac = strtolower( $member->getMACImageReady() );


			
			$output = "# Created by FOG Imaging System\n\n
						  DEFAULT fog\n
						  LABEL fog\n
						  kernel " . PXE_KERNEL . "\n
						  append initrd=" . PXE_IMAGE . "  root=/dev/ram0 rw ramdisk_size=" . PXE_KERNEL_RAMDISK . " ip=dhcp dns=" . PXE_IMAGE_DNSADDRESS . " mode=onlydebug";
						  
			$tmp = createPXEFile( $output );

			if( $tmp !== null )
			{
				// make sure there is no active task for this mac address
				$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
				
				if ( $num == 0 )
				{
					// attempt to ftp file
										
					$ftp = ftp_connect(TFTP_HOST); 
					$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
					if ($ftp && $ftp_loginres ) 
					{
						if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
						{						
							$sql = "insert into 
									tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
									values('" . mysql_real_escape_string('DEBUG') . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'X' )";
							if ( mysql_query( $sql, $conn ) )
							{
								// lets try to wake the computer up!
								wakeUp( $member->getMACColon() );																			
								lg( "debug package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );
								@ftp_close($ftp); 					
								@unlink( $tmp );								
								return true;
							}
							else
							{
								ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
								$reason = mysql_error();
							}
						}  
						else
							$reason = "Unable to upload file."; 											
					}	
					else
						$reason = "Unable to connect to tftp server."; 	
						
					@ftp_close($ftp); 					
					@unlink( $tmp );							
				}
				else
					$reason = "Host is already a member of a active task.";
			}
			else
				$reason = "Failed to open tmp file.";
			
		} 
		else
		{
			if ( $member->getMACDash() == null )
				$reason = "MAC Address is null";
		}
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return false;
}

function getMulticastPort( $conn )
{
	$endingPort = UDPCAST_STARTINGPORT + (FOG_MULTICAST_MAX_SESSIONS * 2);
	if ( $conn !== null && UDPCAST_STARTINGPORT !== null && isValidPortNumber( UDPCAST_STARTINGPORT ) && isValidPortNumber( $endingPort ) )
	{
		$sql = "select msBasePort from multicastSessions order by msID desc limit 1";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		
		$recPort = UDPCAST_STARTINGPORT;
		
		if ( $ar = mysql_fetch_array( $res ) )
		{
			$potPort = ($ar["msBasePort"]  + 2);
			if ( $potPort >= UDPCAST_STARTINGPORT && ($potPort + 1) < $endingPort )
			{
				$recPort = $potPort;
			}
		}
		
		if ( ( ( $recPort % 2 ) == 0 ) && $recPort >= UDPCAST_STARTINGPORT && $recPort + 1 < $endingPort )
		{
			return $recPort;
		}
	}
	return -1;
}

function isValidPortNumber( $port )
{
	if ( $port <= 65535 && $port > 0 && is_numeric($port) )
		return true;
		
	return false;
}


function deleteMulticastJob( $conn, $mcid )
{
	// first pulls all the associations, delete the jobs in task table, delete associations, then deletes mc task.
	if ( $conn != null && is_numeric( $mcid ) && $mcid !== null )
	{
		$mcid = mysql_real_escape_string( $mcid );
		$sql = "select tID from multicastSessionsAssoc where msID = '$mcid'";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			$sql = "select taskHostID from tasks where taskID = '" . mysql_real_escape_string( $ar["tID"] ) . "'";
			$res_hostid = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
			if ( $ar_hid = mysql_fetch_array( $res_hostid ) )
			{
				$im = getImageMemberFromHostID( $conn, $ar_hid["taskHostID"] );
				if ( $im != null )
				{
					if ( ! ftpDelete( TFTP_PXE_CONFIG_DIR . $im->getMACImageReady() ) )
					{
						msgBox( "Unable to delete PXE file" );
					}				
				}
			}
					
			$sql = "delete from tasks where taskID = '" . mysql_real_escape_string( $ar["tID"] ) . "'";
			mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );			
		}
		
		// now remove all the associations
		$sql = "delete from multicastSessionsAssoc where msID = '$mcid'";
		mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		
		// now remove the multicast task
		$sql = "update multicastSessions set msState = '2' where msID = '" . $mcid . "'";
		mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );	
		return true;	
	}
	return false;
}

// returns the parent job id
function createMulticastJob( $conn, $name, $port, $path, $eth, $blIsDD )
{
	if ( $conn != null && isValidPortNumber($port) && $path !== null && $eth !== null )
	{
		$name = mysql_real_escape_string( $name );
		$port = mysql_real_escape_string( $port );
		$path = mysql_real_escape_string( $path );
		$eth  = mysql_real_escape_string( $eth );
		$dd = "0"; 
		
		if ( $blIsDD )
			$dd = "1";
		
		$sql = "insert into multicastSessions(msName, msBasePort, msImage, msInterface, msStartDateTime, msPercent, msState, msIsDD ) values('$name', '$port', '$path', '$eth', NOW(), '0', '0', '$dd')";
		mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		$id = mysql_insert_id( $conn );
		if ( $id !== null )
			return $id;
	}
	return -1;
}

function linkTaskToMultitaskJob( $conn, $taskid, $mcid )
{
	if ( $conn != null && $taskid !== null && $mcid !== null && is_numeric( $taskid) && is_numeric( $mcid ) )
	{
		$taskid = mysql_real_escape_string( $taskid );
		$mcid = mysql_real_escape_string( $mcid );
		
		$sql = "insert into multicastSessionsAssoc(msID, tID) values('$mcid', '$taskid')";
		
		mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		return true;		
	}
	return false;
}

// this function return the insert id so the multicast session can be linked with the single tasks
function createImagePackageMulticast($conn, $member, $taskName, $port, &$reason, $debug=false, $deploySnapins=true )
{
	global $currentUser;
	
	if ( $conn != null && $member != null )
	{
		if ( $port !== null && is_numeric( $port ) )
		{
			if ( $member->getImage() != null && $member->getMACDash() != null )
			{
				$mac = strtolower( $member->getMACImageReady() );

				$image = $member->getImage();
				$mode = "";
				if ($debug)
					$mode = "mode=debug";
				
				$imgType = "imgType=n";
				if ( $member->getImageType() == ImageMember::IMAGETYPE_DISKIMAGE )
					$imgType = "imgType=dd";
				else
				{
					if ( $member->getOSID() == "99" )
					{
						$reason = "Invalid OS type, unable to determine MBR.";
						return false;
					}
					
					if ( strlen( trim($member->getOSID()) ) == 0 )
					{
						$reason = "Invalid OS type, you must specify an OS Type to image.";
						return false;
					}
				}									
				
				$output = "# Created by FOG Imaging System\n\n
							  DEFAULT fog\n
							  LABEL fog\n
							  kernel " . PXE_KERNEL . "\n
							  append initrd=" . PXE_IMAGE . " root=/dev/ram0 rw ramdisk_size=" . PXE_KERNEL_RAMDISK . " ip=dhcp dns=" . PXE_IMAGE_DNSADDRESS . " type=down mc=yes port=" . $port . " storageip=" . sloppyNameLookup(STORAGE_HOST) . " storage=" . sloppyNameLookup(STORAGE_HOST) . ":" . STORAGE_DATADIR . " mac=" . $member->getMACColon() . " ftp=" . sloppyNameLookup(TFTP_HOST) . " web=" . sloppyNameLookup(WEB_HOST) . WEB_ROOT . " osid=" . $member->getOSID() . " $mode $imgType quiet";
							  
				$tmp = createPXEFile( $output );

				if( $tmp !== null )
				{
					// make sure there is no active task for this mac address
					$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
					
					if ( $num == 0 )
					{
						// attempt to ftp file
											
						$ftp = ftp_connect(TFTP_HOST); 
						$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
						if ($ftp && $ftp_loginres ) 
						{
							if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
							{						
								$sql = "insert into 
										tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
										values('" . mysql_real_escape_string($taskName) . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'C' )";
								if ( mysql_query( $sql, $conn ) )
								{
									$insertId = mysql_insert_id( $conn );
									if ( $insertId !== null && $insertId >= 0 )
									{
										if ( $deploySnapins )
										{
											// Remove any exists snapin tasks
											cancelSnapinsForHost( $conn, $member->getID() );
											
											// now do a clean snapin deploy
											deploySnapinsForHost( $conn, $member->getID() );
										}
										
										// lets try to wake the computer up!
										wakeUp( $member->getMACColon() );																			
										lg( "Image push multicast package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );
										@ftp_close($ftp); 					
										@unlink( $tmp );								
										return $insertId;
									}
								}
								else
								{
									ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
									$reason = mysql_error();
								}
							}  
							else
								$reason = "Unable to upload file."; 											
						}	
						else
							$reason = "Unable to connect to tftp server."; 	
							
						@ftp_close($ftp); 					
						@unlink( $tmp ); 							
					}
					else
						$reason = "Host is already a member of a active task.";
				}
				else
					$reason = "Failed to open tmp file.";
			}
			else
			{
				if( $member->getImage() == null )
					$reason = "Image assocation is null, please define an image for this host.";
				
				if ( $member->getMACDash() == null )
					$reason = "MAC Address is null";			
			}
			
		} 
		else
		{
			$reason = "Invalid port number, $port";
		}
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return -1;
}

function createImagePackage($conn, $member, $taskName, &$reason, $debug=false, $deploySnapins=true )
{
	global $currentUser;
	if ( $conn != null && $member != null )
	{
		if ( $member->getImage() != null && $member->getMACDash() != null )
		{
			$mac = strtolower( $member->getMACImageReady() );

			$image = $member->getImage();
			$mode = "";
			if ($debug)
				$mode = "mode=debug";
				
			$imgType = "imgType=n";
			if ( $member->getImageType() == ImageMember::IMAGETYPE_DISKIMAGE )
				$imgType = "imgType=dd";
			else
			{
				if ( $member->getOSID() == "99" )
				{
					$reason = "Invalid OS type, unable to determine MBR.";
					return false;
				}
				
				if ( strlen( trim($member->getOSID()) ) == 0 )
				{
					$reason = "Invalid OS type, you must specify an OS Type to image.";
					return false;
				}
			}				
			
			$output = "# Created by FOG Imaging System\n\n
						  DEFAULT fog\n
						  LABEL fog\n
						  kernel " . PXE_KERNEL . "\n
						  append initrd=" . PXE_IMAGE . "  root=/dev/ram0 rw ramdisk_size=" . PXE_KERNEL_RAMDISK . " ip=dhcp dns=" . PXE_IMAGE_DNSADDRESS . " type=down img=$image mac=" . $member->getMACColon() . " ftp=" . sloppyNameLookup(TFTP_HOST) . " storage=" . sloppyNameLookup(STORAGE_HOST) . ":" . STORAGE_DATADIR . " web=" . sloppyNameLookup(WEB_HOST) . WEB_ROOT . " osid=" . $member->getOSID() . " $mode $imgType quiet";
						  
			$tmp = createPXEFile( $output );

			if( $tmp !== null )
			{
				// make sure there is no active task for this mac address
				$num = getCountOfActiveTasksWithMAC( $conn, $member->getMACColon());
				
				if ( $num == 0 )
				{
					// attempt to ftp file
										
					$ftp = ftp_connect(TFTP_HOST); 
					$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
					if ($ftp && $ftp_loginres ) 
					{
						if ( ftp_put( $ftp, TFTP_PXE_CONFIG_DIR . $mac, $tmp, FTP_ASCII ) )
						{						
							$sql = "insert into 
									tasks(taskName, taskCreateTime, taskCheckIn, taskHostID, taskState, taskCreateBy, taskForce, taskType ) 
									values('" . mysql_real_escape_string($taskName) . "', NOW(), NOW(), '" . $member->getID() . "', '0', '" . mysql_real_escape_string( $currentUser->getUserName() ) . "', '0', 'D' )";
							if ( mysql_query( $sql, $conn ) )
							{
								if ( $deploySnapins )
								{
									// Remove any exists snapin tasks
									cancelSnapinsForHost( $conn, $member->getID() );
									
									// now do a clean snapin deploy
									deploySnapinsForHost( $conn, $member->getID() );
								}
								
								// lets try to wake the computer up!
								wakeUp( $member->getMACColon() );																			
								lg( "Image push package created for host " . $member->getHostName() . " [" . $member->getMACDash() . "]" );
								@ftp_close($ftp); 					
								@unlink( $tmp );								
								return true;
							}
							else
							{
								ftp_delete( $ftp, TFTP_PXE_CONFIG_DIR . $mac ); 									
								$reason = mysql_error();
							}
						}  
						else
							$reason = "Unable to upload file."; 											
					}	
					else
						$reason = "Unable to connect to tftp server."; 	
						
					@ftp_close($ftp); 					
					@unlink( $tmp ); 							
				}
				else
					$reason = "Host is already a member of a active task.";
			}
			else
				$reason = "Failed to open tmp file.";
			
		} 
		else
		{
			if( $member->getImage() == null )
				$reason = "Image assocation is null, please define an image for this host.";
			
			if ( $member->getMACDash() == null )
				$reason = "MAC Address is null";
		}
	}
	else
	{
		$reason = "Either member of database connection was null";
	}
	return false;
}

/*
 *
 *    Below are functions that are used in the service scripts
 *
 *
 */

function cleanIncompleteTasks( $conn, $hostid )
{
	if ( $conn != null && $hostid != null )
	{
		$sql = "update tasks set taskState = '0' where taskHostID = '" . mysql_real_escape_string($hostid) . "' and taskState = '1'";	
		mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
	}
}

function queuedTaskExists( $conn, $mac )
{
	if ( $conn != null && $mac != null )
	{
		if ( getTaskIDByMac( $conn, $mac ) != null ) return true;	
	}
	return false;
}

function getTaskIDByMac( $conn, $mac, $state=0 )
{
	if ( $conn != null && $mac != null )
	{
		$sql = "select 
				* 
				from hosts 
				inner join tasks on ( hosts.hostID = tasks.taskHostID ) where hostMAC = '" . mysql_real_escape_string($mac) . "' and taskState = '$state'";
		//echo $sql;
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			return $ar["taskID"];
		}		
	}
	return null;
}

function getNumberInQueue( $conn, $state )
{
	if ( $conn != null && $state != null )
	{
		$sql = "select count(*) as cnt from tasks where taskState = '" . mysql_real_escape_string($state) . "' and taskType in ('U', 'D')";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		
		if ( $ar = mysql_fetch_array( $res ) )
		{
			return $ar["cnt"];
		}
	}
	return null;
}

function checkIn( $conn, $jobid )
{
	if ( $conn != null && $jobid != null )
	{
		$sql = "update tasks set taskCheckIn = NOW() where taskID = '" . mysql_real_escape_string( $jobid ) . "'";
		if ( mysql_query( $sql, $conn ) )
			return true;
	}
	return false;
}

function isForced( $conn, $jobid )
{
	if ( $conn != null && $jobid != null )
	{
		$sql = "select count(*) as c from tasks where taskID = '" . mysql_real_escape_string( $jobid ) . "' and taskForce = 1";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			if ( $ar["c"] == "1" ) return true;
		} 
	}
	return false;
}

function doImage( $conn, $jobid )
{
	if ( $conn != null && $jobid != null )
	{
		$sql = "update tasks set taskState = '1' where taskID = '" . mysql_real_escape_string($jobid) . "'";
		if ( mysql_query( $sql, $conn ) )
			return true;
		else
		{
			die( mysql_error() );
			return false;
		}
	}
}

function getNumberInFrontOfMe( $conn, $jobid )
{
	if ( $conn != null && $jobid != null )
	{
		$sql = "select count(*) as c from tasks where taskState = '0' and taskID < " . mysql_real_escape_string($jobid) . " and (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(taskCheckIn)) < " . CHECKIN_TIMEOUT;
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		if ( $ar = mysql_fetch_array( $res ) )
			return $ar["c"];
	}
	return null;
}

function getHostID( $conn, $mac )
{
	if ( $conn != null && $mac != null )
	{
		$sql = "select * from hosts where hostMAC = '" . mysql_real_escape_string($mac) . "'";
		$res = mysql_query( $sql, $conn ) or criticalError( mysql_error(), "FOG :: Database error!" );
		while( $ar = mysql_fetch_array( $res ) )
		{
			return $ar["hostID"];
		}		
	}
	return null;
}

function checkOut( $conn, $jobid )
{
	if ( $conn != null && $jobid != null )
	{
		$sql = "update tasks set taskState = '2' where taskID = '" . mysql_real_escape_string($jobid). "'";
		if ( mysql_query( $sql, $conn ) )
			return true;
	}
	return false;
}
?>
