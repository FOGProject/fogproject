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
    case $linuxReleaseName_lower in
        *fedora*|*red*hat*|*centos*|*mageia*|*alma*|*rocky*)
            strSuggestedOS=1
            ;;
        *ubuntu*|*bian*|*mint*)
            strSuggestedOS=2
            ;;
        *alpine*)
            strSuggestedOS=3
            ;;
        *arch*|*manjaro*)
            strSuggestedOS=4
            ;;
        *)
            strSuggestedOS=1
            ;;
    esac
    allinterfaces=($(getAllNetworkInterfaces))
    strSuggestedInterface=${allinterfaces[0]}
    if [[ -z $strSuggestedInterface ]]; then
        echo "ERROR: Not able to find a network interface that is up on your system"
        exit 1
    fi
    strSuggestedRoute=$(ip route | grep -E "default.*${strSuggestedInterface}|${strSuggestedInterface}.*default" | head -n1 | cut -d' ' -f3 | tr -d [:blank:])
    if [[ -z $strSuggestedRoute ]]; then
        strSuggestedRoute=$(route -n 2>/dev/null | grep -E "^.*UG.*${strSuggestedInterface}$" | head -n1 | awk '{print $2}' | tr -d [:blank:])
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
    strSuggestedHostname=$(hostname -f)
fi
displayOSChoices
while [[ -z ${FOG_install_type} ]]; do
    FOG_install_type="N"
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
    case ${FOG_install_type} in
        [Nn]|[Nn][Oo][Rr][Mm][Aa][Ll]|"")
            FOG_install_type="N"
            ;;
        [Ss]|[Ss][Tt][Oo][Rr][Aa][Gg][Ee])
            FOG_install_type="S"
            ;;
        *)
            FOG_install_type=""
            echo "  Invalid input, please try again."
            ;;
    esac
done
testInterface() {
    while [[ -z ${NET_interface} ]]; do
        blInt="N"
        if [[ -z $autoaccept ]]; then
            echo
            echo "  We found the following interfaces on your system:"
            for i in $allinterfaces; do
                iip=$(ip -4 addr show $i | awk '$1 == "inet" {print $2}')
                echo "     * $i - $iip"
            done
            echo "  Would you like to change the default network interface from $strSuggestedInterface?"
            echo -n "  If you are not sure, select No. [y/N] "
            read blInt
        fi
        case $blInt in
            [Nn]|[Nn][Oo]|"")
                NET_interface=$strSuggestedInterface
                ;;
            [Yy]|[Yy][Ee][Ss])
                echo -n "  What network interface would you like to use? "
                read interface
                ;;
            *)
                echo "  Invalid input, please try again."
                ;;
        esac
        ip -4 link show ${NET_interface} >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            echo
            echo "  * The network interface named ${NET_interface} does not exist."
            NET_interface=""
            continue
        fi
    done
}
testInterface
# GH-954: `ip -4 addr show` prints one line per address the interface carries,
# so what comes back here is a LIST, not a single value. ${PKI_san_ip_addresses} keeps the
# whole list for the few consumers that legitimately want every address --
# certificate SANs, nginx server_name, apache ServerAlias, the maintenance
# allow list -- and normalizeIpAddress() then reduces ${NET_fog_server_ip} to the primary,
# which is what every other consumer has always assumed it was.
while [[ -z ${NET_fog_server_ip} ]]; do
    NET_fog_server_ip=$(ip -4 addr show ${NET_interface} | awk '$1 == "inet" {gsub(/\/.*$/, "", $2); print $2}')
    PKI_san_ip_addresses="${NET_fog_server_ip}"
    if [[ $(validip ${NET_fog_server_ip}) -ne 0 ]]; then
        echo
        echo "  * The interface ${NET_interface} does not seem to have a valid IP Configured to it."
        NET_interface=""
        testInterface
    fi
done
NET_subnet_mask=$(cidr2mask $(getCidr ${NET_interface}))
if [[ -z ${NET_subnet_mask} ]]; then
    NET_subnet_mask=$(/sbin/ifconfig -a | grep ${NET_fog_server_ip} -B1 | awk -F'[netmask ]+' '{print $4}' | head -n2)
    NET_subnet_mask=$(mask2cidr ${NET_subnet_mask})
fi
if [[ $strSuggestedHostname == ${NET_fog_server_ip} ]]; then
    strSuggestedHostname=$(hostnamectl --static)
