<?php
/*
 *  FOG  is a computer imaging solution.
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

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	echo ( "<div class=\"scroll\">" );
	echo ( "<p class=\"title\">Confirm Task</p>" );
	if ( $_GET["noconfirm"] != null )
	{	
		$noconfirm = trim( mysql_real_escape_string($_GET[noconfirm]) );
		if ( $_GET["direction"] == "up" )
		{
			$imageMembers = getImageMemberFromHostID( $conn, $noconfirm );
			if ( $imageMembers != null )
			{
				echo ( "<p class=\"confirmMessage\">Are you sure you wish to upload this machine?</p>" );
				echo ( "<table width=\"100%\" cellspacing=\"0\" cellpadding=0 border=0>" );
				echo ( "<tr><td><font>" . $imageMembers->getHostName() . "</font></td><td><font>" . $imageMembers->getMac() . "</font></td><td><font>" . $imageMembers->getIPAddress() . "</font></td><td><font>" . $imageMembers->getImage() . "</font></td></tr>" );
				echo ( "<tr><td colspan=10><font><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Upload Image\" onClick=\"location.href='?node=$_GET[node]&sub=$_GET[sub]&debug=$_GET[debug]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]';\" /></center></font></td></tr>" );
				echo ( "</table>" );
			}
			else
			{
				msgBox( "Error:  Is an image associated with the computer?" );
			}				
		}
		else if ( $_GET["direction"] == "down" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}
			
			if ( count( $imageMembers ) > 0 )
			{
				echo ( "<p class=\"confirmMessage\">Are you sure you wish to image these machines?</p>" );
				echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
				for( $i = 0; $i < count( $imageMembers ); $i++ )
				{
					echo ( "<tr><td><font class=\"smaller\">" . $imageMembers[$i]->getHostName() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
				}
				echo ( "<tr><td colspan=10><font class=\"smaller\"><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Image All Computers\" onClick=\"location.href='?node=$_GET[node]&sub=$_GET[sub]&debug=$_GET[debug]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]';\" /></center></font></td></tr>" );
				echo ( "</table>" );

			}
			else
			{
				echo ( "No Host are members of this group" );
			}
		}
		else if ( $_GET["direction"] == "downmc" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}
			
			if ( count( $imageMembers ) > 0 )
			{
				if ( doAllMembersHaveSameImage( $imageMembers ) )
				{
					echo ( "<p class=\"confirmMessage\">Are you sure you wish to image these machines using multicast?</p>" );
					echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
					for( $i = 0; $i < count( $imageMembers ); $i++ )
					{
						echo ( "<tr><td><font class=\"smaller\">" . $imageMembers[$i]->getHostName() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
					}
					echo ( "<tr><td colspan=10><font class=\"smaller\"><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Image All Computers using multicast\" onClick=\"location.href='?node=$_GET[node]&sub=$_GET[sub]&debug=$_GET[debug]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]';\" /></center></font></td></tr>" );
					echo ( "</table>" );
				}
				else
				{
					echo ( "<p class=\"confirmMessage\">Unable to multicast to this group of computers because they all do not have the same image definition!</p>" );
				}
			}
			else
			{
				echo ( "No Host are members of this group" );
			}
		}		
		else if ( $_GET["direction"] == "wol" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to wake up these machines?</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Wake up computers\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}
		else if ( $_GET["direction"] == "wipe" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to wipe these machines? By wiping these machines you will be destorying all data present on the hard disk.</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Wipe computer(s)\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}
		else if ( $_GET["direction"] == "clamav" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to run ClamAV on these machines?.</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Scan and Report Only\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]&q=0';\" /><br /><br /><input class=\"smaller\" type=\"button\" value=\"Scan and Quarantine\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]&q=1';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}				
		else if ( $_GET["direction"] == "debug" )
		{
			// general debugging environment
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to debug these machines?</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Debug computer(s)\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}
		else if ( $_GET["direction"] == "memtest" )
		{
			// memtest86+
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to run memtest86+ on these machines?</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Run memtest86+\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}
		else if ( $_GET["direction"] == "testdisk" )
		{
			// testdisk
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to run testdisk on these machines?</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Run testdisk\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}	
		else if ( $_GET["direction"] == "photorec" )
		{
			// photorec
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to run file recovery on these machines?</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Run File Recovery\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}
		else if ( $_GET["direction"] == "surfacetest" )
		{
			// badblocks
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to run Disk Surface Test on these machines?</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Run Surface Test\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}
		else if ( $_GET["direction"] == "allsnaps" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to deploy all linked snapins these machines?</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Deploy Snapins\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]&mode=$_GET[mode]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}	
		else if ( $_GET["direction"] == "downnosnap" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}
			
			if ( count( $imageMembers ) > 0 )
			{
				echo ( "<p class=\"confirmMessage\">Are you sure you wish to image these machines (without snapins)?</p>" );
				echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
				for( $i = 0; $i < count( $imageMembers ); $i++ )
				{
					echo ( "<tr><td><font class=\"smaller\">" . $imageMembers[$i]->getHostName() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
				}
				echo ( "<tr><td colspan=10><font class=\"smaller\"><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Image All Computers\" onClick=\"location.href='?node=$_GET[node]&sub=$_GET[sub]&debug=$_GET[debug]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]';\" /></center></font></td></tr>" );
				echo ( "</table>" );

			}
			else
			{
				echo ( "No Host are members of this group" );
			}
		}
		else if ( $_GET["direction"] == "onesnap" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}
			
			if ( count( $imageMembers ) > 0 )
			{
				echo ( "<form method=\"GET\" action=\"index.php\" />" );

				echo ( "<input type=\"hidden\" name=\"node\" value=\"" . $_GET["node"] . "\" />" );
				echo ( "<input type=\"hidden\" name=\"sub\" value=\"" . $_GET["sub"] . "\" />" );
				echo ( "<input type=\"hidden\" name=\"confirm\" value=\"" . $noconfirm . "\" />" );
				echo ( "<input type=\"hidden\" name=\"type\" value=\"" . $_GET["type"] . "\" />" );
				echo ( "<input type=\"hidden\" name=\"direction\" value=\"" . $_GET["direction"] . "\" />" );
																
				echo ( "<p class=\"confirmMessage\">Which snapin would you like to deployed to the machines listed below?<br /><br />" . getSnapinDropDown( $conn ) . "<br /><br /></p>" );

				echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
				for( $i = 0; $i < count( $imageMembers ); $i++ )
				{
					echo ( "<tr><td><font class=\"smaller\">" . $imageMembers[$i]->getHostName() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
				}
				echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"submit\" value=\"Deploy Snapin\" /></center></td></tr>" );
				echo ( "</table>" );
				echo ( "</form>" );

			}
			else
			{
				echo ( "No Host are members of this group" );
			}
		}
		else if ( $_GET["direction"] == "inventory" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $noconfirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $noconfirm );
			}	
			
			echo ( "<p class=\"confirmMessage\">Are you sure you wish to update/take an inventory of these machines?</p>" );
			echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
			}
			echo ( "<tr><td colspan=10><center><br /><br /><input class=\"smaller\" type=\"button\" value=\"Run Inventory\" onClick=\"location.href='?node=$_GET[node]&confirm=$noconfirm&type=$_GET[type]&direction=$_GET[direction]';\" /></center></td></tr>" );
			echo ( "</table>" );				
		}		
		
																
	}
	else if ( $_GET["confirm"] != null )
	{
		$confirm = trim( mysql_real_escape_string($_GET["confirm"]) );
		if ( $_GET["direction"] == "up" )
		{
			$imageMembers = getImageMemberFromHostID( $conn, $confirm );
			if ( $imageMembers != null )
			{
				$reason = "";
				if ( createUploadImagePackage( $conn, $imageMembers, $reason, ($_GET["debug"] == "true" ) ) )
				{
					echo ( "<p class=\"infoMessage\">Task Started!</p>" );
				}
				else
				{
					echo ( "<p class=\"infoMessage\">Unable to start task</p><p>$reason</p>" );
				}
			
			}
			else
			{
				msgBox( "Error:  Is an image associated with the computer?" );
			}		
		}		
		else if ( $_GET["direction"] == "down" )
		{
			$imageMembers = null;
			$taskName = "";
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{		
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
				$taskName = getGroupNameByID( $conn, $confirm );
			}
			
			$output = "";
			$suc = 0;
			$fail = 0;
			
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{
					if(! createImagePackage($conn, $imageMembers[$i], $taskName, $tmp, ($_GET["debug"] == "true" ) ) )
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;
					}
					else
					{
						$suc++;
					}
				}
			}	
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were queued without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were able to be queued!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were queued, $fail Failed!.</p><p>$output</p>";
			}					
		}
		else if ( $_GET["direction"] == "downmc" )
		{
			$imageMembers = null;
			$taskName = "";
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{		
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
				$taskName = getGroupNameByID( $conn, $confirm );
			}
			
			$output = "";
			$suc = 0;
			$fail = 0;
			
			if ( $imageMembers !== null && count( $imageMembers ) > 0 && doAllMembersHaveSameImage( $imageMembers ) )
			{
				
				$port = getMulticastPort( $conn );
				
				if ( $port !== -1 )
				{	
					// create the multicast job
					$blIsDD = false;
					if ( $imageMembers[0]->getImageType() == ImageMember::IMAGETYPE_DISKIMAGE )
						$blIsDD = true;
					
					$mcId = createMulticastJob( $conn, $taskName, $port, STORAGE_DATADIR . $imageMembers[0]->getImage(), UDPCAST_INTERFACE, $blIsDD );	
					
					if ( is_numeric( $mcId ) && $mcId >=0 )
					{
						for( $i = 0; $i < count( $imageMembers ); $i++ )
						{
							$tmp = "";
							if ( $imageMembers[$i] != null )
							{
								$taskid = createImagePackageMulticast($conn, $imageMembers[$i], $taskName, $port, $tmp, ($_GET["debug"] == "true" ) );
								if( $taskid == -1 )
								{
									$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
									$fail++;
								}
								else
								{
									if ( linkTaskToMultitaskJob( $conn, $taskid, $mcId ) )
										$suc++;
									// link it to the multitask job
									
								}
							}
						}	
						
						if ( $fail == 0 )
						{
							echo "<p class=\"infoMessage\">All $suc machines were queued without error.</p>";
						}
						else if ( $suc == 0 )
						{
							echo ( "<p class=\"infoMessage\">None of the machines were able to be queued!</p><p>$output</p>" );
							deleteMulticastJob( $conn, $mcId );
						}
						else
						{
							echo "<p class=\"infoMessage\">$suc machines were queued, $fail Failed!.</p><p>$output</p>";
						}
					}
				}
				else
				{
					echo "<p class=\"infoMessage\">Unable to determine a valid multicast port number.</p>";
				}				
			}
			else
			{
				echo "<p class=\"infoMessage\">Unable to create multicast package.</p>";
			}
			
					
		}		
		else if ( $_GET["direction"] == "wol" )
		{
			
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	

			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				wakeUp( $imageMembers[$i]->getMACColon() );
			}
			echo ( "<p class=\"infoMessage\">Wake up packet sent to " . count( $imageMembers ) . " computer(s).</p>" );				
		}
		else if ( $_GET["direction"] == "wipe" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	
			
			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{
					$blfast = false;
					if ( $_GET["mode"] === "fast" )
						$blfast = true;
					if(! createWipePackage( $conn, $imageMembers[$i], $tmp, $blfast ) )
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;
					}
					else
					{
						$suc++;
					}
				}
			}					
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were queued without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were able to be queued!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were queued, $fail Failed!.</p><p>$output</p>";
			}			

		}
		else if ( $_GET["direction"] == "clamav" )
		{
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	
			
			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{
					$mode = FOG_AV_SCANONLY;
					if ( $_GET["q"] == "1" )
						$mode = FOG_AV_SCANQUARANTINE;
					if(! createAVPackage( $conn, $imageMembers[$i], $tmp, $mode ) )
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;
					}
					else
					{
						$suc++;
					}
				}
			}					
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were queued without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were able to be queued!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were queued, $fail Failed!.</p><p>$output</p>";
			}			

		}		
		else if ( $_GET["direction"] == "debug" )
		{
			
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	

			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{			
					if(! createDebugPackage($conn, $imageMembers[$i], $tmp))
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;					
					}
					else
					{
						$suc++;
					}					
				}
			}
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were prepared for debug mode without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were prepared for debug mode!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were prepared for debug mode, $fail Failed!.</p><p>$output</p>";
			}
		}
		else if ( $_GET["direction"] == "memtest" )
		{
			
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	

			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{			
					if(! createMemtestPackage($conn, $imageMembers[$i], $tmp))
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;					
					}
					else
					{
						$suc++;
					}					
				}
			}
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were prepared for memtest mode without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were prepared for memtest mode!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were prepared for memtest mode, $fail Failed!.</p><p>$output</p>";
			}
		}
		else if ( $_GET["direction"] == "testdisk" )
		{
			
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	

			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{			
					if(! createTestDiskPackage($conn, $imageMembers[$i], $tmp))
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;					
					}
					else
					{
						$suc++;
					}					
				}
			}
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were prepared for testdisk without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were prepared for testdisk!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were prepared for testdisk, $fail Failed!.</p><p>$output</p>";
			}
		}	
		else if ( $_GET["direction"] == "photorec" )
		{
			
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	

			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{			
					if(! createTestDiskPackage($conn, $imageMembers[$i], $tmp))
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;					
					}
					else
					{
						$suc++;
					}					
				}
			}
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were prepared for file recovery without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were prepared for file recovery!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were prepared for file recovery, $fail Failed!.</p><p>$output</p>";
			}
		}
		else if ( $_GET["direction"] == "surfacetest" )
		{
			
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	

			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{			
					if(! createDiskSufaceTestPackage($conn, $imageMembers[$i], $tmp))
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;					
					}
					else
					{
						$suc++;
					}					
				}
			}
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were prepared for surface test without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were prepared for surface test!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were prepared for surface test, $fail Failed!.</p><p>$output</p>";
			}
		}
		else if ( $_GET["direction"] == "allsnaps" )
		{
			
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	

			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{		
					cancelSnapinsForHost( $conn, $imageMembers[$i]->getID() );
					$cnt = deploySnapinsForHost( $conn, $imageMembers[$i]->getID() );
					if( $cnt === -1 )
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] Failed<br />";
						$fail++;					
					}
					else
					{
						$suc++;
					}					
				}
			}
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were queued to receive snapins.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were queued to receive snapins!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were queued to receive snapins, $fail Failed!.</p><p>$output</p>";
			}
		}
		else if ( $_GET["direction"] == "downnosnap" )
		{
			$imageMembers = null;
			$taskName = "";
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{		
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
				$taskName = getGroupNameByID( $conn, $confirm );
			}
			
			$output = "";
			$suc = 0;
			$fail = 0;
			
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{
					if(! createImagePackage($conn, $imageMembers[$i], $taskName, $tmp, ($_GET["debug"] == "true" ), false ) )
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;
					}
					else
					{
						$suc++;
					}
				}
			}	
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were queued without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were able to be queued!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were queued, $fail Failed!.</p><p>$output</p>";
			}					
		}
		else if ( $_GET["direction"] == "onesnap" )
		{
			if ( $_GET["snap"] != "-1" && is_numeric( $_GET["snap"] ) )
			{
				$snapin = mysql_real_escape_string( $_GET["snap"] );

				$imageMembers = null;
				$taskName = "";
				if ( $_GET["type"] == "host" )
				{
					$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
				}
				else if ( $_GET["type"] == "group" )
				{		
					$imageMembers = getImageMembersByGroupID( $conn, $confirm );
					$taskName = getGroupNameByID( $conn, $confirm );
				}
				
				$output = "";
				$suc = 0;
				$fail = 0;
				
				for( $i = 0; $i < count( $imageMembers ); $i++ )
				{
					if ( $imageMembers[$i] != null )
					{
						if( deploySnapinsForHost( $conn, $imageMembers[$i]->getID(), $snapin ) == 1 )
						{
							$suc++;
						}
						else
						{
							
							$output .= "[" . $imageMembers[$i]->getHostName() . "] Deploy failed, make sure snapin is linked with host.<br />";
							$fail++;							
						}
					}
				}	
				
				if ( $fail == 0 )
				{
					echo "<p class=\"infoMessage\">All $suc snapins were queued without error.</p>";
				}
				else if ( $suc == 0 )
				{
					echo ( "<p class=\"infoMessage\">None of the snapins were able to be queued!</p><p>$output</p>" );
				}
				else
				{
					echo "<p class=\"infoMessage\">$suc snapins were queued, $fail Failed!.</p><p>$output</p>";
				}
			}					
		}	
		else if ( $_GET["direction"] == "inventory" )
		{
			
			$imageMembers = null;
			if ( $_GET["type"] == "host" )
			{
				$imageMembers = array( getImageMemberFromHostID( $conn, $confirm ) );
			}
			else if ( $_GET["type"] == "group" )
			{
				$imageMembers = getImageMembersByGroupID( $conn, $confirm );
			}	

			$output = "";
			$suc = 0;
			$fail = 0;
			for( $i = 0; $i < count( $imageMembers ); $i++ )
			{
				$tmp = "";
				if ( $imageMembers[$i] != null )
				{			
					if(! createInventoryPackage($conn, $imageMembers[$i], $tmp))
					{
						$output .= "[" . $imageMembers[$i]->getHostName() . "] " . $tmp . "<br />";
						$fail++;					
					}
					else
					{
						$suc++;
					}					
				}
			}
			
			if ( $fail == 0 )
			{
				echo "<p class=\"infoMessage\">All $suc machines were prepared for inventory without error.</p>";
			}
			else if ( $suc == 0 )
			{
				echo ( "<p class=\"infoMessage\">None of the machines were prepared for inventory!</p><p>$output</p>" );
			}
			else
			{
				echo "<p class=\"infoMessage\">$suc machines were prepared for inventory, $fail Failed!.</p><p>$output</p>";
			}
		}																
							
	}
	echo ( "</div>" );

}
?>
