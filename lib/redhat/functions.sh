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
configureDHCP() {
    dots "Setting up and starting DHCP Server...";
	if [ -f "$dhcpconfig" ]; then
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

# Definition of PXE-specific options
# Code 1: Multicast IP address of bootfile
# Code 2: UDP port that client should monitor for MTFTP responses
# Code 3: UDP port that MTFTP servers are using to listen for MTFTP requests
# Code 4: Number of seconds a client must listen for activity before trying
#         to start a new MTFTP transfer
# Code 5: Number of seconds a client must listen before trying to restart
#         a MTFTP transfer

option space PXE;
option PXE.mtftp-ip    code 1 = ip-address;
option PXE.mtftp-cport code 2 = unsigned integer 16;
option PXE.mtftp-sport code 3 = unsigned integer 16;
option PXE.mtftp-tmout code 4 = unsigned integer 8;
option PXE.mtftp-delay code 5 = unsigned integer 8;
option arch code 93 = unsigned integer 16; # RFC4578

use-host-decl-names on;
ddns-update-style interim;
ignore client-updates;
next-server ${ipaddress};

# Specify subnet of ether device you do NOT want serviced.  For systems with
# two or more ethernet devices.
# subnet 136.165.0.0 netmask 255.255.0.0 { }

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
        if [ "$systemctl" == "yes" ]; then
			systemctl enable dhcpd >/dev/null 2>&1 && /
			systemctl restart dhcpd >/dev/null 2>&1 && /
			systemctl status dhcpd >/dev/null 2>&1
		else
			chkconfig dhcpd on >/dev/null 2>&1 && /
			service dhcpd restart >/dev/null 2>&1 && /
			service dhcpd status >/dev/null 2>&1
		fi
        errorStat $?
	else
		echo "Skipped";
	fi
}
configureHttpd() {
	etcconf="/etc/httpd/conf.d/fog.conf";
	if [ -f "$etcconf" ]; then
		rm $etcconf &>/dev/null;
	fi
	if [ "$installtype" == N -a "$fogupdateloaded" != 1 -a -z "$autoaccept" ]; then
		echo -n "  * Did you leave the mysql password blank during install? (Y/n) ";
		read dummy
		echo "";
		case "$dummy" in
			[nN]*)
			echo -n "  * Please enter your mysql password: "
			read -s PASSWORD1
			echo "";
			echo -n "  * Please re-enter your mysql password: "
			read -s PASSWORD2
			echo "";
			if [ "$PASSWORD1" != "" ] && [ "$PASSWORD2" == $PASSWORD1 ]; then
				dbpass=$PASSWORD1;
			else
				dppass="";
				while [ "$PASSWORD1" != "" ] && [ "$dbpass" != "$PASSWORD1" ]; do
					echo -n "  * Please enter your mysql password: "
					read -s PASSWORD1
					echo "";
					echo -n "  * Please re-enter your mysql password: "
					read -s PASSWORD2
					echo "";
					if [ "$PASSWORD1" != "" ] && [ "$PASSWORD2" == $PASSWORD1 ]; then
						dbpass=$PASSWORD1;
					fi
				done
			fi
			if [ "$snmysqlpass" != "$dbpass" ]; then
				snmysqlpass=$dbpass;
			fi
			;;
			[yY]*)
			;;
			*)
			;;
		esac
	fi
	if [ "$installtype" == "S" -o "$fogupdateloaded" == 1 ]; then
		if [ "$snmysqlhost" != "" ] && [ "$snmysqlhost" != "$dbhost" ]; then
			dbhost=$snmysqlhost;
		fi
		if [ "$snmysqlhost" == "" ]; then
			dbhost="p:127.0.0.1";
		fi
	fi
	if [ "$snmysqluser" != "" ] && [ "$snmysqluser" != "$dbuser" ]; then
		dbuser=$snmysqluser;
	fi
    createSSLCA;
    dots "Setting up and starting Apache Web Server"
	sed -i 's/post_max_size\ \=\ 8M/post_max_size\ \=\ 100M/g' /etc/php.ini
	sed -i 's/upload_max_filesize\ \=\ 2M/upload_max_filesize\ \=\ 100M/g' /etc/php.ini
	if [ -z "$systemctl" ]; then
		chkconfig httpd on >/dev/null 2>&1 && \
		service httpd restart >/dev/null 2>&1
        sleep 2
		service httpd status >/dev/null 2>&1
	else
		systemctl enable httpd >/dev/null 2>&1 && \
		systemctl restart httpd >/dev/null 2>&1
        sleep 2
		systemctl status httpd >/dev/null 2>&1
	fi
    errorStat $?
        if [ -d "/home/fogWeb$version.BACKUP" ]; then
            rm -rf "/home/fogWeb$version.BACKUP";
        fi
        if [ -d "$webdirdest" ]; then
            mv "$webdirdest" "/home/fogWeb$version.BACKUP";
        fi
		mkdir -p "$webdirdest";
		cp -Rf $webdirsrc/* $webdirdest/
		echo "<?php
class Config {
	/** @function __construct() Calls the required functions to define the settings.
      * @return void
      */
	public function __construct() {
		self::db_settings();
		self::svc_setting();
		self::init_setting();
	}
	/** @function db_settings() Defines the database settings for FOG
      * @return void
      */
	private static function db_settings() {
		define('DATABASE_TYPE',		'mysql');	// mysql or oracle
		define('DATABASE_HOST',		'$dbhost');
		define('DATABASE_NAME',		'fog');
		define('DATABASE_USERNAME',		'$dbuser');
		define('DATABASE_PASSWORD',		'$snmysqlpass');
		define('DATABASE_CONNTYPE', $mysql_conntype);
	}
	/** @function svc_setting() Defines the service settings.
	  * (e.g. FOGMulticastManager,
	  *       FOGScheduler,
	  *       FOGImageReplicator)
	  * @return void
	  */
	private static function svc_setting() {
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
		define( \"SNAPINREPLOGPATH\", \"/opt/fog/log/fogsnapinrep.log\" );
		define( \"SNAPINREPDEVICEOUTPUT\", \"/dev/tty5\" );
		define( \"SNAPINREPSLEEPTIME\", 600 );
		define( \"SERVICELOGPATH\", \"/opt/fog/log/servicemaster.log\" );
		define( \"SERVICESLEEPTIME\", 3 );
	}
	/** @function init_setting() Initial values if fresh install are set here
	  * NOTE: These values are only used on initial
	  * installation to set the database values.
	  * If this is an upgrade, they do not change
	  * the values within the Database.
	  * Please use FOG Configuration->FOG Settings
	  * to change these values after everything is
	  * setup.
	  * @return void
	  */
	private static function init_setting() {
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
		define('STORAGE_BANDWIDTHPATH', '/${webroot}status/bandwidth.php');
		define('UPLOADRESIZEPCT',5);
		define('WEB_HOST', \"${ipaddress}\");
		define('WOL_HOST', \"${ipaddress}\");
		define('WOL_PATH', '/${webroot}wol/wol.php');
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
}" > "${webdirdest}/lib/fog/Config.class.php";
		echo "OK";
        dots "Changing permissions on apache log files";
		chmod +rx /var/log/httpd >/dev/null 2>&1
		chmod +rx /var/log/httpd/{access,error}_log >/dev/null 2>&1
		chown -R ${apacheuser}:${apacheuser} /var/www >/dev/null 2>&1
		echo "OK"
        dots "Downloading kernels and inits"
        wget -O "${webdirdest}/service/ipxe/bzImage" "http://downloads.sourceforge.net/project/freeghost/KernelList/bzImage" >/dev/null 2>&1 & disown
        wget -O "${webdirdest}/service/ipxe/bzImage32" "http://downloads.sourceforge.net/project/freeghost/KernelList/bzImage32" >/dev/null 2>&1 & disown
        wget -O "${webdirdest}/service/ipxe/init.xz" "http://downloads.sourceforge.net/project/freeghost/InitList/init.xz" >/dev/null 2>&1 & disown
        wget -O "${webdirdest}/service/ipxe/init_32.xz" "http://downloads.sourceforge.net/project/freeghost/InitList/init_32.xz" >/dev/null 2>&1 & disown
        echo "Backgrounded"
        if [ ! -f "$webredirect" ]; then
            echo "<?php header('Location: ./${webroot}index.php');?>" > $webredirect;
        fi
        dots "Downloading New FOG Client file"
        clientVer="`awk -F\' /"define\('FOG_CLIENT_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/System.class.php | tr -d '[[:space:]]'`"
        clienturl="https://github.com/FOGProject/fog-client/releases/download/${clientVer}/FOGService.msi"
        curl -sl --silent -f -L $clienturl &>/dev/null
        if [ "$?" -eq 0 ]; then
            curl --silent -o "${webdirdest}/client/FOGService.msi" -L $clienturl >/dev/null 2>&1 & disown
            echo "Backgrounded";
        else
            echo "Failed";
            echo -e "\n\t\tYou can try downloading the file yourself by running";
            echo -e "\n\t\tInstallation will continue.  Once complete you can";
            echo -e "\n\t\trun the command:";
            echo -e "\n\t\t\twget -O ${webdirdest}/client/FOGService.msi $clienturl";
        fi
		#if [ -d "${webdirdest}.prev" ]; then
		#	dots "Copying back any custom hook files"
		#	cp -Rf $webdirdest.prev/lib/hooks $webdirdest/lib/ >/dev/null 2>&1
		#	echo "OK";
		#	dots "Copying back any custom report files..."
		#	cp -Rf $webdirdest.prev/management/reports $webdirdest/management/;
		#	echo "OK";
		#fi
		chown -R ${apacheuser}:${apacheuser} "$webdirdest"
}