fi
case ${FOG_install_type} in
    [Nn])
        count=0
        blRouter=""
        blDNS=""
        FOG_install_lang=""
        while [[ -z ${DHCP_enabled} ]]; do
            if [[ -z $autoaccept ]]; then
                echo
                echo -n "  Would you like to use the FOG server for DHCP service? [y/N] "
                read dodhcp
            fi
            case ${DHCP_enabled} in
                [Nn]|[Nn][Oo]|"")
                    DHCP_enabled=0
                    DHCP_enabled="N"
                    ;;
                [Yy]|[Yy][Ee][Ss])
                    DHCP_enabled=1
                    ;;
                *)
                    echo "  Invalid input, please try again."
                    ;;
            esac
        done
        if [[ ${DHCP_enabled} -eq 1 ]]; then
            while [[ -z ${DHCP_router} ]]; do
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
                        case ${DHCP_router} in
                            "")
                                DHCP_router=$(echo $strSuggestedRoute | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
                                ;;
                            *)
                                DHCP_router=$(echo ${DHCP_router} | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
                                ;;
                        esac
                        if [[ ! $(validip ${DHCP_router}) -eq 0 ]]; then
                            DHCP_router=""
                            echo "  Invalid router IP Address!"
                            continue
                        fi
                        DHCP_router=${DHCP_router}
                        ;;
                    [Nn]|[Nn][Oo])
                        DHCP_router="#   No router address added"
                        ;;
                    *)
                        echo "  Invalid input, please try again."
                        ;;
                esac
            done
            count=0
            while [[ -z ${DHCP_dns_server_ip} ]]; do
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
                        case ${DHCP_dns_server_ip} in
                            "")
                                DHCP_dns_server_ip=$(echo $strSuggestedDNS | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
                                ;;
                            *)
                                DHCP_dns_server_ip=$(echo ${DHCP_dns_server_ip} | grep -o '^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$' | tr -d '[[:space:]]')
                                ;;
                        esac
                        if [[ ! $(validip ${DHCP_dns_server_ip}) -eq 0 ]]; then
                            DHCP_dns_server_ip=""
                            echo "  Invalid DNS IP address!"
                        fi
                        ;;
                    [Nn]|[Nn][Oo])
                        DHCP_dns_server_ip="#   No dns added"
                        ;;
                    *)
                        echo "  Invalid input, please try again."
                        ;;
                esac
            done
        else
            DHCP_dns_server_ip="# No dns added"
            DHCP_router="# No router added"
        fi
        while [[ -z ${FOG_install_lang} ]]; do
            if [[ -z $autoaccept ]]; then
                echo
                echo "  This version of FOG has internationalization support, would  "
                echo -n "  you like to install the additional language packs? [y/N] "
                read installlang
            fi
            case ${FOG_install_lang} in
                [Nn]|[Nn][Oo]|"")
                    FOG_install_lang=0
                    ;;
                [Yy]|[Yy][Ee][Ss])
                    FOG_install_lang=1
                    ;;
                *)
                    echo "  Invalid input, please try again."
                    ;;
            esac
        done
        [[ -z ${DB_host} ]] && DB_host='localhost'
        [[ -z ${DB_user} ]] && DB_user='fogmaster'
        ;;
    [Ss])
        while [[ -z ${DB_host} ]]; do
            echo
            echo "  What is the IP address or hostname of the FOG server running "
            echo "  the fog database?  This is typically the server that also "
            echo -n "  runs the web server, dhcp, and tftp.  IP or Hostname: "
            read snmysqlhost
        done
        DB_user='fogstorage'
        while [[ -z ${DB_password} ]]; do
            echo
            echo "  What is the password to access the database?  "
            echo "  This information is storage in the management portal under "
            echo "  'FOG Configuration' -> "
            echo "  'FOG Settings' -> "
            echo "  'FOG Storage Nodes' -> "
            echo  -n "  'FOG_STORAGENODE_MYSQLPASS'.  Password: "
            read -r snmysqlpass
            [[ -z ${DB_password} ]] && echo "Invalid input, please try again."
        done
        ;;
esac
# The "would you like to enable secure HTTPS" question used to live here. It
# set ${WEB_url_proto}, which is now https on every install, so it no longer mapped to
# anything an admin could decide -- and answering "no" to it used to silently
# turn off Secure Boot staging and turn on a 25-minute iPXE rebuild, neither of
# which it mentioned.
#
# What is actually a choice -- the redirect, what the certificate chains to, and
# whether iPXE is rebuilt -- is asked once, together, by promptInstallMode() at
# the end of installfog.sh, where the four options can be shown with their
# consequences instead of hidden behind one yes/no. Two prompts covering the
# same ground could also contradict each other.
#
# (The old text pointed at wiki.fogproject.org, which has been retired in favour
# of docs.fogproject.org.)
