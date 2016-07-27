#!/bin/bash
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
dots() {
    local pad=$(printf "%0.1s" "."{1..60})
    printf " * %s%*.*s" "$1" 0 $((60-${#1})) "$pad"
    return 0
}
backupReports() {
    dots "Backing up user reports"
    [[ ! -d ../rpttmp/ ]] && mkdir ../rpttmp/ >>$workingdir/error_logs/fog_error_${version}.log
    [[ -d $webdirdest/management/reports/ ]] && cp -a $webdirdest/management/reports/* ../rpttmp/ >>$workingdir/error_logs/fog_error_${version}.log
    echo "Done"
    return 0
}
registerStorageNode() {
    [[ -z $webroot ]] && webroot="/"
    local user=$(echo -n $fogguiuser|base64)
    local pass=$(echo -n $fogguipass|base64)
    checkcreds=$(wget -q -O - --no-check-certificate "http://$ipaddress/${webroot}/service/checkcredentials.php" --post-data="username=$user&password=$pass")
    [[ $checkcreds != '#!ok' ]] && return
    dots "Checking if this node is registered"
    storageNodeExists=$(wget -qO - http://$ipaddress/${webroot}/maintenance/check_node_exists.php --post-data="ip=${ipaddress}")
    echo "Done"
    echo " * Node is registered"
    if [[ $storageNodeExists != exists ]]; then
        [[ -z $maxClients ]] && maxClients=10
        dots "Node being registered"
        wget -qO - http://$ipaddress/${webroot}/maintenance/create_update_node.php --post-data="newNode&name=$(echo -n $ipaddress| base64)&path=$(echo -n $storageLocation|base64)&ftppath=$(echo -n $storageLocation|base64)&snapinpath=$(echo -n $snapindir|base64)&sslpath=$(echo -n $sslpath|base64)&ip=$(echo -n $ipaddress|base64)&maxClients=$(echo -n $maxClients|base64)&user=$(echo -n $username|base64)&pass=$(echo -n $password|base64)&interface=$(echo -n $interface|base64)&bandwidth=$(echo -n $interface|base64)&webroot=$(echo -n $webroot|base64)&fogverfied"
        echo "Done"
    fi
}
updateStorageNodeCredentials() {
    [[ -z $webroot ]] && webroot="/"
    local user=$(echo -n $fogguiuser|base64)
    local pass=$(echo -n $fogguipass|base64)
    checkcreds=$(wget -q -O - --no-check-certificate "http://$ipaddress/${webroot}/service/checkcredentials.php" --post-data="username=$user&password=$pass")
    [[ $checkcreds != '#!ok' ]] && return
    dots "Ensuring node username and passwords match"
    wget -qO - http://$ipaddress${webroot}maintenance/create_update_node.php --post-data="nodePass&ip=$(echo -n $ipaddress|base64)&user=$(echo -n $username|base64)&pass=$(echo -n $password|base64)&fogverified"
    echo "Done"
}
backupDB() {
    local user=$(echo -n $fogguiuser|base64)
    local pass=$(echo -n $fogguipass|base64)
    checkcreds=$(wget -q -O - --no-check-certificate "http://$ipaddress/$webroot/service/checkcredentials.php" --post-data="username=$user&password=$pass")
    if [[ $checkcreds == "#!ok" ]]; then
        dots "Backing up database"
        if [[ -d $backupPath/fog_web_${version}.BACKUP ]]; then
            [[ ! -d $backupPath/fogDBbackups ]] && mkdir -p $backupPath/fogDBbackups >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            wget --no-check-certificate -O $backupPath/fogDBbackups/fog_sql_${version}_$(date +"%Y%m%d_%I%M%S").sql "http://$ipaddress/$webroot/management/export.php" --post-data="type=sql&fogguiuser=$fogguiuser&fogguipass=$fogguipass&fogajaxonly=1" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
        errorStat $?
    else
        echo
        echo " ########################################################################################"
        echo "   FOG has adjusted to using a login system to protect what can/cannot be downloaded"
        echo "   We have detected that you don't have credentials defined to perform the backup"
        echo "   If you would like the database to be backed up during install please define"
        echo "   in your /opt/fog/.fogsettings file"
        echo
        echo "   fogguiuser='usernameOfFOGGUI'"
        echo "   fogguipass='passwordOfFOGGUIUser'"
        echo
        echo "   You can also re-run this installer as:"
        echo
        echo "   fogguiuser='usernameOfFOGGUI' fogguipass='passwordOfFOGGUIUser' ./$0 $*"
        echo " ########################################################################################"
        echo
        sleep 10
    fi
}
updateDB() {
    local user=$(echo -n $fogguiuser|base64)
    local pass=$(echo -n $fogguipass|base64)
    checkcreds=$(wget -q -O - --no-check-certificate "http://$ipaddress/$webroot/service/checkcredentials.php" --post-data="username=$user&password=$pass")
    case $dbupdate in
        [Yy]|[Yy][Ee][Ss])
            dots "Updating Database"
            if [[ $checkcreds != '#!ok' ]]; then
                echo "No"
                echo " * FOG GUI Username and Password pair could not be verified"
            else
                wget -qO - --post-data="confirm&fogverified" --no-proxy http://127.0.0.1/${webroot}management/index.php?node=schema >>$workingdir/error_logs/fog_error_${version}.log 2>&1 || wget -qO - --post-data="confirm&fogverified" --no-proxy http://${ipaddress}/${webroot}management/index.php?node=schema >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                errorStat $?
            fi
            ;;
        *)
            echo
            echo " * You still need to install/update your database schema."
            echo " * This can be done by opening a web browser and going to:"
            echo
            echo "   http://${ipaddress}/fog/management"
            echo
            read -p " * Press [Enter] key when database is updated/installed."
            echo
            ;;
    esac
}
validip() {
    local ip=$1
    local stat=1
    if [[ $ip =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
        OIFS=$IFS
        IFS='.'
        ip=($ip)
        IFS=$OIFS
        [[ ${ip[0]} -le 255 && ${ip[1]} -le 255 && ${ip[2]} -le 255 && ${ip[3]} -le 255 ]]
        stat=$?
    fi
    echo $stat
}
getCidr() {
    local cidr
    cidr=$(ip -f inet -o addr | grep $1 | awk -F'[ /]+' '/global/ {print $5}' | head -n2 | tail -n1)
    echo $cidr
}
mask2cidr() {
    local submask=$1
    nbits=0
    OIFS=$IFS
    IFS='.'
    for dec in $submask; do
        case $dec in
            255)
                let nbits+=8
                ;;
            254)
                let nbits+=7
                break
                ;;
            252)
                let nbits+=6
                break
                ;;
            248)
                let nbits+=5
                break
                ;;
            240)
                let nbits+=4
                break
                ;;
            224)
                let
                nbits+=3
                break
                ;;
            192)
                let nbits+=2
                break
                ;;
            128)
                let nbits+=1
                break
                ;;
            0)
                ;;
            *)
                echo "Error: $dec is not recognized"
                exit 1
                ;;
        esac
    done
    IFS=$OIFS
    echo "$nbits"
}
cidr2mask() {
    local i=""
    local mask=""
    local full_octets=$(($1/8))
    local partial_octet=$(($1%8))
    for ((i=0;i<4;i+=1)); do
        if [[ $i -lt $full_octets ]]; then
            mask+=255
        elif [[ $i -eq $full_octets ]]; then
            mask+=$((256 - 2**(8-$partial_octet)))
        else
            mask+=0
        fi
        test $i -lt 3 && mask+=.
    done
    echo $mask
}
mask2network() {
    OIFS=$IFS
    IFS='.'
    read -r i1 i2 i3 i4 <<< "$1"
    read -r m1 m2 m3 m4 <<< "$2"
    IFS=$OIFS
    printf "%d.%d.%d.%d\n"  "$((i1 & m1))" "$((i2 & m2))" "$((i3 & m3))" "$((i4 & m4))"
}
interface2broadcast() {
    local interface=$1
    if [[ -z $interface ]]; then
        echo "No interface passed"
        return 1
    fi
    echo $(ip -4 addr show | grep -w inet | grep $interface | awk '{print $4}')
}
subtract1fromAddress() {
    local ip=$1
    if [[ -z $ip ]]; then
        echo "No IP Passed"
        return 1
    fi
    if [[ ! $(validip $ip) -eq 0 ]]; then
        echo "Invalid IP Passed"
        return 1
    fi
    oIFS=$IFS
    IFS='.'
    read ip1 ip2 ip3 ip4 <<< "$ip"
    IFS=$oIFS
    if [[ $ip4 -gt 0 ]]; then
        let ip4-=1
    elif [[ $ip3 -gt 0 ]]; then
        let ip3-=1
        ip4=255
    elif [[ $ip2 -gt 0 ]]; then
        let ip2-=1
        ip3=255
        ip4=255
    elif [[ $ip1 -gt 0 ]]; then
        let ip1-=1
        ip2=255
        ip3=255
        ip4=255
    else
        echo "Invalid IP ranges were passed"
        echo ${ip1}.${ip2}.${ip3}.${ip4}
        return 2
    fi
    echo ${ip1}.${ip2}.${ip3}.${ip4}
}
subtractFromAddress() {
    local ipaddress="$1"
    local decreaseby=$2
    local maxOctetValue=256
    local octet1=""
    local octet2=""
    local octet3=""
    local octet4=""
    oIFS=$IFS
    IFS='.' read octet1 octet2 octet3 octet4 <<< "$ipaddress"
    IFS=$oIFS
    let octet4-=$decreaseby
    if [[ $octet4 -lt $maxOctetValue && $octet4 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    echo $octet4
    echo $maxOctetValue
    octet4=$(echo $octet4 | sed 's/-//g')
    numRollOver=$((octet4 / maxOctetValue))
    echo $numRollOver
    let octet4-=$((numRollOver * maxOctetValue))
    echo $((numRollOver - octet3))
    let octet3-=$numRollOver
    echo $octet3
    if [[ $octet3 -lt $maxOctetValue && $octet3 -ge 0 ]]; then
        echo 'here'
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    numRollOver=$((octet3 / maxOctetValue))
    let octet3-=$((numRollOver * maxOctetValue))
    let octet2-=$numRollOver
    if [[ $octet2 -lt $maxOctetValue && $octet2 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    numRollOver=$((octet2 / maxOctetValue))
    let octet2-=$((numRollOver * maxOctetValue))
    let octet1-=$numRollOver
    if [[ $octet1 -lt $maxOctetValue && $octet1 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    return 1
}
addToAddress() {
    local ipaddress="$1"
    local increaseby=$2
    local maxOctetValue=256
    local octet1=""
    local octet2=""
    local octet3=""
    local octet4=""
    oIFS=$IFS
    IFS='.' read octet1 octet2 octet3 octet4 <<< "$ipaddress"
    IFS=$oIFS
    let octet4+=$increaseby
    if [[ $octet4 -lt $maxOctetValue && $octet4 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet4 / maxOctetValue))
    let octet4-=$((numRollOver * maxOctetValue))
    let octet3+=$numRollOver
    if [[ $octet3 -lt $maxOctetValue && $octet3 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet3 / maxOctetValue))
    let octet3-=$((numRollOver * maxOctetValue))
    let octet2+=$numRollOver
    if [[ $octet2 -lt $maxOctetValue && $octet2 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet2 / maxOctetValue))
    let octet2-=$((numRollOver * maxOctetValue))
    let octet1+=$numRollOver
    if [[ $octet1 -lt $maxOctetValue && $octet1 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    return 1
}
getFirstGoodInterface() {
    siteToCheckForInternet=www.google.com #Must be domain name.
    ipToCheckForInternet=8.8.8.8 #Must be IP.
    [[ -e $workingdir/tempInterfaces.txt ]] && rm -f $workingdir/tempInterfaces.txt >/dev/null 2>&1
    foundinterfaces=$(ip -4 addr | awk -F'(global )' '/global / {print $2}')
    for interface in $foundinterfaces; do
        ping -c 1 $ipToCheckForInternet -I $interface >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && continue
        ping -c 1 $siteToCheckForInternet -I $interface >/dev/null 2>&1
        if [[ ! $? -eq 0 ]]; then
            echo "Internet detected on $anInterface but there seems to be a DNS problem." >>$workingdir/error_logs/fog_error_${version}.log
            echo "Check the contents of /etc/resolv." >>$workingdir/error_logs/fog_error_${version}.log
            echo "If this is CentOS, RHEL, or Fedora or an other RH variant," >>$workingdir/error_logs/fog_error_${version}.log
            echo "also check the DNS entries for /etc/sysconfig/network-scripts/ifcfg-$anInterface" >>$workingdir/error_logs/fog_error_${version}.log
            continue
        fi
        echo $interface >> $workingdir/goodInterface.txt
        break
    done
    [[ -e $workingdir/tempInterfaces.txt ]] && rm -f $workingdir/tempInterfaces.txt >/dev/null 2>&1
    if [[ -e $workingdir/goodInterface.txt ]]; then
        goodInterface=$(cat $workingdir/goodInterface.txt)
        rm -f $workingdir/goodInterface.txt >/dev/null 2>&1
    fi
    [[ -n $goodInterface ]] && echo $goodInterface
    if [[ -z $goodInterface ]]; then
        echo "There was no interface with an active internet connection found." >>$workingdir/error_logs/fog_error_${version}.log
        echo ""
    fi
}
join() {
    local IFS="$1"
    shift
    echo "$*"
}
restoreReports() {
    dots "Restoring user reports"
    if [[ -d $webdirdest/management/reports ]]; then
        if [[ -d ../rpttmp/ ]]; then
            cp -a ../rpttmp/* $webdirdest/management/reports/
        fi
    fi
    errorStat $?
}
installFOGServices() {
    dots "Setting up FOG Services"
    mkdir -p $servicedst
    cp -Rf $servicesrc/* $servicedst/
    chmod +x -R $servicedst/
    mkdir -p $servicelogs
    errorStat $?
}
configureUDPCast() {
    dots "Setting up UDPCast"
    cp -Rf "$udpcastsrc" "$udpcasttmp"
    cur=$(pwd)
    cd /tmp
    tar xvzf "$udpcasttmp" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    cd $udpcastout
    errorStat $?
    dots "Configuring UDPCast"
    ./configure >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    dots "Building UDPCast"
    make >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    dots "Installing UDPCast"
    make install >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    cd $cur
}
configureFTP() {
    dots "Setting up and starting VSFTP Server..."
    if [[ -f $ftpconfig ]]; then
        mv $ftpconfig ${ftpconfig}.fogbackup
    fi
    if [[ -f $ftpxinetd ]]; then
        mv $ftpxinetd ${ftpxinetd}.fogbackup
    fi
    vsftp=$(vsftpd -version 0>&1 | awk -F'version ' '{print $2}')
    vsvermaj=$(echo $vsftp | awk -F. '{print $1}')
    vsverbug=$(echo $vsftp | awk -F. '{print $3}')
    seccompsand=""
    allow_writeable_chroot=""
    if [[ $vsvermaj -gt 3 ]] || [[ $vsvermaj -eq 3 && $vsverbug -ge 2 ]]; then
        seccompsand="seccomp_sandbox=NO"
    fi
    [[ $osid -eq 3 ]] && tcpwrappers="NO" || tcpwrappers="YES"
    echo -e  "anonymous_enable=NO\nlocal_enable=YES\nwrite_enable=YES\nlocal_umask=022\ndirmessage_enable=YES\nxferlog_enable=YES\nconnect_from_port_20=YES\nxferlog_std_format=YES\nlisten=YES\npam_service_name=vsftpd\nuserlist_enable=NO\ntcp_wrappers=$tcpwrappers\n$seccompsand" > "$ftpconfig"
    case $systemctl in
        yes)
            systemctl enable vsftpd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl stop vsftpd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl start vsftpd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl status vsftpd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            ;;
        *)
            case $osid in
                2)
                    sysv-rc-conf vsftpd on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    service vsftpd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service vsftpd start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service vsftpd status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
                *)
                    chkconfig vsftpd on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    service vsftpd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service vsftpd start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service vsftpd status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
            ;;
    esac
    errorStat $?
}
configureDefaultiPXEfile() {
    [[ -z $webroot ]] && webroot='/'
    echo -e "#!ipxe\ncpuid --ext 29 && set arch x86_64 || set arch i386\nparams\nparam mac0 \${net0/mac}\nparam arch \${arch}\nparam platform \${platform}\nparam product \${product}\nparam manufacturer \${product}\nparam ipxever \${version}\nparam filename \${filename}\nisset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\nisset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n:bootme\nchain http://$ipaddress${webroot}service/ipxe/boot.php##params" > "$tftpdirdst/default.ipxe"
}
configureTFTPandPXE() {
    dots "Setting up and starting TFTP and PXE Servers"
    [[ -d ${tftpdirdst}.prev ]] && rm -rf ${tftpdirdst}.prev >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    [[ ! -d ${tftpdirdst} ]] && mkdir -p $tftpdirdst >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    [[ -e ${tftpdirdst}.fogbackup ]] && rm -rf ${tftpdirdst}.fogbackup >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    [[ -d $tftpdirdst && ! -d ${tftpdirdst}.prev ]] && mkdir -p ${tftpdirdst}.prev >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    [[ -d ${tftpdirdst}.prev ]] && cp -Rf $tftpdirdst/* ${tftpdirdst}.prev/ >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    cd $tftpdirsrc
    for tftpdir in $(ls -d */); do
        [[ ! -d $tftpdirdst/$tftpdir ]] && mkdir -p $tftpdirdst/$tftpdir >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    done
    local findoptions=""
    [[ $notpxedefaultfile == true ]] && findoptions="! -name default"
    find -type f $findoptions -exec cp -Rfv {} $tftpdirdst/{} \; >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    cd $workingdir
    chown -R $username $tftpdirdst >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    chown -R $username $webdirdest/service/ipxe >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    find $tftpdirdst -type d -exec chmod 755 {} \;
    find $webdirdest -type d -exec chmod 755 {} \;
    find $tftpdirdst ! -type d -exec chmod 655 {} \;
    configureDefaultiPXEfile
    if [[ -f $tftpconfig ]]; then
        cp -Rf $tftpconfig ${tftpconfig}.fogbackup >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    if [[ $noTftpBuild != "true" ]]; then
        echo -e "# default: off\n# description: The tftp server serves files using the trivial file transfer \n#    protocol.  The tftp protocol is often used to boot diskless \n# workstations, download configuration files to network-aware printers, \n#   and to start the installation process for some operating systems.\nservice tftp\n{\n    socket_type     = dgram\n   protocol        = udp\n wait            = yes\n user            = root\n    server          = /usr/sbin/in.tftpd\n  server_args     = -s ${tftpdirdst}\n    disable         = no\n  per_source      = 11\n  cps         = 100 2\n   flags           = IPv4\n}" > "$tftpconfig"
    fi
    case $systemctl in
        yes)
            if [[ $osid -eq 2 && -f $tftpconfigupstartdefaults ]]; then
                echo -e "# /etc/default/tftpd-hpa\n# FOG Modified version\nTFTP_USERNAME=\"root\"\nTFTP_DIRECTORY=\"/tftpboot\"\nTFTP_ADDRESS=\":69\"\nTFTP_OPTIONS=\"-s\"" > "$tftpconfigupstartdefaults"
                systemctl disable xinetd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                systemctl enable tftpd-hpa >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                systemctl stop xinetd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                systemctl stop tftpd-hpa >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                systemctl start tftpd-hpa >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                systemctl status tftpd-hpa >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            else
                systemctl enable xinetd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                systemctl stop xinetd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                systemctl start xinetd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                systemctl status xinetd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            fi
            ;;
        *)
            if [[ $osid -eq 2 && -f $tftpconfigupstartdefaults ]]; then
                echo -e "# /etc/default/tftpd-hpa\n# FOG Modified version\nTFTP_USERNAME=\"root\"\nTFTP_DIRECTORY=\"/tftpboot\"\nTFTP_ADDRESS=\":69\"\nTFTP_OPTIONS=\"-s\"" > "$tftpconfigupstartdefaults"
                sysv-rc-conf xinetd off >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                service xinetd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sysv-rc-conf tftpd-hpa on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                service tftpd-hpa stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                service tftpd-hpa start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
            elif [[ $osid -eq 2 ]]; then
                sysv-rc-conf xinetd on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                $initdpath/xinetd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                $initdpath/xinetd start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
            else
                chkconfig xinetd on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                service xinetd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                service xinetd start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                service xinetd status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            fi
            ;;
    esac
    errorStat $?
}
configureMinHttpd() {
    configureHttpd
    echo "<?php die('This is a storage node, please do not access the web ui here!');" > "$webdirdest/management/index.php"
}
addUbuntuRepo() {
    DEBIAN_FRONTEND=noninteractive $packageinstaller python-software-properties software-properties-common >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    ntpdate pool.ntp.org >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    add-apt-repository -y ppa:ondrej/$repo >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    return $?
}
installPackages() {
    dots "Adding needed repository"
    case $osid in
        1)
            $packageinstaller epel-release >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            packages="$packages php-bcmath"
            packages="${packages// mod_fastcgi/}"
            packages="${packages// mod_evasive/}"
            case $linuxReleaseName in
                *[Ff][Ee][Dd][Oo][Rr][Aa]*)
                    repo="fedora"
                    [[ -z $OSVersion ]] && echo "OS Version not detected"
                    ! [[ $OSVersion =~ ^[0-9]+$ ]] && echo "OS Version not detected properly."
                    if [[ $OSVersion -ge 22 ]]; then
                        packages="${packages// mysql / mariadb }">>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        packages="${packages// mysql-server / mariadb-server }">>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        packages="${packages// dhcp / dhcp-server }">>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    fi
                    ;;
                *)
                    repo="enterprise"
                    ;;
            esac
            y="http://rpms.remirepo.net/$repo/remi-release-${OSVersion}.rpm"
            x=$(basename $y | awk -F[.] '{print $1}')
            eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            if [[ ! $? -eq 0 ]]; then
                rpm -Uvh $y >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                rpm --import "http://rpms.remirepo.net/RPM-GPG-KEY-remi" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            fi
            [[ -n $repoenable ]] && eval $repoenable remi >>$workingdir/error_logs/fog_error_${version}.log 2>&1 || true
            ;;
        2)
            packages="${packages// libapache2-mod-fastcgi/}"
            packages="${packages// libapache2-mod-evasive/}"
            packages="$packages php$php_ver-bcmath"
            case $linuxReleaseName in
                *[Dd][Ee][Bb][Ii][Aa][Nn]*)
                    if [[ $OSVersion -eq 7 ]]; then
                        debcode="wheezy"
                        grep -l "deb http://packages.dotdeb.org $debcode-php56 all" "/etc/apt/sources.list" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        if [[ $? != 0 ]]; then
                            echo -e "deb http://packages.dotdeb.org $debcode-php56 all\ndeb-src http://packages.dotdeb.org $debcode-php56 all\n" >> "/etc/apt/sources.list"
                        fi
                    fi
                    ;;
                *)
                    if [[ $linuxReleaseName == +(*[Bb][Uu][Nn][Tt][Uu]*) ]]; then
                        addUbuntuRepo
                        if [[ $? != 0 ]]; then
                            apt-get update >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            apt-get -yq install python-software-properties ntpdate >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            ntpdate pool.ntp.org >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            locale-gen 'en_US.UTF-8' >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y ppa:ondrej/${repo} >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        fi
                    fi
                    apt-get update >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    DEBIAN_FRONTEND=noninteractive $packageinstaller python-software-properties software-properties-common ntpdate >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ntpdate pool.ntp.org >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    locale-gen 'en_US.UTF-8' >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y ppa:ondrej/${repo} >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
            ;;
    esac
    errorStat $?
    dots "Preparing Package Manager"
    $packmanUpdate >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    if [[ $osid -eq 2 ]]; then
        if [[ $? != 0 ]] && [[ $linuxReleaseName == +(*[Bb][Uu][Nn][Tt][Uu]*) ]]; then
            cp /etc/apt/sources.list /etc/apt/sources.list.original_fog_$(date +%s)
            sed -i -e 's/\/\/*archive.ubuntu.com\|\/\/*security.ubuntu.com/\/\/old-releases.ubuntu.com/g' /etc/apt/sources.list
            $packmanUpdate >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            if [[ $? != 0 ]]; then
                cp -f /etc/apt/sources.list.original_fog /etc/apt/sources.list >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                rm -f /etc/apt/sources.list.original_fog >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                false
            fi
        fi
    fi
    errorStat $?
    packages=$(echo "${packages[@]}" | tr ' ' '\n' | sort -u | tr '\n' ' ')
    echo -e " * Packages to be installed:\n\n\t$packages\n\n"
    newPackList=""
    for x in $packages; do
        case $x in
            mysql)
                for sqlclient in $sqlclientlist; do
                    eval $packagelist "$sqlclient" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    if [[ $? -eq 0 ]]; then
                        x=$sqlclient
                        break
                    fi
                done
                ;;
            mysql-server)
                for sqlserver in $sqlserverlist; do
                    eval $packagelist "$sqlserver" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    if [[ $? -eq 0 ]]; then
                        x=$sqlserver
                        break
                    fi
                done
                ;;
            php${php_ver}-json)
                for json in $jsontest; do
                    eval $packagelist "$json" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    if [[ $? -eq 0 ]]; then
                        x=$json
                        break
                    fi
                done
                ;;
            php${php_ver}-mysqlnd)
                for phpmysql in $(echo php${php_ver}-mysqlnd php${php_ver}-mysql); do
                    eval $packagelist "$phpmysql" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    if [[ $? -eq 0 ]]; then
                        x=$phpmysql
                        break
                    fi
                done
                ;;
        esac
        [[ $osid == 2 && -z $dhcpd && $x == +(*'dhcp'*) ]] && dhcpd=$x
        eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ $? -eq 0 ]]; then
            dots "Skipping package: $x"
            echo "(Already Installed)"
            newPackList="$newPackList $x"
            continue
        fi
        eval $packagelist "$x" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ ! $? -eq 0 ]]; then
            dots "Skipping package: $x"
            echo "(Does not exist)"
            continue
        fi
        newPackList="$newPackList $x"
        dots "Installing package: $x"
        DEBIAN_FRONTEND=noninteractive $packageinstaller $x >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        errorStat $?
    done
    packages=$(echo $newPackList)
    packages=$(echo "${packages[@]}" | tr ' ' '\n' | sort -u | tr '\n' ' ')
    dots "Updating packages as needed"
    DEBIAN_FRONTEND=noninteractive $packageupdater $packages >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    echo "OK"
}
confirmPackageInstallation() {
    for x in $packages; do
        dots "Checking package: $x"
        case $x in
            mysql)
                for sqlclient in $sqlclientlist; do
                    x=$sqlclient
                    eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    [[ $? -eq 0 ]] && break
                done
                ;;
            mysql-server)
                for sqlserver in $sqlserverlist; do
                    x=$sqlserver
                    eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    [[ $? -eq 0 ]] && break
                done
                ;;
            php${php_ver}-json)
                for json in $jsontest; do
                    x=$json
                    eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    [[ $? -eq 0 ]] && break
                done
                ;;
            php${php_ver}-mysqlnd)
                for phpmysql in $(echo php${php_ver}-mysqlnd php${php_ver}-mysql); do
                    x=$phpmysql
                    eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    [[ $? -eq 0 ]] && break
                done
                ;;
        esac
        eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        errorStat $?
    done
}
displayOSChoices() {
    blFirst=1
    while [[ -z $osid ]]; do
        if [[ $fogupdateloaded -eq 1 && $blFirst -eq 1 ]]; then
            blFirst=0
        else
            osid=$strSuggestedOS
            if [[ -z $autoaccept && ! -z $osid ]]; then
                echo "  What version of Linux would you like to run the installation for?"
                echo
                echo "          1) Redhat Based Linux (Redhat, CentOS, Mageia)"
                echo "          2) Debian Based Linux (Debian, Ubuntu, Kubuntu, Edubuntu)"
                echo "          3) Arch Linux"
                echo
                echo -n "  Choice: [$strSuggestedOS] "
                read osid
                case $osid in
                    "")
                        osid=$strSuggestedOS
                        break
                        ;;
                    1|2|3)
                        break
                        ;;
                    *)
                        echo "  Invalid input, please try again."
                        osid=""
                        ;;
                esac
            fi
        fi
    done
    doOSSpecificIncludes
}
doOSSpecificIncludes() {
    echo
    case $osid in
        1)
            echo -e "\n\n  Starting Redhat based Installation\n\n"
            osname="Redhat"
            . ../lib/redhat/config.sh
            ;;
        2)
            echo -e "\n\n  Starting Debian based Installation\n\n"
            osname="Debian"
            . ../lib/ubuntu/config.sh
            ;;
        3)
            echo -e "\n\n  Starting Arch Installation\n\n"
            osname="Arch"
            . ../lib/arch/config.sh
            systemctl="yes"
            ;;
        *)
            echo -e "  Sorry, answer not recognized\n\n"
            sleep 2
            osid=""
            ;;
    esac
    currentdir=$(pwd)
    case $currentdir in
        *$webdirdest*|*$tftpdirdst*)
            echo "Please change installation directory."
            echo "Running from here will fail."
            echo "You are in $currentdir which is a folder that will"
            echo "be moved during installation."
            exit 1
            ;;
    esac
}
errorStat() {
    local status=$1
    if [[ $status != 0 ]]; then
        echo "Failed!"
        [[ -z $exitFaile ]] && exit 1
    fi
    echo "OK"
}
stopInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname"
    for serviceItem in $serviceList; do
        dots "Stopping $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl stop $serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
        else
            [[ -x $initdpath/$serviceItem ]] && $initdpath/$serviceItem stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
        fi
        echo "OK"
    done
}
startInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname"
    for serviceItem in $serviceList; do
        dots "Starting $serviceItem Service"
        if [[ $systemctl == yes ]]; then
            systemctl start $serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
        else
            [[ -x $initdpath/$serviceItem ]] && $initdpath/$serviceItem start >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
        fi
        errorStat $?
    done
}
enableInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname"
    for serviceItem in $serviceList; do
        case $systemctl in
            yes)
                dots "Setting permissions on $serviceItem script"
                chmod 644 $initdpath/$serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                errorStat $?
                dots "Enabling $serviceItem Service"
                systemctl enable $serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                if [[ ! $? -eq 0 && $osid -eq 2 ]]; then
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 2 >>$workingdir/error_logs/fog_error${version}.log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 3 >>$workingdir/error_logs/fog_error${version}.log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 4 >>$workingdir/error_logs/fog_error${version}.log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 5 >>$workingdir/error_logs/fog_error${version}.log 2>&1
                fi
                ;;
            *)
                dots "Setting $serviceItem script executable"
                chmod +x $initdpath/$serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                errorStat $?
                case $osid in
                    1)
                        dots "Enabling $serviceItem Service"
                        chkconfig $serviceItem on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        ;;
                    2)
                        dots "Enabling $serviceItem Service"
                        sysv-rc-conf $serviceItem off >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        sysv-rc-conf $serviceItem on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        case $linuxReleaseName in
                            *[Bb][Uu][Nn][Tt][Uu]*)
                                /usr/lib/insserv/insserv -r $initdpath/$serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                                /usr/lib/insserv/insserv -d $initdpath/$serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                                ;;
                            *)
                                insserv -r $initdpath/$serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                                insserv -d $initdpath/$serviceItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                                ;;
                        esac
                        ;;
                esac
                ;;
        esac
        errorStat $?
    done
}
installInitScript() {
    dots "Installing FOG System Scripts"
    cp -f $initdsrc/* $initdpath/ >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    echo
    echo
    echo " * Configuring FOG System Services"
    echo
    echo
    enableInitScript
}
configureMySql() {
    stopInitScript
    dots "Setting up and starting MySQL"
    if [[ $systemctl == yes ]]; then
        if [[ $osid -eq 3 ]]; then
            [[ ! -d /var/lib/mysql ]] && mkdir /var/lib/mysql >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            chown -R mysql:mysql /var/lib/mysql >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            mysql_install_db --user=mysql --ldata=/var/lib/mysql/ >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
        for mysqlconf in `grep -rl '.*bind-address.*=.*127.0.0.1' /etc`; do
            sed -e '/.*bind-address.*=.*127.0.0.1/ s/^#*/#/' -i $mysqlconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        done
        systemctl enable mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        systemctl stop mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sleep 2
        systemctl start mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sleep 2
        systemctl status mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ ! $? -eq 0 ]]; then
            systemctl enable mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            systemctl stop mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl start mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl status mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
        if [[ ! $? -eq 0 ]]; then
            systemctl enable mariadb.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            systemctl stop mariadb.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl start mariadb.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl status mariadb.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
    else
        case $osid in
            1)
                chkconfig mysqld on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                service mysqld stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                service mysqld start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                service mysqld status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                ;;
            2)
                sysv-rc-conf mysql on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                service mysql stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                service mysql start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                ;;
        esac
    fi
    errorStat $?
}
configureFOGService() {
    [[ ! -d $servicedst ]] && mkdir -p $servicedst >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    [[ ! -d $servicedst/etc ]] && mkdir -p $servicedst/etc >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    echo "<?php define('WEBROOT','${webdirdest}');" > $servicedst/etc/config.php
    startInitScript
}
configureNFS() {
    dots "Setting up exports file"
    if [[ $blexports != 1 ]]; then
        echo "Skipped"
    else
        echo -e "$storageLocation *(ro,sync,no_wdelay,no_subtree_check,insecure_locks,no_root_squash,insecure,fsid=0)\n$storageLocation/dev *(rw,async,no_wdelay,no_subtree_check,no_root_squash,insecure,fsid=1)" > "$nfsconfig"
        errorStat $?
        dots "Setting up and starting RPCBind"
        if [[ $systemctl == yes ]]; then
            systemctl enable rpcbind.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            systemctl stop rpcbind.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl start rpcbind.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sleep 2
            systemctl status rpcbind.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        else
            case $osid in
                1)
                    chkconfig rpcbind on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    $initdpath/rpcbind stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    $initdpath/rpcbind start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    $initdpath/rpcbind status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
        fi
        errorStat $?
        dots "Setting up and starting NFS Server..."
        for nfsItem in $nfsservice; do
            if [[ $systemctl == yes ]]; then
                systemctl enable $nfsItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                systemctl stop $nfsItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                systemctl start $nfsItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                sleep 2
                systemctl status $nfsItem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            else
                case $osid in
                    1)
                        chkconfig $nfsItem on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        $initdpath/$nfsItem stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        sleep 2
                        $initdpath/$nfsItem start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        sleep 2
                        $initdpath/$nfsItem status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        ;;
                    2)
                        sysv-rc-conf $nfsItem on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        $initdpath/nfs-kernel-server stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        sleep 2
                        $initdpath/nfs-kernel-server start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        sleep 2
                        ;;
                esac
            fi
            [[ $? -eq 0 ]] && break
        done
        errorStat $?
    fi
}
configureSnapins() {
    dots "Setting up FOG Snapins"
    mkdir -p $snapindir >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    if [[ -d $snapindir ]]; then
        chmod -R 775 $snapindir
        chown -R fog:$apacheuser $snapindir
    fi
    errorStat $?
}
configureUsers() {
    userexists=0
    [[ -z $username ]] && username='fog'
    dots "Setting up $username user"
    getent passwd $username > /dev/null
    if [[ $? -eq 0 ]]; then
        echo "Already setup"
        userexists=1
    fi
    if [[ $userexists -eq 0 ]]; then
        useradd -s "/bin/bash" -d "/home/${username}" $username >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        errorStat $?
        mkdir -p /home/$username >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        chown -R $username /home/$username >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    dots "Setting up $username password"
    if [[ -z $password ]]; then
        password=$(openssl rand -base64 32)
        [[ -f $webdirdest/lib/fog/config.class.php ]] && password="$(awk -F'[(")]' '/TFTP_FTP_PASSWORD/ {print $3}' $webdirdest/lib/fog/config.class.php)"
        if [[ -z $password ]]; then
            false
            errorStat $?
        fi
    fi
    echo -e "$password\n$password" | passwd $username >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    if [[ ! $? -eq 0 ]]; then
        false
        errorStat $?
    fi
    errorStat $?
}
linkOptFogDir() {
    if [[ ! -h /var/log/fog ]]; then
        dots "Linking FOG Logs to Linux Logs"
        ln -s /opt/fog/log /var/log/fog >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        errorStat $?
    fi
    if [[ ! -h /etc/fog ]]; then
        dots "Linking FOG Service config /etc"
        ln -s /opt/fog/service/etc /etc/fog >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        errorStat $?
    fi
    local element='httpd'
    [[ $osid -eq 2 ]] && element='apache2'
    chmod -R 755 /var/log/$element >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    chmod -R 755 /var/log/php*fpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
}
configureStorage() {
    dots "Setting up storage"
    if [[ ! -d $storageLocation ]]; then
        mkdir $storageLocation >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        chmod -R 777 $storageLocation >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    if [[ ! -f $storageLocation/.mntcheck ]]; then
        touch $storageLocation/.mntcheck >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        chmod 777 $storageLocation/.mntcheck >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    if [[ ! -d $storageLocation/postdownloadscripts ]]; then
        mkdir $storageLocation/postdownloadscripts >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ ! -f $storageLocation/postdownloadscripts/fog.postdownload ]]; then
            echo -e "#!/bin/sh\n## This file serves as a starting point to call your custom postimaging scripts.\n## <SCRIPTNAME> should be changed to the script you're planning to use.\n## Syntax of post download scripts are\n#. \${postdownpath}<SCRIPTNAME>" > "$storageLocation/postdownloadscripts/fog.postdownload"
        fi
        chmod -R 777 $storageLocation >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    if [[ ! -d $storageLocationCapture ]]; then
        mkdir $storageLocationCapture >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        chmod -R 777 $storageLocationCapture >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    if [[ ! -f $storageLocationCapture/.mntcheck ]]; then
        touch $storageLocationCapture/.mntcheck >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        chmod 777 $storageLocationCapture/.mntcheck >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    errorStat $?
}
clearScreen() {
    clear
}
writeUpdateFile() {
    tmpDte=$(date +%c)
    replace='s/[]\/$*.^|[]/\\&/g';
    escversion=$(echo $version | sed -e $replace)
    esctmpDte=$(echo $tmpDate | sed -e $replace)
    escipaddress=$(echo $ipaddress | sed -e $replace)
    escinterface=$(echo $interface | sed -e $replace)
    escsubmask=$(echo $submask | sed -e $replace)
    escrouteraddress=$(echo $routeraddress | sed -e $replace)
    escplainrouter=$(echo $plainrouter | sed -e $replace)
    escdnsaddress=$(echo $dnsaddress | sed -e $replace)
    escpassword=$(echo $password | sed -e $replace -e "s/[']{1}/'''/g")
    escosid=$(echo $osid | sed -e $replace)
    escosname=$(echo $osname | sed -e $replace)
    escdodhcp=$(echo $dodhcp | sed -e $replace)
    escbldhcp=$(echo $bldhcp | sed -e $replace)
    escdhcpd=$(echo $dhcpd | sed -e $replace)
    escblexports=$(echo $blexports | sed -e $replace)
    escinstalltype=$(echo $installtype | sed -e $replace)
    escsnmysqluser=$(echo $snmysqluser | sed -e $replace)
    escsnmysqlpass=$(echo $snmysqlpass | sed -e $replace -e "s/[']{1}/'''/g")
    escsnmysqlhost=$(echo $snmysqlhost | sed -e $replace)
    escinstalllange=$(echo $installlang | sed -e $replace)
    escdonate=$(echo $donate | sed -e $replace)
    escstorageLocation=$(echo $storageLocation | sed -e $replace)
    escfogupdateloaded=$(echo $fogupdateloaded | sed -e $replace)
    escusername=$(echo $username | sed -e $replace)
    escdocroot=$(echo $docroot | sed -e $replace)
    escwebroot=$(echo $webroot | sed -e $replace)
    esccaCreated=$(echo $caCreated | sed -e $replace)
    escstartrange=$(echo $startrange | sed -e $replace)
    escendrange=$(echo $endrange | sed -e $replace)
    escbootfilename=$(echo $bootfilename | sed -e $replace)
    escpackages=$(echo "$packages" | sed -e $replace)
    escnoTftpBuild=$(echo $noTftpBuild | sed -e $replace)
    escnotpxedefaultfile=$(echo $notpxedefaultfile | sed -e $replace)
    escsslpath=$(echo $sslpath | sed -e $replace)
    escbackupPath=$(echo $backupPath | sed -e $replace)
    escphp_ver=$(echo $php_ver | sed -e $replace)
    escphp_verAdds=$(echo $php_verAdds | sed -e $replace)
    if [[ -f $fogprogramdir/.fogsettings ]]; then
        grep -q "^## Start of FOG Settings" $fogprogramdir/.fogsettings || grep -q "^## Version:.*" $fogprogramdir/.fogsettings
        if [[ $? == 0 ]]; then
            grep -q "^## Version:.*$" $fogprogramdir/.fogsettings && \
                sed -i "s/^## Version:.*/## Version: $escversion/g" $fogprogramdir/.fogsettings || \
                echo "## Version: $version" >> $fogprogramdir/.fogsettings
            grep -q "ipaddress=" $fogprogramdir/.fogsettings && \
                sed -i "s/ipaddress=.*/ipaddress='$escipaddress'/g" $fogprogramdir/.fogsettings || \
                echo "ipaddress='$ipaddress'" >> $fogprogramdir/.fogsettings
            grep -q "interface=" $fogprogramdir/.fogsettings && \
                sed -i "s/interface=.*/interface='$escinterface'/g" $fogprogramdir/.fogsettings || \
                echo "interface='$interface'" >> $fogprogramdir/.fogsettings
            grep -q "submask=" $fogprogramdir/.fogsettings && \
                sed -i "s/submask=.*/submask='$escsubmask'/g" $fogprogramdir/.fogsettings || \
                echo "submask='$submask'" >> $fogprogramdir/.fogsettings
            grep -q "routeraddress=" $fogprogramdir/.fogsettings && \
                sed -i "s/routeraddress=.*/routeraddress='$escrouteraddress'/g" $fogprogramdir/.fogsettings || \
                echo "routeraddress='$routeraddress'" >> $fogprogramdir/.fogsettings
            grep -q "plainrouter=" $fogprogramdir/.fogsettings && \
                sed -i "s/plainrouter=.*/plainrouter='$escplainrouter'/g" $fogprogramdir/.fogsettings || \
                echo "plainrouter='$plainrouter'" >> $fogprogramdir/.fogsettings
            grep -q "dnsaddress=" $fogprogramdir/.fogsettings && \
                sed -i "s/dnsaddress=.*/dnsaddress='$escdnsaddress'/g" $fogprogramdir/.fogsettings || \
                echo "dnsaddress='$dnsaddress'" >> $fogprogramdir/.fogsettings
            grep -q "password=" $fogprogramdir/.fogsettings && \
                sed -i "s/password=.*/password=\"$escpassword\"/g" $fogprogramdir/.fogsettings || \
                echo "password=\"$escpassword\"" >> $fogprogramdir/.fogsettings
            grep -q "osid=" $fogprogramdir/.fogsettings && \
                sed -i "s/osid=.*/osid='$osid'/g" $fogprogramdir/.fogsettings || \
                echo "osid='$osid'" >> $fogprogramdir/.fogsettings
            grep -q "osname=" $fogprogramdir/.fogsettings && \
                sed -i "s/osname=.*/osname='$escosname'/g" $fogprogramdir/.fogsettings || \
                echo "osname='$osname'" >> $fogprogramdir/.fogsettings
            grep -q "dodhcp=" $fogprogramdir/.fogsettings && \
                sed -i "s/dodhcp=.*/dodhcp='$escdodhcp'/g" $fogprogramdir/.fogsettings || \
                echo "dodhcp='$dodhcp'" >> $fogprogramdir/.fogsettings
            grep -q "bldhcp=" $fogprogramdir/.fogsettings && \
                sed -i "s/bldhcp=.*/bldhcp='$escbldhcp'/g" $fogprogramdir/.fogsettings || \
                echo "bldhcp='$bldhcp'" >> $fogprogramdir/.fogsettings
            grep -q "dhcpd=" $fogprogramdir/.fogsettings && \
                sed -i "s/dhcpd=.*/dhcpd='$escdhcpd'/g" $fogprogramdir/.fogsettings || \
                echo "dhcpd='$dhcpd'" >> $fogprogramdir/.fogsettings
            grep -q "blexports=" $fogprogramdir/.fogsettings && \
                sed -i "s/blexports=.*/blexports='$escblexports'/g" $fogprogramdir/.fogsettings || \
                echo "blexports='$blexports'" >> $fogprogramdir/.fogsettings
            grep -q "installtype=" $fogprogramdir/.fogsettings && \
                sed -i "s/installtype=.*/installtype='$escinstalltype'/g" $fogprogramdir/.fogsettings || \
                echo "installtype='$installtype'" >> $fogprogramdir/.fogsettings
            grep -q "snmysqluser=" $fogprogramdir/.fogsettings && \
                sed -i "s/snmysqluser=.*/snmysqluser='$escsnmysqluser'/g" $fogprogramdir/.fogsettings || \
                echo "snmysqluser='$snmysqluser'" >> $fogprogramdir/.fogsettings
            grep -q "snmysqlpass=" $fogprogramdir/.fogsettings && \
                sed -i "s/snmysqlpass=.*/snmysqlpass=\"$escsnmysqlpass\"/g" $fogprogramdir/.fogsettings || \
                echo "snmysqlpass=\"$escsnmysqlpass\"" >> $fogprogramdir/.fogsettings
            grep -q "snmysqlhost=" $fogprogramdir/.fogsettings && \
                sed -i "s/snmysqlhost=.*/snmysqlhost='$escsnmysqlhost'/g" $fogprogramdir/.fogsettings || \
                echo "snmysqlhost='$snmysqlhost'" >> $fogprogramdir/.fogsettings
            grep -q "installlang=" $fogprogramdir/.fogsettings && \
                sed -i "s/installlang=.*/installlang='$escinstalllang'/g" $fogprogramdir/.fogsettings || \
                echo "installlang='$installlang'" >> $fogprogramdir/.fogsettings
            grep -q "donate=" $fogprogramdir/.fogsettings && \
                sed -i "s/donate=.*/donate='$escdonate'/g" $fogprogramdir/.fogsettings || \
                echo "donate='$donate'" >> $fogprogramdir/.fogsettings
            grep -q "storageLocation=" $fogprogramdir/.fogsettings && \
                sed -i "s/storageLocation=.*/storageLocation='$escstorageLocation'/g" $fogprogramdir/.fogsettings || \
                echo "storageLocation='$storageLocation'" >> $fogprogramdir/.fogsettings
            grep -q "fogupdateloaded=" $fogprogramdir/.fogsettings && \
                sed -i "s/fogupdateloaded=.*/fogupdateloaded=$escfogupdateloaded/g" $fogprogramdir/.fogsettings || \
                echo "fogupdateloaded=$fogupdateloaded" >> $fogprogramdir/.fogsettings
            grep -q "storageftpuser=" $fogprogramdir/.fogsettings && \
                sed -i "/storageftpuser=/d" $fogprogramdir/.fogsettings
            grep -q "storageftppass=" $fogprogramdir/.fogsettings && \
                sed -i "/storageftppass=/d" $fogprogramdir/.fogsettings
            grep -q "username=" $fogprogramdir/.fogsettings && \
                sed -i "s/username=.*/username='$escusername'/g" $fogprogramdir/.fogsettings || \
                echo "username='$username'" >> $fogprogramdir/.fogsettings
            grep -q "docroot=" $fogprogramdir/.fogsettings && \
                sed -i "s/docroot=.*/docroot='$escdocroot'/g" $fogprogramdir/.fogsettings || \
                echo "docroot='$docroot'" >> $fogprogramdir/.fogsettings
            grep -q "webroot=" $fogprogramdir/.fogsettings && \
                sed -i "s/webroot=.*/webroot='$escwebroot'/g" $fogprogramdir/.fogsettings || \
                echo "webroot='$webroot'" >> $fogprogramdir/.fogsettings
            grep -q "caCreated=" $fogprogramdir/.fogsettings && \
                sed -i "s/caCreated=.*/caCreated='$esccaCreated'/g" $fogprogramdir/.fogsettings || \
                echo "caCreated='$caCreated'" >> $fogprogramdir/.fogsettings
            grep -q "startrange=" $fogprogramdir/.fogsettings && \
                sed -i "s/startrange=.*/startrange='$escstartrange'/g" $fogprogramdir/.fogsettings || \
                echo "startrange='$startrange'" >> $fogprogramdir/.fogsettings
            grep -q "endrange=" $fogprogramdir/.fogsettings && \
                sed -i "s/endrange=.*/endrange='$escendrange'/g" $fogprogramdir/.fogsettings || \
                echo "endrange='$endrange'" >> $fogprogramdir/.fogsettings
            grep -q "bootfilename=" $fogprogramdir/.fogsettings && \
                sed -i "s/bootfilename=.*/bootfilename='$escbootfilename'/g" $fogprogramdir/.fogsettings || \
                echo "bootfilename='$bootfilename'" >> $fogprogramdir/.fogsettings
            grep -q "packages=" $fogprogramdir/.fogsettings && \
                sed -i "s/packages=.*/packages='$escpackages'/g" $fogprogramdir/.fogsettings || \
                echo "packages='$packages'" >> $fogprogramdir/.fogsettings
            grep -q "noTftpBuild=" $fogprogramdir/.fogsettings && \
                sed -i "s/noTftpBuild=.*/noTftpBuild='$escnoTftpBuild'/g" $fogprogramdir/.fogsettings || \
                echo "noTftpBuild='$noTftpBuild'" >> $fogprogramdir/.fogsettings
            grep -q "notpxedefaultfile=" $fogprogramdir/.fogsettings && \
                sed -i "s/notpxedefaultfile=.*/notpxedefaultfile='$notpxedefaultfile'/g" $fogprogramdir/.fogsettings || \
                echo "notpxedefaultfile='$escnotpxedefaultfile'" >> $fogprogramdir/.fogsettings
            grep -q "sslpath=" $fogprogramdir/.fogsettings && \
                sed -i "s/sslpath=.*/sslpath='$escsslpath'/g" $fogprogramdir/.fogsettings || \
                echo "sslpath='$sslpath'" >> $fogprogramdir/.fogsettings
            grep -q "backupPath=" $fogprogramdir/.fogsettings && \
                sed -i "s/backupPath=.*/backupPath='$esbackupPath'/g" $fogprogramdir/.fogsettings || \
                echo "backupPath='$backupPath'" >> $fogprogramdir/.fogsettings
            grep -q "php_ver=" $fogprogramdir/.fogsettings && \
                sed -i "s/php_ver=.*/php_ver='$php_ver'/g" $fogprogramdir/.fogsettings || \
                echo "php_ver='$php_ver'" >> $fogprogramdir/.fogsettings
            grep -q "php_verAdds=" $fogprogramdir/.fogsettings && \
                sed -i "s/php_verAdds=.*/php_verAdds='$php_verAdds'/g" $fogprogramdir/.fogsettings || \
                echo "php_verAdds='$php_verAdds'" >> $fogprogramdir/.fogsettings
        else
            echo "## Start of FOG Settings" > "$fogprogramdir/.fogsettings"
            echo "## Created by the FOG Installer" >> "$fogprogramdir/.fogsettings"
            echo "## Version: $version" >> "$fogprogramdir/.fogsettings"
            echo "## Install time: $tmpDte" >> "$fogprogramdir/.fogsettings"
            echo "ipaddress='$ipaddress'" >> "$fogprogramdir/.fogsettings"
            echo "interface='$interface'" >> "$fogprogramdir/.fogsettings"
            echo "submask='$submask'" >> "$fogprogramdir/.fogsettings"
            echo "routeraddress='$routeraddress'" >> "$fogprogramdir/.fogsettings"
            echo "plainrouter='$plainrouter'" >> "$fogprogramdir/.fogsettings"
            echo "dnsaddress='$dnsaddress'" >> "$fogprogramdir/.fogsettings"
            echo "username='$username'" >> "$fogprogramdir/.fogsettings"
            echo "password='$password'" >> "$fogprogramdir/.fogsettings"
            echo "osid='$osid'" >> "$fogprogramdir/.fogsettings"
            echo "osname='$osname'" >> "$fogprogramdir/.fogsettings"
            echo "dodhcp='$dodhcp'" >> "$fogprogramdir/.fogsettings"
            echo "bldhcp='$bldhcp'" >> "$fogprogramdir/.fogsettings"
            echo "dhcpd='$dhcpd'" >> "$fogprogramdir/.fogsettings"
            echo "blexports='$blexports'" >> "$fogprogramdir/.fogsettings"
            echo "installtype='$installtype'" >> "$fogprogramdir/.fogsettings"
            echo "snmysqluser='$snmysqluser'" >> "$fogprogramdir/.fogsettings"
            echo "snmysqlpass='$snmysqlpass'" >> "$fogprogramdir/.fogsettings"
            echo "snmysqlhost='$snmysqlhost'" >> "$fogprogramdir/.fogsettings"
            echo "installlang='$installlang'" >> "$fogprogramdir/.fogsettings"
            echo "donate='$donate'" >> "$fogprogramdir/.fogsettings"
            echo "storageLocation='$storageLocation'" >> "$fogprogramdir/.fogsettings"
            echo "fogupdateloaded=1" >> "$fogprogramdir/.fogsettings"
            echo "docroot='$docroot'" >> "$fogprogramdir/.fogsettings"
            echo "webroot='$webroot'" >> "$fogprogramdir/.fogsettings"
            echo "caCreated='$caCreated'" >> "$fogprogramdir/.fogsettings"
            echo "startrange='$startrange'" >> "$fogprogramdir/.fogsettings"
            echo "endrange='$endrange'" >> "$fogprogramdir/.fogsettings"
            echo "bootfilename='$bootfilename'" >> "$fogprogramdir/.fogsettings"
            echo "packages='$packages'" >> "$fogprogramdir/.fogsettings"
            echo "noTftpBuild='$noTftpBuild'" >> "$fogprogramdir/.fogsettings"
            echo "notpxedefaultfile='$notpxedefaultfile'" >> "$fogprogramdir/.fogsettings"
            echo "sslpath='$sslpath'" >> "$fogprogramdir/.fogsettings"
            echo "backupPath='$backupPath'" >> "$fogprogramdir/.fogsettings"
            echo "php_ver='$php_ver'" >> "$fogprogramdir/.fogsettings"
            echo "php_verAdds='$php_verAdds'" >> "$fogprogramdir/.fogsettings"
            echo "## End of FOG Settings" >> "$fogprogramdir/.fogsettings"
        fi
    else
        echo "## Start of FOG Settings" > "$fogprogramdir/.fogsettings"
        echo "## Created by the FOG Installer" >> "$fogprogramdir/.fogsettings"
        echo "## Version: $version" >> "$fogprogramdir/.fogsettings"
        echo "## Install time: $tmpDte" >> "$fogprogramdir/.fogsettings"
        echo "ipaddress='$ipaddress'" >> "$fogprogramdir/.fogsettings"
        echo "interface='$interface'" >> "$fogprogramdir/.fogsettings"
        echo "submask='$submask'" >> "$fogprogramdir/.fogsettings"
        echo "routeraddress='$routeraddress'" >> "$fogprogramdir/.fogsettings"
        echo "plainrouter='$plainrouter'" >> "$fogprogramdir/.fogsettings"
        echo "dnsaddress='$dnsaddress'" >> "$fogprogramdir/.fogsettings"
        echo "username='$username'" >> "$fogprogramdir/.fogsettings"
        echo "password='$password'" >> "$fogprogramdir/.fogsettings"
        echo "osid='$osid'" >> "$fogprogramdir/.fogsettings"
        echo "osname='$osname'" >> "$fogprogramdir/.fogsettings"
        echo "dodhcp='$dodhcp'" >> "$fogprogramdir/.fogsettings"
        echo "bldhcp='$bldhcp'" >> "$fogprogramdir/.fogsettings"
        echo "dhcpd='$dhcpd'" >> "$fogprogramdir/.fogsettings"
        echo "blexports='$blexports'" >> "$fogprogramdir/.fogsettings"
        echo "installtype='$installtype'" >> "$fogprogramdir/.fogsettings"
        echo "snmysqluser='$snmysqluser'" >> "$fogprogramdir/.fogsettings"
        echo "snmysqlpass='$snmysqlpass'" >> "$fogprogramdir/.fogsettings"
        echo "snmysqlhost='$snmysqlhost'" >> "$fogprogramdir/.fogsettings"
        echo "installlang='$installlang'" >> "$fogprogramdir/.fogsettings"
        echo "donate='$donate'" >> "$fogprogramdir/.fogsettings"
        echo "storageLocation='$storageLocation'" >> "$fogprogramdir/.fogsettings"
        echo "fogupdateloaded=1" >> "$fogprogramdir/.fogsettings"
        echo "docroot='$docroot'" >> "$fogprogramdir/.fogsettings"
        echo "webroot='$webroot'" >> "$fogprogramdir/.fogsettings"
        echo "caCreated='$caCreated'" >> "$fogprogramdir/.fogsettings"
        echo "startrange='$startrange'" >> "$fogprogramdir/.fogsettings"
        echo "endrange='$endrange'" >> "$fogprogramdir/.fogsettings"
        echo "bootfilename='$bootfilename'" >> "$fogprogramdir/.fogsettings"
        echo "packages='$packages'" >> "$fogprogramdir/.fogsettings"
        echo "noTftpBuild='$noTftpBuild'" >> "$fogprogramdir/.fogsettings"
        echo "notpxedefaultfile='$notpxedefaultfile'" >> "$fogprogramdir/.fogsettings"
        echo "sslpath='$sslpath'" >> "$fogprogramdir/.fogsettings"
        echo "backupPath='$backupPath'" >> "$fogprogramdir/.fogsettings"
        echo "php_ver='$php_ver'" >> "$fogprogramdir/.fogsettings"
        echo "php_verAdds='$php_verAdds'" >> "$fogprogramdir/.fogsettings"
        echo "## End of FOG Settings" >> "$fogprogramdir/.fogsettings"
    fi
}
displayBanner() {
    echo
    echo
    echo "   +------------------------------------------+"
    echo "   |     ..#######:.    ..,#,..     .::##::.  |"
    echo "   |.:######          .:;####:......;#;..     |"
    echo "   |...##...        ...##;,;##::::.##...      |"
    echo "   |   ,#          ...##.....##:::##     ..:: |"
    echo "   |   ##    .::###,,##.   . ##.::#.:######::.|"
    echo "   |...##:::###::....#. ..  .#...#. #...#:::. |"
    echo "   |..:####:..    ..##......##::##  ..  #     |"
    echo "   |    #  .      ...##:,;##;:::#: ... ##..   |"
    echo "   |   .#  .       .:;####;::::.##:::;#:..    |"
    echo "   |    #                     ..:;###..       |"
    echo "   |                                          |"
    echo "   +------------------------------------------+"
    echo "   |      Free Computer Imaging Solution      |"
    echo "   +------------------------------------------+"
    echo "   |  Credits: http://fogproject.org/Credits  |"
    echo "   |       http://fogproject.org/Credits      |"
    echo "   |       Released under GPL Version 3       |"
    echo "   +------------------------------------------+"
    echo
    echo
}
createSSLCA() {
    if [[ -z $sslpath ]]; then
        [[ -d /opt/fog/snapins/CA && -d /opt/fog/snapins/ssl ]] && mv /opt/fog/snapins/CA /opt/fog/snapins/ssl/
        sslpath='/opt/fog/snapins/ssl/'
    fi
    if [[ $recreateCA == yes || $caCreated != yes || ! -e $sslpath/CA || ! -e $sslpath/CA/.fogCA.key ]]; then
        mkdir -p $sslpath/CA >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        dots "Creating SSL CA"
        openssl genrsa -out $sslpath/CA/.fogCA.key 4096 >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        openssl req -x509 -new -sha512 -nodes -key $sslpath/CA/.fogCA.key -days 3650 -out $sslpath/CA/.fogCA.pem >>$workingdir/error_logs/fog_error_${version}.log 2>&1 << EOF
.
.
.
.
.
FOG Server CA
.
EOF
        errorStat $?
    fi
    if [[ $recreateKeys == yes || $recreateCA == yes || $caCreated != yes || ! -e $sslpath || ! -e $sslpath/.srvprivate.key ]]; then
        dots "Creating SSL Private Key"
        mkdir -p $sslpath >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        openssl genrsa -out $sslpath/.srvprivate.key 4096 >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        openssl req -new -sha512 -key $sslpath/.srvprivate.key -out $sslpath/fog.csr >>$workingdir/error_logs/fog_error_${version}.log 2>&1 << EOF
.
.
.
.
.
$ipaddress
.


EOF
        errorStat $?
    fi
    dots "Creating SSL Certificate"
    mkdir -p $webdirdest/management/other/ssl >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    openssl x509 -req -in $sslpath/fog.csr -CA $sslpath/CA/.fogCA.pem -CAkey $sslpath/CA/.fogCA.key -CAcreateserial -out $webdirdest/management/other/ssl/srvpublic.crt -days 3650 >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    dots "Creating auth pub key and cert"
    cp $sslpath/CA/.fogCA.pem $webdirdest/management/other/ca.cert.pem >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    openssl x509 -outform der -in $webdirdest/management/other/ca.cert.pem -out $webdirdest/management/other/ca.cert.der >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    dots "Resetting SSL Permissions"
    chown -R $apacheuser:$apacheuser $webdirdest/management/other >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    dots "Setting up SSL FOG Server"
    if [[ $recreateCA == yes || $recreateKeys == yes || ! -f $etcconf ]]; then
        [[ $forcehttps == yes ]] && forcehttps='' || forcehttps='#'
        echo -e "<VirtualHost *:80>\n\tKeepAlive Off\n\tServerName $ipaddress\n\tDocumentRoot $docroot\n\t${forcehttps}RewriteEngine On\n\t${forcehttps}RewriteRule /management/other/ca.cert.der$ - [L]\n\t${forcehttps}RewriteRule /management/ https://%{HTTP_HOST}%{REQUEST_URI}%{QUERY_STRING} [R,L]\n</VirtualHost>\n<VirtualHost *:443>\n\tKeepAlive Off\n\tServername $ipaddress\n\tDocumentRoot $docroot\n\tSSLEngine On\n\tSSLProtocol all -SSLv3 -SSLv2\n\tSSLCipherSuite ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:AES:CAMELLIA:DES-CBC3-SHA:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!aECDH:!EDH-DSS-DES-CBC3-SHA:!EDH-RSA-DES-CBC3-SHA:!KRB5-DES-CBC3-SHA\n\tSSLHonorCipherOrder on\n\tSSLCertificateFile $webdirdest/management/other/ssl/srvpublic.crt\n\tSSLCertificateKeyFile $sslpath/.srvprivate.key\n\tSSLCertificateChainFile $webdirdest/management/other/ca.cert.der\n</VirtualHost>" > "$etcconf"
        errorStat $?
        dots "Restarting Apache2 for fog vhost"
        ln -s $webdirdest $webdirdest/ >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ $osid -eq 2 ]]; then
            a2enmod $phpcmd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            a2enmod rewrite >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            a2enmod ssl >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            a2ensite "001-fog" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
    else
        echo "Done"
    fi
    case $systemctl in
        yes)
            case $osid in
                2)
                    systemctl stop apache2 $phpfpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    systemctl start apache2 $phpfpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    systemctl status apache2 $phpfpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
                *)
                    systemctl stop httpd php-fpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    systemctl start httpd php-fpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    systemctl status httpd php-fpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
            ;;
        *)
            case $osid in
                2)
                    service apache2 stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service apache2 start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service $phpfpm stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service $phpfpm start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service apache2 status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    service $phpfpm status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
                *)
                    service httpd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service httpd start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service php-fpm stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service php-fpm start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    service httpd status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    service php-fpm status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
            ;;
    esac
    errorStat $?
    caCreated="yes"
}
configureHttpd() {
    dots "Stopping web service"
    case $systemctl in
        yes)
            case $osid in
                1)
                    systemctl stop httpd php-fpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
                    ;;
                2)
                    systemctl stop apache2 php${php_ver}-fpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
                    ;;
            esac
            errorStat $?
            ;;
        *)
            case $osid in
                1)
                    service httpd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
                    service php-fpm stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
                    errorStat $?
                    ;;
                2)
                    service apache2 stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
                    errorStat $?
                    service php${php_ver}-fpm stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
            ;;
    esac
    if [[ -f $etcconf ]]; then
        dots "Removing vhost file"
        [[ $osid -eq 2 ]] && a2dissite 001-fog >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        rm $etcconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        errorStat $?
    fi
    if [[ $installtype == N && ! $fogupdateloaded -eq 1 && -z $autoaccept ]]; then
        dummy=""
        while [[ -z $dummy ]]; do
            echo -n " * Is the MySQL password blank? (Y/n) "
            read dummy
            case $dummy in
                [Yy]|[Yy][Ee][Ss]|"")
                    dummy='Y'
                    ;;
                [Nn]|[Nn][Oo])
                    echo -n " * Enter the MySQL password: "
                    read -s PASSWORD1
                    echo
                    echo -n " * Re-enter the MySQL password: "
                    read -s PASSWORD2
                    echo
                    if [[ ! -z $PASSWORD1 && $PASSWORD2 == $PASSWORD1 ]]; then
                        dbpass=$PASSWORD1
                    else
                        dppass=""
                        while [[ ! -z $PASSWORD1 && $PASSWORD2 == $PASSWORD1 ]]; do
                            echo -n " * Enter the MySQL password: "
                            read -s PASSWORD1
                            echo
                            echo -n " * Re-enter the MySQL password: "
                            read -s PASSWORD2
                            echo
                            [[ ! -z $PASSWORD1 && $PASSWORD2 == $PASSWORD1 ]] && dbpass=$PASSWORD1
                        done
                    fi
                    [[ $snmysqlpass != $dbpass ]] && snmysqlpass=$dbpass
                    ;;
                *)
                    dummy=""
                    echo " * Invalid input, please try again!"
                    ;;
            esac
        done
    fi
    dots "Setting up Apache and PHP files"
    if [[ ! -f $phpini ]]; then
        echo "Failed"
        echo "   ###########################################"
        echo "   #                                         #"
        echo "   #      PHP Failed to install properly     #"
        echo "   #                                         #"
        echo "   ###########################################"
        echo
        echo "   Could not find $phpini!"
        exit 1
    fi
    if [[ $osid -eq 3 ]]; then
        if [[ ! -f /etc/httpd/conf/httpd.conf ]]; then
            echo "   Apache configs not found!"
            exit 1
        fi
        echo -e "<FilesMatch \.php$>\n\tSetHandler \"proxy:unix:/run/php-fpm/php-fpm.sock|fcgi://127.0.0.1/\"\n</FilesMatch>\n<IfModule dir_module>\n\tDirectoryIndex index.php index.html\n</IfModule>" >> /etc/httpd/conf/httpd.conf
        sed -i 's@#LoadModule ssl_module modules/mod_ssl.so@LoadModule ssl_module modules/mod_ssl.so@g' /etc/httpd/conf/httpd.conf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's@#LoadModule socache_shmcb_module modules/mod_socache_shmcb.so@LoadModule socache_shmcb_module modules/mod_socache_shmcb.so@g' /etc/httpd/conf/httpd.conf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        echo -e "# FOG Virtual Host\nInclude conf/extra/fog.conf" >> /etc/httpd/conf/httpd.conf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/;extension=mysqli.so/extension=mysqli.so/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/;extension=openssl.so/extension=openssl.so/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/;extension=mcrypt.so/extension=mcrypt.so/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/;extension=posix.so/extension=posix.so/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/;extension=sockets.so/extension=sockets.so/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/;extension=ftp.so/extension=ftp.so/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/open_basedir\ =/;open_basedir\ ="/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    sed -i 's/post_max_size\ \=\ 8M/post_max_size\ \=\ 3000M/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    sed -i 's/upload_max_filesize\ \=\ 2M/upload_max_filesize\ \=\ 3000M/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    sed -i 's/.*max_input_vars\ \=.*$/max_input_vars\ \=\ 250000/g' $phpini >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    dots "Testing and removing symbolic links if found"
    if [[ -h ${docroot}fog ]]; then
        rm -f ${docroot}fog >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    if [[ -h ${docroot}${webroot} ]]; then
        rm -f ${docroot}${webroot} >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    errorStat $?
    dots "Backing up old data"
    if [[ -d $backupPath/fog_web_${version}.BACKUP ]]; then
        rm -rf $backupPath/fog_web_${version}.BACKUP >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    if [[ -d $webdirdest ]]; then
        cp -RT "$webdirdest" "${backupPath}/fog_web_${version}.BACKUP" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        rm -rf ${backupPath}/fog_web_${version}.BACKUP/lib/plugins/accesscontrol
        rm -rf "$webdirdest" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    if [[ $osid -eq 2 ]]; then
        if [[ -d ${docroot}fog ]]; then
            rm -rf ${docroot} >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
    fi
    mkdir -p "$webdirdest" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    if [[ -d $docroot && ! -h ${docroot}fog ]] || [[ ! -d ${docroot}fog ]]; then
        ln -s $webdirdest  ${docroot}/fog >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    errorStat $?
    if [[ -d ${backupPath}/fog_web_${version}.BACKUP ]]; then
        dots "Copying back old web folder as is";
        cp -Rf ${backupPath}/fog_web_${version}.BACKUP/* $webdirdest/
        errorStat $?
        dots "Ensuring all classes are lowercased"
        for i in $(find $webdirdest -type f -name "*[A-Z]*\.class\.php"); do
            mv "$i" "$(echo $i | tr A-Z a-z)" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        done
        for i in $(find $webdirdest -type f -name "*[A-Z]*\.event\.php"); do
            mv "$i" "$(echo $i | tr A-Z a-z)" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        done
        for i in $(find $webdirdest -type f -name "*[A-Z]*\.hook\.php"); do
            mv "$i" "$(echo $i | tr A-Z a-z)" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        done
        errorStat $?
    fi
    dots "Copying new files to web folder"
    cp -Rf $webdirsrc/* $webdirdest/
    errorStat $?
    if [[ $installlang -eq 1 ]]; then
        dots "Creating the language binaries"
        msgfmt -o $webdirdest/management/languages/de_DE.UTF-8/LC_MESSAGES/messages.mo $webdirdest/management/languages/de_DE.UTF-8/LC_MESSAGES/messages.po
        msgfmt -o $webdirdest/management/languages/en_US.UTF-8/LC_MESSAGES/messages.mo $webdirdest/management/languages/en_US.UTF-8/LC_MESSAGES/messages.po
        msgfmt -o $webdirdest/management/languages/es_ES.UTF-8/LC_MESSAGES/messages.mo $webdirdest/management/languages/es_ES.UTF-8/LC_MESSAGES/messages.po
        msgfmt -o $webdirdest/management/languages/fr_FR.UTF-8/LC_MESSAGES/messages.mo $webdirdest/management/languages/fr_FR.UTF-8/LC_MESSAGES/messages.po
        msgfmt -o $webdirdest/management/languages/it_IT.UTF-8/LC_MESSAGES/messages.mo $webdirdest/management/languages/it_IT.UTF-8/LC_MESSAGES/messages.po
        msgfmt -o $webdirdest/management/languages/pt_BR.UTF-8/LC_MESSAGES/messages.mo $webdirdest/management/languages/pt_BR.UTF-8/LC_MESSAGES/messages.po
        msgfmt -o $webdirdest/management/languages/zh_CN.UTF-8/LC_MESSAGES/messages.mo $webdirdest/management/languages/zh_CN.UTF-8/LC_MESSAGES/messages.po
        echo "Done"
    fi
    dots "Creating config file"
    [[ -z $snmysqlhost ]] && snmysqlhost='127.0.0.1'
    [[ -z $snmysqluser ]] && snmysqluser='root'
    echo "<?php
class Config {
    /** @function __construct() Calls the required functions to define items
     * @return void
     */
    public function __construct() {
        self::db_settings();
        self::svc_setting();
        if (\$_REQUEST['node'] == 'schema') self::init_setting();
    }
    /** @function db_settings() Defines the database settings for FOG
     * @return void
     */
    private static function db_settings() {
        define('DATABASE_TYPE','mysql'); // mysql or oracle
        define('DATABASE_HOST','$snmysqlhost');
        define('DATABASE_NAME','fog');
        define('DATABASE_USERNAME','$snmysqluser');
        define('DATABASE_PASSWORD',\"$snmysqlpass\");
    }
    /** @function svc_setting() Defines the service settings
     * (e.g. FOGMulticastManager)
     * @return void
     */
    private static function svc_setting() {
        define('UDPSENDERPATH','/usr/local/sbin/udp-sender');
        define('MULTICASTINTERFACE','${interface}');
        define('UDPSENDER_MAXWAIT',null);
    }
    /** @function init_setting() Initial values if fresh install are set here
     * NOTE: These values are only used on initial
     * installation to set the database values.
     * If this is an upgrade, they do not change
     * the values within the Database.
     * Please use FOG Configuration->FOG Settings
     * to change these values after everything is
     * setup.
     * @return void
     */
    private static function init_setting() {
        define('TFTP_HOST', \"${ipaddress}\");
        define('TFTP_FTP_USERNAME', \"${username}\");
        define('TFTP_FTP_PASSWORD', \"${password}\");
        define('TFTP_PXE_KERNEL_DIR', \"${webdirdest}/service/ipxe/\");
        define('PXE_KERNEL', 'bzImage');
        define('PXE_KERNEL_RAMDISK',127000);
        define('USE_SLOPPY_NAME_LOOKUPS',true);
        define('MEMTEST_KERNEL', 'memtest.bin');
        define('PXE_IMAGE', 'init.xz');
        define('STORAGE_HOST', \"${ipaddress}\");
        define('STORAGE_FTP_USERNAME', \"${username}\");
        define('STORAGE_FTP_PASSWORD', \"${password}\");
        define('STORAGE_DATADIR', '${storageLocation}/');
        define('STORAGE_DATADIR_CAPTURE', '${storageLocationCapture}');
        define('STORAGE_BANDWIDTHPATH', '/${webroot}status/bandwidth.php');
        define('STORAGE_INTERFACE','${interface}');
        define('CAPTURERESIZEPCT',5);
        define('WEB_HOST', \"${ipaddress}\");
        define('WOL_HOST', \"${ipaddress}\");
        define('WOL_PATH', '/${webroot}wol/wol.php');
        define('WOL_INTERFACE', \"${interface}\");
        define('SNAPINDIR', \"${snapindir}/\");
        define('QUEUESIZE', '10');
        define('CHECKIN_TIMEOUT',600);
        define('USER_MINPASSLENGTH',4);
        define('USER_VALIDPASSCHARS','1234567890ABCDEFGHIJKLMNOPQRSTUVWZXYabcdefghijklmnopqrstuvwxyz_()^!#-');
        define('NFS_ETH_MONITOR', \"${interface}\");
        define('UDPCAST_INTERFACE', \"${interface}\");
        define('UDPCAST_STARTINGPORT', 63100 ); // Must be an even number! recommended between 49152 to 65535
        define('FOG_MULTICAST_MAX_SESSIONS',64);
        define('FOG_JPGRAPH_VERSION', '2.3');
        define('FOG_REPORT_DIR', './reports/');
        define('FOG_CAPTUREIGNOREPAGEHIBER',true);
        define('FOG_DONATE_MINING', \"${donate}\");
    }
}" > "${webdirdest}/lib/fog/config.class.php"
    errorStat $?
    clientVer="$(awk -F\' /"define\('FOG_CLIENT_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"

    clienturl="https://github.com/FOGProject/fog-client/releases/download/${clientVer}/FOGService.msi"
    siurl="https://github.com/FOGProject/fog-client/releases/download/${clientVer}/SmartInstaller.exe"
    [[ ! -d $workingdir/checksum_init ]] && mkdir -p $workingdir/checksum_init >/dev/null 2>&1
    [[ ! -d $workingdir/checksum_kernel ]] && mkdir -p $workingdir/checksum_kernel >/dev/null 2>&1
    dots "Getting checksum files for kernels and inits"
    curl --silent -ko "${workingdir}/checksum_init/checksums" https://fogproject.org/inits/index.php -ko "${workingdir}/checksum_kernel/checksums" https://fogproject.org/kernels/index.php >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    dots "Downloading inits, kernels, and the fog client"
    >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    curl --silent -ko "${webdirdest}/service/ipxe/init.xz" https://fogproject.org/inits/init.xz -ko "${webdirdest}/service/ipxe/init_32.xz" https://fogproject.org/inits/init_32.xz -ko "${webdirdest}/service/ipxe/bzImage" https://fogproject.org/kernels/bzImage -ko "${webdirdest}/service/ipxe/bzImage32" https://fogproject.org/kernels/bzImage32 >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && curl --silent -ko "${webdirdest}/client/FOGService.msi" -L $clienturl -ko "${webdirdest}/client/SmartInstaller.exe" -L $siurl >> $workingdir/error_logs/fog_error_${version}.log 2>&1
    errorStat $?
    dots "Comparing checksums of kernels and inits"
    localinitsum=$(sha512sum $webdirdest/service/ipxe/init.xz | awk '{print $1}')
    localinit_32sum=$(sha512sum $webdirdest/service/ipxe/init_32.xz | awk '{print $1}')
    localbzImagesum=$(sha512sum $webdirdest/service/ipxe/bzImage | awk '{print $1}')
    localbzImage32sum=$(sha512sum $webdirdest/service/ipxe/bzImage32 | awk '{print $1}')
    remoteinitsum=$(awk '/init\.xz$/{print $1}' $workingdir/checksum_init/checksums)
    remoteinit_32sum=$(awk '/init_32\.xz$/{print $1}' $workingdir/checksum_init/checksums)
    remotebzImagesum=$(awk '/bzImage$/{print $1}' $workingdir/checksum_kernel/checksums)
    remotebzImage32sum=$(awk '/bzImage32$/{print $1}' $workingdir/checksum_kernel/checksums)
    cnt=0
    while [[ $localinitsum != $remoteinitsum && $cnt -lt 10 ]]; do
        [[ $cnt -eq 0 ]] && echo "Failed init.xz"
        let cnt+=1
        dots "Attempting to redownload init.xz"
        curl --silent -ko "${webdirdest}/service/ipxe/init.xz" https://fogproject.org/inits/init.xz >/dev/null 2>&1
        errorStat $?
        localinitsum=$(sha512sum $webdirdest/service/ipxe/init.xz | awk '{print $1}')
    done
    if [[ $localinitsum != $remoteinitsum ]]; then
        echo " * Could not download init.xz properly"
        [[ -z $exitFail ]] && exit 1
    fi
    cnt=0
    while [[ $localinit_32sum != $remoteinit_32sum && $cnt -lt 10 ]]; do
        [[ $cnt -eq 0 ]] && echo "Failed init_32.xz"
        let cnt+=1
        dots "Attempting to redownload init_32.xz"
        curl --silent -ko "${webdirdest}/service/ipxe/init_32.xz" https://fogproject.org/inits/init_32.xz >/dev/null 2>&1
        errorStat $?
        localinit_32sum=$(sha512sum $webdirdest/service/ipxe/init_32.xz | awk '{print $1}')
    done
    if [[ $localinit_32sum != $remoteinit_32sum ]]; then
        echo " * Could not download init_32.xz properly"
        [[ -z $exitFail ]] && exit 1
    fi
    cnt=0
    while [[ $localbzImagesum != $remotebzImagesum && $cnt -lt 10 ]]; do
        [[ $cnt -eq 0 ]] && echo "Failed bzImage"
        let cnt+=1
        dots "Attempting to redownload bzImage"
        curl --silent -ko "${webdirdest}/service/ipxe/bzImage" https://fogproject.org/kernels/bzImage >/dev/null 2>&1
        errorStat $?
        localbzImagesum=$(sha512sum $webdirdest/service/ipxe/bzImage | awk '{print $1}')
    done
    if [[ $localbzImagesum != $remotebzImagesum ]]; then
        echo " * Could not download bzImage properly"
        [[ -z $exitFail ]] && exit 1
    fi
    cnt=0
    while [[ $localbzImage32sum != $remotebzImage32sum && $cnt -lt 10 ]]; do
        [[ $cnt -eq 0 ]] && echo "Failed bzImage32"
        let cnt+=1
        dots "Attempting to redownload bzImage32"
        curl --silent -ko "${webdirdest}/service/ipxe/bzImage32" https://fogproject.org/kernels/bzImage32 >/dev/null 2>&1
        errorStat $?
        localbzImage32sum=$(sha512sum $webdirdest/service/ipxe/bzImage32 | awk '{print $1}')
    done
    if [[ $localbzImage32sum != $remotebzImage32sum ]]; then
        echo " * Could not download bzImage32 properly"
        [[ -z $exitFail ]] && exit 1
    fi
    echo "Done"
    if [[ $osid -eq 2 ]]; then
        php -m | grep mysqlnd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ ! $? -eq 0 ]]; then
            php${php_ver}enmod mysqlnd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            if [[ ! $? -eq 0 ]]; then
                if [[ -e /etc/php${php_ver}/conf.d/mysqlnd.ini ]]; then
                    cp -f "/etc/php${php_ver}/conf.d/mysqlnd.ini" "/etc/php${php_ver}/mods-available/php${php_ver}-mysqlnd.ini" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    php${php_ver}enmod mysqlnd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                fi
            fi
        fi
        php -m | grep mcrypt >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ ! $? -eq 0 ]]; then
            php${php_ver}enmod mcrypt >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            if [[ ! $? -eq 0 ]]; then
                if [[ -e /etc/php${php_ver}/conf.d/mcrypt.ini ]]; then
                    cp -f "/etc/php${php_ver}/conf.d/mcrypt.ini" "/etc/php${php_ver}/mods-available/php${php_ver}-mcrypt.ini" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    php${php_ver}enmod mcrypt >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                fi
            fi
        fi
    fi
    dots "Enabling apache and fpm services on boot"
    if [[ $osid -eq 2 ]]; then
        if [[ $systemctl == yes ]]; then
            systemctl enable apache2 >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            systemctl enable $phpfpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        else
            sysv-rc-conf apache2 on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            sysv-rc-conf $phpfpm on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
    elif [[ $systemctl == yes ]]; then
        systemctl enable httpd php-fpm >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    else
        chkconfig php-fpm on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        chkconfig httpd on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    errorStat $?
    createSSLCA
    dots "Changing permissions on apache log files"
    chmod +rx $apachelogdir
    chmod +rx $apacheerrlog
    chmod +rx $apacheacclog
    chown -R ${apacheuser}:${apacheuser} $webdirdest
    errorStat $?
    rm -f "$webdirdest/mobile/css/font-awesome.css" $webdirdest/mobile/{fonts,less,scss} &>>$workingdir/error_logs/fog_error_${version}.log 2>&1
    [[ -d /var/www/html/ && ! -e /var/www/html/fog/ ]] && ln -s "$webdirdest" /var/www/html/
    [[ -d /var/www/ && ! -e /var/www/fog ]] && ln -s "$webdirdest" /var/www/
    ln -s "$webdirdest/management/css/font-awesome.css" "$webdirdest/mobile/css/font-awesome.css"
    ln -s "$webdirdest/management/fonts" "$webdirdest/mobile/"
    ln -s "$webdirdest/management/less" "$webdirdest/mobile/"
    ln -s "$webdirdest/management/scss" "$webdirdest/mobile/"
    chown -R ${apacheuser}:${apacheuser} "$webdirdest"
}
configureDHCP() {
    dots "Setting up and starting DHCP Server"
    case $bldhcp in
        1)
            [[ -f $dhcpconfig ]] && cp -f $dhcpconfig ${dhcpconfig}.fogbackup
            serverip=$(ip -4 -o addr show $interface | awk -F'([ /])+' '/global/ {print $4}')
            [[ -z $serverip ]] && serverip=$(/sbin/ifconfig $interface | grep -oE 'inet[:]? addr[:]?([0-9]{1,3}\.){3}[0-9]{1,3}' | awk -F'(inet[:]? ?addr[:]?)' '{print $2}')
            [[ -z $submask ]] && submask=$(cidr2mask $(getCidr $interface))
            network=$(mask2network $serverip $submask)
            [[ -z $startrange ]] && startrange=$(addToAddress $network 10)
            [[ -z $endrange ]] && endrange=$(subtract1fromAddress $(echo $(interface2broadcast $interface)))
            [[ -f $dhcpconfig ]] && dhcptouse=$dhcpconfig
            [[ -f $dhcpconfigother ]] && dhcptouse=$dhcpconfigother
            if [[ -z $dhcptouse || ! -f $dhcptouse ]]; then
                echo "Failed"
                echo "Could not find dhcp config file"
                exit 1
            fi
            [[ -z $bootfilename ]] && bootfilename="undionly.kpxe"
            echo "# DHCP Server Configuration file\n#see /usr/share/doc/dhcp*/dhcpd.conf.sample" > $dhcptouse
            echo "# This file was created by FOG" >> "$dhcptouse"
            echo "#Definition of PXE-specific options" >> "$dhcptouse"
            echo "# Code 1: Multicast IP Address of bootfile" >> "$dhcptouse"
            echo "# Code 2: UDP Port that client should monitor for MTFTP Responses" >> "$dhcptouse"
            echo "# Code 3: UDP Port that MTFTP servers are using to listen for MTFTP requests" >> "$dhcptouse"
            echo "# Code 4: Number of seconds a client must listen for activity before trying" >> "$dhcptouse"
            echo "#         to start a new MTFTP transfer" >> "$dhcptouse"
            echo "# Code 5: Number of seconds a client must listen before trying to restart" >> "$dhcptouse"
            echo "#         a MTFTP transfer" >> "$dhcptouse"
            echo "option space PXE;" >> "$dhcptouse"
            echo "option PXE.mtftp-ip code 1 = ip-address;" >> "$dhcptouse"
            echo "option PXE.mtftp-cport code 2 = unsigned integer 16;" >> "$dhcptouse"
            echo "option PXE.mtftp-sport code 3 = unsigned integer 16;" >> "$dhcptouse"
            echo "option PXE.mtftp-tmout code 4 = unsigned integer 8;" >> "$dhcptouse"
            echo "option PXE.mtftp-delay code 5 = unsigned integer 8;" >> "$dhcptouse"
            echo "option arch code 93 = unsigned integer 16;" >> "$dhcptouse"
            echo "use-host-decl-names on;" >> "$dhcptouse"
            echo "ddns-update-style interim;" >> "$dhcptouse"
            echo "ignore client-updates;" >> "$dhcptouse"
            echo "# Specify subnet of ether device you do NOT want service." >> "$dhcptouse"
            echo "# For systems with two or more ethernet devices." >> "$dhcptouse"
            echo "# subnet 136.165.0.0 netmask 255.255.0.0 {}" >> "$dhcptouse"
            echo "subnet $network netmask $submask{" >> "$dhcptouse"
            echo "    option subnet-mask $submask;" >> "$dhcptouse"
            echo "    range dynamic-bootp $startrange $endrange;" >> "$dhcptouse"
            echo "    default-lease-time 21600;" >> "$dhcptouse"
            echo "    max-lease-time 43200;" >> "$dhcptouse"
            [[ ! $(validip $routeraddress) -eq 0 ]] && routeraddress=$(echo $routeraddress | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            [[ ! $(validip $dnsaddress) -eq 0 ]] && dnsaddress=$(echo $dnsaddress | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            [[ $(validip $routeraddress) -eq 0 ]] && echo "    option routers $routeraddress;" >> "$dhcptouse" || ( echo "    #option routers 0.0.0.0" >> "$dhcptouse" && echo " !!! No router address found !!!" )
            [[ $(validip $dnsaddress) -eq 0 ]] && echo "    option domain-name-servers $dnsaddress;" >> "$dhcptouse" || ( echo "    #option routers 0.0.0.0" >> "$dhcptouse" && echo " !!! No dns address found !!!" )
            echo "    next-server $ipaddress;" >> "$dhcptouse"
            echo "    class \"Legacy\" {" >> "$dhcptouse"
            echo "        match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00000\";" >> "$dhcptouse"
            echo "        filename \"undionly.kkpxe\";" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "    class \"UEFI-32-2\" {" >> "$dhcptouse"
            echo "        match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00002\";" >> "$dhcptouse"
            echo "        filename \"i386-efi/ipxe.efi\";" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "    class \"UEFI-32-1\" {" >> "$dhcptouse"
            echo "        match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00006\";" >> "$dhcptouse"
            echo "        filename \"i386-efi/ipxe.efi\";" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "    class \"UEFI-64-1\" {" >> "$dhcptouse"
            echo "        match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00007\";" >> "$dhcptouse"
            echo "        filename \"ipxe.efi\";" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "    class \"UEFI-64-2\" {" >> "$dhcptouse"
            echo "        match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00008\";" >> "$dhcptouse"
            echo "        filename \"ipxe.efi\";" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "    class \"UEFI-64-3\" {" >> "$dhcptouse"
            echo "        match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00009\";" >> "$dhcptouse"
            echo "        filename \"ipxe.efi\";" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "    class \"SURFACE-PRO-4\" {" >> "$dhcptouse"
            echo "        match if substring(option vendor-class-identifier, 0, 32) = \"PXEClient:Arch:00007:UNDI:003016\";" >> "$dhcptouse"
            echo "        filename \"ipxe7156.efi\";" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "    class \"Apple-Intel-Netboot\" {" >> "$dhcptouse"
            echo "        match if substring (option vendor-class-identifier, 0, 14) = \"AAPLBSDPC/i386\";" >> "$dhcptouse"
            echo "        option dhcp-parameter-request-list 1,3,17,43,60;" >> "$dhcptouse"
            echo "        if (option dhcp-message-type = 8) {" >> "$dhcptouse"
            echo "            option vendor-class-identifier \"AAPLBSDPC\";" >> "$dhcptouse"
            echo "            if (substring(option vendor-encapsulated-options, 0, 3) = 01:01:01) {" >> "$dhcptouse"
            echo "                # BSDP List" >> "$dhcptouse"
            echo "                option vendor-encapsulated-options 01:01:01:04:02:80:00:07:04:81:00:05:2a:09:0D:81:00:05:2a:08:69:50:58:45:2d:46:4f:47;" >> "$dhcptouse"
            echo "            }" >> "$dhcptouse"
            echo "        elsif (substring(option vendor-encapsulated-options, 0, 3) = 01:01:02) {" >> "$dhcptouse"
            echo "            # BSDP Select" >> "$dhcptouse"
            echo "            option vendor-encapsulated-options 01:01:02:08:04:81:00:05:2a:82:0a:4e:65:74:42:6f:6f:74:30:30:31;" >> "$dhcptouse"
            echo "            filename \"ipxe.efi\";" >> "$dhcptouse"
            echo "            }" >> "$dhcptouse"
            echo "        }" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"


            echo "}" >> "$dhcptouse"
            case $systemctl in
                yes)
                    systemctl enable $dhcpd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    systemctl stop $dhcpd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    systemctl start $dhcpd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    sleep 2
                    systemctl status $dhcpd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
                *)
                    case $osid in
                        1)
                            chkconfig $dhcpd on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            service $dhcpd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            sleep 2
                            service $dhcpd start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            sleep 2
                            service $dhcpd status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            ;;
                        2)
                            sysv-rc-conf $dhcpd on >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            /etc/init.d/$dhcpd stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            sleep 2
                            /etc/init.d/$dhcpd start >>$workingdir/error_logs/fog_error_${version}.log 2>&1 && sleep 2
                            ;;
                    esac
                    ;;
            esac
            errorStat $?
            ;;
        *)
            echo "Skipped"
            ;;
    esac
}
