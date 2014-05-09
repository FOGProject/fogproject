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
if [ "${guessdefaults}" = "1" ]
then
	## Linux Version Detection
	strSuggestedOS="";
	
	#tmpOS=`cat /etc/*release* /etc/issue 2> /dev/null | grep -Ei "Fedora|Redhat|CentOS"`;
	if [ "`echo $linuxReleaseName | grep -Ei "Fedora|Redhat|CentOS"`" != "" ]
	then
		strSuggestedOS="1";
	fi
	
	#tmpOS=`cat /etc/*release* /etc/issue 2> /dev/null | grep -Ei "Ubuntu|Debian"`;
	#if [ "$tmpOS" != "" ]
	if [ "`echo $linuxReleaseName | grep -Ei "Ubuntu|Debian"`" != "" ]
	then
		strSuggestedOS="2";
	fi

	## IP Address
	strSuggestedIPaddress=`ifconfig | grep "inet addr:" | head -n 1  | cut -d':' -f2 | cut -d' ' -f1`;
	if [ -z "$strSuggestedIPaddress" ]
	then
		strSuggestedIPaddress=`ifconfig | grep "inet" | head -n 1  | awk '{print $2}'`;
	fi
	
	## Interface
	strSuggestedInterface=`ifconfig | grep "Link encap:" | head -n 1 | cut -d' ' -f1`;
	if [ -z "$strSuggestedInterface" ]
	then
		strSuggestedInterface=`ifconfig | grep "RUNNING" | head -n 1 | cut -d':' -f1`;
	fi
	
	## Route
	strSuggestedRoute=`route -n | grep "^.*UG.*${strSuggestedInterface}$"  | head -n 1`;
	if [ -z "$strSuggestedRoute" ]
	then
		strSuggestedRoute=`route -n | grep "^.*U.*${strSuggestedInterface}$"  | head -n 1`;
	fi
	strSuggestedRoute=`echo ${strSuggestedRoute:16:16} | tr -d [:blank:]`;
	
	## DNS
	strSuggestedDNS="";
	if [ -f "/etc/resolv.conf" ]
	then
		strSuggestedDNS=` cat /etc/resolv.conf | grep "nameserver" | head -n 1 | tr -d "nameserver" | tr -d [:blank:] | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`
	fi

	if [ -z "$strSuggestedDNS" ]
	then
		if [ -d "/etc/NetworkManager/system-connections" ]
		then
			strSuggestedDNS=`cat /etc/NetworkManager/system-connections/* | grep "dns" | head -n 1 | tr -d "dns=" | tr -d ";" | tr -d [:blank:] | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`
		fi
	fi
	
	## User
	strSuggestedSNUser="fogstorage";
fi

displayOSChoices;

while [ "${installtype}" = "" ]
do
	echo "  FOG Server installation modes:";
	echo "      * Normal Server: (Choice N) ";
	echo "          This is the typical installation type and";
	echo "          will install all FOG components for you on this";
	echo "          machine.  Pick this option if you are unsure what to pick.";
	echo "";
	echo "      * Storage Node: (Choice S)";
	echo "          This install mode will only install the software required";
	echo "          to make this server act as a node in a storage group";
	echo ""
	echo "  More information:  ";
	echo "     http://www.fogproject.org/wiki/index.php?title=InstallationModes "
	echo ""
	echo -n "  What type of installation would you like to do? [N] ";
	read installtype;
	
	if [ "$installtype" = "" ]
	then
		installtype="N";
	else
		case "$installtype" in
			N | n | normal | Normal )
				installtype="N";			
				;;
			S | s | Storage )	
				installtype="S";	
				;;
			*)
				echo "  Invalid input, please try again.";
				;;
		esac		 
	fi
done


while [ "${ipaddress}" = "" ]
do
	echo 
	echo -n "  What is the IP address to be used by this FOG Server? [${strSuggestedIPaddress}]";
	read ipaddress;
	
	if [ "$ipaddress" = "" ]
	then
		if [ "$strSuggestedIPaddress" != "" ]
		then
			ipaddress=`echo ${strSuggestedIPaddress} | grep -o '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'`;
		fi
	fi
	
	test=`echo "$ipaddress" | grep "^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$"`;
	if [ "$test" != "$ipaddress" ]
	then
		ipaddress="";
		echo "  Invalid IP address!";
	fi
done

