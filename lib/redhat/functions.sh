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
	echo -n "  * Installing init scripts...";
	
	service ${initdMCfullname} stop >/dev/null 2>&1;
	service ${initdIRfullname} stop >/dev/null 2>&1;
	service ${initdSDfullname} stop >/dev/null 2>&1;
		
	cp -f ${initdsrc}/* ${initdpath}/
	chmod 755 ${initdpath}/${initdMCfullname}
	chkconfig ${initdMCfullname} on;
	chmod 755 ${initdpath}/${initdIRfullname}
	chkconfig ${initdIRfullname} on;	
	chmod 755 ${initdpath}/${initdSDfullname}
	chkconfig ${initdSDfullname} on;
	echo "OK";
}

configureFOGService()
{
	echo "<?php

define( \"WEBROOT\", \"${webdirdest}\" );
?>" > ${servicedst}/etc/config.php;

	echo -n "  * Starting FOG Multicast Management Server..."; 
	service ${initdMCfullname} restart >/dev/null 2>&1;
	service ${initdMCfullname} status  >/dev/null 2>&1;
	if [ "$?" != "0" ]
	then
		echo "Failed!";
		exit 1;	
	else
		echo "OK";
	fi
	
	echo -n "  * Starting FOG Image Replicator Server..."; 
	service ${initdIRfullname} restart >/dev/null 2>&1;
	service ${initdIRfullname} status  >/dev/null 2>&1;
	if [ "$?" != "0" ]
	then
		echo "Failed!";
		exit 1;	
	else
		echo "OK";
	fi	
	
	echo -n "  * Starting FOG Task Scheduler Server..."; 
	${initdpath}/${initdSDfullname} stop >/dev/null 2>&1;
	${initdpath}/${initdSDfullname} start >/dev/null 2>&1;
	if [ "$?" != "0" ]
	then
		echo "Failed!";
		exit 1;	
	else
		echo "OK";
	fi	
}

configureNFS()
{
	echo -n "  * Setting up and starting NFS Server..."; 
	
	echo "/images                        *(ro,sync,no_wdelay,insecure_locks,no_root_squash,insecure,fsid=1)
/images/dev                    *(rw,sync,no_wdelay,no_root_squash,insecure,fsid=2)" > "$nfsconfig";
	chkconfig rpcbind on;
	service rpcbind restart >/dev/null 2>&1;
	chkconfig nfs on;
	service nfs restart >/dev/null 2>&1;
	service nfs status  >/dev/null 2>&1;
	if [ "$?" != "0" ]
	then
		echo "Failed!";
		exit 1;	
	else
		echo "OK";
	fi		
}

configureSudo()
{
	echo -n "  * Setting up sudo settings...";
	#ret=`cat /etc/sudoers | grep "${apacheuser} ALL=(ALL) NOPASSWD: /sbin/ether-wake"`
	#if [ "$ret" = "" ]
	#then
	#	 echo "${apacheuser} ALL=(ALL) NOPASSWD: /sbin/ether-wake" >>  "/etc/sudoers";
	#	 echo "Defaults:${apacheuser} !requiretty" >>  "/etc/sudoers";
	#fi
	echo "OK";	
}

configureFTP()
{
	echo -n "  * Setting up and starting VSFTP Server...";
	if [ -f "$ftpconfig" ]
	then
		mv "$ftpconfig" "${ftpconfig}.fogbackup";
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

	chkconfig vsftpd on;
	service vsftpd restart >/dev/null 2>&1;
	service vsftpd status  >/dev/null 2>&1;
	if [ "$?" != "0" ] 
	then
		echo "Failed!";
		exit 1;	
	else
		echo "OK";
	fi	

}
configureDefaultiPXEfile()
{
	find "${tftpdirdst}" ! -type d -exec chmod 644 {} \;
	echo "#!ipxe
cpuid --ext 29 && set arch x86_64 || set arch i386
params
param mac0 \${net0/mac}
param arch \${arch}
isset(\${net1/mac}) && param mac1 \${net1/mac} || goto bootme
isset(\${net2/mac}) && param mac2 \${net2/mac} || goto bootme
:bootme
chain http://${ipaddress}/fog/service/ipxe/boot.php##params
" > "${tftpdirdst}/default.ipxe";
}

configureTFTPandPXE()
{
	echo -n "  * Setting up and starting TFTP and PXE Servers...";
	if [ -d "${tftpdirdst}.prev" ]; then
		rm -rf "${tftpdirdst}.prev" 2>/dev/null;
	fi
	if [ -d "$tftpdirdst" ]; then
		rm -rf "${tftpdirdst}.fogbackup" 2>/dev/null;
		mv "$tftpdirdst" "${tftpdirdst}.prev" 2>/dev/null;
	fi
	mkdir "$tftpdirdst" 2>/dev/null;
	cp -Rf $tftpdirsrc/* ${tftpdirdst}/
	chown -R ${username} "${tftpdirdst}";
	chown -R ${username} "${webdirdest}/service/ipxe" 2>/dev/null;
	find "${tftpdirdst}" -type d -exec chmod 755 {} \;
	find "${tftpdirdst}" ! -type d -exec chmod 644 {} \;
	configureDefaultiPXEfile;
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
}" > "$tftpconfig";

	chkconfig xinetd on;
	service xinetd restart >/dev/null 2>&1;
	service xinetd status  >/dev/null 2>&1;	
	if [ "$?" != "0" ]
	then
		echo "Failed!";
		exit 1;	
	else
		echo "OK";	
	fi	
	
}

configureDHCP()
{
	echo -n "  * Setting up and starting DHCP Server...";

	if [ -f "$dhcpconfig" ]
	then
		mv "$dhcpconfig" "${dhcpconfig}.fogbackup"
	fi
	
	networkbase=`echo "${ipaddress}" | cut -d. -f1-3`;
	network="${networkbase}.0";
	startrange="${networkbase}.10";
	endrange="${networkbase}.254";
	
	dhcptouse=$dhcpconfig;
	if [ -f "${dhcpconfigother}" ]; then
		dhcptouse=$dhcpconfigother;
	fi 
	
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
        filename \"undionly.kpxe\";
}" > "$dhcptouse";
		
	if [ "$bldhcp" = "1" ]; then
		chkconfig dhcpd on;
		service dhcpd restart >/dev/null 2>&1
		service dhcpd status  >/dev/null 2>&1;
		if [ "$?" != "0" ]
		then
			echo "Failed!";
			exit 1;	
		else
			echo "OK";
		fi	
	else
		echo "Skipped";
	fi
}

configureMinHttpd()
{
	configureHttpd;
	echo "<?php die( \"This is a storage node, please do not access the web ui here!\" ); ?>" > "$webdirdest/management/index.php";
}

configureHttpd()
{
	echo -n "  * Did you leave the mysql password blank during install? (Y/n) ";
	read dummy
	echo "";
	case "$dummy" in
		[nN]*)
		while [ $dbpass != $PASSWORD1 ]; do
			echo -n "  * Please enter your mysql password: "
			read -s PASSWORD1
			echo "";
			echo -n "  * Please re-enter your mysql password: "
			read -s PASSWORD2
			echo "";
			if [ $PASSWORD1 = $PASSWORD2 ]; then
				dbpass=$PASSWORD1;
			fi
			done
		;;
		[yY]*)
		;;
		*)
		;;
	esac
	echo -n "  * Setting up and starting Apache Web Server...";
	chkconfig httpd on;
	service httpd restart >/dev/null 2>&1
	sleep 2;
	service httpd status >/dev/null 2>&1;
	ret=$?;
	if [ "$ret" != "0" ]
	then
		echo "Failed! ($ret)";
		exit 1;	
	else
		if [ -d "${webdirdest}.prev" ]; then
			rm -rf "${webdirdest}.prev";
		fi
		if [ -d "$webdirdest" ]; then
			mv "$webdirdest" "${webdirdest}.prev";
		fi
		mkdir "$webdirdest";
		cp -Rf $webdirsrc/* $webdirdest/
		
		echo "<?php
/**
* Class Name: Config
* Initializes default settings.
* Most notably the sql connection.
*/
class Config
{
	/**
	* Calls the required functions to define the settings.
	* method db_settings()
	* method svc_setting()
	* method init_setting()
	*/
	public function __construct()
	{
		self::db_settings();
		self::svc_setting();
		self::init_setting();
	}
	/**
	* db_settings()
	* Defines the database settings for FOG
	* @return void
	*/
	private static function db_settings()
	{
		define('DATABASE_TYPE',		'mysql');	// mysql or oracle
		define('DATABASE_HOST',		'${dbhost}');
		define('DATABASE_NAME',		'fog');
		define('DATABASE_USERNAME',		'${dbuser}');
		define('DATABASE_PASSWORD',		'${dbpass}');
	}
	/**
	* svc_setting()
	* Defines the service settings.
	* (e.g. FOGMulticastManager,
	*       FOGScheduler,
	*       FOGImageReplicator)
	* @return void
	*/
	private static function svc_setting()
	{
		define( \"UDPSENDERPATH\", \"/usr/local/sbin/udp-sender\" );
		define( \"MULTICASTLOGPATH\", \"/opt/fog/log/multicast.log\" );
		define( \"MULTICASTDEVICEOUTPUT\", \"/dev/tty2\" );
		define( \"MULTICASTSLEEPTIME\", 10 );
		define( \"MULTICASTINTERFACE\", \"${interface}\" );
		define( \"UDPSENDER_MAXWAIT\", null );
		define( \"LOGMAXSIZE\", \"1000000\" );
		define( \"REPLICATORLOGPATH\", \"/opt/fog/log/fogreplicator.log\" );
		define( \"REPLICATORDEVICEOUTPUT\", \"/dev/tty3\" );
		define( \"REPLICATORSLEEPTIME\", 600 );
		define( \"REPLICATORIFCONFIG\", \"/sbin/ifconfig\" );
		define( \"SCHEDULERLOGPATH\", \"/opt/fog/log/fogscheduler.log\" );
		define( \"SCHEDULERDEVICEOUTPUT\", \"/dev/tty4\" );
		define( \"SCHEDULERSLEEPTIME\", 60 );
	}
	/**
	* init_setting()
	* Initial values if fresh install are set here
	* NOTE: These values are only used on initial
	* installation to set the database values.
	* If this is an upgrade, they do not change
	* the values within the Database.
	* Please use FOG Configuration->FOG Settings
	* to change these values after everything is
	* setup.
	* @return void
	*/
	private static function init_setting()
	{
		define('TFTP_HOST', \"${ipaddress}\");
		define('TFTP_FTP_USERNAME', \"${username}\");
		define('TFTP_FTP_PASSWORD', \"${password}\");
		define('TFTP_PXE_KERNEL_DIR', '${webdirdest}/service/ipxe/');
		define('PXE_KERNEL', 'bzImage');
		define('PXE_KERNEL_RAMDISK',127000);
		define('USE_SLOPPY_NAME_LOOKUPS',true);
		define('MEMTEST_KERNEL', 'memtest.bin');
		define('PXE_IMAGE', 'init.xz');
		define('PXE_IMAGE_DNSADDRESS', \"${dnsbootimage}\");
		define('STORAGE_HOST', \"${ipaddress}\");
		define('STORAGE_FTP_USERNAME', \"${username}\");
		define('STORAGE_FTP_PASSWORD', \"${password}\");
		define('STORAGE_DATADIR', '/images/');
		define('STORAGE_DATADIR_UPLOAD', '/images/dev/');
		define('STORAGE_BANDWIDTHPATH', '/fog/status/bandwidth.php');
		define('UPLOADRESIZEPCT',5);
		define('WEB_HOST', \"${ipaddress}\");
		define('WOL_HOST', \"${ipaddress}\");
		define('WOL_PATH', '/fog/wol/wol.php');
		define('WOL_INTERFACE', \"${interface}\");					
		define('SNAPINDIR', \"${snapindir}/\");
		define('QUEUESIZE', '10');
		define('CHECKIN_TIMEOUT',600);
		define('USER_MINPASSLENGTH',4);
		define('USER_VALIDPASSCHARS', '1234567890ABCDEFGHIJKLMNOPQRSTUVWZXYabcdefghijklmnopqrstuvwxyz_()^!#-');
		define('NFS_ETH_MONITOR', \"${interface}\");
		define('UDPCAST_INTERFACE', \"${interface}\");
		define('UDPCAST_STARTINGPORT', 63100 ); 					// Must be an even number! recommended between 49152 to 65535
		define('FOG_MULTICAST_MAX_SESSIONS',64);
		define('FOG_JPGRAPH_VERSION', '2.3');
		define('FOG_REPORT_DIR', './reports/');
		define('FOG_UPLOADIGNOREPAGEHIBER',true);
		define('FOG_DONATE_MINING', \"${donate}\");
	}
}" > "${webdirdest}/commons/config.php";
		
		
		chown -R ${apacheuser}:${apacheuser} "$webdirdest"
		
		if [ ! -f "$webredirect" ]
		then
			echo "<?php header('Location: ./fog/index.php');?>" > $webredirect;
		fi
		
		echo "OK";
	fi
}

configureMySql()
{
	echo -n "  * Setting up and starting MySQL...";
	chkconfig mysqld on;
	service mysqld restart >/dev/null 2>&1;
	service mysqld status >/dev/null 2>&1;
	if [ "$?" != "0" ]
	then
		echo "Failed!";
		exit 1;	
	else
		echo "OK";
	fi	
}

installPackages()
{
	if [ "$installlang" = "1" ]
	then
		packages="$packages $langPackages"
	fi
	
	echo "  * Packages to be installed: $packages";
	echo "";

	for x in $packages
	do
		rpm -q $x >/dev/null 2>&1
		if [ $x == "mysql" ]
		then
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="mariadb";
			fi
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="mysql";
			fi
		fi
		if [ $x == "mysql-server" ]
		then
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="mariadb-server";
			fi
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="mysql-server";
			fi
		fi
		if [ $x == "php-mysql" ]
		then
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="php-mysqlnd";
			fi
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="php-mysql";
			fi
		fi
		rpm -q $x >/dev/null 2>&1;
		if [ "$?" != "0" ]
		then
			echo  "  * Installing package: $x";
			yum -y install $x 1>/dev/null;
		else
			echo  "  * Skipping package: $x (Already installed)";
		fi
	done

}

confirmPackageInstallation()
{
	for x in $packages
	do
		echo -n "  * Checking package: $x...";
		if [ $x == "mysql" ]
		then
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="mariadb";
			fi
		fi
		if [ $x == "mysql-server" ]
		then
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="mariadb-server";
			fi
		fi
		if [ $x == "php-mysql" ]
		then
			rpm -q $x >/dev/null 2>&1;
			if [ "$?" != "0" ]
			then
				x="php-mysqlnd";
			fi
		fi
		rpm -q $x >/dev/null 2>&1;
		if [ "$?" != "0" ]
		then
			echo "Failed!"
			exit 1;		
		else
			echo "OK";
		fi
	done;
}

setupFreshClam()
{
	echo  -n "  * Configuring Fresh Clam...";
	echo "Skipped (See wiki for installation instructions)";
}
