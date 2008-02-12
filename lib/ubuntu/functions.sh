#!/bin/sh
#
#  FOG is a computer imaging solution.
#  Copyright (C) 2007  Chuck Syperski & Jian Zhang
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#    any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#
#

installInitScript()
{
	echo -n "  * Installing init scripts";
	${initdpath}/${initdfullname} stop >/dev/null 2>&1;
	
	cp -f ${initdsrc}/* ${initdpath}/
	chmod 755 ${initdpath}/${initdfullname}
	sysv-rc-conf ${initdfullname} on >/dev/null 2>&1;
	echo "...OK";	
}

configureFOGService()
{
	echo -n "  * Starting FOG Multicast Management Server"; 
	${initdpath}/${initdfullname} stop >/dev/null 2>&1;
	ret=`${initdpath}/${initdfullname} start 2>/dev/null | grep "[ OK ]"`;
	if [ "$ret" = "" ]
	then
		echo "...Failed!";
		exit 1;	
	else
		echo "...OK";
	fi	
}

configureNFS()
{
	echo -n "  * Setting up and starting NFS Server"; 
	
	echo "/images                        *(ro,sync,no_wdelay,insecure_locks,no_root_squash)
/images/dev                    *(rw,sync,no_wdelay,no_root_squash,insecure)" > "${nfsconfig}";
	
	sysv-rc-conf nfs-kernel-server on >/dev/null 2>&1;
	/etc/init.d/nfs-kernel-server stop >/dev/null 2>&1;
	ret=`/etc/init.d/nfs-kernel-server start 2>/dev/null | grep "[ OK ]"`;	
	if [ "$ret" = "" ]
	then
		echo "...Failed!";
		exit 1;	
	else
		echo "...OK";
	fi		
}

configureFTP()
{
	echo -n "  * Setting up and starting VSFTP Server";
	if [ -f "$ftpconfig" ]
	then
		mv "${ftpconfig}" "${ftpconfig}.fogbackup";
	fi
	
	echo "anonymous_enable=NO
local_enable=YES
write_enable=YES
local_umask=022
dirmessage_enable=YES
xferlog_enable=YES
connect_from_port_20=YES
xferlog_std_format=YES
listen=YES
pam_service_name=vsftpd
userlist_enable=NO
tcp_wrappers=YES" > "$ftpconfig";

	sysv-rc-conf vsftpd on >/dev/null 2>&1;
	/etc/init.d/vsftpd stop >/dev/null 2>&1;
	ret=`/etc/init.d/vsftpd start 2>/dev/null | grep "[ OK ]"`;	
	if [ "$ret" = "" ] 
	then
		echo "...Failed!";
		exit 1;	
	else
		echo "...OK";
	fi	

}

configureTFTPandPXE()
{
	echo -n "  * Setting up and starting TFTP and PXE Servers";
	if [ -d "$tftpdirdst" ]
	then
		mv "$tftpdirdst" "${tftpdirdst}.fogbackup" >/dev/null 2>&1;
		if [ -d "$tftpdirdst" ]
		then
			echo "...Failed!";
			echo "  * Failed to move $tftpdirdst to ${tftpdirdst}.fogbackup";
			echo "  * Make sure ${tftpdirdst}.fogbackup does NOT exists.";		
			echo "  * If ${tftpdirdst}.fogbackup does exist delete or rename ";	
			echo "    it and start over and everything should work.";
			exit 1;
		fi
	fi
	
	mkdir -p "$tftpdirdst" >/dev/null 2>&1;
	cp -Rf ${tftpdirsrc}/* ${tftpdirdst}/
	chown -R ${username} "${tftpdirdst}";
	chmod -R 777 "${tftpdirdst}";

	if [ -f "$tftpconfig" ]
	then
		mv "$tftpconfig" "${tftpconfig}.fogbackup";
	fi

	echo "# default: off
# description: The tftp server serves files using the trivial file transfer \
#	protocol.  The tftp protocol is often used to boot diskless \
#	workstations, download configuration files to network-aware printers, \
#	and to start the installation process for some operating systems.
service tftp
{
	socket_type		= dgram
	protocol		= udp
	wait			= yes
	user			= root
	server			= /usr/sbin/in.tftpd
	server_args		= -s /tftpboot
	disable			= no
	per_source		= 11
	cps			= 100 2
	flags			= IPv4
}" > "${tftpconfig}";

	sysv-rc-conf xinetd on >/dev/null 2>&1;
	/etc/init.d/xinetd stop >/dev/null 2>&1;
	ret=`/etc/init.d/xinetd start 2>/dev/null | grep "[ OK ]"`;		
	if [ "$ret" = "" ]
	then
		echo "...Failed!";
		exit 1;	
	else
		echo "...OK";	
	fi	
	
	
}

configureDHCP()
{
	echo -n "  * Setting up and starting DHCP Server";

	if [ -f "$dhcpconfig" ]
	then
		mv "$dhcpconfig" "${dhcpconfig}.fogbackup"
	fi
	
	networkbase=`echo "${ipaddress}" | cut -d. -f1-3`;
	network="${networkbase}.0";
	startrange="${networkbase}.10";
	endrange="${networkbase}.254";
	
	echo "# DHCP Server Configuration file.
# see /usr/share/doc/dhcp*/dhcpd.conf.sample
# This file was created by FOG
use-host-decl-names on;
ddns-update-style interim;
ignore client-updates;
next-server ${ipaddress};

subnet ${network} netmask 255.255.255.0 {
        option subnet-mask              255.255.255.0;
        range dynamic-bootp ${startrange} ${endrange};
        default-lease-time 21600;
        max-lease-time 43200;
${dnsaddress}
${routeraddress} 
        filename \"pxelinux.0\";
}" > "$dhcpconfig";
		
	sysv-rc-conf dhcpd on >/dev/null 2>&1;
	/etc/init.d/dhcp3-server stop >/dev/null 2>&1;
	ret=`/etc/init.d/dhcp3-server start 2>/dev/null | grep "[ OK ]"`;		
	if [ "$ret" = "" ]
	then
		echo "...Failed!";
		exit 1;	
	else
		echo "...OK";
	fi

}