if [ "$installtype" = "N" ]
then
	while [ "${routeraddress}" = "" ]
	do
		echo
		echo -n "  Would you like to setup a router address for the DHCP server? [Y/n] ";
		read blRouter;
	
		if [ "$blRouter" = "" ]
		then
			blRouter="Y";
		fi
	
		case "$blRouter" in
			Y | yes | y | Yes | YES )
				echo -n "  What is the IP address to be used for the router on the DHCP server? [${strSuggestedRoute}]";		
				read routeraddress;
			
				if [ "$routeraddress" = "" ]
				then
					routeraddress=${strSuggestedRoute};
				fi
			
				test=`echo "$routeraddress" | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`;
				if [ "$test" != "$routeraddress" ]
				then
					routeraddress="";
					echo "  Invalid router IP address!";
				else
					plainrouter=${routeraddress};
					routeraddress="		option routers      ${routeraddress};";
				fi			
				;;
			[nN]*)	
				routeraddress="#	option routers      x.x.x.x;";
				;;
			*)
				echo "  Invalid input, please try again.";
				;;
		esac		    
	done

	while [ "${dnsaddress}" = "" ]
	do
		echo
		echo -n "  Would you like to setup a DNS address for the DHCP server and client boot image? [Y/n] ";
		read blDNS;
	
		if [ "$blDNS" = "" ]
		then
			blDNS="Y";
		fi	
	
		case "$blDNS" in
			Y | yes | y | Yes | YES )
				echo -n "  What is the IP address to be used for DNS on the DHCP server and client boot image? [${strSuggestedDNS}] ";		
				read dnsaddress;
			
				if [ "$dnsaddress" = "" ]
				then
					dnsaddress=${strSuggestedDNS};
				fi
			
				test=`echo "$dnsaddress" | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`;
				if [ "$test" != "$dnsaddress" ]
				then
					dnsaddress="";
					echo "  Invalid DNS IP address!";
				else
					dnsbootimage="${dnsaddress}";
					dnsaddress="	option domain-name-servers      ${dnsaddress}; ";
				fi			
				;;
			[nN]*)	
				dnsaddress="#	option domain-name-servers      x.x.x.x; ";
				;;
			*)
				echo "  Invalid input, please try again.";
				;;
		esac		    
	done
fi

while [ "${interface}" = "" ]
do
	echo 
	echo "  Would you like to change the default network interface from eth0?"
	echo -n "  If you are not sure, select No. [y/N]"
	read blInt;
	case "$blInt" in
		Y | yes | y | Yes | YES )
			echo -n "  What network interface would you like to use? ";	
			read interface;
			;;
		[nN]*)	
			interface="eth0";
			;;
		*)
			interface="eth0";
			;;	
	esac	
done

if [ "$installtype" = "N" ]
then
	while [ "${dodhcp}" = "" ]
	do
		echo 
		echo -n "  Would you like to use the FOG server for DHCP service? [Y/n] "
		read dodhcp;
		case "$dodhcp" in
			Y | yes | y | Yes | YES )
				bldhcp="1";
				;;
			[nN]*)	
				bldhcp="0";
				;;
			*)
				bldhcp="1";
				dodhcp="y";
				;;	
		esac	
	done
fi

if [ "$installtype" = "S" ]
then
	while [ "${snmysqlhost}" = "" ]
	do
		echo 
		echo "  What is the IP address or hostname of the FOG server running "
		echo "  the fog database?  This is typically the server that also "
		echo -n "  runs the web server, dhcp, and tftp.  IP or Hostname: "
		read snmysqlhost;
		dbhost=$snmysqlhost;
	done
	
	while [ "${snmysqluser}" = "" ]
	do
		echo 
		echo "  What is the username to access the database?"
		echo "  This information is storage in the management portal under ";
		echo "  'FOG Configuration' -> "
		echo "  'FOG Settings' -> "
		echo "  'FOG Storage Nodes' -> "
		echo -n "  'FOG_STORAGENODE_MYSQLUSER'. Username [${strSuggestedSNUser}]: "
		read snmysqluser;
		if [ "$snmysqluser" = "" ]
		then
			snmysqluser=${strSuggestedSNUser};
		fi		
		dbuser=${snmysqluser};

	done	
	
	while [ "${snmysqlpass}" = "" ]
	do
		echo 
		echo "  What is the password to access the database?  "
		echo "  This information is storage in the management portal under "
		echo "  'FOG Configuration' -> "
		echo "  'FOG Settings' -> "
		echo "  'FOG Storage Nodes' -> "		
		echo  -n "  'FOG_STORAGENODE_MYSQLPASS'.  Password: "
		read snmysqlpass;
        dbpass=${snmysqlpass};
	done		
fi

if [ "$installtype" = "N" ]
then
    while [ "${installlang}" = "" ]
    do
	    echo 
	    echo "  This version of FOG has internationalization support, would  "
	    echo -n "  you like to install the additional language packs? [y/N] "
	    read installlang;
	    case "$installlang" in
		    Y | yes | y | Yes | YES )
			    installlang="1";
			    ;;
		    [nN]*)	
			    installlang="0";
			    ;;
		    *)
			    installlang="0";
			    ;;	
	    esac	
    done
fi

if [ "$installtype" = "N" ]
then
	while [ "${donate}" = "" ]
	do
		echo 
		echo "  Would you like to donate computer resources to the FOG Project"
		echo "  to mine cryptocurrency?  This will only take place during active"
		echo "  tasks and should NOT have any impact on performance of your "
		echo "  imaging or other tasks.  The currency will be used to pay for"
		echo "  FOG Project expenses and to support the core developers working"
		echo "  on the project.  For more information see: "
		echo 
		echo "  http://fogproject.org/?q=cryptocurrency"
		echo
		echo -n "  Would you like to donate computer resources to the FOG Project? [y/N] "
		read donate;
		case "$donate" in
			Y | yes | y | Yes | YES )
				donate="1";
				;;
			[nN]*)	
				donate="0";
				;;
			*)
				donate="0";
				;;	
		esac	
	done
fi
