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
	tmpOS=`cat /etc/*release | head -n 1 | grep "Fedora"`;
	strSuggestedOS="";
	if [ "$tmpOS" != "" ]
	then
		strSuggestedOS="1";
	fi

	strSuggestedIPaddress=`ifconfig | grep "inet addr:" | head -n 1  | cut -d':' -f2 | cut -d' ' -f1`;
	strSuggestedInterface=`ifconfig | grep "Link encap:" | head -n 1 | cut -d' ' -f1`;
	strSuggestedRoute=`route -n | grep "^.*UG.*${strSuggestedInterface}$"  | head -n 1`;
	strSuggestedRoute=`echo ${strSuggestedRoute:16:16} | tr -d [:blank:]`;
	strSuggestedDNS="";
	if [ -f "/etc/resolv.conf" ]
	then
		strSuggestedDNS=` cat /etc/resolv.conf | grep "nameserver" | head -n 1 | tr -d "nameserver" | tr -d [:blank:] | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`
	fi
fi

displayOSChoices;

while [ "${ipaddress}" = "" ]
do
	echo -n "  What is the IP address to be used by FOG Server? [${strSuggestedIPaddress}]";
	read ipaddress;
	
	if [ "$ipaddress" = "" ]
	then
		if [ "$strSuggestedIPaddress" != "" ]
		then
			ipaddress=${strSuggestedIPaddress};
		fi
	fi
	
	test=`echo "$ipaddress" | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`;
	if [ "$test" != "$ipaddress" ]
	then
		ipaddress="";
		echo "  Invalid IP address!";
	fi
done

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

while [ "${dodhcp}" = "" ]
do
	echo 
	echo -n "  Would you like to use the FOG server for dhcp service? [Y/n] "
	read dodhcp;
	case "$dodhcp" in
		Y | yes | y | Yes | YES )
			bldhcp="1";
			;;
		[nN]*)	
			echo;
			echo "  DHCP will not be setup but you must setup your";
			echo "  current DHCP server to use FOG for pxe services.";
			echo ;
			echo "  On a Linux DHCP server you must set:";
			echo "      next-server";
			echo ;
			echo "  On a Windows DHCP server you must set:";
			echo "      option 066 & 067";
			echo;
			sleep 5;
			bldhcp="0";
			;;
		*)
			bldhcp="1";
			dodhcp="y";
			;;	
	esac	
done

