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

define( "IS_INCLUDED", true );
define( "TFTP_HOST", "x.x.x.x" );
define( "TFTP_FTP_USERNAME", "fog" );
define( "TFTP_FTP_PASSWORD", "" );
define( "TFTP_PXE_CONFIG_DIR", "/tftpboot/pxelinux.cfg/" );
define( "TFTP_PXE_KERNEL_DIR", "/tftpboot/fog/kernel/" );
define( "PXE_KERNEL", "fog/kernel/bzImage" );
define( "PXE_KERNEL_RAMDISK", 127000 ); 					// default 127000
define( "USE_SLOPPY_NAME_LOOKUPS", true);
define( "MEMTEST_KERNEL", "fog/memtest/memtest" );
define( "PXE_IMAGE",  "fog/images/init.gz" );
define( "PXE_IMAGE_DNSADDRESS",  "x.x.x.x" );
define( "STORAGE_HOST", "x.x.x.x" );
define( "STORAGE_DATADIR", "/images/" );
define( "STORAGE_DATADIR_UPLOAD", "/images/dev/" );
define( "STORAGE_BANDWIDTHPATH", "/fog/status/bandwidth.php" );
define( "CLONEMETHOD", "ntfsclone" );  						// valid values partimage (ntfsclone in the future)
define( "UPLOADRESIZEPCT", 5 ); 						// What percentage of extra space do you want to allow on the image?
define( "WEB_HOST", "x.x.x.x" );
define( "WEB_ROOT", "/fog/" );
define( "WOL_HOST", "x.x.x.x" ); 	
define( "WOL_PATH", "/fog/wol/wol.php" ); 
define( "WOL_INTERFACE", "eth0" );						
define( "SNAPINDIR", "/opt/fog/snapins/" );
define( "QUEUESIZE", "10" );
define( "CHECKIN_TIMEOUT", 600 );
define( "MYSQL_HOST", "localhost" );
define( "MYSQL_DATABASE", "fog" );
define( "MYSQL_USERNAME", "root" );
define( "MYSQL_PASSWORD", "" );
define( "USER_MINPASSLENGTH", 4 );
define( "USER_VALIDPASSCHARS", "1234567890ABCDEFGHIJKLMNOPQRSTUVWZXYabcdefghijklmnopqrstuvwxyz_()^!" );
define( "NFS_ETH_MONITOR", "eth0" );
define("UDPCAST_INTERFACE","eth0");
define("UDPCAST_STARTINGPORT", 63100 ); 					// Must be an even number! recommended between 49152 to 65535
define("FOG_MULTICAST_MAX_SESSIONS", 64 );					// every session will use two ports, starting from the UPDCAST_STARTINGPORT												
define( "FOG_JPGRAPH_VERSION", "2.3" );
define( "FOG_REPORT_DIR", "./reports/" );
define( "FOG_THEME", "blackeye/blackeye.css" );
define( "FOG_UPLOADIGNOREPAGEHIBER", true );
define( "FOG_VERSION", "0.13" );
define( "FOG_SCHEMA", 7);
?>
