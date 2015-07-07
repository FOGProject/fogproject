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
    max=60
    if [ -n "$1" ]; then
        n=`expr $max - ${#1}`
        echo -n " * ${1:0:max}"
        if [ "$n" -gt 0 ]; then
            for dot in `seq $n`; do
                printf %s .
            done
        fi
    fi
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
    echo -e "Usage: $0 [-hdUuHSCKY] [-f <filename>]";
    echo -e "\t-h -? --help\t\t\tDisplay this info"
    echo -e "\t-d    --no-defaults\t\tDon't guess defaults"
    echo -e "\t-U    --no-upgrade\t\tDon't attempt to upgrade"
    echo -e "\t-H    --no-htmldoc\t\tNo htmldoc, means no PDFs"
    echo -e "\t-S    --force-https\t\tForce HTTPS redirect"
    echo -e "\t-C    --recreate-CA\t\tRecreate the CA Keys"
    echo -e "\t-K    --recreate-keys\t\tRecreate the SSL Keys"
    echo -e "\t-Y -y --autoaccept\t\tAuto accept defaults and install"
    echo -e "\t-f    --file\t\t\tUse different update file"
    echo -e "\t      --uninstall\t\tUninstall FOG"
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
	vsftp=`vsftpd -version 0>&1 | awk -F'version ' '{print $2}'`
    vsvermaj=`echo $vsftp | awk -F. '{print $1}'`
	vsverbug=`echo $vsftp | awk -F. '{print $3}'`
    seccompsand=""
	if [ "$vsvermaj" -gt 3 ] || [ "$vsvermaj" -eq 3 -a "$vsverbug" -ge 2 ]; then
		seccompsand="seccomp_sandbox=NO"
	fi
	echo -e  "anonymous_enable=NO\nlocal_enable=YES\nwrite_enable=YES\nlocal_umask=022\ndirmessage_enable=YES\nxferlog_enable=YES\nconnect_from_port_20=YES\nxferlog_std_format=YES\nlisten=YES\npam_service_name=vsftpd\nuserlist_enable=NO\ntcp_wrappers=YES\n$seccompsand" > "$ftpconfig"
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
    echo -e "#!ipxe\ncpuid --ext 29 && set arch x86_64 || set arch i386\nparams\nparam mac0 \${net0/mac}\nparam arch \${arch}\nparam product \${product}\nparam manufacturer \${product}\nparam ipxever \${version}\nparam filename \${filename}\nisset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\nisset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n:bootme\nchain http://${ipaddress}/fog/service/ipxe/boot.php##params" > "${tftpdirdst}/default.ipxe"
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
	find "${tftpdirdst}" ! -type d -exec chmod 644 {} \;
	configureDefaultiPXEfile;
    if [ -f "$tftpconfig" ]; then
		mv "$tftpconfig" "${tftpconfig}.fogbackup";
	fi
	echo -e "# default: off\n# description: The tftp server serves files using the trivial file transfer \n#	protocol.  The tftp protocol is often used to boot diskless \n#	workstations, download configuration files to network-aware printers, \n#	and to start the installation process for some operating systems.\nservice tftp\n{\n	socket_type		= dgram\n	protocol		= udp\n	wait			= yes\n	user			= root\n	server			= /usr/sbin/in.tftpd\n	server_args		= -s ${tftpdirdst}\n	disable			= no\n	per_source		= 11\n	cps			= 100 2\n	flags			= IPv4\n}" > "$tftpconfig";
    if [ "$systemctl" == "yes" ]; then
        systemctl enable xinetd >/dev/null 2>&1
        systemctl restart xinetd >/dev/null 2>&1
        sleep 2
        systemctl status xinetd >/dev/null 2>&1
    elif [ "$osid" -eq 2 ]; then
        blUpstart=0
        if [ -f "$tftpconfigupstartdefaults" ]; then
            blUpstart=1
        fi
        if [ "$blUpstart" = "1" ]; then
            echo -e "# /etc/default/tftpd-hpa\n# FOG Modified version\nTFTP_USERNAME=\"root\"\nTFTP_DIRECTORY=\"/tftpboot\"\nTFTP_ADDRESS=\":69\"\nTFTP_OPTIONS=\"-s\"" > "$tftpconfigupstartdefaults"
            sysv-rc-conf xinetd off >/dev/null 2>&1
            service xinetd stop >/dev/null 2>&1
            sysv-rc-conf tftpd-hpa on >/dev/null 2>&1
            service tftpd-hpa stop >/dev/null 2>&1
            sleep 2
            service tftpd-hpa start >/dev/null 2>&1
        else
            sysv-rc-conf xinetd on >/dev/null 2>&1
            $initdpath/xinetd stop >/dev/null 2>&1
            $initdpach/xinetd start >/dev/null 2>&1
        fi
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
    if [ "$osid" -eq 1 ]; then
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
        fi
    elif [ "$osid" -eq 2 ]; then
        dots "Adding needed repository"
        DEBIAN_FRONTEND=noninteractive $packageinstaller python-software-properties software-properties-common >/dev/null 2>&1;
        if [[ "$linuxReleaseName" == +(*'buntu'*|*'int'*) ]]; then
            add-apt-repository -y ppa:ondrej/php5-5.6 >/dev/null 2>&1
            if [ "$?" != 0 ]; then
                echo "deb http://ppa.launchpad.net/ondrej/php5-5.6/ubuntu vivid main" > "/etc/apt/sources.list.d/ondrej-ubuntu-php5-5_6-vivid.list"
                echo "deb http://ppa.launchpad.net/ondrej/php5/ubuntu vivid main" > "/etc/apt/sources.list.d/ondrej-ubuntu-php5-vivid.list"
            fi
            true
        elif [[ "$linuxReleaseName" == +(*'ebian'*) ]]; then
            if [ "$OSVersion" -eq 7 ]; then
                debcode="wheezy";
                echo -e "deb http://packages.dotdeb.org wheezy-php56 all\ndeb-src http://packages.dotdeb.org wheezy-php56 all\n" >> "/etc/apt/sources.list";
            fi
        fi
    fi
    errorStat $?
    dots "Preparing Package Manager"
    $packmanUpdate >/dev/null 2>&1
    if [ "$osid" -eq 2 ]; then
        if [ "$?" != 0 ] && [[ "$linuxReleaseName" == +(*'buntu'*) ]]; then
            cp /etc/apt/sources.list /etc/apt/source.list.original_fog
            sed -i -e 's/\/\/*archive.ubuntu.com\|\/\/*security.ubuntu.com/\/\/old-releases.ubuntu.com/g' /etc/apt/sources.list
            $packmanUpdate >/dev/null 2>&1
            if [ "$?" != 0 ]; then
                cp -f /etc/apt/sources.list.original_fog /etc/apt/sources.list >/dev/null 2>&1
                rm -f /etc/apt/sources.list.original_fog >/dev/null 2>&1
                false
            fi
        fi
        if [[ "$linuxRelease" == +(*[Dd]'ebian'*) ]] && [ "$OSVersion" -eq 7 ]; then
            packages="${packages} libapache2-mod-php5";
        fi
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
            . ../lib/redhat/functions.sh
            . ../lib/redhat/config.sh
        ;;
        "2")
            echo -e "\n\n  Starting Debian based Installation\n\n"
            osname="Debian"
            . ../lib/ubuntu/functions.sh
            . ../lib/ubuntu/config.sh
        ;;
        "3")
            echo -e "\n\n  Starting Arch Installation\n\n"
            osname="Arch"
            . ../lib/arch/functions.sh
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
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname"
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
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname"
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
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname"
    for serviceItem in $serviceList; do
        dots "Setting $serviceItem script executable"
        chmod 755 $initdpath/$serviceItem >/dev/null 2>&1
        errorStat $?
        dots "Enabling $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl enable $serviceItem >/dev/null 2>&1
        elif [ "$osid" -eq 2 ]; then
            sysv-rc-conf $serviceItem on >/dev/null 2>&1
            if [[ "$linuxReleaseName" == +(*'buntu'*) ]]; then
                /usr/lib/insserv/insserv -d $initdpath/$serviceItem >/dev/null 2>&1
            else
                insserv -d $initdpath/$serviceItem >/dev/null 2>&1
            fi
        elif [ "$osid" -eq 1 ]; then
            chkconfig $serviceItem on >/dev/null 2>&1
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
		systemctl="yes";
		systemctl enable mariadb.service >/dev/null 2>&1 && \
		systemctl restart mariadb.service >/dev/null 2>&1 && \
		systemctl status mariadb.service >/dev/null 2>&1
		if [ "$?" != "0" ]; then
			systemctl enable mysql.service >/dev/null 2>&1 && \
			systemctl restart mysql.service >/dev/null 2>&1 && \
			systemctl status mysql.service >/dev/null 2>&1
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
	echo "<?php
