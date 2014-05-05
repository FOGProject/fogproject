#!/bin/bash
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

# Include all the common installer stuff for all distros

. ../lib/common/functions.sh
. ../lib/common/config.sh

# Determine Linux release name
linuxReleaseName=`lsb_release -a 2> /dev/null | grep "Distributor ID" | awk '{print $3,$4,$5,$6,$7,$8,$9}'`;
if [ -z "$linuxReleaseName" ];
then
	# Fall back incase lsb_release does not exist / fails - use /etc/issue over /etc/*release*
	linuxReleaseName=`cat /etc/issue /etc/*release* 2>/dev/null | head -n1 | awk '{print $1}'`;
fi

installtype="";
ipaddress="";
interface="";
routeraddress="";
plainrouter="";
dnsaddress="";
dnsbootimage="";
password="";
osid="";
osname="";
dodhcp="";
bldhcp="";
# new with version 0.24
snmysqluser=""
snmysqlpass="";
snmysqlhost="";
# new with version 0.29
installlang="";

bluseralreadyexists="0";

storageftpuser="";
storageftppass="";

#argument options
guessdefaults="1";

#do update if the .fogsettings file exists
doupdate="1";

#ignore htmldoc
ignorehtmldoc="0";

clearScreen;
displayBanner;
echo "  Version: ${version} Installer/Updater";
echo "";

sleep 1;

# process arguments
for arg in $*
do 
	case "$arg" in
		"--help" )
			help;
			exit 1;
			;;
		"--no-defaults" )
			guessdefaults="0";
			;;
		"--no-upgrade" )
			doupdate="0";
			;;	
		"--no-htmldoc" )
			ignorehtmldoc="1";
			;;		
	esac
done

warnRoot

if [ "$doupdate" = "1" ]
then
	if [ -f "$fogprogramdir/.fogsettings" ]
	then
		echo "";
		echo "  * Found FOG Settings from previous install at: $fogprogramdir/.fogsettings";
		echo -n "  * Performing upgrade using these settings";
		sleep 1;
		echo -n ".";
		sleep 1;
		echo -n ".";
		sleep 1;
		echo ".";
		sleep 1;
		
		. "$fogprogramdir/.fogsettings";
		doOSSpecificIncludes;
	fi
else
	echo "";
	echo "  FOG Installer will NOT attempt to upgrade from";
	echo "  previous version of FOG.";
	echo "";
fi


. ../lib/common/input.sh

if [ "$installtype" = "N" ]
then
	echo "";
	echo "  #####################################################################";
	echo "";
	echo "  FOG now has everything it needs to setup your server, but please"
	echo "  understand that this script will overwrite any setting you may"
	echo "  have setup for services like DHCP, apache, pxe, tftp, and NFS."
	echo "  ";
	echo "  It is not recommended that you install this on a production system";
	echo "  as this script modifies many of your system settings.";
	echo "";
	echo "  This script should be run by the root user on Redhat or with sudo on Ubuntu."
	
	echo
	echo "  ** Notice ** Redhat users will need to disable SELinux and iptables in order to use FOG"
	echo "  Please see our wiki for more information at http://www.fogproject.org/wiki"
	echo 
	
	echo "";
	echo "  Here are the settings FOG will use:";
	echo "         Base Linux: ${osname}";
	echo "         Detected Linux Distribution: ${linuxReleaseName}";
	echo "         Installation Type: Normal Server";
	echo "         Server IP Address: ${ipaddress}";
	echo "         DHCP router Address: ${plainrouter}";
	echo "         DHCP DNS Address: ${dnsbootimage}";
	echo "         Interface: ${interface}";
	echo "         Using FOG DHCP: ${bldhcp}";
	echo "         Internationalization: ${installlang}";
	echo "         Donate: ${donate}";
	echo "";
elif [ "$installtype" = "S" ]
then
	echo "";
	echo "  #####################################################################";
	echo "";
	echo "  FOG now has everything it needs to setup your storage node, but please"
	echo "  understand that this script will overwrite any setting you may"
	echo "  have setup for services like FTP, and NFS."
	echo "  ";
	echo "  It is not recommended that you install this on a production system";
	echo "  as this script modifies many of your system settings.";
	echo "";
	echo "  This script should be run by the root user on Fedora, or with sudo on Ubuntu."
	echo "";
	echo "  Here are the settings FOG will use:";
	echo "         Base Linux: ${osname}";
	echo "         Detected Linux Distribution: ${linuxReleaseName}";
	echo "         Installation Type: Storage Node";
	echo "         Server IP Address: ${ipaddress}";
	echo "         Interface: ${interface}";
	echo "         MySql Database Host: ${snmysqlhost}";
	echo "         MySql Database User: ${snmysqluser}";
	echo "         MySql Database Password: [Protected]";
	echo "";
