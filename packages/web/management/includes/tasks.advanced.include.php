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
echo ( "<p class=\"title\">Advanced Options</p>" );

$hostid = mysql_real_escape_string( $_GET["hostid"] );
$groupid = mysql_real_escape_string( $_GET["groupid"] );
$blIsHost = false;
if ( $hostid !== "" )
{
	$blIsHost = true;
	$imageMembers = getImageMemberFromHostID( $conn, $hostid );	
}
else if( $groupid !== "" )
{
	$imageMembers = getImageMembersByGroupID( $conn, $groupid );
}
else
{
	echo "Error, no host or group found!";
	exit;
}

if ( $blIsHost )
{
	echo ( "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=90%>" );
	echo ( "<tr><td>&nbsp;Hostname </td><td>" . $imageMembers->getHostName() . "</td></tr>" );
	echo ( "<tr><td>&nbsp;IP Address</td><td>" . $imageMembers->getIPAddress() . "</td></tr>" );
	echo ( "<tr><td>&nbsp;MAC Address</td><td>" . $imageMembers->getMAC() . "</td></tr>" );
	echo ( "</table>" );
	echo ( "<p class=\"titleBottomLeft\">Advanced Actions</p>" );
	echo ( "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=100%>" );
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=debug&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/debug.png\" /><p class=\"advancedTitle\">Debug</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Debug mode will load the boot image and load a prompt so you can run any commands you wish.  When you are done, you must remember to remove the PXE file, by clicking on \"Active Tasks\" and clicking on the \"Kill Task\" button.</p></td></tr>" );	
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=down&debug=true&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/senddebug.png\" /><p class=\"advancedTitle\">Send-Debug</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Send-Debug mode allows FOG to setup the environment to allow you send a specific image to a computer, but instead of sending the image, FOG will leave you at a prompt right before sending.  If you actually wish to send the image all you need to do is type \"fog\" and hit enter.</p></td></tr>" );		
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=up&debug=true&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/restoredebug.png\" /><p class=\"advancedTitle\">Upload-Debug</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Upload-Debug mode allows FOG to setup the environment to allow you Upload a specific image to a computer, but instead of Upload the image, FOG will leave you at a prompt right before restoring.  If you actually wish to Upload the image all you need to do is type \"fog\" and hit enter.</p></td></tr>" );			
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=downnosnap&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/sendnosnapin.png\" /><p class=\"advancedTitle\">Send without Snapins</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Send without snapins allows FOG to image the workstation, but after the task is complete any snapins linked to the host or group will NOT be sent.</p></td></tr>" );		
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=allsnaps&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/snap.png\" /><p class=\"advancedTitle\">Deploy Snapins</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">This option allows you to send all the snapins to host without imaging the computer.  (Requires FOG Service to be installed on client)</p></td></tr>" );		
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=onesnap&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/snap.png\" /><p class=\"advancedTitle\">Deploy Single Snapin</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">This option allows you to send a single snapin to a host.  (Requires FOG Service to be installed on client)</p></td></tr>" );			
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=memtest&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/memtest.png\" /><p class=\"advancedTitle\">Memtest86+</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Memtest86+ loads Memtest86+ on the client computer and will have it continue to run until stopped.  When you are done, you must remember to remove the PXE file, by clicking on \"Active Tasks\" and clicking on the \"Kill Task\" button.</p></td></tr>" );		
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=wol&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/wake.png\" /><p class=\"advancedTitle\">Wake Up</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Wake Up will attempt to send the Wake-On-LAN packet to the computer to turn the computer on.  In switched environments, you typically need to configure your hardware to allow for this (iphelper).</p></td></tr>" );			
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=wipe&mode=fast&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/veryfastwipe.png\" /><p class=\"advancedTitle\">Fast Wipe</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Fast Wipe will boot the client computer and perform a quick and lazy disk wipe.  This method writes zero's to the start of the hard disk, destroying the MBR, but NOT overwritting everything on the disk.</p></td></tr>" );					
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=wipe&mode=normal&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/quickwipe.png\" /><p class=\"advancedTitle\">Normal Wipe</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Normal Wipe will boot the client computer and perform a simple disk wipe.  This method writes one pass or zero's to the hard disk.</p></td></tr>" );				
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=wipe&mode=full&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/fullwipe.png\" /><p class=\"advancedTitle\">Full Wipe</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Full Wipe will boot the client computer and perform a full disk wipe.  This method writes a few passes of random data to the hard disk.</p></td></tr>" );					
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=surfacetest&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/surfacetest.png\" /><p class=\"advancedTitle\">Disk Surface Test</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Disk Surface Test checks the hard drive's surface sector by sector for any errors and reports back if errors are present.</p></td></tr>" );							
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=testdisk&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/testdisk.png\" /><p class=\"advancedTitle\">Test Disk</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Test Disk loads the testdisk utility that can be used to check a hard disk and recover lost partitions.</p></td></tr>" );						
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=photorec&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/recover.png\" /><p class=\"advancedTitle\">Recover</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Recover loads the photorec utility that can be used to recover lost files from a hard drisk.  When recovering files, make sure you save them to your NFS volume (ie: /images).</p></td></tr>" );								
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=clamav&noconfirm=" . $imageMembers->getID() ."\"><img class=\"advancedIcon\" src=\"./images/clam.png\" /><p class=\"advancedTitle\">Anti-Virus</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Anti-Virus loads Clam AV on the client boot image, updates the scanner and then scans the Windows partition.  </p></td></tr>" );									
	echo ( "</table>" );
}
else
{
	echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
	for( $i = 0; $i < count( $imageMembers ); $i++ )
	{
		echo ( "<tr><td>&nbsp;" . $imageMembers[$i]->getHostName() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getMac() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getIPAddress() . "</font></td><td><font class=\"smaller\">" . $imageMembers[$i]->getImage() . "</font></td></tr>" );
	}
	echo ( "</table>" );
	echo ( "<p class=\"titleBottomLeft\">Advanced Actions</p>" );
	echo ( "<table width=\"98%\" cellspacing=\"0\" cellpadding=0 border=0>" );
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=debug&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/debug.png\" /><p class=\"advancedTitle\">Debug</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Debug mode will load the boot image and load a prompt so you can run any commands you wish.  When you are done, you must remember to remove the PXE file, by clicking on \"Active Tasks\" and clicking on the \"Kill Task\" button.</p></td></tr>" );	
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=down&debug=true&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/senddebug.png\" /><p class=\"advancedTitle\">Send-Debug</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Send-Debug mode allows FOG to setup the environment to allow you send a specific image to a computer, but instead of sending the image, FOG will leave you at a prompt right before sending.  If you actually wish to send the image all you need to do is type \"fog\" and hit enter.</p></td></tr>" );		
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=downnosnap&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/sendnosnapin.png\" /><p class=\"advancedTitle\">Send without Snapins</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Send without snapins allows FOG to image the workstation, but after the task is complete any snapins linked to the host or group will NOT be sent.</p></td></tr>" );		
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=host&direction=allsnaps&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/snap.png\" /><p class=\"advancedTitle\">Deploy Snapins</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">This option allows you to send all the snapins to host without imaging the computer.  (Requires FOG Service to be installed on client)</p></td></tr>" );		
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=memtest&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/memtest.png\" /><p class=\"advancedTitle\">Memtest86+</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Memtest86+ loads Memtest86+ on the client computer and will have it continue to run until stopped.  When you are done, you must remember to remove the PXE file, by clicking on \"Active Tasks\" and clicking on the \"Kill Task\" button.</p></td></tr>" );		
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=wol&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/wake.png\" /><p class=\"advancedTitle\">Wake Up</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Wake Up will attempt to send the Wake-On-LAN packet to the computer to turn the computer on.  In switched environments, you typically need to configure your hardware to allow for this (iphelper).</p></td></tr>" );			
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=wipe&mode=fast&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/veryfastwipe.png\" /><p class=\"advancedTitle\">Fast Wipe</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Fast Wipe will boot the client computer and perform a quick and lazy disk wipe.  This method writes zero's to the start of the hard disk, destroying the MBR, but NOT overwritting everything on the disk.</p></td></tr>" );					
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=wipe&mode=normal&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/quickwipe.png\" /><p class=\"advancedTitle\">Normal Wipe</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Normal Wipe will boot the client computer and perform a simple disk wipe.  This method writes one pass or zero's to the hard disk.</p></td></tr>" );				
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=wipe&mode=full&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/fullwipe.png\" /><p class=\"advancedTitle\">Full Wipe</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Full Wipe will boot the client computer and perform a full disk wipe.  This method writes a few passes of random data to the hard disk.</p></td></tr>" );					
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=surfacetest&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/surfacetest.png\" /><p class=\"advancedTitle\">Disk Surface Test</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Disk Surface Test checks the hard drive's surface sector by sector for any errors and reports back if errors are present.</p></td></tr>" );								
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=testdisk&noconfirm=" .$groupid ."\"><img class=\"advancedIcon\" src=\"./images/testdisk.png\" /><p class=\"advancedTitle\">Test Disk</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Test Disk loads the testdisk utility that can be used to check a hard disk and recover lost partitions.</p></td></tr>" );						
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=photorec&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/recover.png\" /><p class=\"advancedTitle\">Recover</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Recover loads the photorec utility that can be used to recover lost files from a hard drisk.  When recovering files, make sure you save them to your NFS volume (ie: /images).</p></td></tr>" );						
	echo ( "<tr><td class=\"leadingSpace bottomLightBorder\"><a href=\"?node=tasks&type=group&direction=clamav&noconfirm=" . $groupid ."\"><img class=\"advancedIcon\" src=\"./images/clam.png\" /><p class=\"advancedTitle\">Anti-Virus</p></a></td><td class=\"leadingSpace bottomLightBorder\"><p class=\"advancedDesc\">Anti-Virus loads Clam AV on the client boot image, updates the scanner and then scans the Windows partition.  </p></td></tr>" );							
	echo ( "</table>" );
}

	
echo ( "</div>" );		
?>
