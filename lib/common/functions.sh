#
#  FOG - Free, Open-Source Ghost is a computer imaging solution.
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

warnRoot()
{
	currentuser=`whoami`;
	if [ "$currentuser" != "root" ]
	then
		echo 
		echo "  This installation script should be run as"
		echo "  user \"root\".  You are currenly running ";
		echo "  as $currentuser.  "
		echo 
		echo -n "  Do you wish to continue? [N] "
		
		read ignoreroot;
		if [ "$ignoreroot" = "" ]
		then
			ignoreroot="N";
		else
			case "$ignoreroot" in
				Y | yes | y | Yes | YES )
					ignoreroot="Y";			
					;;
				[nN]*)
					ignoreroot="N";	
					;;
				*)
					ignoreroot="N";
					;;
			esac		 
		fi
		
		if [ "$ignoreroot" = "N" ];
		then
			echo " Exiting...";
			echo
			exit 1;
		fi
		
	fi
}

installUtils()
{
	echo -n "  * Setting up FOG Utils";	
	mkdir -p ${fogutilsdir} >/dev/null 2>&1;
	cp -Rf ${fogutilsdirsrc}/* "${fogutilsdir}" >/dev/null 2>&1;
	chown -R ${apacheuser} ${fogutilsdir} >/dev/null 2>&1;
	chmod -R 700 ${fogutilsdir} >/dev/null 2>&1;
	echo "...OK";
}

help()
{
	echo "";
	echo "  Usage: ./installfog.sh [options]";
	echo "       Options:";
	echo "             --help              Displays this message";
	echo "             --no-defaults       Don't guess default values";
	echo "             --no-upgrade        Don't attempt to upgrade";
	echo "				       from previous version.";	
	echo "             --uninstall         Not yet supported";
	echo "             --no-htmldoc        Don't try to install htmldoc";
	echo "                                 (You won't be able to create pdf reports)";
	echo "";
}

backupReports()
{
	echo -n "  * Backing up user reports";
	if [ ! -d "../rpttmp/" ]
	then
		mkdir "../rpttmp/";
	fi
	
	if [ -d "${webdirdest}/management/reports" ]
	then
		cp -a ${webdirdest}/management/reports/* "../rpttmp/" >/dev/null 2>&1;
	fi
	echo "...OK";
}

restoreReports()
{
	echo -n "  * Restoring user reports"; 
	if [ -d "${webdirdest}/management/reports" ]
	then
		if [ -d "../rpttmp/" ]
		then
			cp -a ../rpttmp/* ${webdirdest}/management/reports/ >/dev/null 2>&1;
		fi
	fi
	echo "...OK";
}

installFOGServices()
{
	echo -n "  * Setting up FOG Services";
	mkdir -p ${servicedst} >/dev/null 2>&1;
	cp -Rf ${servicesrc}/* ${servicedst}/
	mkdir -p ${servicelogs} >/dev/null 2>&1;
	echo "...OK";	
}

configureUDPCast()
{
	echo -n "  * Setting up and building UDPCast"; 
	cp -Rf "${udpcastsrc}" "${udpcasttmp}";
	cur=`pwd`;
	cd /tmp;
	tar xvzf "${udpcasttmp}"  >/dev/null 2>&1;
	cd ${udpcastout};
	./configure >/dev/null 2>&1;
	if [ $? = "0" ]; then
		make >/dev/null 2>&1;
		
		if [ "$?" = "0" ]; then
			make install >/dev/null 2>&1;
			
			if [ "$?" = "0" ]; then
				echo "...OK";
			else
				echo "...Failed!";			
				echo;
				echo "make install failed!"
				echo;
				exit 1;			
			fi
		else
			echo "...Failed!";
			echo;
			echo "make failed!"
			echo;
			exit 1;		
		fi
	else
		echo "...Failed!";
		echo;
		echo "./configure failed!"
		echo;
		exit 1;
	fi
	cd $cur
}

displayOSChoices()
{
	blFirst="1";
	while [ "$osid" = "" ]
	do
		if [ "$fogupdateloaded" = "1" -a  "$osid" != "" -a "$blFirst" = "1" ]
		then
			blFirst="0";
		else
			echo "  What version of Linux would you like to run the installation for?"
			echo "";
			echo "          1) Redhat Based Linux (Redhat, CentOS, Mageia)";
			echo "          2) Debian Based Linux (Debian, Ubuntu, Kubuntu, Edubuntu)";		
			echo "";
			echo -n "  Choice: [${strSuggestedOS}]";
			read osid;
		fi
		
		if [ "$osid" = "" ] 
		then
			if [ "$strSuggestedOS" != "" ]
			then
				osid=$strSuggestedOS;
			fi
		fi
		
		doOSSpecificIncludes;
		
	done
}
	
doOSSpecificIncludes()
{
	echo "";
	case "$osid" in
		"1")
		    	echo "  Staring Redhat / CentOS Installation."
		    	osname="Redhat";
		    	. ../lib/redhat/functions.sh
			. ../lib/redhat/config.sh
		    	echo "";
			;;
		"2")
			echo "  Starting Debian / Ubuntu / Kubuntu / Edubuntu Installtion.";
			osname="Debian";
		    	. ../lib/ubuntu/functions.sh
			. ../lib/ubuntu/config.sh
			echo "";
			;;				
		*)
			echo "  Sorry, answer not recognized."
			echo "";
			sleep 2;
			echo "";
			osid="";
			;;
	esac
}	

configureSnapins()
{
	echo -n "  * Setting up FOG Snapins"; 
	mkdir -p $snapindir >/dev/null 2>&1;
	if [ -d "$snapindir" ]
	then
		chmod 755 $snapindir;
		chown ${apacheuser} ${snapindir};
		echo "...OK";	
	else
		echo "...Failed!";
		exit 1;		
	fi

}

sendInstallationNotice()
{
	echo "";
	echo "";
	echo "  Would you like to notify the FOG group about this installation?";
	echo "    * This information is only used to help the FOG group determine";
	echo "      if FOG is being used.  This information helps to let us know";
	echo "      if we should keep improving this product.";
	echo "";
	echo -n "  Send notification? (Y/N)";
	read send;
	case "$send" in
	    yes | y | Yes | YES )
	    	echo -n "  * Thank you, sending notification..."
	    	wget -q -O - "http://freeghost.no-ip.org/notify/index.php?version=$version" >/dev/null 2>&1;
	    	echo "Done";
	    	;;
    	    *)
           	echo "  NOT sending notification."
           	;;
	esac	    	
	
	echo "";
	echo "";
}


configureUsers()
{
	getent passwd $username > /dev/null;
	if [ $? != 0 ] || [ "$doupdate" != "1" ]; then
		echo -n "  * Setting up fog user";
		password=`date | md5sum | cut -d" " -f1`;
		password=${password:0:6}
		if [ "$installtype" = "S" ]
		then
			# save everyone wrist injuries
			storageftpuser=${username};
			storageftppass=${password};
		fi
		
		if [ $password != "" ]
		then
			useradd -s "/bin/bash" -d "/home/${username}" ${username} >/dev/null 2>&1;
			if [ "$?" = "0" ] 
			then
				passwd ${username} >/dev/null 2>&1 << EOF
${password}
${password}
EOF
				mkdir "/home/${username}" >/dev/null 2>&1;
				chown -R ${username} "/home/${username}" >/dev/null 2>&1;
				echo "...OK";
			else
				if [ -f "${webdirdest}/lib/fog/Config.class.php" ]
				then
					password=`cat ${webdirdest}/lib/fog/Config.class.php | grep TFTP_FTP_PASSWORD | cut -d"," -f2 | cut -d"\"" -f2`;
				fi
				echo "...Exists";
				bluseralreadyexists="1";
			fi
		else
			echo "...Failed!";
			exit 1;
		fi
	fi
}



configureStorage()
{
	echo -n "  * Setting up storage";
	if [ ! -d "$storage" ]
	then
		mkdir "$storage";
		touch "$storage/.mntcheck";
		chmod -R 777 "$storage"
	fi
	if [ ! -d "$storage/postdownloadscripts" ]; then
		mkdir "$storage/postdownloadscripts";
		echo "#!bin/sh
## This file serves as a starting point to call your custom postimaging scripts.
## <SCRIPTNAME> should be changed to the script you're planning to use.
## Syntax of post download scripts are
#sh \${postdownpath}<SCRIPTNAME>" > "$storage/postdownloadscripts/fog.postdownload";
	fi
	if [ ! -d "$storageupload" ]
	then
		mkdir "$storageupload";
		touch "$storageupload/.mntcheck";
		chmod -R 777 "$storageupload"
	fi	
	echo "...OK";
}

clearScreen()
{
	echo -e "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
}

writeUpdateFile()
{
	
	tmpDte=`date +%c`;
	echo "## Created by the FOG Installer
## Version: $version
## Install time: $tmpDte
	
ipaddress=\"$ipaddress\";
interface=\"$interface\";
routeraddress=\"$routeraddress\";
plainrouter=\"$plainrouter\";
dnsaddress=\"$dnsaddress\";
dnsbootimage=\"$dnsbootimage\";
password=\"$password\";
osid=\"$osid\";
osname=\"$osname\";
dodhcp=\"$dodhcp\";
bldhcp=\"$bldhcp\";
installtype=\"$installtype\";
snmysqluser=\"$snmysqluser\"
snmysqlpass=\"$snmysqlpass\";
snmysqlhost=\"$snmysqlhost\";
installlang=\"$installlang\";
donate=\"$donate\";
fogupdateloaded=\"1\"" > "$fogprogramdir/.fogsettings";

}

displayBanner()
{
	echo "";                                        
	echo "       ..#######:.    ..,#,..     .::##::.   ";
	echo "  .:######          .:;####:......;#;..      ";
	echo "  ...##...        ...##;,;##::::.##...       ";
	echo "     ,#          ...##.....##:::##     ..::  ";
	echo "     ##    .::###,,##.   . ##.::#.:######::. ";
	echo "  ...##:::###::....#. ..  .#...#. #...#:::.  ";
	echo "  ..:####:..    ..##......##::##  ..  #      ";
	echo "      #  .      ...##:,;##;:::#: ... ##..    ";
	echo "     .#  .       .:;####;::::.##:::;#:..     ";
	echo "      #                     ..:;###..        ";
	echo "";
	echo "  ###########################################";
	echo "  #     FOG                                 #";
	echo "  #     Free Computer Imaging Solution      #";
	echo "  #                                         #";
	echo "  #     http://www.fogproject.org/          #";
	echo "  #                                         #";
	echo "  #     Developers:                         #";
	echo "  #         Chuck Syperski                  #";	
	echo "  #         Jian Zhang                      #";
	echo "  #         Peter Gilchrist                 #";
	echo "  #         Tom Elliott                     #";		
	echo "  #     GNU GPL Version 3                   #";		
	echo "  ###########################################";
	echo "";
	
}
