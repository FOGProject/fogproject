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
if [ "${guessdefaults}" = "1" ]; then
	## Linux Version Detection
	strSuggestedOS="";
	#tmpOS=`cat /etc/*release* /etc/issue 2> /dev/null | grep -Ei "Fedora|Redhat|CentOS"`;
	if [ "`echo $linuxReleaseName | grep -Ei "Fedora|Redhat|CentOS|Mageia"`" != "" ]; then
		strSuggestedOS="1";
	fi
	#tmpOS=`cat /etc/*release* /etc/issue 2> /dev/null | grep -Ei "Ubuntu|Debian"`;
	#if [ "$tmpOS" != "" ]
	if [ "`echo $linuxReleaseName | grep -Ei "Ubuntu|Debian"`" != "" ]; then
		strSuggestedOS="2";
	fi
	#tmpOS=`cat /etc/*release* /etc/issue 2> /dev/null | grep -Ei "Arch"`;
	#if [ "$tmpOS" != "" ]
	if [ "`echo $linuxReleaseName | grep -Ei "Arch"`" != "" ]; then
		strSuggestedOS="3";
	fi
	## IP Address
    strSuggestedIPAddress=`/sbin/ip -f inet -o addr | awk -F'[ /]+' '/global/ {print $4}' | head -n2 | tail -n1`
	if [ -z "$strSuggestedIPAddress" ]; then
        strSuggestedIPAddress=`/sbin/ifconfig -a | awk '/(cast)/ {print $2}' | cut -d ':' -f2 | head -n2 | tail -n1`
	fi
	## Interface
    strSuggestedInterface=`/sbin/ip -f inet -o addr | awk -F'[ /]+' '/global/ {print $2}' | head -n2 | tail -n1`
	if [ -z "$strSuggestedInterface" ]; then
        strSuggestedIntervace=`/sbin/ifconfig -a | grep "'${strSuggestedIPAddress}'" -B1 | awk -F'[:]+' '{print $1}' | head -n1`
	fi
    ## Subnet Mask
    strSuggestedSubMask=`/sbin/ip -f inet -o addr | awk -F'[ /]+' '/global/ {print $5}' | head -n2 | tail -n1`
    if [ -z "$strSuggestedSubMask" ]; then
        strSuggestedSubMask=`/sbin/ifconfig -a | grep ${strSuggestedIPAddress} -B1 | awk -F'[netmask ]+' '{print $4}' | head -n2`
        strSuggestedSubMask=`mask2cidr ${strSuggestedSubMask}`
    fi
    strSuggestedSubMask=`cidr2mask ${strSuggestedSubMask}`
    submask=$strSuggestedSubMask
	## Route
    strSuggestedRoute=`ip route | head -n1 | cut -d' ' -f3 | tr -d [:blank:]`;
	if [ -z "$strSuggestedRoute" ]; then
		strSuggestedRoute=`route -n | grep "^.*U.*${strSuggestedInterface}$"  | head -n 1`;
		strSuggestedRoute=`echo ${strSuggestedRoute:16:16} | tr -d [:blank:]`;
	fi
	## DNS
	strSuggestedDNS="";
	if [ -f "/etc/resolv.conf" ]; then
		strSuggestedDNS=` cat /etc/resolv.conf | grep "nameserver" | head -n 1 | tr -d "nameserver" | tr -d [:blank:] | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`
	fi
	if [ -z "$strSuggestedDNS" ]; then
		if [ -d "/etc/NetworkManager/system-connections" ]; then
			strSuggestedDNS=`cat /etc/NetworkManager/system-connections/* | grep "dns" | head -n 1 | tr -d "dns=" | tr -d ";" | tr -d [:blank:] | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$"`
		fi
	fi
	## User
	strSuggestedSNUser="fogstorage";
fi
displayOSChoices;
while [ "${installtype}" = "" ]; do
    installtype="N"
    if [ -z "$autoaccept" ]; then
        echo "  FOG Server installation modes:";
        echo "      * Normal Server: (Choice N) ";
        echo "          This is the typical installation type and";
        echo "          will install all FOG components for you on this";
        echo "          machine.  Pick this option if you are unsure what to pick.";
        echo
        echo "      * Storage Node: (Choice S)";
        echo "          This install mode will only install the software required";
        echo "          to make this server act as a node in a storage group";
        echo
        echo "  More information:  ";
        echo "     http://www.fogproject.org/wiki/index.php?title=InstallationModes "
        echo
        echo -n "  What type of installation would you like to do? [N] ";
        read installtype
        if [ -z "$installtype" ]; then
            installtype="N"
        fi
    fi
    case "$installtype" in
        [Nn]|[Nn][Oo][Rr][Mm][Aa][Ll])
        installtype="N"
        ;;
        [Ss]|[Ss][Tt][Oo][Rr][Aa][Gg][Ee])
        installtype="S"
        ;;
        *)
        echo "  Invalid input, please try again.";
        installtype=""
        ;;
    esac
done
count=0
while [ "${ipaddress}" = "" ]; do
	ipaddress=`echo ${strSuggestedIPAddress} | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]'`
    if [ "$count" -ge 1 -o -z "$autoaccept" ]; then
        echo
        echo -n "  What is the IP address to be used by this FOG Server? [${strSuggestedIPAddress}]";
        read ipaddress;
        if [ ! -z "$strSuggestedIPAddress" -a "$ipaddress" = "" ]; then
            ipaddress=`echo ${strSuggestedIPAddress} | grep -o '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}' | tr -d '[[:space:]]'`
        fi
    fi
	test=`echo ${ipaddress} | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]'`
    if [ "$test" != "$ipaddress" ]; then
        ipaddress="";
        echo "  Invalid IP Address";
        count=`expr $count '+' 1`;
    fi