configureHttpd()
{
	echo -n "  * Setting up and starting Apache Web Server";
	sysv-rc-conf apache2 on;
	/etc/init.d/apache2  stop  >/dev/null 2>&1
	ret=`/etc/init.d/apache2 start  2>/dev/null  | grep "[ OK ]"`;	
	if [ "$ret" = "" ]
	then
		echo "...Failed!";
		exit 1;	
	else
		if [ ! -d "$webdirdest" ]
		then
			mkdir "$webdirdest";
		else
			rm -Rf "$webdirdest";
			mkdir "$webdirdest";
		fi		
		
		cp -Rf $webdirsrc/* $webdirdest/
		
		echo "<?php
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

define( \"IS_INCLUDED\", true );

define( \"TFTP_HOST\", \"${ipaddress}\" );
define( \"TFTP_FTP_USERNAME\", \"${username}\" );
define( \"TFTP_FTP_PASSWORD\", \"${password}\" );
define( \"TFTP_PXE_CONFIG_DIR\", \"/tftpboot/pxelinux.cfg/\" );
define( \"TFTP_PXE_KERNEL_DIR\", \"/tftpboot/fog/kernel/\" );

define( \"PXE_KERNEL\", \"fog/kernel/bzImage\" );
define( \"PXE_KERNEL_RAMDISK\", 127000 ); 
define( \"USE_SLOPPY_NAME_LOOKUPS\", true);
define( \"MEMTEST_KERNEL\", \"fog/memtest/memtest\" );

define( \"PXE_IMAGE\",  \"fog/images/init.gz\" );
define( \"PXE_IMAGE_DNSADDRESS\",  \"${dnsbootimage}\" );

define( \"STORAGE_HOST\", \"${ipaddress}\" );
define( \"STORAGE_DATADIR\", \"/images/\" );
define( \"STORAGE_DATADIR_UPLOAD\", \"/images/dev/\" );
define( \"STORAGE_BANDWIDTHPATH\", \"/fog/status/bandwidth.php\" );

define( \"CLONEMETHOD\", \"ntfsclone\" );  // valid values partimage, ntfsclone
define( \"UPLOADRESIZEPCT\", 5 ); 

define( \"WEB_HOST\", \"${ipaddress}\" );
define( \"WEB_ROOT\", \"/fog/\" );

define( \"WOL_HOST\", \"${ipaddress}\" ); 	
define( \"WOL_PATH\", \"/fog/wol/wol.php\" );   
define( \"WOL_INTERFACE\", \"${interface}\" );

define( \"SNAPINDIR\", \"${snapindir}/\" );

define( \"QUEUESIZE\", \"10\" );
define( \"CHECKIN_TIMEOUT\", 600 );

define( \"MYSQL_HOST\", \"localhost\" );
define( \"MYSQL_DATABASE\", \"fog\" );
define( \"MYSQL_USERNAME\", \"root\" );
define( \"MYSQL_PASSWORD\", \"\" );

define( \"USER_MINPASSLENGTH\", 4 );
define( \"USER_VALIDPASSCHARS\", \"ABCDEFGHIJKLMNOPQRSTUVWZXYabcdefghijklmnopqrstuvwxyz_$-()^!\" );

define( \"NFS_ETH_MONITOR\", \"${interface}\" );

define(\"UDPCAST_INTERFACE\",\"${interface}\");
define(\"UDPCAST_STARTINGPORT\", 63100 ); 					// Must be an even number! recommended between 49152 to 65535

define(\"FOG_MULTICAST_MAX_SESSIONS\", 64 );

define( \"FOG_THEME\", \"blackeye/blackeye.css\" );
define( \"FOG_VERSION\", \"0.11\" );
define( \"FOG_SCHEMA\", 6);
?>" > "${webdirdest}/commons/config.php";
		
		
		chown -R ${apacheuser}:${apacheuser} "$webdirdest"
		
		echo "...OK";
	fi
}

configureSudo()
{
	echo -n "  * Setting up sudo settings";
	ret=`cat /etc/sudoers | grep "${apacheuser} ALL=(ALL) NOPASSWD: /usr/sbin/etherwake"`
	if [ "$ret" = "" ]
	then
		 echo "${apacheuser} ALL=(ALL) NOPASSWD: /usr/sbin/etherwake" >>  "/etc/sudoers";
	fi
	echo "...OK";	
}

configureMySql()
{
	echo -n "  * Setting up and starting MySql";
	sysv-rc-conf mysql on >/dev/null 2>&1;
	/etc/init.d/mysql stop >/dev/null 2>&1
	ret=`/etc/init.d/mysql start 2>/dev/null | grep "[ OK ]"`;
	if [ "$ret" = "" ]
	then
		echo "...Failed!";
		exit 1;	
	else
		echo "...OK";
	fi	
}

installPackages()
{
	echo "  * Preparing apt-get";
	apt-get -y -q update >/dev/null 2>&1;
	sleep 1;
	for x in $packages
	do
		echo  "  * Installing package: $x";
		if [ "$x" = "mysql-server" ]
		then
			strDummy="";

			echo "";
			echo "     We are about to install MySQL Server on ";
			echo "     this server, if MySQL isn't installed already";
			echo "     you will be prompted for a root password.  If";
			echo "     you don't leave it blank you will need to change";
			echo "     it in the config.php file located at:";
			echo "     ";
			echo "     ${webdirdest}/commons/config.php";
			echo "";
			sleep 3;
			echo "     Press enter to acknowledge this message.";
			read strDummy;
			apt-get -y -q install $x;
			echo "";
		else
			apt-get -y -q install $x >/dev/null 2>&1;
		fi
	done
}

confirmPackageInstallation()
{
	for x in $packages
	do
		echo -n "  * Checking package: $x";
		ret=`dpkg -l $x 2>&1 | grep "No packages found matching"`;
		if [ "$ret" = "" ]
		then
			echo "...OK";
		else
			echo "...Failed!"
			exit 1;
		fi
	done;
}