fi

if [ "$bldhcp" == "0" ];
then
	echo "         DHCP will NOT be setup but you must setup your";
	echo "         current DHCP server to use FOG for PXE services.";
	echo ;
	echo "         On a Linux DHCP server you must set:";
	echo "             next-server";
	echo ;
	echo "         On a Windows DHCP server you must set:";
	echo "             option 066 & 067";
	echo;
	echo "		   Option 066 is the IP of the FOG Server: (e.g. ${ipaddres}";
	echo "		   Option 067 is the undionly.kpxe file: (e.g. undionly.kpxe)";
fi;

while [ "$blGo" = "" ]
do
    echo 
	echo -n "  Are you sure you wish to continue (Y/N) ";
	read blGo;
	echo "";
	case "$blGo" in
	    Y | yes | y | Yes | YES )
	           echo "  Installation Started...";
	           echo "";
	           echo "  Installing required packages, if this fails";
	           echo "  make sure you have an active internet connection.";
	           echo "";
	           
	           # Which package list do we use?
	           if [ "$installtype" = "S" ]
	           then
	           	packages=$storageNodePackages;
	           fi
	           
	           if [ "${ignorehtmldoc}" = "1" ]
	           then
	           	newpackagelist="";
			for z in $packages
			do
				if [ "$z" != "htmldoc" ]
				then
					newpackagelist="${newpackagelist} $z";
				fi
			done	  
			packages=$newpackagelist;
	           fi
	           
	           if [ "${bldhcp}" = "0" ]
	           then
	           	newpackagelist="";
			for z in $packages
			do
				if [ "$z" != "$dhcpname" ]
				then
					newpackagelist="${newpackagelist} $z";
				fi
			done	  
			packages=$newpackagelist;	           	
	           fi
	           
	           
	           installPackages;
	           echo "";
	           echo "  Confirming package installation.";
	           echo "";
	           confirmPackageInstallation;
	           echo "";
	           echo "  Configuring services.";
	           echo "";
	           
	           if [ "$installtype" = "S" ]
	           then
	           	# Storage Node installation
			configureUsers;
			configureMinHttpd;
	           	configureStorage;
	           	configureNFS;
	           	configureFTP;
			configureUDPCast;          
			installInitScript;
			installFOGServices;
			configureFOGService;	
			sendInstallationNotice;
			writeUpdateFile;
			configureDefaultiPXEfile;
			if [ "$bluseralreadyexists" = "1" ]
			then
				echo "";
				echo "  Upgrade complete!";
				echo "";
			else				
				echo "";
				echo "  Setup complete!";
				echo "";
				echo "";
				echo "  You still need to setup this node in the fog management ";
				echo "  portal.  You will need the username and password listed";
				echo "  below.";
				echo "";
				echo "  Management Server URL:  ";
				echo "      http://${snmysqlhost}/fog";
				echo "";			
				echo "  You will need this, write this down!";
				echo "      Username:  ${storageftpuser}";
				echo "      Password:  ${storageftppass}";
				echo ""
				echo "";
			fi									           	
	           else
			# Normal installation
			configureUsers;
			configureMySql;
			backupReports;
			configureHttpd;
			restoreReports;
			setupFreshClam;
			configureStorage;
			configureNFS;
			configureDHCP;
			configureTFTPandPXE;
			configureFTP;
			configureSudo;
			configureSnapins;
			configureUDPCast;          
			installInitScript;
			installFOGServices;
			configureFOGService;
			installUtils;
			sendInstallationNotice;
			writeUpdateFile;
			echo "";
		
			echo "  Setup complete!";
			echo "";
			echo "  You still need to install/update your database schema.";
			echo "  This can be done by opening a web browser and going to:";
			echo "";
			echo "      http://${ipaddress}/fog/management";
			echo ""
			echo "      Default User:";
			echo "             Username: fog";
			echo "             Password: password";
			echo "";	           	
	           fi
	           

	           ;;
	    [nN]*)
	           echo "  FOG installer exited by user request."
	           exit 1;
	           ;;
	    *)
	    	   echo "";
	           echo "  Sorry, answer not recognized."
	           echo "";
	           ;;
	esac
done