done
if [ "$installtype" = "N" ]; then
    count=0
    while [ "${routeraddress}" = "" ]; do
        blRouter="Y"
        if [ -z "$autoaccept" ]; then
            echo
            echo -n "  Would you like to setup a router address for the DHCP server? [Y/n] "
            read blRouter
            if [ -z "$blRouter" ]; then
                blRouter="Y"
            fi
        fi
        case "$blRouter" in
            [Yy]*)
            routeraddress=`echo ${strSuggestedRoute} | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]'`
            if [ "$count" -ge 1 -o -z "$autoaccept" ]; then
                echo "  What is the IP address to be used for the router on";
                echo -n "      the DHCP server? [$strSuggestedRoute]";
                read routeraddress;
				if [ -z "$routeraddress" ]; then
                    routeraddress=`echo ${strSuggestedRoute} | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]'`
                fi
                test=`echo "$routeraddress" | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]'`
                if [ "$test" != "$routeraddress" ]; then
                    routeraddress=""
                    echo "  Invalid router IP address!"
                    continue
                fi
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
    count=0
    while [ "${dnsaddress}" = "" ]; do
        blDNS="Y"
        if [ -z "$autoaccept" ]; then
            echo
            echo "  Would you like to setup a DNS address for"
            echo -n "      the DHCP server and client boot image? [Y/n] "
            read blDNS
            if [ -z "$blDNS" ]; then
                blDNS="Y"
            fi
        fi
        case "$blDNS" in
            [Yy]*)
            dnsaddress=`echo ${strSuggestedDNS} | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]'`
            if [ "$count" -ge 1 -o -z "$autoaccept" ]; then
                echo "  What is the IP address to be used for DNS on"
                echo -n "      the DHCP server and client boot image? [${strSuggestedDNS}] "
                read dnsaddress;
                if [ -z "$dnsaddress" ]; then
                    dnsaddress=$strSuggestedDNS
                fi
            fi
            test=`echo "$dnsaddress" | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]'`
            if [ "$test" != "$dnsaddress" ]; then
                dnsaddress=""
                echo "  Invalid DNS IP address!"
                continue
            fi
            dnsbootimage="${dnsaddress}"
            dnsaddress="	option domain-name-servers      ${dnsaddress}; "
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
while [ "${interface}" = "" ]; do
    blInt="N"
    if [ -z "$autoaccept" ]; then
        echo
        echo "  Would you like to change the default network interface from ${strSuggestedInterface}?"
        echo -n "  If you are not sure, select No. [y/N]"
        read blInt
        if [ -z "$blInt" ]; then
            blInt="N"
        fi
    fi
    case "$blInt" in
        [Yy]*)
        echo -n "  What network interface would you like to use? "
        read interface
        ;;
        [nN]*)
        interface="${strSuggestedInterface}";
        ;;
    esac
done
if [ "$installtype" = "N" ]; then
    while [ "${dodhcp}" = "" ]; do
        dodhcp="N"
        if [ -z "$autoaccept" ]; then
            echo
            echo -n "  Would you like to use the FOG server for DHCP service? [y/N] "
            read dodhcp;
            if [ -z "$dodhcp" ]; then
                dodhcp="N"
            fi
        fi
        case "$dodhcp" in
            [Yy]*)
            bldhcp="1";
            dodhcp="Y";
            ;;
            [Nn]*)
            bldhcp="0"
            dodhcp="N"
            ;;
        esac
    done
fi
if [ "$installtype" = "S" ]; then
    while [ -z "${snmysqlhost}" ]; do
        echo
        echo "  What is the IP address or hostname of the FOG server running "
        echo "  the fog database?  This is typically the server that also "
        echo -n "  runs the web server, dhcp, and tftp.  IP or Hostname: "
        read snmysqlhost;
		dbhost=$snmysqlhost;
	done
    while [ "${snmysqluser}" = "" ]; do
        snmysqluser=$strSuggestedSNUser
        dbuser=$snmysqluser
        if [ -z "$autoaccept" ]; then
            echo
            echo "  What is the username to access the database?"
            echo "  This information is storage in the management portal under ";
            echo "  'FOG Configuration' -> "
            echo "  'FOG Settings' -> "
            echo "  'FOG Storage Nodes' -> "
            echo -n "  'FOG_STORAGENODE_MYSQLUSER'. Username [${strSuggestedSNUser}]: "
            read snmysqluser;
            if [ -z "$snmysqluser" ]; then
                snmysqluser=${strSuggestedSNUser}
            fi
            dbuser=$snmysqluser;
        fi
    done
    while [ -z "${snmysqlpass}" ]; do
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
if [ "$installtype" = "N" ]; then
    while [ -z "${installlang}" ]; do
        installlang="N"
        if [ -z "$autoaccept" ]; then
            echo
            echo "  This version of FOG has internationalization support, would  "
            echo -n "  you like to install the additional language packs? [y/N] "
            read installlang
            if [ -z "$installlang" ]; then
                installlang="N"
            fi
        fi
        case "$installlang" in
            [Yy]*)
            installlang="1";
            ;;
            [nN]*)
            installlang="0";
            ;;
        esac
    done
fi
if [ "$installtype" = "N" ]; then
    while [ -z "${donate}" ]; do
        donate="N"
        if [ -z "$autoaccept" ]; then
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
            read donate
            if [ -z "$donate" ]; then
                donate="N"
            fi
        fi
        case "$donate" in
            [Yy]*)
            donate="1"
            ;;
            [nN]*)
            donate="0"
            ;;
        esac
    done
fi
