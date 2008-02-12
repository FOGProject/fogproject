#!/bin/sh
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
	while [ "$osid" = "" ]
	do
		echo "  What version of Linux would you like to run the installtion for?"
		echo "";
		echo "          1) Redhat Based Linux (Fedora, CentOS)";
		echo "          2) Ubuntu Based Linux (Kubuntu, Edubuntu)";		
		echo "";
		echo -n "  Choice: ";
		read osid;
		echo "";
		case "$osid" in
			"1")
			    	echo "  Staring Redhat Installation."
			    	osname="Redhat";
			    	. ../lib/redhat/functions.sh
				. ../lib/redhat/config.sh
			    	echo "";
				;;
			"2")
				echo "  Starting Ubuntu Installtion.";
				osname="Ubuntu";
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
	done
}
	

# to do change user to variable in config file
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
	    	wget "http://freeghost.no-ip.org/notify/index.php?version=$version" &>/dev/null;
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
	echo -n "  * Setting up fog user";
	password=`date | md5sum | cut -d" " -f1`;
	if [ $password != "" ]
	then
		useradd -d "/home/${username}" ${username} >/dev/null 2>&1;
		passwd ${username} >/dev/null 2>&1 << EOF
${password}
${password}
EOF
		mkdir "/home/${username}" >/dev/null 2>&1;
		chown -R ${username} "/home/${username}" >/dev/null 2>&1;
		echo "...OK";
	else
		echo "...Failed!";
		exit 1;
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

displayBanner()
{
	echo "        ___           ___           ___      ";
	echo "       /\  \         /\  \         /\  \     ";
	echo "      /::\  \       /::\  \       /::\  \    ";
	echo "     /:/\:\  \     /:/\:\  \     /:/\:\  \   ";
	echo "    /::\-\:\  \   /:/  \:\  \   /:/  \:\  \  ";
	echo "   /:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\ ";
	echo "   \/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/ ";
	echo "        \:\__\    \:\  /:/  /   \:\ \:\__\   ";
	echo "         \/__/     \:\/:/  /     \:\/:/  /   ";
	echo "                    \::/  /       \::/  /    ";
	echo "                     \/__/         \/__/     ";
	echo "";
	echo "  ###########################################";
	echo "  #     Free Computer Imaging Solution      #";
	echo "  #                                         #";
	echo "  #     Created by:                         #";
	echo "  #         Chuck Syperski                  #";	
	echo "  #         Jian Zhang                      #";
	echo "  #                                         #";		
	echo "  #     GNU GPL Version 3                   #";		
	echo "  ###########################################";
	echo "";
	
}
