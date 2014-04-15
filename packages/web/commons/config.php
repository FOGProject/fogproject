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

/*
 *  DATABASE VARIABLES
 *  ------------------
 */

define('DATABASE_TYPE',		'mysql');	// mysql or oracle
define('DATABASE_HOST',		'127.0.0.1');
define('DATABASE_NAME',		'fog');
define('DATABASE_USERNAME',		'root');
define('DATABASE_PASSWORD',		'');

/*
 *  SYSTEM CONFIG VARIABLES
 *  -----------------------
 */

require_once('system.php');

/*
 *	SERVICE CONFIG VARIABLES
 *
 */
define( "UDPSENDERPATH", "/usr/local/sbin/udp-sender" );
define( "MULTICASTLOGPATH", "/opt/fog/log/multicast.log" );
define( "MULTICASTDEVICEOUTPUT", "/dev/tty2" );
define( "MULTICASTSLEEPTIME", 10 );
define( "MULTICASTINTERFACE", "eth0" );
define( "UDPSENDER_MAXWAIT", null );

define( "LOGMAXSIZE", "1000000");
define( "REPLICATORLOGPATH", "/opt/fog/log/fogreplicator.log" );
define( "REPLICATORDEVICEOUTPUT", "/dev/tty3" );
define( "REPLICATORSLEEPTIME", 600 );
define( "REPLICATORIFCONFIG", "/sbin/ifconfig" );

define( "SCHEDULERLOGPATH", "/opt/fog/log/fogscheduler.log" );
define( "SCHEDULERDEVICEOUTPUT", "/dev/tty4" );
define( "SCHEDULERWEBROOT", "/data/mastaweb/var/www/fog" );
define( "SCHEDULERSLEEPTIME", 60 );


/*
 *  IMPORTANT NOTICE!
 *  -----------------
 *  In order to make updating from version to version of fog easier, we have moved
 *  most off these settings into the fog database.  The only settings which are 
 *  active are the settings above.  All settings below this message are transfered 
 *  to the fog database during schema update/installation.  To modify these 
 *  settings please use the fog management portal.
 *
 */

define('TFTP_HOST', '10.0.0.10');
define('TFTP_FTP_USERNAME', 'fog');
define('TFTP_FTP_PASSWORD', 'password');
define('TFTP_PXE_KERNEL_DIR', BASEPATH . WEB_ROOT .'service/ipxe/');
define('PXE_KERNEL', 'bzImage');
define('PXE_KERNEL_RAMDISK',127000);
define('USE_SLOPPY_NAME_LOOKUPS',true);
define('MEMTEST_KERNEL', 'memtest');
define('PXE_IMAGE', 'init.xz');
define('PXE_IMAGE_DNSADDRESS', '10.0.0.10');
define('STORAGE_HOST', '10.0.0.10');
define('STORAGE_FTP_USERNAME', 'fog');
define('STORAGE_FTP_PASSWORD', 'password');
define('STORAGE_DATADIR', '/images/');
define('STORAGE_DATADIR_UPLOAD', '/images/dev/');
define('STORAGE_BANDWIDTHPATH', '/fog/status/bandwidth.php');
define('UPLOADRESIZEPCT',5);
define('WEB_HOST', '10.0.0.10');
define('SNAPINDIR', '/opt/fog/snapins/');
define('QUEUESIZE', '20');
define('CHECKIN_TIMEOUT',600);
define('USER_MINPASSLENGTH',4);
define('USER_VALIDPASSCHARS', '1234567890ABCDEFGHIJKLMNOPQRSTUVWZXYabcdefghijklmnopqrstuvwxyz_()^!#');
define('NFS_ETH_MONITOR', 'eth0');
define('UDPCAST_INTERFACE', 'eth0');
define('UDPCAST_STARTINGPORT',63100);					//Mustbeanevennumber!recommendedbetween49152to65535
define('FOG_MULTICAST_MAX_SESSIONS',64);
define('FOG_JPGRAPH_VERSION', '2.3');
define('FOG_REPORT_DIR', './reports/');
define('FOG_THEME', 'blackeye/blackeye.css');
define('FOG_UPLOADIGNOREPAGEHIBER',true);
