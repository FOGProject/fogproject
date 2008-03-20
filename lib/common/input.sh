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

displayOSChoices;

while [ "${ipaddress}" = "" ]
do
	echo -n "  What is the IP address to be used by FOG Server? ";
	read ipaddress;
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
	echo -n "  Would you like to setup a router address for the DHCP server? (Y/N) ";
	read blRouter;
	case "$blRouter" in
		Y | yes | y | Yes | YES )
			echo -n "  What is the IP address to be used for the router on the DHCP server? ";		
			read routeraddress;
			test=`echo "$routeraddress" | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`;
			if [ "$test" != "$routeraddress" ]
			then
				routeraddress="";
				echo "  Invalid router IP address!";
			else
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
	echo -n "  Would you like to setup a DNS address for the DHCP server and client boot image? (Y/N) ";
	read blDNS;
	case "$blDNS" in
		Y | yes | y | Yes | YES )
			echo -n "  What is the IP address to be used for DNS on the DHCP server and client boot image? ";		
			read dnsaddress;
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
	echo -n "  If you are not sure, select No. (Y/N)"
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
			echo "  Invalid input, please try again.";
			;;	
	esac	
done

while [ "${dodhcp}" = "" ]
do
	echo 
	echo -n "  Would you like to use the FOG server for dhcp service? (Y/N) "
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
			echo "  Invalid input, please try again.";
			;;	
	esac	
done

