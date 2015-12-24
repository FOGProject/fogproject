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
#
#
dots() {
    local pad=$(printf "%0.1s" "."{1..60})
    printf " * %s%*.*s" "$1" 0 $((60-${#1})) "$pad"
}
uninstall() {
    case "$autoaccept" in
        yes)
        blUninstall="Y"
        ;;
        *)
        echo "You have chosen to uninstall fog."
        echo
        echo "Uninstalling will not delete your images or snapins folder."
        echo "    It will delete the FOG database after backing it up"
        echo "    It will not delete the installer"
        echo
        echo "The snapins folder, usually located in /opt/fog/snapins"
        echo "    will be moved into the ${storageLocation} folder and"
        echo "    the /opt/fog directory will be removed."
        echo
        echo -n "Are you sure you want to uninstall fog? (y/N) "
        read blUninstall
        ;;
    esac
    case "$blUninstall" in
        [Yy]*)
        echo "We are going to uninstall"
        ;;
        "N"|*)
        echo "We are not going to uninstall"
        ;;
    esac
}
installUtils() {
    dots "Setting up FOG Utils"
    mkdir -p ${fogutilsdir} >/dev/null 2>&1
    cp -Rf ${fogutilsdirsrc}/* "${fogutilsdir}" >/dev/null 2>&1
    chown -R ${apacheuser} ${fogutilsdir} >/dev/null 2>&1
    chmod -R 700 ${fogutilsdir} >/dev/null 2>&1
    errorStat $?
}
help() {
    echo -e "Usage: $0 [-h?dEUuHSCKY] [-f <filename>] [-D </directory/to/document/root/>]";
    echo -e "\t\t\t[-W <webroot/to/fog/after/docroot/>] [-B </backup/path/>]";
    echo -e "\t\t\t[-s <192.168.1.10>] [-e 192.168.1.254] [-b <undionly.kpxe>]";
    echo -e "\t-h -? --help\t\t\tDisplay this info"
    echo -e "\t-d    --no-defaults\t\tDon't guess defaults"
    echo -e "\t-U    --no-upgrade\t\tDon't attempt to upgrade"
    echo -e "\t-H    --no-htmldoc\t\tNo htmldoc, means no PDFs"
    echo -e "\t-S    --force-https\t\tForce HTTPS redirect"
    echo -e "\t-C    --recreate-CA\t\tRecreate the CA Keys"
    echo -e "\t-K    --recreate-keys\t\tRecreate the SSL Keys"
    echo -e "\t-Y -y --autoaccept\t\tAuto accept defaults and install"
    echo -e "\t-f    --file\t\t\tUse different update file"
    echo -e "\t-D    --docroot\t\t\tSpecify the Apache Docroot for fog"
    echo -e "\t               \t\t\t\tdefaults to OS DocumentRoot"
    echo -e "\t-W    --webroot\t\t\tSpecify the web root url want fog to use"
    echo -e "\t            \t\t\t\t(E.G. http://127.0.0.1/fog,"
    echo -e "\t            \t\t\t\t      http://127.0.0.1/)"
    echo -e "\t            \t\t\t\tDefaults to /fog/"
    echo -e "\t-B    --backuppath\t\tSpecify the backup path"
    echo -e "\t      --uninstall\t\tUninstall FOG"
    echo -e "\t-s    --startrange\t\tDHCP Start range"
    echo -e "\t-e    --endrange\t\tDHCP End range"
    echo -e "\t-b    --bootfile\t\tDHCP Boot file"
    echo -e "\t-E    --no-exportbuild\t\tSkip building nfs file"
    exit 0
}
backupReports() {
    dots "Backing up user reports"
    if [ ! -d "../rpttmp/" ]; then
        mkdir "../rpttmp/"
    fi
    if [ -d "${webdirdest}/management/reports" ]; then
        cp -a ${webdirdest}/management/reports/* "../rpttmp/" >/dev/null 2>&1
    fi
    errorStat $?
}
validip() {
    local ip="$1"
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
mask2cidr() {
    nbits=0
    IFS=.
    for dec in $1; do
        case $dec in
            255) let nbits+=8;;
            254) let nbits+=7; break ;;
            252) let nbits+=6; break ;;
            248) let nbits+=5; break ;;
            240) let nbits+=4; break ;;
            224) let nbits+=3; break ;;
            192) let nbits+=2; break ;;
            128) let nbits+=1; break ;;
            0);;
            *) echo "Error: $dec is not recognized"; exit 1
        esac
    done
    echo "$nbits"
}
cidr2mask() {
    local i=""
    local mask=""
    local full_octets=$(($1/8))
    local partial_octet=$(($1%8))
    for ((i=0;i<4;i+=1)); do
        if [ $i -lt $full_octets ]; then
            mask+=255
        elif [ $i -eq $full_octets ]; then
            mask+=$((256 - 2**(8-$partial_octet)))
        else
            mask+=0
        fi
        test $i -lt 3 && mask+=.
    done
    echo $mask
}
mask2network() {
    IFS=.
    read -r i1 i2 i3 i4 <<< "$1"
    read -r m1 m2 m3 m4 <<< "$2"
    printf "%d.%d.%d.%d\n"  "$((i1 & m1))" "$((i2 & m2))" "$((i3 & m3))" "$((i4 & m4))"
}
restoreReports() {
    dots "Restoring user reports"
    if [ -d "${webdirdest}/management/reports" ]; then
        if [ -d "../rpttmp/" ]; then
            cp -a ../rpttmp/* ${webdirdest}/management/reports/ >/dev/null 2>&1;
        fi
    fi
    errorStat $?
}
installFOGServices() {
    dots "Setting up FOG Services"
    mkdir -p ${servicedst} >/dev/null 2>&1
    cp -Rf ${servicesrc}/* ${servicedst}/ >/dev/null 2>&1
    mkdir -p ${servicelogs} >/dev/null 2>&1
    errorStat $?
}
configureUDPCast() {
    dots "Setting up UDPCast"
    cp -Rf "${udpcastsrc}" "${udpcasttmp}"
    cur=`pwd`
    cd /tmp
    tar xvzf "${udpcasttmp}"  >/dev/null 2>&1
    cd ${udpcastout}
    errorStat $?
    dots "Configuring UDPCast"
    ./configure >/dev/null 2>&1
    errorStat $?
    dots "Building UDPCast"
    make >/dev/null 2>&1
    errorStat $?
    dots "Installing UDPCast"
    make install >/dev/null 2>&1;
    errorStat $?
    cd $cur
}
configureFTP() {
	dots "Setting up and starting VSFTP Server...";
	if [ -f "$ftpconfig" ]; then
		mv "$ftpconfig" "${ftpconfig}.fogbackup";
	fi
    if [ -f "$ftpxinetd" ]; then
        mv "$ftpxinetd" "${ftpxinetd}.fogbackup";
    fi
	vsftp=`vsftpd -version 0>&1 | awk -F'version ' '{print $2}'`
    vsvermaj=`echo $vsftp | awk -F. '{print $1}'`
	vsverbug=`echo $vsftp | awk -F. '{print $3}'`
    seccompsand=""
	if [ "$vsvermaj" -gt 3 ] || [ "$vsvermaj" -eq 3 -a "$vsverbug" -ge 2 ]; then
		seccompsand="seccomp_sandbox=NO"
	fi
    tcpwrappers="YES"
    if [ "$osid" == 3 ]; then
        tcpwrappers="NO"
    fi
	echo -e  "anonymous_enable=NO\nlocal_enable=YES\nwrite_enable=YES\nlocal_umask=022\ndirmessage_enable=YES\nxferlog_enable=YES\nconnect_from_port_20=YES\nxferlog_std_format=YES\nlisten=YES\npam_service_name=vsftpd\nuserlist_enable=NO\ntcp_wrappers=$tcpwrappers\n$seccompsand" > "$ftpconfig"
    if [ "$systemctl" == "yes" ]; then
        systemctl enable vsftpd >/dev/null 2>&1
        systemctl restart vsftpd >/dev/null 2>&1
        systemctl status vsftpd >/dev/null 2>&1
    elif [ "$osid" -eq 2 ]; then
        sysv-rc-conf vsftpd on >/dev/null 2>&1
        service vsftpd stop >/dev/null 2>&1
        service vsftpd start >/dev/null 2>&1
        service vsftpd status >/dev/null 2>&1
    else
        chkconfig vsftpd on >/dev/null 2>&1
        service vsftpd stop >/dev/null 2>&1
        service vsftpd start >/dev/null 2>&1
        service vsftpd status >/dev/null 2>&1
    fi
    errorStat $?
}
configureDefaultiPXEfile() {
    find "${tftpdirdst}" ! -type d -exec chmod 644 {} \;
    echo -e "#!ipxe\ncpuid --ext 29 && set arch x86_64 || set arch i386\nparams\nparam mac0 \${net0/mac}\nparam arch \${arch}\nparam platform \${platform}\nparam product \${product}\nparam manufacturer \${product}\nparam ipxever \${version}\nparam filename \${filename}\nisset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\nisset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n:bootme\nchain http://${ipaddress}/${webroot}service/ipxe/boot.php##params" > "${tftpdirdst}/default.ipxe"
}
configureTFTPandPXE() {
    dots "Setting up and starting TFTP and PXE Servers";
	if [ -d "${tftpdirdst}.prev" ]; then
		rm -rf "${tftpdirdst}.prev" 2>/dev/null;
	fi
	if [ -d "$tftpdirdst" ]; then
		rm -rf "${tftpdirdst}.fogbackup" 2>/dev/null;
		mv "$tftpdirdst" "${tftpdirdst}.prev" 2>/dev/null;
	fi
	mkdir -p "$tftpdirdst" >/dev/null 2>&1;
	cp -Rf ${tftpdirsrc}/* ${tftpdirdst}/
	chown -R ${username} "${tftpdirdst}";
	chown -R ${username} "${webdirdest}/service/ipxe";
	find "${tftpdirdst}" -type d -exec chmod 755 {} \;
	find "${webdirdest}" -type d -exec chmod 755 {} \;
	find "${tftpdirdst}" ! -type d -exec chmod 644 {} \;
	configureDefaultiPXEfile;
    if [ -f "$tftpconfig" ]; then
		mv "$tftpconfig" "${tftpconfig}.fogbackup";
	fi
	echo -e "# default: off\n# description: The tftp server serves files using the trivial file transfer \n#	protocol.  The tftp protocol is often used to boot diskless \n#	workstations, download configuration files to network-aware printers, \n#	and to start the installation process for some operating systems.\nservice tftp\n{\n	socket_type		= dgram\n	protocol		= udp\n	wait			= yes\n	user			= root\n	server			= /usr/sbin/in.tftpd\n	server_args		= -s ${tftpdirdst}\n	disable			= no\n	per_source		= 11\n	cps			= 100 2\n	flags			= IPv4\n}" > "$tftpconfig";
    if [ "$osid" -eq 2 ]; then
        blUpstart=0
        if [ -f "$tftpconfigupstartdefaults" ]; then
            blUpstart=1
        fi
        if [ "$blUpstart" = "1" ]; then
            echo -e "# /etc/default/tftpd-hpa\n# FOG Modified version\nTFTP_USERNAME=\"root\"\nTFTP_DIRECTORY=\"/tftpboot\"\nTFTP_ADDRESS=\":69\"\nTFTP_OPTIONS=\"-s\"" > "$tftpconfigupstartdefaults"
            if [ "$systemctl" == "yes" ]; then
                systemctl enable xinetd >/dev/null 2>&1
                systemctl enable tftpd-hpa >/dev/null 2>&1
                systemctl restart tftpd-hpa >/dev/null 2>&1
                systemctl restart xinetd >/dev/null 2>&1
                sleep 2
                systemctl status tftpd-hpa >/dev/null 2>&1
                systemctl status xinetd >/dev/null 2>&1
            else
                sysv-rc-conf xinetd off >/dev/null 2>&1
                service xinetd stop >/dev/null 2>&1
                sysv-rc-conf tftpd-hpa on >/dev/null 2>&1
                service tftpd-hpa stop >/dev/null 2>&1
                sleep 2
                service tftpd-hpa start >/dev/null 2>&1
            fi
        else
            sysv-rc-conf xinetd on >/dev/null 2>&1
            $initdpath/xinetd stop >/dev/null 2>&1
            $initdpath/xinetd start >/dev/null 2>&1
        fi
    elif [ "$systemctl" == "yes" ]; then
        systemctl enable xinetd >/dev/null 2>&1
        systemctl restart xinetd >/dev/null 2>&1
        sleep 2
        systemctl status xinetd >/dev/null 2>&1
    else
        chkconfig xinetd on >/dev/null 2>&1
        service xinetd restart >/dev/nul 2>&1
        sleep 2
        service xinetd status >/dev/null 2>&1
    fi
    errorStat $?
}
configureMinHttpd() {
    configureHttpd
	echo "<?php die(\"This is a storage node, please do not access the web ui here!\")" > "$webdirdest/management/index.php"
}
installPackages() {
    dots "Adding needed repository"
    if [ "$osid" -eq 1 ]; then
        ${packageinstaller} epel-release >/dev/null 2>&1
        repo="enterprise"
        if [[ "$linuxReleaseName" == +(*[Ff]'edora'*) ]]; then
            repo="fedora"
        fi
        if [ -d "/etc/yum.repos.d/" -a ! -f "/etc/yum.repos.d/remi.repo" ]; then
            rpm -Uvh http://rpms.famillecollet.com/$repo/remi-release-$OSVersion.rpm >/dev/null 2>&1
            rpm --import http://rpms.famillecollet.com/RPM-GPG-KEY-remi >/dev/null 2>&1
        else
            true
        fi
    elif [ "$osid" -eq 2 ]; then
        if [[ "$linuxReleaseName" != +(*'ebian'*) ]]; then
            DEBIAN_FRONTEND=noninteractive $packageinstaller python-software-properties software-properties-common >/dev/null 2>&1
            ntpdate pool.ntp.org >/dev/null 2>&1
            add-apt-repository -y ppa:ondrej/php5-5.6 >/dev/null 2>&1
            if [ "$?" != 0 ]; then
                apt-get update >/dev/null 2>&1
                apt-get -yq install python-software-properties ntpdate >/dev/null 2>&1
                ntpdate pool.ntp.org >/dev/null 2>&1
                locale-gen 'en_US.UTF-8' >/dev/null 2>&1
                LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y ppa:ondrej/php5-5.6 >/dev/null 2>&1
            fi
        elif [[ "$linuxReleaseName" == +(*'ebian'*) ]]; then
            if [ "$OSVersion" -eq 7 ]; then
                debcode="wheezy";
                grep -l "deb http://packages.dotdeb.org $debcode-php56 all" "/etc/apt/sources.list" >/dev/null 2>&1
                if [ "$?" != 0 ]; then
                    echo -e "deb http://packages.dotdeb.org $debcode-php56 all\ndeb-src http://packages.dotdeb.org $debcode-php56 all\n" >> "/etc/apt/sources.list";
                fi
            fi
        fi
    fi
    errorStat $?
    dots "Preparing Package Manager"
    $packmanUpdate >/dev/null 2>&1
    if [ "$osid" -eq 2 ]; then
        if [ "$?" != 0 ] && [[ "$linuxReleaseName" == +(*'buntu'*) ]]; then
            cp /etc/apt/sources.list /etc/apt/sources.list.original_fog_$(date +%s)
            sed -i -e 's/\/\/*archive.ubuntu.com\|\/\/*security.ubuntu.com/\/\/old-releases.ubuntu.com/g' /etc/apt/sources.list
            $packmanUpdate >/dev/null 2>&1
            if [ "$?" != 0 ]; then
                cp -f /etc/apt/sources.list.original_fog /etc/apt/sources.list >/dev/null 2>&1
                rm -f /etc/apt/sources.list.original_fog >/dev/null 2>&1
                false
            fi
        fi
        packages="${packages}";
    fi
    errorStat $?
    echo -e " * Packages to be installed:\n\n\t$packages\n\n"
    newPackList=""
    for x in $packages; do
        if [ "$x" == "mysql" ]; then
            for sqlclient in $sqlclientlist; do
                if [ "`eval $packagelist $sqlclient >/dev/null 2>&1; echo $?`" -eq 0 ]; then
                    x=$sqlclient
                    break
                fi
            done
        elif [ "$x" == "mysql-server" ]; then
            for sqlserver in $sqlserverlist; do
                if [ "`eval $packagelist $sqlserver >/dev/null 2>&1; echo $?`" -eq 0 ]; then
                    x=$sqlserver
                    break
                fi
            done
        elif [ "$x" == "php5-json" ]; then
            for json in $jsontest; do
                if [ "`eval $packagelist $json >/dev/null 2>&1; echo $?`" -eq 0 ]; then
                    x="$json"
                    break;
                fi
            done
        fi
        newPackList="$newPackList $x"
        if [ "$osid" -eq 1 ]; then
            rpm -q $x >/dev/null 2>&1
        elif [ "$osid" -eq 2 ]; then
            dpkg -l $x 2>/dev/null | grep '^ii' >/dev/null 2>&1
        elif [ "$osid" -eq 3 ]; then
            pacman -Q $x >/dev/null 2>&1
        fi
        if [ "$?" -eq 0 ]; then
            dots "Skipping package: $x"
            echo "(Already Installed)"
            continue
        fi
        dots "Installing package: $x"
        eval "DEBIAN_FRONTEND=noninteractive ${packageinstaller} $x >/dev/null 2>&1"
        errorStat $?
    done
    dots "Updating packages as needed";
    eval "DEBIAN_FRONTEND=noninteractive $packageupdater $packages >/dev/null 2>&1"
    echo "OK";
}
confirmPackageInstallation() {
    for x in $packages; do
        dots "Checking package: $x"
        if [ "$x" == "mysql" ]; then
            for sqlclient in $sqlclientlist; do
                x=$sqlclient
                if [ "$osid" -eq 1 ]; then
                    rpm -q $x >/dev/null 2>&1
                elif [ "$osid" -eq 2 ]; then
                    dpkg -l $x 2>/dev/null | grep '^ii' >/dev/null 2>&1
                elif [ "$osid" -eq 3 ]; then
                    pacman -Q $x >/dev/null 2>&1
                fi
                if [ "$?" -eq 0 ]; then
                    break
                fi
            done
        elif [ "$x" == "mysql-server" ]; then
            for sqlserver in $sqlserverlist; do
                x=$sqlserver
                if [ "$osid" -eq 1 ]; then
                    rpm -q $x >/dev/null 2>&1
                elif [ "$osid" -eq 2 ]; then
                    dpkg -l $x 2>/dev/null | grep '^ii' >/dev/null 2>&1
                elif [ "$osid" -eq 3 ]; then
                    pacman -Q $x >/dev/null 2>&1
                fi
                if [ "$?" -eq 0 ]; then
                    break
                fi
            done
        elif [ "$x" == "php5-json" ]; then
            for json in $jsontest; do
                x=$json
                if [ "$osid" -eq 1 ]; then
                    rpm -q $x >/dev/null 2>&1
                elif [ "$osid" -eq 2 ]; then
                    dpkg -l $x 2>/dev/null | grep '^ii' >/dev/null 2>&1
                elif [ "$osid" -eq 3 ]; then
                    pacman -Q $x >/dev/null 2>&1
                fi
                if [ "$?" -eq 0 ]; then
                    break
                fi
            done
        fi
        if [ "$osid" -eq 1 ]; then
            rpm -q $x >/dev/null 2>&1
        elif [ "$osid" -eq 2 ]; then
            dpkg -l $x 2>/dev/null | grep '^ii' >/dev/null 2>&1
        elif [ "$osid" -eq 3 ]; then
            pacman -Q $x >/dev/null 2>&1
        fi
        errorStat $?
    done
}
displayOSChoices() {
    blFirst="1";
    while [ "$osid" = "" ]; do
        if [ "$fogupdateloaded" = "1" -a  "$osid" != "" -a "$blFirst" = "1" ]; then
            blFirst="0";
        else
            osid=$strSuggestedOS
            if [ -z "$autoaccept" -a ! -z "$osid" ]; then
                echo "  What version of Linux would you like to run the installation for?"
                echo "";
                echo "          1) Redhat Based Linux (Redhat, CentOS, Mageia)";
                echo "          2) Debian Based Linux (Debian, Ubuntu, Kubuntu, Edubuntu)";
                echo "          3) Arch Linux";
                echo "";
                echo -n "  Choice: [${strSuggestedOS}]";
                read osid
                if [ -z "$osid" ]; then
                    osid=$strSuggestedOS
                fi
            fi
        fi
        doOSSpecificIncludes;
    done
}
doOSSpecificIncludes() {
    echo "";
    case "$osid" in
        "1")
            echo -e "\n\n  Starting Redhat based Installation\n\n"
            osname="Redhat"
            . ../lib/redhat/config.sh
        ;;
        "2")
            echo -e "\n\n  Starting Debian based Installation\n\n"
            osname="Debian"
            . ../lib/ubuntu/config.sh
        ;;
        "3")
            echo -e "\n\n  Starting Arch Installation\n\n"
            osname="Arch"
            . ../lib/arch/config.sh
            systemctl="yes"
        ;;
        *)
            echo -e "  Sorry, answer not recognized\n\n"
            sleep 2;
            osid="";
        ;;
    esac
    currentdir=`pwd`;
    if [ "${currentdir/$webdirdest}" != "$currentdir" -o "${currentdir/$tftpdirdst}" != "$currentdir" ];then
        echo "Please change installation directory.";
        echo "Running from here will fail.";
        echo "You are in $currentdir which is a folder that will";
        echo "be moved during installation.";
        exit 1;
    fi
}
errorStat() {
    if [ "$1" != "0" -a -z "$2" ]; then
        echo "Failed!"
        exit 1
    fi
    echo "OK"
}
stopInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname"
    for serviceItem in $serviceList; do
        dots "Stopping $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl stop $serviceItem >/dev/null 2>&1
        else
            $initdpath/$serviceItem stop >/dev/null 2>&1
        fi
        echo "OK"
    done
}
startInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname"
    for serviceItem in $serviceList; do
        dots "Starting $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl start $serviceItem >/dev/null 2>&1
        else
            $initdpath/$serviceItem start >/dev/null 2>&1
        fi
        errorStat $?
    done
}
enableInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname"
    for serviceItem in $serviceList; do
        dots "Setting $serviceItem script executable"
        chmod 755 $initdpath/$serviceItem >/dev/null 2>&1
        errorStat $?
        dots "Enabling $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl enable $serviceItem >/dev/null 2>&1
            true
        elif [ "$osid" -eq 2 ]; then
            sysv-rc-conf $serviceItem on >/dev/null 2>&1
            if [[ "$linuxReleaseName" == +(*'buntu'*) ]]; then
                /usr/lib/insserv/insserv -d $initdpath/$serviceItem >/dev/null 2>&1
            else
                insserv -d $initdpath/$serviceItem >/dev/null 2>&1
            fi
            true
        elif [ "$osid" -eq 1 ]; then
            chkconfig $serviceItem on >/dev/null 2>&1
            true
        fi
        errorStat $?
    done
}
installInitScript() {
    dots "Installing FOG System Scripts"
    cp -f $initdsrc/* $initdpath/ >/dev/null 2>&1
    errorStat $?
    echo -e "\n\n  * Configuring FOG System Services\n\n"
    enableInitScript
}
configureMySql() {
    stopInitScript
    dots "Setting up and starting MySQL"
    if [ "$systemctl" == "yes" ]; then
        if [ "$osid" == 3 ]; then
            if [ ! -d "/var/lib/mysql" ]; then
                mkdir /var/lib/mysql >/dev/null 2>&1
            fi
            chown -R mysql:mysql /var/lib/mysql >/dev/null 2>&1
            mysql_install_db --user=mysql --ldata=/var/lib/mysql/ >/dev/null 2>&1
        fi
        systemctl enable mysql.service >/dev/null 2>&1 && \
        systemctl restart mysql.service >/dev/null 2>&1 && \
        sleep 2 && \
        systemctl status mysql.service >/dev/null 2>&1
        if [ "$?" != "0" ]; then
            systemctl enable mysqld.service >/dev/null 2>&1 && \
            systemctl restart mysqld.service >/dev/null 2>&1 && \
            sleep 2 && \
            systemctl status mysqld.service >/dev/null 2>&1
        fi
        if [ "$?" != "0" ]; then
            systemctl enable mariadb.service >/dev/null 2>&1 && \
            systemctl restart mariadb.service >/dev/null 2>&1 && \
            sleep 2 && \
            systemctl status mariadb.service >/dev/null 2>&1
        fi
    elif [ "$osid" -eq 2 ]; then
        sysv-rc-conf mysql on >/dev/null 2>&1 && \
        service mysql stop >/dev/null 2>&1 && \
        service mysql start >/dev/null 2>&1
    else
        chkconfig mysqld on >/dev/null 2>&1 && \
        service mysqld restart >/dev/null 2>&1 && \
        service mysqld status >/dev/null 2>&1
    fi
    errorStat $?
}
configureFOGService() {
    if [[ ! -d $servicedst ]]; then
        mkdir -p $servicedst >/dev/null 2>&1
    fi
    if [[ ! -d $servicedst/etc ]]; then
        mkdir -p $servicedst/etc >/dev/null 2>&1
    fi
	echo "<?php
define('WEBROOT','${webdirdest}');" > ${servicedst}/etc/config.php
    startInitScript
}
configureNFS() {
#    if [ -f "$nfsconfig" ]; then
#        if [ $(grep -c "##FOG_SHARE_START##" $nfsconfig) -ne 0 ]; then
#            sed -in '/##FOG_SHARE_START##/,/##FOG_END_SHARE##/{//d;D}' $nfsconfig
#        fi
#    fi
    dots "Setting up exports file";
    if [ "$blexports" != 1 ]; then
        echo "Skipped";
    else
        echo -e "$storageLocation *(ro,sync,no_wdelay,no_subtree_check,insecure_locks,no_root_squash,insecure,fsid=0)\n$storageLocation/dev *(rw,async,no_wdelay,no_subtree_check,no_root_squash,insecure,fsid=1)" > "$nfsconfig";
        errorStat $?
        dots "Setting up and starting RPCBind";
        if [ "$systemctl" == "yes" ]; then
            systemctl enable rpcbind.service >/dev/null 2>&1 && \
            systemctl restart rpcbind.service >/dev/null 2>&1 && \
            systemctl status rpcbind.service >/dev/null 2>&1
        elif [ "$osid" -eq 2 ]; then
            true
        else
            chkconfig rpcbind on >/dev/null 2>&1 && \
            $initdpath/rpcbind restart >/dev/null 2>&1 && \
            $initdpath/rpcbind status >/dev/null 2>&1
        fi
        errorStat $?
        dots "Setting up and starting NFS Server..."
        for nfsItem in $nfsservice; do
            if [ "$systemctl" == "yes" ]; then
                systemctl enable $nfsItem >/dev/null 2>&1 && \
                systemctl restart $nfsItem >/dev/null 2>&1 && \
                systemctl status $nfsItem >/dev/null 2>&1
            else
                if [ "$osid" == 2 ]; then
                    sysv-rc-conf $nfsItem on >/dev/null 2>&1 && \
                    $initdpath/nfs-kernel-server stop >/dev/null 2>&1 && \
                    $initdpath/nfs-kernel-server start >/dev/null 2>&1
                else
                    chkconfig $nfsItem on >/dev/null 2>&1 && \
                    $initdpath/$nfsItem restart >/dev/null 2>&1 && \
                    $initdpath/$nfsItem status >/dev/null 2>&1
                fi
            fi
            if [ "$?" -eq 0 ]; then
                break
            fi
        done
        errorStat $?
    fi
}
configureSnapins() {
    dots "Setting up FOG Snapins"
    mkdir -p $snapindir >/dev/null 2>&1
    if [ -d "$snapindir" ]; then
        chmod 775 $snapindir
        chown -R fog:${apacheuser} ${snapindir}
    fi
    errorStat $?
}
configureUsers() {
    getent passwd $username > /dev/null;
    if [ $? != 0 ] || [ "$doupdate" != "1" ]; then
        dots "Setting up fog user";
        # Consider this a temporary security fix
        password=`dd if=/dev/urandom bs=1 count=9 2>/dev/null | base64`
        if [ "$installtype" = "S" ]; then
            # save everyone wrist injuries
            storageftpuser=${username};
            storageftppass=${password};
        else
            storageftpuser=${storageftpuser};
            storageftppass=${storageftppass};
            if [ -z "$storageftpuser" ]; then
                storageftpuser='fog';
            fi
            if [ -z "$storageftppass" ]; then
                storageftppass=${password};
            fi
        fi
        if [ $password != "" ]; then
            useradd -s "/bin/bash" -d "/home/${username}" ${username} >/dev/null 2>&1;
            if [ "$?" = "0" ]; then
                passwd ${username} >/dev/null 2>&1 << EOF
${password}
${password}
EOF
                mkdir "/home/${username}" >/dev/null 2>&1;
                chown -R ${username} "/home/${username}" >/dev/null 2>&1;
                echo "...OK";
            else
                if [ -f "${webdirdest}/lib/fog/config.class.php" ]; then
                    password=`cat ${webdirdest}/lib/fog/config.class.php | grep TFTP_FTP_PASSWORD | cut -d"," -f2 | cut -d"\"" -f2`;
                fi
                echo "...Exists";
                bluseralreadyexists="1";
            fi
            true
        else
            false
        fi
        errorStat $?
    fi
    if [ -z "$password" -a -z "$storageftppass" ]; then
        dots "Setting password for FOG User"
        # Consider this a temporary security fix
        password=`dd if=/dev/urandom bs=1 count=9 2>/dev/null | base64`
        passwd ${username} >/dev/null 2>&1 << EOF
${password}
${password}
EOF
        errorStat $?
        storageftpuser=$username
        storageftppass=$password
        echo -e " * New password set for:\n\t\tusername: $username\n\t\tpassword: $password\n"
        sleep 10;
    fi
}
linkOptFogDir() {
    if [ ! -h "/var/log/fog" ]; then
        dots "Linking FOG Logs to Linux Logs"
        ln -s "/opt/fog/log" "/var/log/fog" >/dev/null 2>&1
        errorStat $?
    fi
    if [ ! -h "/etc/fog" ]; then
        dots "Linking FOG Service config /etc"
        ln -s "/opt/fog/service/etc" "/etc/fog" >/dev/null 2>&1
        errorStat $?
    fi
}
configureStorage() {
    dots "Setting up storage";
    if [ ! -d "$storage" ]; then
        mkdir "$storage" >/dev/null 2>&1
        chmod -R 777 "$storage" >/dev/null 2>&1
    fi
    if [ ! -f "$storage/.mntcheck" ]; then
        touch "$storage/.mntcheck" >/dev/null 2>&1
        chmod 777 "$storage/.mntcheck" >/dev/null 2>&1
    fi
    if [ ! -d "$storage/postdownloadscripts" ]; then
        mkdir "$storage/postdownloadscripts" >/dev/null 2>&1
        if [ ! -f "$storage/postdownloadscripts/fog.postdownload" ]; then
            echo "#!/bin/sh
## This file serves as a starting point to call your custom postimaging scripts.
## <SCRIPTNAME> should be changed to the script you're planning to use.
## Syntax of post download scripts are
#. \${postdownpath}<SCRIPTNAME>" > "$storage/postdownloadscripts/fog.postdownload";
        fi
        chmod -R 777 "$storage" >/dev/null 2>&1
    fi
    if [ ! -d "$storageupload" ]; then
        mkdir "$storageupload" >/dev/null 2>&1
        chmod -R 777 "$storageupload" >/dev/null 2>&1
    fi
    if [ ! -f "$storageupload/.mntcheck" ]; then
        touch "$storageupload/.mntcheck" >/dev/null 2>&1
        chmod 777 "$storageload/.mntcheck" >/dev/null 2>&1
    fi
    errorStat $?
}
clearScreen() {
    clear
}
writeUpdateFile() {
    tmpDte=$(date +%c)
    if [[ -f $fogprogramdir/.fogsettings ]]; then
        grep -q "^## Start of FOG Settings" $fogprogramdir/.fogsettings || grep -q "^## Version:.*" $fogprogramdir/.fogsettings
        if [[ $? == 0 ]]; then
            grep -q "^## Version:.*$" $fogprogramdir/.fogsettings && sed -i "s/^## Version:.*/## Version: $version/g" $fogprogramdir/.fogsettings
            grep -q "ipaddress=" $fogprogramdir/.fogsettings && sed -i "s/ipaddress=?['\"][0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}?['\"]/ipaddress='$ipaddress'/g" $fogprogramdir/.fogsettings
            grep -q "interface=" $fogprogramdir/.fogsettings && sed -i "s/interface='?['\"].*?['\"]/interface='$interface'/g" $fogprogramdir/.fogsettings
            grep -q "submask=" $fogprogramdir/.fogsettings && sed -i "s/submask=?['\"][0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}?['\"]/submask='$submask'/g" $fogprogramdir/.fogsettings
            grep -q "routeraddress=" $fogprogramdir/.fogsettings && sed -i "s/routeraddress=?['\"].*?['\"]/routeraddress='$routeraddress'/g" $fogprogramdir/.fogsettings
            grep -q "plainrouter=" $fogprogramdir/.fogsettings && sed -i "s/plainrouter=?['\"][0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}?['\"]/plainrouter='$plainrouter'/g" $fogprogramdir/.fogsettings
            grep -q "dnsaddress=" $fogprogramdir/.fogsettings && sed -i "s/dnsaddress=?['\"].*?['\"]/dnsaddress='$dnsaddress'/g" $fogprogramdir/.fogsettings
            grep -q "dnsbootimage=" $fogprogramdir/.fogsettings && sed -i "s/dnsbootimage=?['\"].*?['\"]/dnsbootimage='$dnsbootimage'/g" $fogprogramdir/.fogsettings
            grep -q "password=" $fogprogramdir/.fogsettings && sed -i "s/password=?['\"].*?['\"]/password='$password'/g" $fogprogramdir/.fogsettings
            grep -q "osid=" $fogprogramdir/.fogsettings && sed -i "s/osid=?['\"].*?['\"]/osid='$osid'/g" $fogprogramdir/.fogsettings
            grep -q "osname=" $fogprogramdir/.fogsettings && sed -i "s/osname=?['\"].*?['\"]/osname='$osname'/g" $fogprogramdir/.fogsettings
            grep -q "dodhcp=" $fogprogramdir/.fogsettings && sed -i "s/dodhcp=?['\"].*?['\"]/dodhcp='$dodhcp'/g" $fogprogramdir/.fogsettings
            grep -q "bldhcp=" $fogprogramdir/.fogsettings && sed -i "s/bldhcp=?['\"].*?['\"]/bldhcp='$bldhcp'/g" $fogprogramdir/.fogsettings
            grep -q "blexports=" $fogprogramdir/.fogsettings && sed -i "s/blexports=?['\"].*?['\"]/blexports='$blexports'/g" $fogprogramdir/.fogsettings
            grep -q "installtype=" $fogprogramdir/.fogsettings && sed -i "s/installtype=?['\"].*?['\"]/installtype='$installtype'/g" $fogprogramdir/.fogsettings
            grep -q "snmysqluser=" $fogprogramdir/.fogsettings && sed -i "s/snmysqluser=?['\"].*?['\"]/snmysqluser='$snmysqluser'/g" $fogprogramdir/.fogsettings
            grep -q "snmysqlpass=" $fogprogramdir/.fogsettings && sed -i "s/snmysqlpass=?['\"].*?['\"]/snmysqlpass='$snmysqlpass'/g" $fogprogramdir/.fogsettings
            grep -q "snmysqlhost=" $fogprogramdir/.fogsettings && sed -i "s/snmysqlhost=?['\"].*?['\"]/snmysqlhost='$snmysqlhost'/g" $fogprogramdir/.fogsettings
            grep -q "installlang=" $fogprogramdir/.fogsettings && sed -i "s/installlang=?['\"].*?['\"]/installlang='$installlang'/g" $fogprogramdir/.fogsettings
            grep -q "donate=" $fogprogramdir/.fogsettings && sed -i "s/donate=?['\"].*?['\"]/donate='$donate'/g" $fogprogramdir/.fogsettings
            grep -q "storageLocation=" $fogprogramdir/.fogsettings && sed -i "s#storageLocation=?['\"].*?['\"]#storageLocation='$storageLocation'#g" $fogprogramdir/.fogsettings
            grep -q "fogupdateloaded=" $fogprogramdir/.fogsettings && sed -i "s/fogupdateloaded=?['\"].*?['\"]/fogupdateloaded=$fogupdateloaded/g" $fogprogramdir/.fogsettings
            grep -q "storageftpuser=" $fogprogramdir/.fogsettings && sed -i "s/storageftpuser=?['\"].*?['\"]/storageftpuser='$storageftpuser'/g" $fogprogramdir/.fogsettings
            grep -q "storageftppass=" $fogprogramdir/.fogsettings && sed -i "s/storageftppass=?['\"].*?['\"]/storageftppass='$storageftppass'/g" $fogprogramdir/.fogsettings
            grep -q "docroot=" $fogprogramdir/.fogsettings && sed -i "s#docroot=?['\"].*?['\"]#docroot='$docroot'#g" $fogprogramdir/.fogsettings
            grep -q "webroot=" $fogprogramdir/.fogsettings && sed -i "s#webroot=?['\"].*?['\"]#webroot='$webroot'#g" $fogprogramdir/.fogsettings
            grep -q "caCreated=" $fogprogramdir/.fogsettings && sed -i "s/caCreated=?['\"].*?['\"]/caCreated='$caCreated'/g" $fogprogramdir/.fogsettings
            grep -q "startrange=" $fogprogramdir/.fogsettings && sed -i "s/startrange=?['\"].*?['\"]/startrange='$startrange'/g" $fogprogramdir/.fogsettings
            grep -q "endrange=" $fogprogramdir/.fogsettings && sed -i "s/endrange=?['\"].*?['\"]/endrange='$endrange'/g" $fogprogramdir/.fogsettings
            grep -q "bootfilename=" $fogprogramdir/.fogsettings && sed -i "s/bootfilename=?['\"].*?['\"]/bootfilename='$bootfilename'/g" $fogprogramdir/.fogsettings
            grep -q "packages=" $fogprogramdir/.fogsettings && sed -i "s/packages=?['\"].*?['\"]/packages='$packages'/g" $fogprogramdir/.fogsettings
        else
            echo "## Start of FOG Settings
## Created by the FOG Installer
## Version: $version
## Install time: $tmpDte

ipaddress='$ipaddress'
interface='$interface'
submask='$submask'
routeraddress='$routeraddress'
plainrouter='$plainrouter'
dnsaddress='$dnsaddress'
dnsbootimage='$dnsbootimage'
password='$password'
osid='$osid'
osname='$osname'
dodhcp='$dodhcp'
bldhcp='$bldhcp'
blexports='$blexports'
installtype='$installtype'
snmysqluser='$snmysqluser'
snmysqlpass='$snmysqlpass'
snmysqlhost='$snmysqlhost'
installlang='$installlang'
donate='$donate'
storageLocation='$storageLocation'
fogupdateloaded=1
storageftpuser='$storageftpuser'
storageftppass='$storageftppass'
docroot='$docroot'
webroot='$webroot'
caCreated='$caCreated'
startrange='$startrange'
endrange='$endrange'
bootfilename='$bootfilename'
packages='$packages'
## End of FOG Settings
" >> "$fogprogramdir/.fogsettings"
        fi
    else
        echo "## Start of FOG Settings
## Created by the FOG Installer
## Version: $version
## Install time: $tmpDte

ipaddress='$ipaddress'
interface='$interface'
submask='$submask'
routeraddress='$routeraddress'
plainrouter='$plainrouter'
dnsaddress='$dnsaddress'
dnsbootimage='$dnsbootimage'
password='$password'
osid='$osid'
osname='$osname'
dodhcp='$dodhcp'
bldhcp='$bldhcp'
blexports='$blexports'
installtype='$installtype'
snmysqluser='$snmysqluser'
snmysqlpass='$snmysqlpass'
snmysqlhost='$snmysqlhost'
installlang='$installlang'
donate='$donate'
storageLocation='$storageLocation'
fogupdateloaded=1
storageftpuser='$storageftpuser'
storageftppass='$storageftppass'
docroot='$docroot'
webroot='$webroot'
caCreated='$caCreated'
startrange='$startrange'
endrange='$endrange'
bootfilename='$bootfilename'
packages='$packages'
## End of FOG Settings
" > "$fogprogramdir/.fogsettings"
    fi
}
displayBanner() {
    echo
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
    echo
    echo "  ###########################################";
    echo "  #     FOG                                 #";
    echo "  #     Free Computer Imaging Solution      #";
    echo "  #                                         #";
    echo "  #     http://www.fogproject.org/          #";
    echo "  #                                         #";
    echo "  #     Credits:                            #";
    echo "  #     http://fogproject.org/Credits       #"
    echo "  #     GNU GPL Version 3                   #";
    echo "  ###########################################";
    echo
}
createSSLCA() {
    if [ "$recreateCA" == "yes" -o "$caCreated" != "yes" -o ! -e "/opt/fog/snapins/CA" -o ! -e "/opt/fog/snapins/CA/.fogCA.key" ]; then
        mkdir -p "/opt/fog/snapins/CA" >/dev/null 2>&1
        dots "Creating SSL CA"
        openssl genrsa -out "/opt/fog/snapins/CA/.fogCA.key" 4096 >/dev/null 2>&1
        openssl req -x509 -new -nodes -key /opt/fog/snapins/CA/.fogCA.key -days 3650 -out /opt/fog/snapins/CA/.fogCA.pem >/dev/null 2>&1 << EOF
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
    if [ "$recreateKeys" == "yes" -o "$recreateCA" == "yes" -o "$caCreated" != "yes" -o ! -e "/opt/fog/snapins/ssl" -o ! -e "/opt/fog/snapins/ssl/.srvprivate.key" ]; then
        dots "Creating SSL Private Key"
        mkdir -p /opt/fog/snapins/ssl &>/dev/null
        openssl genrsa -out "/opt/fog/snapins/ssl/.srvprivate.key" 4096 >/dev/null 2>&1
        openssl req -new -key "/opt/fog/snapins/ssl/.srvprivate.key" -out "/opt/fog/snapins/ssl/fog.csr" >/dev/null 2>&1 << EOF
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
    mkdir -p $webdirdest/management/other/ssl >/dev/null 2>&1
    openssl x509 -req -in "/opt/fog/snapins/ssl/fog.csr" -CA "/opt/fog/snapins/CA/.fogCA.pem" -CAkey "/opt/fog/snapins/CA/.fogCA.key" -CAcreateserial -out "$webdirdest/management/other/ssl/srvpublic.crt" -days 3650 >/dev/null 2>&1
    errorStat $?
    dots "Creating auth pub key and cert"
    cp /opt/fog/snapins/CA/.fogCA.pem $webdirdest/management/other/ca.cert.pem >/dev/null 2>&1
    openssl x509 -outform der -in $webdirdest/management/other/ca.cert.pem -out $webdirdest/management/other/ca.cert.der >/dev/null 2>&1
    errorStat $?
    dots "Resetting SSL Permissions"
    chown -R $apacheuser:$apacheuser $webdirdest/management/other >/dev/null 2>&1
    errorStat $?
    dots "Setting up SSL FOG Server"
    echo "<VirtualHost *:80>
    KeepAlive Off
    ServerName $ipaddress
    DocumentRoot $docroot
    ${forcehttps}RewriteEngine On
    ${forcehttps}RewriteRule /management/other/ca.cert.der$ - [L]
    ${forcehttps}RewriteRule /management/ https://%{HTTP_HOST}%{REQUEST_URI}%{QUERY_STRING} [R,L]
</VirtualHost>
<VirtualHost *:443>
    KeepAlive Off
    Servername $ipaddress
    DocumentRoot $docroot
    SSLEngine On
    SSLCertificateFile $webdirdest/management/other/ssl/srvpublic.crt
    SSLCertificateKeyFile /opt/fog/snapins/ssl/.srvprivate.key
    SSLCertificateChainFile $webdirdest/management/other/ca.cert.der
</VirtualHost>" > "$etcconf";
    errorStat $?
    dots "Restarting Apache2 for fog vhost"
    ln -s $webdirdest $webdirdest >/dev/null 2>&1
    if [ "$osid" -eq 2 ]; then
        a2enmod php5 >/dev/null 2>&1
        a2enmod rewrite >/dev/null 2>&1
        a2enmod ssl >/dev/null 2>&1
        a2ensite "001-fog" >/dev/null 2>&1
        if [ "$systemctl" == "yes" ]; then
            systemctl restart apache2 php5-fpm >/dev/null 2>&1
            sleep 2
            systemctl status apache2 php5-fpm >/dev/null 2>&1
        else
            service apache2 restart >/dev/null 2>&1
            sleep 2
            service apache2 status >/dev/null 2>&1
        fi
    elif [ "$systemctl" == "yes" ]; then
        systemctl restart httpd php-fpm >/dev/null 2>&1
        sleep 2
        systemctl status httpd php-fpm >/dev/null 2>&1
    else
        service httpd restart >/dev/null 2>&1
        service php-fpm restart >/dev/null 2>&1
        sleep 2
        service httpd status >/dev/null 2>&1
        service php-fpm status >/dev/null 2>&1
    fi
    errorStat $?
    caCreated="yes"
}
configureHttpd() {
    dots "Stopping web service"
    if [ "$osid" -eq 2 ]; then
        if [ "$systemctl" == "yes" ]; then
            systemctl stop apache2 php5-fpm >/dev/null 2>&1
        fi
    elif [ "$systemctl" == "yes" ]; then
        systemctl stop httpd php-fpm >/dev/null 2>&1
    else
        service httpd stop >/dev/null 2>&1
        service php-fpm stop >/dev/null 2>&1
    fi
    errorStat $?
    if [ -f "$etcconf" ]; then
        dots "Removing vhost file"
        if [ "$osid" == "2" ]; then
            a2dissite 0001-fog >/dev/null 2>&1
        fi
        rm $etcconf >/dev/null 2>&1
        errorStat $?
    fi
    if [ "$installtype" == "N" -a "$fogupdateloaded" != 1 -a -z "$autoaccept" ]; then
        echo -n " * Is the MySQL password blank? (Y/n) "
        read dummy
        case "$dummy" in
            [nN]*)
            echo -n " * Enter the MySQL password: "
            read -s PASSWORD1
            echo
            echo -n " * Re-enter the MySQL password: "
            read -s PASSWORD2
            echo
            if [ ! -z "$PASSWORD1" -a "$PASSWORD2" == $PASSWORD1 ]; then
                dbpass=$PASSWORD1
            else
                dppass=""
                while [ ! -z "$PASSWORD1" -a "$PASSWORD2" == $PASSWORD1 ]; do
                    echo -n " * Enter the MySQL password: "
                    read -s PASSWORD1
                    echo
                    echo -n " * Re-enter the MySQL password: "
                    read -s PASSWORD2
                    echo
                    if [ ! -z "$PASSWORD1" -a "$PASSWORD2" == $PASSWORD1 ]; then
                        dbpass=$PASSWORD1
                    fi
                done
            fi
            if [ "$snmysqlpass" != "$dbpass" ]; then
                snmysqlpass=$dbpass
            fi
            ;;
            [yY]*)
            ;;
            *)
            ;;
        esac
    fi
    if [ "$installtype" == "S" -o "$fogupdateloaded" = "1" ]; then
        if [ ! -z "$snmysqlhost" -a "$snmysqlhost" != "$dbhost" ]; then
            dbhost=$snmysqlhost
        elif [ ! -z "$snmysqlhost" ]; then
            dbhost="p:127.0.0.1"
        fi
    fi
    if [ ! -z "$snmysqluser" -a "$snmysqluser" != "$dbuser" ]; then
        dbuser=$snmysqluser
    fi
    dots "Setting up Apache and PHP files"
    if [ "$osid" -eq 3 ]; then
        echo -e "<FilesMatch \.php$>\n\tSetHandler \"proxy:unix:/run/php-fpm/php-fpm.sock|fcgi://127.0.0.1/\"\n</FilesMatch>\n<IfModule dir_module>\n\tDirectoryIndex index.php index.html\n</IfModule>" >> /etc/httpd/conf/httpd.conf
        sed -i 's@#LoadModule ssl_module modules/mod_ssl.so@LoadModule ssl_module modules/mod_ssl.so@g' /etc/httpd/conf/httpd.conf >/dev/null 2>&1
        sed -i 's@#LoadModule socache_shmcb_module modules/mod_socache_shmcb.so@LoadModule socache_shmcb_module modules/mod_socache_shmcb.so@g' /etc/httpd/conf/httpd.conf >/dev/null 2>&1
        echo -e "# FOG Virtual Host\nInclude conf/extra/fog.conf" >> /etc/httpd/conf/httpd.conf >/dev/null 2>&1
        sed -i 's/;extension=mysqli.so/extension=mysqli.so/g' $phpini >/dev/null 2>&1
        sed -i 's/;extension=openssl.so/extension=openssl.so/g' $phpini >/dev/null 2>&1
        sed -i 's/;extension=mcrypt.so/extension=mcrypt.so/g' $phpini >/dev/null 2>&1
        sed -i 's/;extension=posix.so/extension=posix.so/g' $phpini >/dev/null 2>&1
        sed -i 's/;extension=sockets.so/extension=sockets.so/g' $phpini >/dev/null 2>&1
        sed -i 's/;extension=ftp.so/extension=ftp.so/g' $phpini >/dev/null 2>&1
        sed -i 's/open_basedir\ =/;open_basedir\ ="/g' $phpini >/dev/null 2>&1
    fi
    sed -i 's/post_max_size\ \=\ 8M/post_max_size\ \=\ 100M/g' $phpini >/dev/null 2>&1
    sed -i 's/upload_max_filesize\ \=\ 2M/upload_max_filesize\ \=\ 100M/g' $phpini >/dev/null 2>&1
    errorStat $?
    dots "Testing and removing symbolic links if found"
    if [ -h "/var/www/fog" ]; then
        rm -f "/var/www/fog" >/dev/null 2>&1
    else
        true
    fi
    if [ -h "/var/www/html/fog" ]; then
        rm -f "/var/www/html/fog" >/dev/null 2>&1
    else
        true
    fi
    errorStat $?
    dots "Backing up old data"
    if [ -d "$backupPath/fog_web_${version}.BACKUP" ]; then
        rm -rf "$backupPath/fog_web_${version}.BACKUP" >/dev/null 2>&1
    fi
    if [ -d "$webdirdest" ]; then
        cp -RT "$webdirdest" "${backupPath}/fog_web_${version}.BACKUP" >/dev/null 2>&1
        rm -rf "$webdirdest" >/dev/null 2>&1
    fi
    if [ "$osid" == 2 ]; then
        if [ -d "/var/www/fog" ]; then
            rm -rf /var/www/fog >/dev/null 2>&1
        fi
    fi
    mkdir -p "$webdirdest" >/dev/null 2>&1
    if [ -d "/var/www" ] && [ ! -h "/var/www/fog" -o ! -d "/var/www/fog" ]; then
        ln -s $webdirdest  /var/www/fog >/dev/null 2>&1
    fi
    errorStat $?
    if [ -d "${backupPath}/fog_web_${version}.BACKUP" ]; then
        dots "Copying back old web folder as is";
        cp -Rf ${backupPath}/fog_web_${version}.BACKUP/* $webdirdest/;
        errorStat $?
        dots "Ensuring all classes are lowercased";
        for i in $(find $webdirdest -type f -name "*[A-Z]*\.class\.php")
        do
            mv "$i" "$(echo $i | tr A-Z a-z)" >/dev/null 2>&1
        done
        for i in $(find $webdirdest -type f -name "*[A-Z]*\.event\.php")
        do
            mv "$i" "$(echo $i | tr A-Z a-z)" >/dev/null 2>&1
        done
        for i in $(find $webdirdest -type f -name "*[A-Z]*\.hook\.php")
        do
            mv "$i" "$(echo $i | tr A-Z a-z)" >/dev/null 2>&1
        done
        errorStat $?
    fi
    dots "Copying new files to web folder";
    cp -Rf $webdirsrc/* $webdirdest/
    errorStat $?
    dots "Creating config file"
    echo "<?php
class Config {
    /** @function __construct() Calls the required functions to define items
     * @return void
     */
    public function __construct() {
        self::db_settings();
        self::svc_setting();
        if (\$_REQUEST['node'] == 'schemaupdater') self::init_setting();
    }
    /** @function db_settings() Defines the database settings for FOG
     * @return void
     */
    private static function db_settings() {
        define('DATABASE_TYPE','mysql'); // mysql or oracle
        define('DATABASE_HOST','$dbhost');
        define('DATABASE_NAME','fog');
        define('DATABASE_USERNAME','$dbuser');
        define('DATABASE_PASSWORD','$snmysqlpass');
    }
    /** @function svc_setting() Defines the service settings
     * (e.g. FOGMulticastManager)
     * @return void
     */
    private static function svc_setting() {
        define('UDPSENDERPATH','/usr/local/sbin/udp-sender');
        define('MULTICASTLOGPATH','/opt/fog/log/multicast.log');
        define('MULTICASTDEVICEOUTPUT','/dev/tty2');
        define('MULTICASTSLEEPTIME',10);
        define('MULTICASTINTERFACE','${interface}');
        define('UDPSENDER_MAXWAIT',null);
        define('LOGMAXSIZE',1000000);
        define('REPLICATORLOGPATH','/opt/fog/log/fogreplicator.log');
        define('REPLICATORDEVICEOUTPUT','/dev/tty3');
        define('REPLICATORSLEEPTIME', 600);
        define('REPLICATORIFCONFIG','/sbin/ifconfig');
        define('SCHEDULERLOGPATH','/opt/fog/log/fogscheduler.log');
        define('SCHEDULERDEVICEOUTPUT','/dev/tty4');
        define('SCHEDULERSLEEPTIME',60);
        define('SNAPINREPLOGPATH','/opt/fog/log/fogsnapinrep.log');
        define('SNAPINREPDEVICEOUTPUT','/dev/tty5');
        define('SNAPINREPSLEEPTIME',600);
        define('SERVICELOGPATH','/opt/fog/log/servicemaster.log');
        define('SERVICESLEEPTIME',3);
        define('PINGHOSTLOGPATH','/opt/fog/log/pinghosts.log');
        define('PINGHOSTDEVICEOUTPUT','/dev/tty5');
        define('PINGHOSTSLEEPTIME',300);
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
        define('PXE_IMAGE_DNSADDRESS', \"${dnsbootimage}\");
        define('STORAGE_HOST', \"${ipaddress}\");
        define('STORAGE_FTP_USERNAME', \"${username}\");
        define('STORAGE_FTP_PASSWORD', \"${password}\");
        define('STORAGE_DATADIR', '${storageLocation}/');
        define('STORAGE_DATADIR_UPLOAD', '${storageLocation}/dev/');
        define('STORAGE_BANDWIDTHPATH', '/${webroot}status/bandwidth.php');
        define('STORAGE_INTERFACE','${interface}');
        define('UPLOADRESIZEPCT',5);
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
        define('FOG_UPLOADIGNOREPAGEHIBER',true);
        define('FOG_DONATE_MINING', \"${donate}\");
     }
 }" > "${webdirdest}/lib/fog/config.class.php"
    errorStat $?
    dots "Downloading inits, kernels, and the fog client"
    clientVer="`awk -F\' /"define\('FOG_CLIENT_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]'`"
    clienturl="https://github.com/FOGProject/fog-client/releases/download/${clientVer}/FOGService.msi"
    curl --silent -ko "${webdirdest}/service/ipxe/init.xz" https://fogproject.org/inits/init.xz -ko "${webdirdest}/service/ipxe/init_32.xz" https://fogproject.org/inits/init_32.xz -ko "${webdirdest}/service/ipxe/bzImage" https://fogproject.org/kernels/bzImage -ko "${webdirdest}/service/ipxe/bzImage32" https://fogproject.org/kernels/bzImage32 >/dev/null 2>&1 && curl --silent -ko "${webdirdest}/client/FOGService.msi" -L $clienturl >/dev/null 2>&1
    errorStat $?
    if [ "$osid" -eq 2 ]; then
        php -m | grep mysqlnd >/dev/null 2>&1
        if [ "$?" != 0 ]; then
            php5enmod mysqlnd >/dev/null 2>&1
            if [ "$?" != 0 ]; then
                if [ -e "/etc/php5/conf.d/mysqlnd.ini" ]; then
                    cp -f "/etc/php5/conf.d/mysqlnd.ini" "/etc/php5/mods-available/php5-mysqlnd.ini" >/dev/null 2>&1
                    php5enmod mysqlnd >/dev/null 2>&1
                fi
            fi
        fi
        php -m | grep mcrypt >/dev/null 2>&1
        if [ "$?" != 0 ]; then
            php5enmod mcrypt >/dev/null 2>&1
            if [ "$?" != 0 ]; then
                if [ -e "/etc/php5/conf.d/mcrypt.ini" ]; then
                    cp -f "/etc/php5/conf.d/mcrypt.ini" "/etc/php5/mods-available/php5-mcrypt.ini" >/dev/null 2>&1
                    php5enmod mcrypt >/dev/null 2>&1
                fi
            fi
        fi
        cp /etc/apache2/mods-available/php5* /etc/apache2/mods-enabled/ >/dev/null 2>&1
    fi
    dots "Enabling apache and fpm services on boot"
    if [ "$osid" -eq 2 ]; then
        if [ "$systemctl" == "yes" ]; then
            systemctl enable apache2 >/dev/null 2>&1
            systemctl enable php5-fpm >/dev/null 2>&1
        else
            sysv-rc-conf apache2 on >/dev/null 2>&1
            sysv-rc-conf php5-fpm on >/dev/null 2>&1
        fi
    elif [ "$systemctl" == "yes" ]; then
        systemctl enable httpd php-fpm >/dev/null 2>&1
    else
        chkconfig php-fpm on >/dev/null 2>&1
        chkconfig httpd on >/dev/null 2>&1
    fi
    errorStat $?
    createSSLCA
    dots "Changing permissions on apache log files"
    chmod +rx $apachelogdir
    chmod +rx $apacheerrlog
    chmod +rx $apacheacclog
    chown -R ${apacheuser}:${apacheuser} $webdirdest
    errorStat $?
    rm -f "$webdirdest/mobile/css/font-awesome.css" $webdirdest/mobile/{fonts,less,scss} &>/dev/null 2>&1;
    ln -s "$webdirdest/management/css/font-awesome.css" "$webdirdest/mobile/css/font-awesome.css";
    ln -s "$webdirdest/management/fonts" "$webdirdest/mobile/";
    ln -s "$webdirdest/management/less" "$webdirdest/mobile/";
    ln -s "$webdirdest/management/scss" "$webdirdest/mobile/";
    chown -R ${apacheuser}:${apacheuser} "$webdirdest"
}
configureDHCP() {
    dots "Setting up and starting DHCP Server"
    if [ -f "$dhcpconfig" ]; then
        mv "$dhcpconfig" "${dhcpconfig}.fogbackup"
    fi
    serverip=`/sbin/ip -4 addr show $interface | awk -F'[ /]+' '/global/ {print $3}'`
    if [ -z "$serverip" ]; then
        serverip=`/sbin/ifconfig $interface | awk '/(cast)/ {print $2}' | cut -d ':' -f2 | head -n2 | tail -n1`
    fi
    network=`mask2network $serverip $submask`
    networkbase=`echo "$serverip" | cut -d. -f1-3`
    if [ -z "$startrange" ]; then
        startrange="${networkbase}.10"
    fi
    if [ -z "$endrange" ]; then
        endrange="${networkbase}.254"
    fi
    dhcptouse="$dhcpconfig"
    if [ -f "${dhcpconfigother}" ]; then
        dhcptouse="$dhcpconfigother"
    fi
    if [ -z "$bootfilename" ]; then
        bootfilename="undionly.kpxe";
    fi
    if [ "$bldhcp" -eq 1 ]; then
        echo -e "# DHCP Server Configuration file\n#see /usr/share/doc/dhcp*/dhcpd.conf.sample\n# This file was created by FOG\n\n#Definition of PXE-specific options\n# Code 1: Multicast IP Address of bootfile\n# Code 2: UDP Port that client should monitor for MTFTP Responses\n# Code 3: UDP Port that MTFTP servers are using to listen for MTFTP requests\n# Code 4: Number of seconds a client must listen for activity before trying\n#         to start a new MTFTP transfer\n# Code 5: Number of seconds a client must listen before trying to restart\n#         a MTFTP transfer\n\n" > "$dhcptouse"
        echo -e "option space PXE;\noption PXE.mtftp-ip code 1 = ip-address;\noption PXE.mtftp-cport code 2 = unsigned integer 16;\noption PXE.mtftp-sport code 3 = unsigned integer 16;\noption PXE.mtftp-tmout code 4 = unsigned integer 8;\noption PXE.mtftp-delay code 5 = unsigned integer 8;\noption arch code 93 = unsigned integer 16; # RFC4578\n\n" >> "$dhcptouse"
        echo -e "use-host-decl-names on;\nddns-update-style interim;\nignore client-updates;\nnext-server $ipaddress;\n\n" >> "$dhcptouse"
        echo -e "# Specify subnet of ether device you do NOT want service. for systems with\n# two or more ethernet devices.\n# subnet 136.165.0.0 netmask 255.255.0.0 {}\n\n" >> "$dhcptouse"
        echo -e "subnet $network netmask $submask {\n\toption subnet-mask $submask;\n\trange dynamic-bootp $startrange $endrange;\n\tdefault-lease-time 21600;\n\tmax-lease-time 43200;\n\t$dnsaddress\n\t$routeraddress\n\tfilename \"$bootfilename\";\n}" >> "$dhcptouse";
        if [ "$systemctl" == "yes" ]; then
            systemctl enable $dhcpd >/dev/null 2>&1
            systemctl restart $dhcpd >/dev/null 2>&1
            sleep 2
            systemctl status $dhcpd >/dev/null 2>&1
            if [ "$?" != 0 -a "$osid" -eq 2 ]; then
                sysv-rc-conf ${dhcpd} on >/dev/null 2>&1
                /etc/init.d/${dhcpd} stop >/dev/null 2>&1
                /etc/init.d/${dhcpd} start >/dev/null 2>&1
            fi
        elif [ "$osid" -eq 1 ]; then
            chkconfig $dhcpd on >/dev/null 2>&1
            service $dhcpd restart >/dev/null 2>&1
            sleep 2
            systemctl status $dhcpd >/dev/null 2>&1
        elif [ "$osid" -eq 2 ]; then
            sysv-rc-conf ${dhcpd} on >/dev/null 2>&1
            /etc/init.d/${dhcpd} stop >/dev/null 2>&1
            /etc/init.d/${dhcpd} start >/dev/null 2>&1
        fi
        errorStat $?
    else
        echo "Skipped"
    fi
}
