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
if [[ $guessdefaults == 1 ]]; then
    case $linuxReleaseName in
        *[Ff][Ee][Dd][Oo][Rr][Aa]*|*[Rr][Ee][Dd][Hh][Aa][Tt]*|*[Cc][Ee][Nn][Tt][Oo][Ss]*|*[Mm][Aa][Gg][Ee][Ii][Aa]*)
            strSuggestedOS=1
            ;;
        *[Uu][Bb][Uu][Nn][Tt][Uu]*|*[Bb][Ii][Aa][Nn]*|*[Mm][Ii][Nn][Tt]*)
            strSuggestedOS=2
            ;;
        *[Aa][Rr][Cc][Hh]*)
            strSuggestedOS=3
            ;;
        *)
            strSuggestedOS=1
            ;;
    esac
    strSuggestedInterface=$(getFirstGoodInterface)
    if [[ -z $strSuggestedInterface ]]; then
        echo "Not able to find an interface with an active internet connection. Trying alternative methods for determining the interface."
        strSuggestedIPAddress=$(/sbin/ip -f inet -o addr | awk -F'[ /]+' '/global/ {print $4}' | head -n2 | tail -n1)
        [[ -z $strSuggestedIPAddress ]] && strSuggestedIPAddress=$(/sbin/ifconfig -a | awk '/(cast)/ {print $2}' | cut -d ':' -f2 | head -n2 | tail -n1)
        strSuggestedInterface=$(/sbin/ip -f inet -o addr | awk -F'[ /]+' '/global/ {print $2}' | head -n2 | tail -n1)
        [[ -z $strSuggestedInterface ]] && strSuggestedInterface=$(/sbin/ifconfig -a | grep "'${strSuggestedIPAddress}'" -B1 | awk -F'[:]+' '{print $1}' | head -n1)
    fi
    strSuggestedIPAddress=$(/sbin/ip -4 addr show | grep $strSuggestedInterface | grep -o "inet [0-9]*\.[0-9]*\.[0-9]*\.[0-9]*" | grep -o "[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*")
    [[ -z $strSuggestedIPAddress ]] && strSuggestedIPAddress=$(/sbin/ifconfig -a | awk '/(cast)/ {print $2}' | cut -d ':' -f2 | head -n2 | tail -n1)
    strSuggestedSubMask=$(cidr2mask $(getCidr $strSuggestedInterface))
    if [[ -z $strSuggestedSubMask ]]; then
        strSuggestedSubMask=$(/sbin/ifconfig -a | grep $strSuggestedIPAddress -B1 | awk -F'[netmask ]+' '{print $4}' | head -n2)
        strSuggestedSubMask=$(mask2cidr $strSuggestedSubMask)
    fi
    submask=$strSuggestedSubMask
    strSuggestedRoute=$(ip route | grep -E "default.*${strSuggestedInterface}|${strSuggestedInterface}.*default" | head -n1 | cut -d' ' -f3 | tr -d [:blank:])
    if [[ -z $strSuggestedRoute ]]; then
        strSuggestedRoute=$(route -n | grep -E "^.*UG.*${strSuggestedInterface}$"  | head -n1)
        strSuggestedRoute=$(echo ${strSuggestedRoute:16:16} | tr -d [:blank:])
    fi
    strSuggestedDNS=""
    [[ -f /etc/resolv.conf ]] && strSuggestedDNS=$(cat /etc/resolv.conf | grep -E "^nameserver" | head -n 1 | tr -d "nameserver" | tr -d [:blank:] | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$")
    [[ -z $strSuggestedDNS && -d /etc/NetworkManager/system-connections ]] && strSuggestedDNS=$(cat /etc/NetworkManager/system-connections/* | grep "dns" | head -n 1 | tr -d "dns=" | tr -d ";" | tr -d [:blank:] | grep "^[0-9]*\.[0-9]*\.[0-9]*\.[0-9]*$")
    if [[ -z $strSuggestedDNS ]]; then #If the suggested DNS is still empty, take further steps to get the addresses.
        mkdir -p /tmp > /dev/null 2>&1 #Make sure /tmp exists, this will be the working directory.
        cat /etc/resolv.conf | grep "nameserver" > /tmp/nameservers.txt #Get all lines from reslov.conf that have "nameserver" in them.
        sed -i 's:#.*$::g' /tmp/nameservers.txt #Remove all comments from new file.
        sed -i -- 's/nameserver //g' /tmp/nameservers.txt #Change "nameserver " to "tmpDns="
        sed -i '/^$/d' /tmp/nameservers.txt #Delete blank lines from temp file.
        strSuggestedDNS=$(head -n 1 /tmp/nameservers.txt) #Get first DNS Address from the file.
	rm -f /tmp/nameservers.txt #Cleanup after ourselves.	
    fi	    
    strSuggestedSNUser="fogstorage"
fi
displayOSChoices
while [[ -z $installtype ]]; do
    installtype="N"
    if [[ -z $autoaccept ]]; then
        echo "  FOG Server installation modes:"
        echo "      * Normal Server: (Choice N) "
        echo "          This is the typical installation type and"
        echo "          will install all FOG components for you on this"
        echo "          machine.  Pick this option if you are unsure what to pick."
        echo
        echo "      * Storage Node: (Choice S)"
        echo "          This install mode will only install the software required"
        echo "          to make this server act as a node in a storage group"
        echo
        echo "  More information:  "
        echo "     http://www.fogproject.org/wiki/index.php?title=InstallationModes"
        echo
        echo -n "  What type of installation would you like to do? [N/s (Normal/Storage)] "
        read installtype
    fi
    case $installtype in
        [Nn]|[Nn][Oo][Rr][Mm][Aa][Ll]|"")
            installtype="N"
            ;;
        [Ss]|[Ss][Tt][Oo][Rr][Aa][Gg][Ee])
            installtype="S"
            ;;
        *)
            installtype=""
            echo "  Invalid input, please try again."
            ;;
    esac
done
count=0
while [[ -z $ipaddress ]]; do
    if [[ $count -ge 1 ]] || [[ -z $autoaccept ]]; then
        echo
        echo -n "  What is the IP address to be used by this FOG Server? [$strSuggestedIPAddress]"
        read ipaddress
    fi
    case $ipaddress in
        "")
            ipaddress=$(echo $strSuggestedIPAddress | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
            ;;
        *)
            ipaddress=$(echo $ipaddress | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
            ;;
    esac
    if [[ ! $(validip $ipaddress) -eq 0 ]]; then
        ipaddress=""
        echo "  Invalid IP Address"
        let count+=1
    fi
done
if [[ $strSuggestedIPAddress != $ipaddress ]]; then
    inetIPline=$(ip -4 addr | grep "inet $ipaddress")
    numberOfFieldsInOutput=$(grep -o " " <<< "$inetIPline" | wc -l)
    let numberOfFieldsInOutput+=1 >/dev/null 2>&1
    strSuggestedInterface=$(echo $line | cut -d ' ' -f $numberOfFieldsInOutput)
    strSuggestedSubMask=$(/sbin/ifconfig -a | grep $ipaddress -B1 | awk -F'[netmask ]+' '{print $4}' | head -n2)
    submask=$(mask2cidr $strSuggestedSubMask)
fi
while [[ -z $interface ]]; do
    blInt="N"
    if [[ -z $autoaccept ]]; then
        echo
        echo "  Would you like to change the default network interface from $strSuggestedInterface?"
        echo -n "  If you are not sure, select No. [y/N] "
        read blInt
    fi
    case $blInt in
        [Nn]|[Nn][Oo]|"")
            interface=$strSuggestedInterface
            ;;
        [Yy]|[Yy][Ee][Ss])
            echo -n "  What network interface would you like to use? "
            read interface
            ;;
        *)
            echo "  Invalid input, please try again."
            ;;
    esac
done
if [[ $strSuggestedInterface != $interface ]]; then
    strSuggestedSubMask=$(cidr2mask $(getCidr $interface))
    if [[ -z $strSuggestedSubMask ]]; then
        strSuggestedSubMask=$(/sbin/ifconfig -a | grep $strSuggestedIPAddress -B1 | awk -F'[netmask ]+' '{print $4}' | head -n2)
        strSuggestedSubMask=$(mask2cidr $strSuggestedSubMask)
    fi
    submask=$strSuggestedSubMask
fi
case $installtype in
    [Nn])
        count=0
        blRouter=""
        blDNS=""
        dodhcp=""
        installlang=""
        while [[ -z $routeraddress ]]; do
            if [[ -z $autoaccept ]]; then
                echo
                echo -n "  Would you like to setup a router address for the DHCP server? [Y/n] "
                read blRouter
            fi
            case $blRouter in
                [Yy]|[Yy][Ee][Ss]|"")
                    if [[ $count -ge 1 ]] || [[ -z $autoaccept ]]; then
                        echo "  What is the IP address to be used for the router on"
                        echo -n "      the DHCP server? [$strSuggestedRoute]"
                        read routeraddress
                    fi
                    case $routeraddress in
                        "")
                            routeraddress=$(echo $strSuggestedRoute | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
                            ;;
                        *)
                            routeraddress=$(echo $routeraddress | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
                            ;;
                    esac
                    if [[ ! $(validip $routeraddress) -eq 0 ]]; then
                        routeraddress=""
                        echo "  Invalid router IP Address!"
                        continue
                    fi
                    plainrouter=$routeraddress
                    ;;
                [Nn]|[Nn][Oo])
                    routeraddress="#   No router address added"
                    ;;
                *)
                    echo "  Invalid input, please try again."
                    ;;
            esac
        done
        count=0
        while [[ -z $dnsaddress ]]; do
            if [[ -z $autoaccept ]]; then
                echo
                echo -n "  Would you like DHCP to handle DNS? [Y/n] "
                read blDNS
            fi
            case $blDNS in
                [Yy]|[Yy][Ee][Ss]|"")
                    if [[ $count -ge 1 ]] || [[ -z $autoaccept ]]; then
                        echo -n "  What DNS address should DHCP allow? [$strSuggestedDNS] "
                        read dnsaddress
                    fi
                    case $dnsaddress in
                        "")
                            dnsaddress=$(echo $strSuggestedDNS | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
                            ;;
                        *)
                            dnsaddress=$(echo $dnsaddress | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
                            ;;
                    esac
                    if [[ ! $(validip $dnsaddress) -eq 0 ]]; then
                        dnsaddress=""
                        echo "  Invalid DNS IP address!"
                    fi
                    ;;
                [Nn]|[Nn][Oo])
                    dnsaddress="#   No dns added"
                    ;;
                *)
                    echo "  Invalid input, please try again."
                    ;;
            esac
        done
        while [[ -z $dodhcp ]]; do
            if [[ -z $autoaccept ]]; then
                echo
                echo -n "  Would you like to use the FOG server for DHCP service? [y/N] "
                read dodhcp
            fi
            case $dodhcp in
                [Nn]|[Nn][Oo]|"")
                    bldhcp=0
                    dodhcp="N"
                    ;;
                [Yy]|[Yy][Ee][Ss])
                    bldhcp=1
                    ;;
                *)
                    echo "  Invalid input, please try again."
                    ;;
            esac
        done
        while [[ -z $installlang ]]; do
            if [[ -z $autoaccept ]]; then
                echo
                echo "  This version of FOG has internationalization support, would  "
                echo -n "  you like to install the additional language packs? [y/N] "
                read installlang
            fi
            case $installlang in
                [Nn]|[Nn][Oo]|"")
                    installlang=0
                    ;;
                [Yy]|[Yy][Ee][Ss])
                    installlang=1
                    ;;
                *)
                    echo "  Invalid input, please try again."
                    ;;
            esac
        done
	[[ -z $snmysqlhost ]] && snmysqlhost='localhost'
	[[ -z $snmysqluser ]] && snmysqluser='root'
        ;;
    [Ss])
        while [[ -z $snmysqlhost ]]; do
            echo
            echo "  What is the IP address or hostname of the FOG server running "
            echo "  the fog database?  This is typically the server that also "
            echo -n "  runs the web server, dhcp, and tftp.  IP or Hostname: "
            read snmysqlhost
        done
        while [[ -z $snmysqluser ]]; do
            snmysqluser=$strSuggestedSNUser
            if [[ -z $autoaccept ]]; then
                echo
                echo "  What is the username to access the database?"
                echo "  This information is storage in the management portal under ";
                echo "  'FOG Configuration' -> "
                echo "  'FOG Settings' -> "
                echo "  'FOG Storage Nodes' -> "
                echo -n "  'FOG_STORAGENODE_MYSQLUSER'. Username [$strSuggestedSNUser]: "
                read snmysqluser
                [[ -z $snmysqluser ]] && snmysqluser=$strSuggestedSNUser
            fi
        done
        while [[ -z $snmysqlpass ]]; do
            echo
            echo "  What is the password to access the database?  "
            echo "  This information is storage in the management portal under "
            echo "  'FOG Configuration' -> "
            echo "  'FOG Settings' -> "
            echo "  'FOG Storage Nodes' -> "
            echo  -n "  'FOG_STORAGENODE_MYSQLPASS'.  Password: "
            read snmysqlpass
            [[ -z $snmysqlpass ]] && echo "Invalid input, please try again."
        done
        ;;
esac