define( \"WEBROOT\", \"${webdirdest}\" );" > ${servicedst}/etc/config.php
    startInitScript
}
configureNFS() {
    echo -e "$storageLocation *(ro,sync,no_wdelay,no_subtree_check,insecure_locks,no_root_squash,insecure,fsid=0)\n$storageLocation/dev *(rw,async,no_wdelay,no_subtree_check,no_root_squash,insecure,fsid=1)" > "$nfsconfig";
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
                if [ -f "${webdirdest}/lib/fog/Config.class.php" ]; then
                    password=`cat ${webdirdest}/lib/fog/Config.class.php | grep TFTP_FTP_PASSWORD | cut -d"," -f2 | cut -d"\"" -f2`;
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
        echo -e "  * New password set for:\nusername: $username\npassword: $password\n"
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
        touch "$storage/.mntcheck" >/dev/null 2>&1
        chmod -R 777 "$storage" >/dev/null 2>&1
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
        touch "$storageupload/.mntcheck" >/dev/null 2>&1
        chmod -R 777 "$storageupload" >/dev/null 2>&1
    fi
    errorStat $?
}
clearScreen() {
    clear
}
writeUpdateFile() {
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
storageLocation=\"$storageLocation\";
mysql_conntype=\"$mysql_conntype\";
fogupdateloaded=\"1\";
storageftpuser=\"$storageftpuser\";
storageftppass=\"$storageftppass\";
" > "$fogprogramdir/.fogsettings";
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
    ServerName $ipaddress
    DocumentRoot $docroot
    ${forcehttps}RewriteEngine On
    ${forcehttps}RewriteRule /management/other/ca.cert.der$ - [L]
    ${forcehttps}RewriteRule /management/ https://%{HTTP_HOST}%{REQUEST_URI}%{QUERY_STRING} [R,L]
</VirtualHost>
<VirtualHost *:443>
    Servername $ipaddress
    DocumentRoot $docroot
    SSLEngine On
    SSLCertificateFile $webdirdest/management/other/ssl/srvpublic.crt
    SSLCertificateKeyFile /opt/fog/snapins/ssl/.srvprivate.key
    SSLCertificateChainFile $webdirdest/management/other/ca.cert.der
</VirtualHost>" > "$etcconf";
    errorStat $?
    dots "Restarting Apache2 for fog vhost"
    if [ "$osid" -eq 2 ]; then
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
    echo "caCreated=\"yes\"" >> "$fogprogramdir/.fogsettings";
}
