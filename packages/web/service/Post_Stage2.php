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
require_once( "../commons/config.php" );
require_once( "../commons/functions.include.php" );

$conn = mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
if ( $conn )
{
	if ( ! mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "Unable to select database" );
}
else
{
	die( "Unable to connect to Database" );
}

$mac = $_GET["mac"];
$size = $_GET["size"];
$imgid = $_GET["imgid"];
if ( ! isValidMACAddress( $mac ) )
{
	die( "Invalid MAC address format!" );
}

if ( ! is_numeric( $imgid ) )
{
	die( "Image ID must be numeric" );
}

if ( $mac != null  )
{
	$ftp = ftp_connect(TFTP_HOST); 
	$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
	if ((!$ftp) || (!$ftp_loginres )) 
	{
  		echo "FTP connection has failed!";
 		exit;
 	}			
 	$mac = str_replace( ":", "-", $mac );
	@ftp_delete ( $ftp, TFTP_PXE_CONFIG_DIR . "01-". $mac );
	
	
	$mac = str_replace( "-", ":", $mac );
	$jobid = getTaskIDByMac( $conn, $mac, 0 );

	$src = STORAGE_DATADIR_UPLOAD . $mac . ".000";
	$srcdd = STORAGE_DATADIR_UPLOAD . $mac;
	$dest = STORAGE_DATADIR . $_GET["to"];
	if (ftp_rename ( $ftp, $src, $dest ) || ftp_rename ( $ftp, $srcdd, $dest ))
	{
		if ( checkOut( $conn, $jobid ) )
		{
			echo "##";
		}
		else
			echo ( "Error: Checkout failed!" );
	}
	else
	{
		echo "unable to move $src to $dest";
	}
	
	ftp_close($ftp); 
}
else
	echo "Invalid MAC or FTP Address";
?>
