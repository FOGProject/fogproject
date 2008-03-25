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

# Include all the common installer stuff for all distros

. ../lib/common/functions.sh
. ../lib/common/config.sh

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

#argument options
guessdefaults="1";

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
	esac
done

. ../lib/common/input.sh

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
echo "  This script should be run by the root user, or with sudo."
echo "";
echo "  Here are the settings FOG will use:";
echo "         Distro: ${osname}";
echo "         Server IP Address: ${ipaddress}";
echo "         DHCP router Address: ${plainrouter}";
echo "         DHCP DNS Address: ${dnsbootimage}";
echo "         Interface: ${interface}";
echo "         Using FOG DHCP: ${bldhcp}";
echo "";
while [ "$blGo" = "" ]
do
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
	           installPackages;
	           echo "";
	           echo "  Confirming package installation.";
	           echo "";
	           confirmPackageInstallation;
	           echo "";
	           echo "  Configuring services.";
	           echo "";
	           configureUsers;
	           configureMySql;
	           backupReports;
	           configureHttpd;
	           restoreReports;
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
	           sendInstallationNotice;
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


